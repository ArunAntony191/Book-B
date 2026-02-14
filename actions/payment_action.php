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
        
        // Fetch listing details
        $stmt = $pdo->prepare("SELECT * FROM listings WHERE id = ?");
        $stmt->execute([$listingId]);
        $listing = $stmt->fetch();

        if (!$listing) {
            throw new Exception("Listing not found");
        }

        if ($listing['quantity'] <= 0) {
            throw new Exception("This book is currently out of stock.");
        }

        // Calculate Amount (Price + Delivery if applicable)
        // Note: Razorpay accepts amount in paise (1 INR = 100 paise)
        $price = (float)$listing['price'];
        
        // Add delivery cost if applicable (assuming flat rate or dynamic)
        // For now, let's assume a flat delivery fee of 50 INR if delivery is selected
        // Using token-based logic reference: 10 tokens ~ 10 INR? Or completely separate?
        // Let's stick to the price on the listing for now.
        // If delivery is selected, we should probably add a delivery charge.
        // Let's add 50 INR as a standard delivery fee for now if delivery is selected.
        $deliveryFee = $delivery ? 50 : 0;
        $totalAmount = ($price + $deliveryFee) * 100; // Convert to paise
        
        // Razorpay requires minimum amount of 1 INR (100 paise)
        if ($totalAmount < 100) {
            $totalAmount = 100; // Enforce minimum 1 INR for testing/verification if price is 0
        }

        $orderData = [
            'receipt'         => 'rcpt_' . uniqid(),
            'amount'          => $totalAmount,
            'currency'        => 'INR',
            'payment_capture' => 1 // Auto capture
        ];

        $razorpayOrder = $api->order->create($orderData);

        ob_clean();
        echo json_encode([
            'success' => true,
            'order_id' => $razorpayOrder['id'],
            'amount' => $totalAmount,
            'key_id' => RAZORPAY_KEY_ID,
            'contact' => '', // Phone not in session currently
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
        $dueDateInput = $_POST['due_date'] ?? null;
        $dueDate = !empty($dueDateInput) ? $dueDateInput : null;
        $orderInfo = json_decode($_POST['order_info'], true); // Address, etc.

        // Fetch Listing/Book Info for notification
        $stmt = $pdo->prepare("SELECT b.title, l.price FROM listings l JOIN books b ON l.book_id = b.id WHERE l.id = ?");
        $stmt->execute([$listingId]);
        $listing = $stmt->fetch();
        $bookTitle = $listing['title'] ?? 'your book';
        $bookPrice = $listing['price'] ?? 0;

        // Insert into transactions
        $stmt = $pdo->prepare("
            INSERT INTO transactions (
                listing_id, borrower_id, lender_id, transaction_type, status, 
                due_date, borrow_date, delivery_method, order_address, order_landmark, order_lat, order_lng,
                razorpay_payment_id, payment_status, payment_method, book_price
            ) 
            VALUES (?, ?, ?, 'purchase', 'approved', ?, CURDATE(), ?, ?, ?, ?, ?, ?, 'paid', 'online', ?)
        ");

        $stmt->execute([
            $listingId, 
            $currentUser, 
            $ownerId, 
            $dueDate, 
            ($orderInfo['delivery'] ? 'delivery' : 'pickup'),
            $orderInfo['address'], 
            $orderInfo['landmark'], 
            $orderInfo['lat'], 
            $orderInfo['lng'],
            $razorpay_payment_id,
            $bookPrice
        ]);
        
        $transactionId = $pdo->lastInsertId();

        // If delivery is requested, it will now be in the 'Available' pool (status=approved/paid)
        // for any agent to claim.
        /* if ($orderInfo['delivery']) {
            assignDeliveryAgent($transactionId);
        } */

        // Decrease quantity
        $stmt = $pdo->prepare("UPDATE listings SET quantity = quantity - 1 WHERE id = ?");
        $stmt->execute([$listingId]);

        // Create Notification for Owner
        $msg = "New Sale! User " . $_SESSION['firstname'] . " bought your book '{$bookTitle}'. Payment ID: " . $razorpay_payment_id;
        if (function_exists('createNotification')) {
            createNotification($ownerId, 'book_purchased', $msg, $transactionId);
        } else {
             $pdo->prepare("INSERT INTO notifications (user_id, type, message, reference_id) VALUES (?, ?, ?, ?)")
                ->execute([$ownerId, 'book_purchased', $msg, $transactionId]);
        }
        
        ob_clean();
        echo json_encode(['success' => true, 'transaction_id' => $transactionId]);
        exit;
    }

} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
