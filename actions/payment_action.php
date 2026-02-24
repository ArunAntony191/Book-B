<?php
ob_start();
require_once '../includes/db_helper.php';
require_once '../config/razorpay_config.php';
if (!file_exists('../vendor/autoload.php')) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Composer autoloader missing. Please run composer install.']);
    exit;
}
require_once '../vendor/autoload.php'; // Ensure Composer autoloader is included

use Razorpay\Api\Api;

header('Content-Type: application/json');
session_start();

$action = $_POST['action'] ?? '';
$currentUser = $_SESSION['user_id'] ?? 0;

if (!$currentUser) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $api = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);
    $pdo = getDBConnection();

    if ($action === 'create_order') {
        $listingId = $_POST['listing_id'] ?? 0;
        $delivery = isset($_POST['delivery']) && $_POST['delivery'] == '1';
        $quantity = (int)($_POST['quantity'] ?? 1);
        if ($quantity < 1) $quantity = 1;
        
        // Fetch listing details
        $stmt = $pdo->prepare("SELECT * FROM listings WHERE id = ?");
        $stmt->execute([$listingId]);
        $listing = $stmt->fetch();

        if (!$listing) {
            throw new Exception("Listing not found");
        }

        // Calculate Amount (Price * Qty + Delivery)
        $price = (float)$listing['price'];
        
        // If paying for an existing approved transaction, stock was already deducted/reserved.
        $txId = $_POST['transaction_id'] ?? 0;
        $isExistingApproved = false;
        if ($txId) {
            $stmtTx = $pdo->prepare("SELECT status, transaction_type FROM transactions WHERE id = ?");
            $stmtTx->execute([$txId]);
            $tx = $stmtTx->fetch();
            if ($tx && in_array($tx['status'], ['approved', 'active'])) {
                $isExistingApproved = true;
            }
        }

        if (!$isExistingApproved && $listing['quantity'] < $quantity) {
             throw new Exception("Only {$listing['quantity']} copies left.");
        }

        // Calculate Amount (Price * Qty + Delivery)
        $price = (float)$listing['price'];
        $deliveryFee = $delivery ? 50 : 0;
        
        $discountAmountMonetary = 0;
        if ($txId) {
            $stmtDisc = $pdo->prepare("SELECT credit_discount FROM transactions WHERE id = ?");
            $stmtDisc->execute([$txId]);
            $discountAmountMonetary = (int)$stmtDisc->fetchColumn();
        } else {
            $discountAmountCredits = (int)($_POST['use_discount_credits'] ?? 0);
            if ($discountAmountCredits == 100) {
                $subtotal = $price * $quantity;
                $discountAmountMonetary = $subtotal * 0.2; // 20% discount
            }
        }

        $totalAmount = (($price * $quantity) + $deliveryFee - $discountAmountMonetary) * 100; // Convert to paise
        
        if ($totalAmount < 100) {
            $totalAmount = 100; // Razorpay minimum is ₹1
        }

        $orderData = [
            'receipt'         => 'rcpt_' . uniqid(),
            'amount'          => $totalAmount,
            'currency'        => 'INR',
            'payment_capture' => 1 
        ];

        $razorpayOrder = $api->order->create($orderData);

        ob_clean();
        echo json_encode([
            'success' => true,
            'order_id' => $razorpayOrder['id'],
            'amount' => $totalAmount,
            'key_id' => RAZORPAY_KEY_ID,
            'contact' => '', 
            'email' => $_SESSION['user_email'] ?? '',   
            'name' => ($_SESSION['firstname'] ?? '') . ' ' . ($_SESSION['lastname'] ?? '')
        ]);
        exit;

    } elseif ($action === 'verify_payment') {
        $razorpay_payment_id = $_POST['razorpay_payment_id'];
        $razorpay_order_id = $_POST['razorpay_order_id'];
        $razorpay_signature = $_POST['razorpay_signature'];

        $attributes = [
            'razorpay_order_id' => $razorpay_order_id,
            'razorpay_payment_id' => $razorpay_payment_id,
            'razorpay_signature' => $razorpay_signature
        ];

        $api->utility->verifyPaymentSignature($attributes);

        // Payment Successful - Create Transaction
        $listingId = $_POST['listing_id'];
        $ownerId = $_POST['owner_id'];
        $quantity = (int)($_POST['quantity'] ?? 1);
        
        $orderInfo = json_decode($_POST['order_info'], true); 

        // Fetch Listing/Book Info
        $stmt = $pdo->prepare("SELECT b.title, l.price FROM listings l JOIN books b ON l.book_id = b.id WHERE l.id = ?");
        $stmt->execute([$listingId]);
        $listing = $stmt->fetch();
        $bookTitle = $listing['title'] ?? 'your book';
        $bookPrice = $listing['price'] ?? 0;
        
        $transactionId = $_POST['transaction_id'] ?? 0;

        if ($transactionId) {
             // Fetch existing transaction first
             $stmtFetch = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
             $stmtFetch->execute([$transactionId]);
             $transaction = $stmtFetch->fetch();

             $isDelivery = (!empty($orderInfo['delivery']) || ($transaction['delivery_method'] ?? '') === 'delivery');
             $newStatus = $isDelivery ? 'assigned' : 'active';
             
             $stmt = $pdo->prepare("UPDATE transactions SET payment_status = 'paid', payment_method = 'online', status = ?, razorpay_payment_id = ?, order_address=?, order_landmark=?, order_lat=?, order_lng=?, delivery_method=? WHERE id = ?");
             $stmt->execute([
                 $newStatus,
                 $razorpay_payment_id,
                 $orderInfo['address'] ?: ($transaction['order_address'] ?? ''), 
                 $orderInfo['landmark'] ?: ($transaction['order_landmark'] ?? ''), 
                 $orderInfo['lat'] ?: ($transaction['order_lat'] ?? ''), 
                 $orderInfo['lng'] ?: ($transaction['order_lng'] ?? ''),
                 ($isDelivery ? 'delivery' : ($transaction['delivery_method'] ?: 'pickup')),
                 $transactionId
             ]);
        } else {
            // New Insert
            $discountCredits = (int)($orderInfo['use_discount_credits'] ?? 0);
            $discountAmountMonetary = 0;
            if ($discountCredits == 100) {
                $discountAmountMonetary = ($bookPrice * $quantity) * 0.2;
            }

            $stmt = $pdo->prepare("
                INSERT INTO transactions (
                    listing_id, borrower_id, lender_id, transaction_type, status, 
                    due_date, borrow_date, delivery_method, order_address, order_landmark, order_lat, order_lng,
                    razorpay_payment_id, payment_status, payment_method, book_price, quantity, credit_discount
                ) 
                VALUES (?, ?, ?, 'purchase', 'approved', NULL, CURDATE(), ?, ?, ?, ?, ?, ?, 'paid', 'online', ?, ?, ?)
            ");

            $stmt->execute([
                $listingId, 
                $currentUser, 
                $ownerId, 
                ($orderInfo['delivery'] ? 'delivery' : 'pickup'),
                $orderInfo['address'], 
                $orderInfo['landmark'], 
                $orderInfo['lat'], 
                $orderInfo['lng'],
                $razorpay_payment_id,
                $bookPrice,
                $quantity,
                $discountAmountMonetary
            ]);
            
            $transactionId = $pdo->lastInsertId();

            if ($discountCredits == 100) {
                deductCredits($currentUser, 100, 'spend', "Used 100 credits for 20% discount on Order #{$transactionId}", $transactionId);
            }
            
            // Decrease quantity
            $stmt = $pdo->prepare("UPDATE listings SET quantity = quantity - ? WHERE id = ?");
            $stmt->execute([$quantity, $listingId]);
        }

        // Create Notification for Owner
        $msg = "New Sale! User " . $_SESSION['firstname'] . " bought {$quantity} copies of '{$bookTitle}'. Payment ID: " . $razorpay_payment_id;
        if (function_exists('createNotification')) {
            createNotification($ownerId, 'book_purchased', $msg, $transactionId);
        } else {
             $pdo->prepare("INSERT INTO notifications (user_id, type, message, reference_id) VALUES (?, ?, ?, ?)")
                ->execute([$ownerId, 'book_purchased', $msg, $transactionId]);
        }
        
        ob_clean();
        echo json_encode(['success' => true, 'transaction_id' => $transactionId]);
        exit;

    } elseif ($action === 'create_fine_order') {
        // Fetch User's Unpaid Fines
        $stmt = $pdo->prepare("SELECT unpaid_fines FROM users WHERE id = ?");
        $stmt->execute([$currentUser]);
        $unpaidFines = (float)$stmt->fetchColumn();

        if ($unpaidFines <= 0) {
            throw new Exception("No pending fines to pay.");
        }

        $totalAmount = $unpaidFines * 100; // Convert to paise
        if ($totalAmount < 100) $totalAmount = 100;

        $orderData = [
            'receipt'         => 'fine_' . uniqid(),
            'amount'          => $totalAmount,
            'currency'        => 'INR',
            'payment_capture' => 1 
        ];

        $razorpayOrder = $api->order->create($orderData);

        ob_clean();
        echo json_encode([
            'success' => true,
            'order_id' => $razorpayOrder['id'],
            'amount' => $totalAmount,
            'key_id' => RAZORPAY_KEY_ID,
            'name' => ($_SESSION['firstname'] ?? '') . ' ' . ($_SESSION['lastname'] ?? ''),
            'email' => $_SESSION['user_email'] ?? ''
        ]);
        exit;

    } elseif ($action === 'verify_fine_payment') {
        $razorpay_payment_id = $_POST['razorpay_payment_id'];
        $razorpay_order_id = $_POST['razorpay_order_id'];
        $razorpay_signature = $_POST['razorpay_signature'];

        $attributes = [
            'razorpay_order_id' => $razorpay_order_id,
            'razorpay_payment_id' => $razorpay_payment_id,
            'razorpay_signature' => $razorpay_signature
        ];

        $api->utility->verifyPaymentSignature($attributes);

        // Payment Successful - Create Transaction
        $listingId = $_POST['listing_id'];
        $ownerId = $_POST['owner_id'];
        $quantity = (int)($_POST['quantity'] ?? 1);
        
        $orderInfo = json_decode($_POST['order_info'], true); 

        // Fetch Listing/Book Info
        $stmt = $pdo->prepare("SELECT b.title, l.price FROM listings l JOIN books b ON l.book_id = b.id WHERE l.id = ?");
        $stmt->execute([$listingId]);
        $listing = $stmt->fetch();
        $bookTitle = $listing['title'] ?? 'your book';
        $bookPrice = $listing['price'] ?? 0;

        // Check if there is already an APPROVED transaction for this user/listing that is UNPAID (Bulk flow)
        // Actually, currently we won't have the transaction ID passed here easily unless we add it to create_order.
        // For now, let's create a NEW transaction for instant buys, 
        // OR update if we can find a matching 'approved' purchase with NO payment_id yet.
        
        // Simple approach: Always insert new if not explicitly updating.
        // But for bulk approval, the transaction already exists as 'approved' (but unpaid?).
        // Wait, my design said "Purchases > 10 ... Request -> Approve -> Pay".
        // When allowed to pay, we need to update that specific transaction.
        
        // Let's assume for now this flow is for NEW instant buys (<=10).
        // To support paying for approved bulk requests, we'd need to pass transaction_id.
        // Let's add that support.
        
        $transactionId = $_POST['transaction_id'] ?? 0;

        if ($transactionId) {
             // Fetch existing transaction first
             $stmtFetch = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
             $stmtFetch->execute([$transactionId]);
             $transaction = $stmtFetch->fetch();

             $isDelivery = (!empty($orderInfo['delivery']) || ($transaction['delivery_method'] ?? '') === 'delivery');
             $newStatus = $isDelivery ? 'assigned' : 'active';
             
             $stmt = $pdo->prepare("UPDATE transactions SET payment_status = 'paid', payment_method = 'online', status = ?, razorpay_payment_id = ?, order_address=?, order_landmark=?, order_lat=?, order_lng=?, delivery_method=? WHERE id = ?");
             $stmt->execute([
                 $newStatus,
                 $razorpay_payment_id,
                 $orderInfo['address'] ?: ($transaction['order_address'] ?? ''), 
                 $orderInfo['landmark'] ?: ($transaction['order_landmark'] ?? ''), 
                 $orderInfo['lat'] ?: ($transaction['order_lat'] ?? ''), 
                 $orderInfo['lng'] ?: ($transaction['order_lng'] ?? ''),
                 ($isDelivery ? 'delivery' : ($transaction['delivery_method'] ?: 'pickup')),
                 $transactionId
             ]);
             // Quantity deducation happened on approval? 
             // If manual approval happened, we should have deducted quantity then? 
             // Or deduct now? 
             // Logic: "Approve" usually reserves it. Check `accept_request`.
        } else {
            // New Insert
            $discountCredits = (int)($orderInfo['use_discount_credits'] ?? 0);
            $discountAmountMonetary = 0;
            if ($discountCredits == 100) {
                $discountAmountMonetary = ($bookPrice * $quantity) * 0.2;
            }

            $stmt = $pdo->prepare("
                INSERT INTO transactions (
                    listing_id, borrower_id, lender_id, transaction_type, status, 
                    due_date, borrow_date, delivery_method, order_address, order_landmark, order_lat, order_lng,
                    razorpay_payment_id, payment_status, payment_method, book_price, quantity, credit_discount
                ) 
                VALUES (?, ?, ?, 'purchase', 'approved', NULL, CURDATE(), ?, ?, ?, ?, ?, ?, 'paid', 'online', ?, ?, ?)
            ");

            $stmt->execute([
                $listingId, 
                $currentUser, 
                $ownerId, 
                ($orderInfo['delivery'] ? 'delivery' : 'pickup'),
                $orderInfo['address'], 
                $orderInfo['landmark'], 
                $orderInfo['lat'], 
                $orderInfo['lng'],
                $razorpay_payment_id,
                $bookPrice,
                $quantity,
                $discountAmountMonetary
            ]);
            
            $transactionId = $pdo->lastInsertId();

            if ($discountCredits == 100) {
                deductCredits($currentUser, 100, 'spend', "Used 100 credits for 20% discount on Order #{$transactionId}", $transactionId);
            }
            
            // Decrease quantity
            $stmt = $pdo->prepare("UPDATE listings SET quantity = quantity - ? WHERE id = ?");
            $stmt->execute([$quantity, $listingId]);
        }

        // Create Notification for Owner
        $msg = "New Sale! User " . $_SESSION['firstname'] . " bought {$quantity} copies of '{$bookTitle}'. Payment ID: " . $razorpay_payment_id;
        if (function_exists('createNotification')) {
            createNotification($ownerId, 'book_purchased', $msg, $transactionId);
        } else {
             $pdo->prepare("INSERT INTO notifications (user_id, type, message, reference_id) VALUES (?, ?, ?, ?)")
                ->execute([$ownerId, 'book_purchased', $msg, $transactionId]);
        }
        
        ob_clean();
        echo json_encode(['success' => true, 'transaction_id' => $transactionId]);
        exit;

    } elseif ($action === 'verify_fine_payment') {
        $razorpay_payment_id = $_POST['razorpay_payment_id'];
        $razorpay_order_id = $_POST['razorpay_order_id'];
        $razorpay_signature = $_POST['razorpay_signature'];

        $attributes = [
            'razorpay_order_id' => $razorpay_order_id,
            'razorpay_payment_id' => $razorpay_payment_id,
            'razorpay_signature' => $razorpay_signature
        ];

        $api->utility->verifyPaymentSignature($attributes);

        // Payment Successful - Clear Fines
        $pdo->beginTransaction();
        try {
            // Get current fine amount for logging
            $stmt = $pdo->prepare("SELECT unpaid_fines FROM users WHERE id = ?");
            $stmt->execute([$currentUser]);
            $clearedAmount = (float)$stmt->fetchColumn();

            // Reset user's unpaid fines
            $stmt = $pdo->prepare("UPDATE users SET unpaid_fines = 0 WHERE id = ?");
            $stmt->execute([$currentUser]);

            // Update penalties table status
            $stmt = $pdo->prepare("UPDATE penalties SET status = 'applied' WHERE user_id = ? AND status = 'pending'");
            $stmt->execute([$currentUser]);

            // Log the payment in some way? (Optional: Add a payment log entry)
            
            $pdo->commit();

            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Fines cleared successfully']);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
