<?php
require_once 'includes/db_helper.php';
session_start();

header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? 0;
$userRole = $_SESSION['role'] ?? 'user';
if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';
$listingId = $_POST['listing_id'] ?? 0;

try {
    $pdo = getDBConnection();

    if ($action === 'toggle_wishlist') {
        $stmt = $pdo->prepare("SELECT id FROM wishlist WHERE user_id = ? AND listing_id = ?");
        $stmt->execute([$userId, $listingId]);
        
        if ($stmt->fetch()) {
            $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND listing_id = ?")->execute([$userId, $listingId]);
            echo json_encode(['success' => true, 'status' => 'removed']);
        } else {
            $pdo->prepare("INSERT INTO wishlist (user_id, listing_id) VALUES (?, ?)")->execute([$userId, $listingId]);
            echo json_encode(['success' => true, 'status' => 'added']);
        }

    } elseif ($action === 'check_delivery') {
        $lLat = $_POST['l_lat'] ?? null;
        $lLng = $_POST['l_lng'] ?? null;
        $bLat = $_POST['b_lat'] ?? null;
        $bLng = $_POST['b_lng'] ?? null;
        
        $available = checkDeliveryAvailability($lLat, $lLng, $bLat, $bLng);
        echo json_encode(['success' => true, 'available' => $available]);

    } elseif ($action === 'create_request') {
        $type = $_POST['type'] ?? 'borrow'; 
        $ownerId = $_POST['owner_id'] ?? 0;
        $dueDate = $_POST['due_date'] ?? null;
        $bookTitle = $_POST['book_title'] ?? 'a book';

        if (!$ownerId) throw new Exception("Invalid Owner");

        // Check if quantity is available
        $quantity = checkAvailableQuantity($listingId);
        if ($quantity <= 0) {
            throw new Exception("This book is currently out of stock");
        }

        // Get credit cost for this listing
        $listing = getListingWithQuantity($listingId);
        $creditCost = $listing['credit_cost'] ?? 10;

        // Check if borrower has sufficient credits
        if (!checkSufficientCredits($userId, $creditCost)) {
            $userCredits = getUserCredits($userId);
            throw new Exception("Insufficient credits. You have {$userCredits}, but need {$creditCost}");
        }

        // Map type to transaction_type
        $transactionType = ($type === 'sell') ? 'purchase' : $type;

        // Create Transaction with delivery info
        $wantDelivery = ($_POST['delivery'] ?? 0) == 1;
        $orderAddress = $_POST['address'] ?? null;
        $orderLandmark = $_POST['landmark'] ?? null;
        $orderLat = $_POST['lat'] ?? null;
        $orderLng = $_POST['lng'] ?? null;

        $stmt = $pdo->prepare("
            INSERT INTO transactions (
                listing_id, borrower_id, lender_id, transaction_type, status, 
                due_date, borrow_date, delivery_method, order_address, order_landmark, order_lat, order_lng
            ) 
            VALUES (?, ?, ?, ?, 'requested', ?, CURDATE(), ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $listingId, $userId, $ownerId, $transactionType, 
            $dueDate, ($wantDelivery ? 'delivery' : 'pickup'), 
            $orderAddress, $orderLandmark, $orderLat, $orderLng
        ]);
        $transactionId = $pdo->lastInsertId();

        // Construct Message
        $msg = "User " . $_SESSION['firstname'] . " wants to ";
        if ($type === 'borrow') {
            $msg .= "borrow '{$bookTitle}' until " . date('M d, Y', strtotime($dueDate));
        } elseif ($type === 'sell') {
            $msg .= "buy '{$bookTitle}'";
        } else {
            $msg .= "swap for '{$bookTitle}'";
        }

        // Insert Notification with reference_id
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, message, reference_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$ownerId, $type . '_request', $msg, $transactionId]);

        // Auto-Send Chat Message
        $chatMsg = "Hi, I would like to ";
        if ($type === 'borrow') {
            $chatMsg .= "borrow '{$bookTitle}' until " . date('M d, Y', strtotime($dueDate)) . ". Is this date okay?";
        } elseif ($type === 'sell') {
            $chatMsg .= "buy '{$bookTitle}'.";
        } else {
            $chatMsg .= "swap for '{$bookTitle}'.";
        }
        
        $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)")
            ->execute([$userId, $ownerId, $chatMsg]);

        echo json_encode(['success' => true, 'message' => 'Request sent to owner!']);

    } elseif ($action === 'accept_request') {
        $transactionId = $_POST['transaction_id'] ?? 0;
        if (!$transactionId) throw new Exception("Invalid transaction");

        // Get transaction details
        $stmt = $pdo->prepare("
            SELECT t.*, l.book_id, l.credit_cost, l.quantity, b.title 
            FROM transactions t 
            JOIN listings l ON t.listing_id = l.id 
            JOIN books b ON l.book_id = b.id 
            WHERE t.id = ?
        ");
        $stmt->execute([$transactionId]);
        $transaction = $stmt->fetch();

        if (!$transaction || $transaction['lender_id'] != $userId) {
            throw new Exception("Unauthorized");
        }

        // Check quantity
        if ($transaction['quantity'] <= 0) {
            throw new Exception("Book is out of stock");
        }

        // Deduct credits from borrower
        $creditCost = $transaction['credit_cost'] ?? 10;
        if (!deductCredits($transaction['borrower_id'], $creditCost, 'spend', "Borrowed: {$transaction['title']}", $transactionId)) {
            throw new Exception("Failed to process credit transaction");
        }

        // Decrease quantity
        updateListingQuantity($transaction['listing_id'], -1);

        // Update transaction status
        $pdo->prepare("UPDATE transactions SET status = 'approved' WHERE id = ?")
            ->execute([$transactionId]);

        // Increment borrow count
        $pdo->prepare("UPDATE users SET total_borrows = total_borrows + 1 WHERE id = ?")
            ->execute([$transaction['borrower_id']]);

        // Send notification to requester
        $msg = "Your request for '{$transaction['title']}' has been ACCEPTED! {$creditCost} credits have been deducted.";
        $pdo->prepare("INSERT INTO notifications (user_id, type, message, reference_id) VALUES (?, 'request_accepted', ?, ?)")
            ->execute([$transaction['borrower_id'], $msg, $transactionId]);

        echo json_encode(['success' => true, 'message' => 'Request accepted! Credits deducted from borrower.']);

    } elseif ($action === 'decline_request') {
        $transactionId = $_POST['transaction_id'] ?? 0;
        if (!$transactionId) throw new Exception("Invalid transaction");

        $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
        $stmt->execute([$transactionId]);
        $transaction = $stmt->fetch();

        if (!$transaction || $transaction['lender_id'] != $userId) {
            throw new Exception("Unauthorized");
        }

        // Update status
        $pdo->prepare("UPDATE transactions SET status = 'cancelled' WHERE id = ?")
            ->execute([$transactionId]);

        // Notify requester
        $pdo->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, 'request_declined', 'Your request was declined.')")
            ->execute([$transaction['borrower_id']]);

        echo json_encode(['success' => true, 'message' => 'Request declined']);
        
    } elseif ($action === 'mark_returned') {
        $transactionId = $_POST['transaction_id'] ?? 0;
        if (!$transactionId) throw new Exception("Invalid transaction");

        // Get transaction details
        $stmt = $pdo->prepare("
            SELECT t.*, l.book_id, l.credit_cost, b.title 
            FROM transactions t 
            JOIN listings l ON t.listing_id = l.id 
            JOIN books b ON l.book_id = b.id 
            WHERE t.id = ?
        ");
        $stmt->execute([$transactionId]);
        $transaction = $stmt->fetch();

        if (!$transaction || $transaction['lender_id'] != $userId) {
            throw new Exception("Unauthorized");
        }

        // Update transaction with return date
        $pdo->prepare("UPDATE transactions SET status = 'returned', return_date = CURDATE() WHERE id = ?")
            ->execute([$transactionId]);

        // Increase quantity back
        updateListingQuantity($transaction['listing_id'], 1);

        // Check for late return and apply penalty
        $penalty = calculatePenalty($transactionId);
        $penaltyApplied = false;

        if ($penalty['days'] > 0) {
            applyPenalty($transactionId, $transaction['borrower_id']);
            $penaltyApplied = true;
            $penaltyMsg = "Late return penalty: -{$penalty['credit_penalty']} credits, -{$penalty['trust_penalty']} trust";
        } else {
            // Award credits to lender for successful lending
            $creditEarned = $transaction['credit_cost'] ?? 10;
            addCredits($transaction['lender_id'], $creditEarned, 'earn', "Book returned: {$transaction['title']}", $transactionId);
            
            // Increase trust for on-time return
            updateTrustScore($transaction['borrower_id'], 5, 'on_time_return');
            
            // Increment lend count
            $pdo->prepare("UPDATE users SET total_lends = total_lends + 1 WHERE id = ?")
                ->execute([$transaction['lender_id']]);
        }

        // Send notifications
        $borrowerMsg = $penaltyApplied 
            ? "Book '{$transaction['title']}' marked as returned. {$penaltyMsg}" 
            : "Book '{$transaction['title']}' returned on time. +5 trust score!";
        
        $pdo->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, 'book_returned', ?)")
            ->execute([$transaction['borrower_id'], $borrowerMsg]);

        if (!$penaltyApplied) {
            $lenderMsg = "'{$transaction['title']}' was returned on time. You earned {$creditEarned} credits!";
            $pdo->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, 'book_returned', ?)")
                ->execute([$transaction['lender_id'], $lenderMsg]);
        }

        echo json_encode([
            'success' => true, 
            'message' => 'Book marked as returned',
            'penalty_applied' => $penaltyApplied,
            'penalty_details' => $penaltyApplied ? $penalty : null
        ]);
    } elseif ($action === 'update_delivery_status') {
        $transactionId = $_POST['transaction_id'] ?? 0;
        $status = $_POST['status'] ?? ''; 

        require_once 'includes/class.delivery.php';
        $dm = new DeliveryManager();
        $dm->updateStatus($userId, $transactionId, $status);
        
        echo json_encode(['success' => true, 'message' => 'Delivery status updated!']);

    } elseif ($action === 'update_availability') {
        $status = $_POST['status'] ?? 0; // 1 for online, 0 for offline
        
        if ($userRole !== 'delivery_agent') {
            throw new Exception("Unauthorized");
        }

        $pdo->prepare("UPDATE users SET is_accepting_deliveries = ? WHERE id = ?")
            ->execute([$status, $userId]);
            
        echo json_encode(['success' => true, 'message' => 'Availability updated']);

    } elseif ($action === 'confirm_handover') {
        $transactionId = $_POST['transaction_id'] ?? 0;
        require_once 'includes/class.delivery.php';
        $dm = new DeliveryManager();
        $dm->confirmHandover($userId, $transactionId);
        echo json_encode(['success' => true, 'message' => 'Handover confirmed! Trust +1']);

    } elseif ($action === 'confirm_receipt') {
        $transactionId = $_POST['transaction_id'] ?? 0;
        require_once 'includes/class.delivery.php';
        $dm = new DeliveryManager();
        $dm->confirmReceipt($userId, $transactionId);
        echo json_encode(['success' => true, 'message' => 'Receipt confirmed! Transaction Complete.']);

    } elseif ($action === 'claim_job') {
        $transactionId = $_POST['transaction_id'] ?? 0;
        
        require_once 'includes/class.delivery.php';
        $dm = new DeliveryManager();
        $dm->claimJob($userId, $transactionId);
            
        echo json_encode(['success' => true, 'message' => 'Job Claimed']);

    } elseif ($action === 'ban_user') {
        if ($userRole !== 'admin') throw new Exception("Unauthorized");
        $targetId = $_POST['user_id'] ?? 0;
        if (banUser($targetId)) {
            echo json_encode(['success' => true, 'message' => 'User has been banned']);
        } else {
            throw new Exception("Failed to ban user");
        }
    } elseif ($action === 'unban_user') {
        if ($userRole !== 'admin') throw new Exception("Unauthorized");
        $targetId = $_POST['user_id'] ?? 0;
        if (unbanUser($targetId)) {
            echo json_encode(['success' => true, 'message' => 'User has been unbanned']);
        } else {
            throw new Exception("Failed to unban user");
        }
    } elseif ($action === 'submit_report') {
        $reportedId = $_POST['reported_id'] ?? 0;
        $reason = $_POST['reason'] ?? '';
        $description = $_POST['description'] ?? '';
        
        if (!$reportedId || !$reason) throw new Exception("Missing required fields");
        
        if (createReport($userId, $reportedId, $reason, $description)) {
            echo json_encode(['success' => true, 'message' => 'Report submitted successfully']);
        } else {
            throw new Exception("Failed to submit report");
        }
    } elseif ($action === 'resolve_report') {
        if ($userRole !== 'admin') throw new Exception("Unauthorized");
        $reportId = $_POST['report_id'] ?? 0;
        $status = $_POST['status'] ?? 'resolved';
        
        if (resolveReport($reportId, $status)) {
            echo json_encode(['success' => true, 'message' => 'Report updated']);
        } else {
            throw new Exception("Failed to update report");
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid Action']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
