<?php
ob_start();

// Debug Logging
function logDebug($msg) {
    file_put_contents('../debug_log.txt', date('[Y-m-d H:i:s] ') . $msg . PHP_EOL, FILE_APPEND);
}
logDebug("Request Action Hit: " . print_r($_POST, true));
require_once '../includes/db_helper.php';
require_once '../includes/validation_helper.php';
session_start();
header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? 0;
$userRole = $_SESSION['role'] ?? 'user';
if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Global Sanitization of Input
$_POST = sanitizeInput($_POST);

$action = $_POST['action'] ?? '';
// Validate Listing ID if present
$listingId = isset($_POST['listing_id']) ? validateId($_POST['listing_id']) : 0;


try {
    $pdo = getDBConnection();

    // --- Wishlist ---
    if ($action === 'toggle_wishlist') {
        if (!$listingId) throw new Exception("Invalid listing ID.");
        
        $stmt = $pdo->prepare("SELECT id FROM wishlist WHERE user_id = ? AND listing_id = ?");
        $stmt->execute([$userId, $listingId]);
        
        if ($stmt->fetch()) {
            ob_clean();
            echo json_encode(['success' => true, 'status' => 'removed']);
            exit;
        } else {
            ob_clean();
            $pdo->prepare("INSERT INTO wishlist (user_id, listing_id) VALUES (?, ?)")->execute([$userId, $listingId]);
            echo json_encode(['success' => true, 'status' => 'added']);
            exit;
        }

    // --- Delivery Check ---
    } elseif ($action === 'check_delivery') {
        $lLat = $_POST['l_lat'] ?? null;
        $lLng = $_POST['l_lng'] ?? null;
        $bLat = $_POST['b_lat'] ?? null;
        $bLng = $_POST['b_lng'] ?? null;
        
        if (!$lLat || !$lLng || !$bLat || !$bLng) throw new Exception("Missing location coordinates.");

        $available = checkDeliveryAvailability($lLat, $lLng, $bLat, $bLng);
        ob_clean();
        echo json_encode(['success' => true, 'available' => $available]);
        exit;

    // --- Create Request ---
    } elseif ($action === 'create_request') {
        logDebug("Create Request Action Triggered");
        if (!$listingId) {
             logDebug("Error: Invalid listing listingId");
             throw new Exception("Invalid listing specified.");
        }
        
        $type = $_POST['type'] ?? 'borrow'; 
        $owner_raw = $_POST['owner_id'] ?? 0;
        $ownerId = validateId($owner_raw);
        $dueDate = $_POST['due_date'] ?? null;
        $bookTitle = $_POST['book_title'] ?? 'a book';
        
        // Bulk Purchase Fields
        $quantity = (int)($_POST['quantity'] ?? 1);
        $requestMessage = $_POST['request_message'] ?? null;
        
        if ($quantity < 1) $quantity = 1;

        // Duplicate Check
        if ($type === 'sell') {
            // For sales, only block if there's a PENDING request. 
            // Allow multiple approved/active transactions (users can buy again).
            $duplicateStatuses = "('requested')";
        } else {
            // For borrowing/swapping, strictly one transaction at a time.
            $duplicateStatuses = "('requested', 'approved', 'assigned', 'active', 'returning', 'delivered')";
        }
        
        $stmt = $pdo->prepare("SELECT id FROM transactions WHERE listing_id = ? AND borrower_id = ? AND status IN $duplicateStatuses");
        $stmt->execute([$listingId, $userId]);
        if ($stmt->fetch()) {
            if ($type === 'sell') {
                throw new Exception("You already have a pending request for this book. Please wait for approval.");
            } else {
                throw new Exception("You have already requested or are currently processing an order for this book.");
            }
        }

        if (!$ownerId) throw new Exception("Invalid Owner ID.");
        if ($type === 'borrow') {
             if (!$dueDate) throw new Exception("Due date is required for borrowing.");
             if (!validateDate($dueDate)) throw new Exception("Invalid due date format.");
             if ($dueDate <= date('Y-m-d')) throw new Exception("Due date must be in the future.");
        }

        // Check availability
        $availQty = checkAvailableQuantity($listingId);
        if ($availQty < $quantity) {
            throw new Exception("Only $availQty copies available. You requested $quantity.");
        }

        // Cost Calculation
        $listing = getListingWithQuantity($listingId);
        if (!$listing) throw new Exception("Listing not found.");

        if ($listing['user_id'] == $userId) {
            throw new Exception("You cannot request your own book.");
        }
        
        $creditCost = $listing['credit_cost'] ?? 10;
        
        $wantDelivery = ($_POST['delivery'] ?? 0) == 1;
        // Total cost calculation might differ for bulk/sell, but keeping token logic for borrow
        $totalCost = $creditCost + ($wantDelivery ? 10 : 0);

        // ENFORCE MINIMUM BALANCE (30 credits)
        $userCredits = getUserCredits($userId);
        if ($userCredits < 30) {
            throw new Exception("Your credit balance is below the minimum threshold (30). Please earn more tokens by returning books on time or completing missions.");
        }

        // Only check credits if it's NOT a sell (purchase) OR if it's a token-based sell
        if ($type !== 'sell') {
            if (!checkSufficientCredits($userId, $totalCost)) {
                throw new Exception("Insufficient credits. You need $totalCost tokens.");
            }
        }

        // Credit Discount Logic (for Purchases/Delivery)
        $useDiscount = (int)($_POST['use_discount_credits'] ?? 0); // 50 or 75
        $discountAmount = 0;
        if ($useDiscount > 0) {
            if ($userCredits < ($totalCost + $useDiscount)) {
                throw new Exception("Insufficient credits to use this discount.");
            }
            $discountAmount = $useDiscount; // 1:1 ratio for now
        }

        // Prepare Transaction
        $transactionType = ($type === 'sell') ? 'purchase' : $type;
        
        // Status Logic
        // Sell + Qty > 10 => requested (needs approval)
        // Sell + Qty <= 10 => should have been paid via Razorpay directly? 
        // If we are here for Sell <= 10, it implies COD or some other flow.
        // Let's stick to 'approved' for small COD sells if allowed, or 'requested' if we want manual approval.
        // Existing logic was 'approved'.
        
        if ($type === 'sell' && $quantity > 10) {
            $initialStatus = 'requested';
        } elseif ($type === 'sell') {
            $initialStatus = 'approved'; 
        } else {
            $initialStatus = 'requested';
        }

        $orderAddress = $_POST['address'] ?? null;
        $orderLandmark = $_POST['landmark'] ?? null;
        $orderLat = $_POST['lat'] ?? null;
        $orderLng = $_POST['lng'] ?? null;
        $paymentMethod = ($type === 'sell' && $quantity > 10) ? 'online' : ($_POST['payment_method'] ?? 'online'); // Bulk defaults to online payment LATER

        if ($wantDelivery && (!$orderLat || !$orderLng)) {
             throw new Exception("Delivery location is required for delivery requests.");
        }

        $stmt = $pdo->prepare("
            INSERT INTO transactions (
                listing_id, borrower_id, lender_id, transaction_type, status, 
                due_date, borrow_date, delivery_method, order_address, order_landmark, order_lat, order_lng,
                payment_method, quantity, request_message, book_price, credit_discount
            ) 
            VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $listingId, $userId, $ownerId, $transactionType, $initialStatus, 
            $dueDate, ($wantDelivery ? 'delivery' : 'pickup'), 
            $orderAddress, $orderLandmark, $orderLat, $orderLng,
            $paymentMethod, $quantity, $requestMessage, ($listing['price'] ?? 0),
            $discountAmount
        ]);
        $transactionId = $pdo->lastInsertId();

        // If Discount applied, deduct those credits now
        if ($discountAmount > 0) {
            deductCredits($userId, $discountAmount, 'spend', "Credit Discount for Order #{$transactionId}", $transactionId);
        }

        // If it's a purchase (small qty, instant approved), decrease quantity immediately
        if ($type === 'sell' && $initialStatus === 'approved') {
            updateListingQuantity($listingId, -$quantity);
        }

        // Notifications & Messages
        if ($type === 'sell' && $quantity > 10) {
            $msg = "User " . $_SESSION['firstname'] . " requests to buy {$quantity} copies of '{$bookTitle}'. Reason: $requestMessage";
            $notifyType = 'sell_request';
        } elseif ($type === 'sell') {
            $msg = "User " . $_SESSION['firstname'] . " bought '{$bookTitle}'";
            $notifyType = 'book_purchased';
        } else {
            $msg = "User " . $_SESSION['firstname'] . " wants to ";
            if ($type === 'borrow') $msg .= "borrow '{$bookTitle}' until " . date('M d, Y', strtotime($dueDate));
            $notifyType = $type . '_request';
        }

        createNotification($ownerId, $notifyType, $msg, $transactionId);
        logDebug("Notification Created");

        if ($type === 'sell' && $quantity > 10) {
            $chatMsg = "Hi, I would like to buy {$quantity} copies of '{$bookTitle}'. Reason: $requestMessage";
        } elseif ($type === 'sell') {
            $chatMsg = "Hi, I just bought '{$bookTitle}' from your listing.";
        } else {
            $chatMsg = "Hi, I would like to ";
            if ($type === 'borrow') $chatMsg .= "borrow '{$bookTitle}' until " . date('M d, Y', strtotime($dueDate)) . ". Is this date okay?";
        }
        
        $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)")
            ->execute([$userId, $ownerId, $chatMsg]);

        ob_clean();
        echo json_encode(['success' => true, 'message' => 'Request sent successfully!', 'transaction_id' => $transactionId]);
        exit;

    // --- Interactions (Accept/Decline/Return etc) ---
    } elseif (in_array($action, ['accept_request', 'decline_request', 'request_extension', 'approve_extension', 'decline_extension', 'mark_returned', 'update_delivery_status', 'confirm_handover', 'confirm_receipt', 'claim_job', 'cancel_job', 'request_return_delivery', 'cancel_order', 'confirm_cod_payment'])) {
        
        $transactionId = validateId($_POST['transaction_id'] ?? 0);
        if (!$transactionId) throw new Exception("Invalid Transaction ID.");

        if ($action === 'accept_request') {
            // ... (Logic from before, just wrapped with better validation context)
             $stmt = $pdo->prepare("SELECT t.*, t.quantity as order_qty, l.book_id, l.credit_cost, l.quantity as listing_qty, b.title FROM transactions t JOIN listings l ON t.listing_id = l.id JOIN books b ON l.book_id = b.id WHERE t.id = ?");
             $stmt->execute([$transactionId]);
             $transaction = $stmt->fetch();

             // Check Authorization
             if (!$transaction || $transaction['lender_id'] != $userId) throw new Exception("Unauthorized or transaction not found.");
             
             // Branch Logic based on Type
             if ($transaction['transaction_type'] === 'purchase') {
                 // Reserve Stock on Approval
                 $qty = (int)($transaction['order_qty'] ?? 1);
                 updateListingQuantity($transaction['listing_id'], -$qty);
                 
                 $pdo->prepare("UPDATE transactions SET status = 'approved' WHERE id = ?")->execute([$transactionId]);
                 
                 $msg = "Your purchase request for '{$transaction['title']}' has been APPROVED! Please proceed to payment.";
                 createNotification($transaction['borrower_id'], 'request_accepted', $msg, $transactionId);
                 
             } else {
                 // For Borrow: Deduct Credits & Stock immediately (legacy flow)
                 if ($transaction['quantity'] <= 0) throw new Exception("Book is out of stock.");
    
                 $transactionCreditCost = $transaction['credit_cost'] ?? 10; 
                 $totalDeduct = $transactionCreditCost + ($transaction['delivery_method'] === 'delivery' ? 10 : 0);
                 
                 if (!deductCredits($transaction['borrower_id'], $totalDeduct, 'spend', "Borrowed: {$transaction['title']}", $transactionId)) {
                     throw new Exception("Failed to process credits. Borrower might have insufficient funds.");
                 }
                 
                 updateListingQuantity($transaction['listing_id'], -1);
                 $pdo->prepare("UPDATE transactions SET status = 'approved' WHERE id = ?")->execute([$transactionId]);
                 $pdo->prepare("UPDATE users SET total_borrows = total_borrows + 1 WHERE id = ?")->execute([$transaction['borrower_id']]);
                 
                 $msg = "Your request for '{$transaction['title']}' has been ACCEPTED! {$totalDeduct} credits deducted.";
                 createNotification($transaction['borrower_id'], 'request_accepted', $msg, $transactionId);
             }

             ob_clean();
             echo json_encode(['success' => true, 'message' => 'Request accepted!']);
             exit;

        } elseif ($action === 'decline_request') {
             // ... existing decline logic ...
             $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
             $stmt->execute([$transactionId]);
             $transaction = $stmt->fetch();
             if (!$transaction || $transaction['lender_id'] != $userId) throw new Exception("Unauthorized.");
             
             $pdo->prepare("UPDATE transactions SET status = 'cancelled' WHERE id = ?")->execute([$transactionId]);
             createNotification($transaction['borrower_id'], 'request_declined', 'Your request was declined.');
             ob_clean();
             echo json_encode(['success' => true, 'message' => 'Request declined']);
             exit;

        } elseif ($action === 'request_extension') {
             $newDate = $_POST['new_date'] ?? null;
             $reason = $_POST['reason'] ?? '';
             if (!$newDate || !validateDate($newDate)) throw new Exception("Invalid extension date.");

             $stmt = $pdo->prepare("SELECT borrower_id, lender_id FROM transactions WHERE id = ?");
             $stmt->execute([$transactionId]);
             $tx = $stmt->fetch();
             if (!$tx || $tx['borrower_id'] != $userId) throw new Exception("Unauthorized.");

             if (requestExtension($transactionId, $newDate, $reason)) {
                 $msg = "Borrower requested extension until " . date('M d, Y', strtotime($newDate));
                 createNotification($tx['lender_id'], 'extension_request', $msg, $transactionId);
                 ob_clean();
                 echo json_encode(['success' => true, 'message' => 'Extension request sent.']);
                 exit;
             } else {
                 throw new Exception("Failed to update extension.");
             }
        } elseif ($action === 'confirm_cod_payment') {
             // Handle Cash Confirmation for approved bulk request
             $transactionId = validateId($_POST['transaction_id'] ?? 0);
             $listingId = validateId($_POST['listing_id'] ?? 0);
             $quantity = (int)($_POST['quantity'] ?? 1);
             
             if (!$transactionId || !$listingId) throw new Exception("Invalid Data.");

             // Check if stock available (final check)
             $avail = checkAvailableQuantity($listingId);
             if ($avail < $quantity) {
                 throw new Exception("Stock no longer available. Only $avail left.");
             }
             
             // Update transaction
             $pdo->prepare("UPDATE transactions SET status = 'active', payment_status = 'unpaid', payment_method = 'cod', delivery_method = 'pickup', request_message = CONCAT(IFNULL(request_message, ''), ' [Confirmed COD]') WHERE id = ?")->execute([$transactionId]);
             
             // Stock already deducted on approval or creation. No need to deduct again.
             
             // Notify Owner
             $msg = "Buyer has confirmed COD for {$quantity} copies. Please arrange handover.";
             // Need owner ID... fetch tx first? assume caller has it or fetch it.
             $stmt = $pdo->prepare("SELECT lender_id, title FROM transactions t JOIN listings l ON t.listing_id = l.id JOIN books b ON l.book_id = b.id WHERE t.id = ?");
             $stmt->execute([$transactionId]);
             $tx = $stmt->fetch();
             
             if ($tx) {
                 createNotification($tx['lender_id'], 'order_confirmed', $msg, $transactionId);
             }

             ob_clean();
             echo json_encode(['success' => true, 'message' => 'Order Confirmed (COD).']);
             exit;

        } elseif ($action === 'approve_extension') {
            // ... approve logic ...
             $stmt = $pdo->prepare("SELECT lender_id, borrower_id, pending_due_date FROM transactions WHERE id = ?");
             $stmt->execute([$transactionId]);
             $tx = $stmt->fetch();
             if (!$tx || $tx['lender_id'] != $userId) throw new Exception("Unauthorized.");
             
             if (!checkSufficientCredits($tx['borrower_id'], 5)) {
                 throw new Exception("Borrower has insufficient credits (5 needed) for this extension.");
             }
             
             if (approveExtension($transactionId)) {
                 deductCredits($tx['borrower_id'], 5, 'spend', "Borrow Extension: #{$transactionId}", $transactionId);
                 $msg = "Extension APPROVED! New due date: " . date('M d, Y', strtotime($tx['pending_due_date'])) . ". 5 credits deducted.";
                 createNotification($tx['borrower_id'], 'extension_approved', $msg, $transactionId);
                 ob_clean();
                 echo json_encode(['success' => true, 'message' => 'Extension approved']);
                 exit;
             } else { throw new Exception("Failed to approve."); }

        } elseif ($action === 'decline_extension') {
             // ... decline logic ...
             $stmt = $pdo->prepare("SELECT lender_id, borrower_id FROM transactions WHERE id = ?");
             $stmt->execute([$transactionId]);
             $tx = $stmt->fetch();
             if (!$tx || $tx['lender_id'] != $userId) throw new Exception("Unauthorized.");
             
             $pdo->prepare("UPDATE transactions SET pending_due_date = NULL WHERE id = ?")->execute([$transactionId]);
             createNotification($tx['borrower_id'], 'extension_declined', "Extension request declined.", $transactionId);
             ob_clean();
             echo json_encode(['success' => true, 'message' => 'Extension declined']);
             exit;

        } elseif ($action === 'mark_returned') {
             // ... mark returned logic ...
             $stmt = $pdo->prepare("SELECT t.*, l.book_id, l.credit_cost, b.title FROM transactions t JOIN listings l ON t.listing_id = l.id JOIN books b ON l.book_id = b.id WHERE t.id = ?");
             $stmt->execute([$transactionId]);
             $transaction = $stmt->fetch();
             if (!$transaction || $transaction['lender_id'] != $userId) throw new Exception("Unauthorized.");
             if ($transaction['transaction_type'] !== 'borrow') throw new Exception("Returns are only allowed for borrowed books.");
             
             $pdo->prepare("UPDATE transactions SET status = 'returned', return_date = CURDATE() WHERE id = ?")->execute([$transactionId]);
              if (empty($transaction['is_restocked'])) {
                  updateListingQuantity($transaction['listing_id'], 1);
                  $pdo->prepare("UPDATE transactions SET is_restocked = 1 WHERE id = ?")->execute([$transactionId]);
              }
             
             $penalty = calculatePenalty($transactionId);
             $penaltyApplied = false;
             if ($penalty['days'] > 0) {
                 applyPenalty($transactionId, $transaction['borrower_id']);
                 $penaltyApplied = true;
             } else {
                 $creditEarned = $transaction['credit_cost'] ?? 10;
                 addCredits($transaction['lender_id'], $creditEarned, 'earn', "Book returned: {$transaction['title']}", $transactionId);
                 updateTrustScore($transaction['borrower_id'], 5, 'on_time_return');
                   addCredits($transaction['borrower_id'], 2, 'bonus', "On-time return bonus: {$transaction['title']}", $transactionId);
                 $pdo->prepare("UPDATE users SET total_lends = total_lends + 1 WHERE id = ?")->execute([$transaction['lender_id']]);
             }
             // Notify... (Simplified for brevity but logic stands)
             ob_clean();
             echo json_encode(['success' => true, 'message' => 'Book marked returned.']);
             exit;

        } elseif ($action === 'update_delivery_status') {
             $status = $_POST['status'] ?? '';
             require_once '../includes/class.delivery.php';
             $dm = new DeliveryManager();
             $dm->updateStatus($userId, $transactionId, $status);
             ob_clean();
             echo json_encode(['success' => true, 'message' => 'Delivery status updated!']);
             exit;

        } elseif ($action === 'confirm_handover') {
             require_once '../includes/class.delivery.php';
             $dm = new DeliveryManager();
             $dm->confirmHandover($userId, $transactionId);
             ob_clean();
             echo json_encode(['success' => true, 'message' => 'Handover confirmed!']);
             exit;

        } elseif ($action === 'confirm_receipt') {
             $restock = isset($_POST['restock']) && $_POST['restock'] == '1';
             require_once '../includes/class.delivery.php';
             $dm = new DeliveryManager();
             $dm->confirmReceipt($userId, $transactionId, $restock);
             ob_clean();
             echo json_encode(['success' => true, 'message' => 'Receipt confirmed!']);
             exit;

        } elseif ($action === 'claim_job') {
             require_once '../includes/class.delivery.php';
             $dm = new DeliveryManager();
             $dm->claimJob($userId, $transactionId);
             ob_clean();
             echo json_encode(['success' => true, 'message' => 'Job Claimed']);
             exit;
        
        } elseif ($action === 'cancel_job') {
             require_once '../includes/class.delivery.php';
             $dm = new DeliveryManager();
             $dm->cancelJob($userId, $transactionId);
             ob_clean();
             echo json_encode(['success' => true, 'message' => 'Job Cancelled']);
             exit;

        } elseif ($action === 'request_return_delivery') {
             // ... Request Return Delivery Logic ...
             $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ? AND borrower_id = ?");
             $stmt->execute([$transactionId, $userId]);
             $tx = $stmt->fetch();
             
             if (!$tx) throw new Exception("Unauthorized or transaction not found.");
             if ($tx['transaction_type'] !== 'borrow') throw new Exception("Returns are only allowed for borrowed books.");
             if ($tx['status'] === 'returning' || $tx['return_delivery_method'] === 'delivery') throw new Exception("Already requested.");
             
             if (!checkSufficientCredits($userId, 10)) throw new Exception("Insufficient credits (10 required).");
             
             deductCredits($userId, 10, 'spend', "Return delivery fee #{$transactionId}", $transactionId);
             $stmt = $pdo->prepare("UPDATE transactions SET status = 'returning', return_delivery_method = 'delivery' WHERE id = ?");
             $stmt->execute([$transactionId]);
             
             // Notify Lender...
             ob_clean();
             echo json_encode(['success' => true, 'message' => 'Return delivery requested!']);
             exit;

        } elseif ($action === 'cancel_order') {
             // Cancel Order (Buyer or Seller)
             $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
             $stmt->execute([$transactionId]);
             $tx = $stmt->fetch();

             if (!$tx) throw new Exception("Transaction not found.");
             if ($tx['borrower_id'] != $userId && $tx['lender_id'] != $userId) throw new Exception("Unauthorized.");
             
             // Check if cancellable (not delivered yet)
             if (in_array($tx['status'], ['delivered', 'returned', 'cancelled'])) {
                 throw new Exception("Cannot cancel order in status: " . $tx['status']);
             }
             
             // RESTORE STOCK if it was deducted
             // Stock is deducted if:
             // 1. Status was 'active' (Purchase confirmed/paid)
             // 2. Status was 'approved' AND it was a borrow (borrow deducltion happens on accept)
             // 3. Status was 'approved' AND it was a purchase? NO, purchase deducts on 'approve' ONLY IF instant? 
             //    Let's check `accept_request` logic again.
             //    If purchase > 10, accept sets 'approved' (NO DEDUCTION). Verify/COD confirms 'active' (DEDUCTION).
             //    If purchase <= 10, create sets 'approved' (DEDUCTION).
             
             $shouldRestock = false;
             if ($tx['transaction_type'] === 'purchase') {
                 // If it was already approved (including small ones) or active (paid), it had stock deducted
                 if (in_array($tx['status'], ['approved', 'assigned', 'active', 'returning'])) {
                      $shouldRestock = true;
                 }
             } elseif ($tx['transaction_type'] === 'borrow') {
                 if (in_array($tx['status'], ['approved', 'assigned', 'active', 'returning'])) {
                      $shouldRestock = true;
                 }
             }
             
             $pdo->prepare("UPDATE transactions SET status = 'cancelled' WHERE id = ?")->execute([$transactionId]);
             
             if ($shouldRestock) {
                 updateListingQuantity($tx['listing_id'], $tx['quantity']);
             }
             
             // Notify other party
             $notifyUserId = ($userId == $tx['borrower_id']) ? $tx['lender_id'] : $tx['borrower_id'];
             createNotification($notifyUserId, 'order_cancelled', "Order #{$transactionId} was cancelled by user.", $transactionId);
             
             // APPLY CANCELLATION PENALTY (5 tokens)
             deductCredits($userId, 5, 'penalty', "Cancellation Penalty for Order #{$transactionId}", $transactionId);
             
             ob_clean();
             echo json_encode(['success' => true, 'message' => 'Order cancelled successfully. Stock updated if applicable.']);
             exit;
        }
    
    // --- Admin Actions ---
    } elseif ($action === 'ban_user' || $action === 'unban_user' || $action === 'resolve_report' || $action === 'adjust_tokens') {
        if ($userRole !== 'admin') throw new Exception("Unauthorized access.");

        if ($action === 'ban_user') {
            $targetId = validateId($_POST['user_id'] ?? 0);
            if (!$targetId) throw new Exception("Invalid User ID.");
            if (banUser($targetId)) echo json_encode(['success' => true, 'message' => 'User banned.']);
            else throw new Exception("Failed to ban user.");

        } elseif ($action === 'unban_user') {
            $targetId = validateId($_POST['user_id'] ?? 0);
            if (!$targetId) throw new Exception("Invalid User ID.");
            if (unbanUser($targetId)) echo json_encode(['success' => true, 'message' => 'User unbanned.']);
            else throw new Exception("Failed to unban user.");

        } elseif ($action === 'resolve_report') {
            $reportId = validateId($_POST['report_id'] ?? 0);
            $status = $_POST['status'] ?? 'resolved';
            if (!$reportId) throw new Exception("Invalid Report ID.");
             // Behavioral Credit System: Penalty for valid reports
             if ($status === 'resolved' || $status === 'punished') {
                 $stmt = $pdo->prepare("SELECT reporter_id, reported_id FROM reports WHERE id = ?");
                 $stmt->execute([$reportId]);
                 $report = $stmt->fetch();
                 if ($report) {
                     deductCredits($report['reported_id'], 20, 'report_penalty', 'Penalty for confirmed report', $reportId);
                     updateTrustScore($report['reported_id'], -10, 'report_confirmed');
                 }
             }

            if (resolveReport($reportId, $status)) echo json_encode(['success' => true, 'message' => 'Report resolved.']);
            else throw new Exception("Failed to update report.");

        } elseif ($action === 'adjust_tokens') {
            $targetId = validateId($_POST['user_id'] ?? 0);
            $amount = (int)($_POST['amount'] ?? 0);
            $reason = trim($_POST['reason'] ?? 'Admin adjustment');
            
            if (!$targetId || $amount === 0) throw new Exception("Invalid parameters.");
            
            $currentCredits = getUserCredits($targetId);
            
            if ($amount > 0) {
                if ($currentCredits >= 500) throw new Exception("User already has maximum tokens (500).");
                $newBalance = min($currentCredits + $amount, 500);
                $actualAdded = $newBalance - $currentCredits;
                
                if (addCredits($targetId, $amount, 'bonus', $reason)) {
                    echo json_encode(['success' => true, 'message' => "Added $actualAdded tokens. New balance: $newBalance."]);
                } else {
                    throw new Exception("Failed to add tokens.");
                }
            } else {
                if ($currentCredits <= 0) throw new Exception("User already has 0 tokens.");
                $absAmount = abs($amount);
                $newBalance = max(0, $currentCredits - $absAmount);
                $actualRemoved = $currentCredits - $newBalance;
                
                if (deductCredits($targetId, $absAmount, 'penalty', $reason)) {
                    echo json_encode(['success' => true, 'message' => "Removed $actualRemoved tokens. New balance: $newBalance."]);
                } else {
                    throw new Exception("Failed to remove tokens.");
                }
            }
        }

    // --- Report Submission ---
    } elseif ($action === 'submit_report') {
        $reportedId = validateId($_POST['reported_id'] ?? 0);
        $communityId = validateId($_POST['reported_community_id'] ?? 0);
        $reason = $_POST['reason'] ?? '';
        $description = $_POST['description'] ?? '';
        $type = $_POST['type'] ?? 'user';
        
        if (!$reason) throw new Exception("Missing required fields.");
        
        $targetId = ($type === 'community') ? $communityId : $reportedId;
        if (!$targetId) throw new Exception("Invalid target ID.");

        if (createReport($userId, $targetId, $reason, $description, $type)) {
            echo json_encode(['success' => true, 'message' => 'Report submitted.']);
        } else {
            throw new Exception("Failed to submit report.");
        }

    } elseif ($action === 'delete_community') {
        if ($userRole !== 'admin') throw new Exception("Unauthorized access.");
        
        $communityId = validateId($_POST['community_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? 'Violation of community guidelines');
        
        if (!$communityId) throw new Exception("Invalid Community ID.");
        
        if (deleteCommunity($communityId, $reason)) {
            echo json_encode(['success' => true, 'message' => 'Community deleted successfully.']);
        } else {
            throw new Exception("Failed to delete community.");
        }

    } elseif ($action === 'warn_community') {
        if ($userRole !== 'admin') throw new Exception("Unauthorized access.");
        
        $communityId = validateId($_POST['community_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? 'Violation of community guidelines');
        
        if (!$communityId) throw new Exception("Invalid Community ID.");
        
        if (warnCommunity($communityId, $userId, $reason)) {
            echo json_encode(['success' => true, 'message' => 'Warning sent to the community.']);
        } else {
            throw new Exception("Failed to send warning.");
        }

    } elseif ($action === 'update_availability') {
        $status = $_POST['status'] ?? 0;
        if ($userRole !== 'delivery_agent') throw new Exception("Unauthorized.");
        $pdo->prepare("UPDATE users SET is_accepting_deliveries = ? WHERE id = ?")->execute([$status, $userId]);
        echo json_encode(['success' => true, 'message' => 'Availability updated']);

    } else {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid Action or parameter missing.']);
        exit;
    }

} catch (Exception $e) {
    logDebug("Exception: " . $e->getMessage());
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
    exit;
?>
