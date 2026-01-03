<?php
require_once 'includes/db_helper.php';
session_start();

header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';
$listingId = $_POST['listing_id'] ?? 0;

try {
    $pdo = getDBConnection();

    if ($action === 'toggle_wishlist') {
        // ... (Wishlist Logic)
        $stmt = $pdo->prepare("SELECT id FROM wishlist WHERE user_id = ? AND listing_id = ?");
        $stmt->execute([$userId, $listingId]);
        
        if ($stmt->fetch()) {
            $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND listing_id = ?")->execute([$userId, $listingId]);
            echo json_encode(['success' => true, 'status' => 'removed']);
        } else {
            $pdo->prepare("INSERT INTO wishlist (user_id, listing_id) VALUES (?, ?)")->execute([$userId, $listingId]);
            echo json_encode(['success' => true, 'status' => 'added']);
        }

    } elseif ($action === 'create_request') {
        $type = $_POST['type'] ?? 'borrow'; 
        $ownerId = $_POST['owner_id'] ?? 0;
        $dueDate = $_POST['due_date'] ?? null;
        $bookTitle = $_POST['book_title'] ?? 'a book';

        if (!$ownerId) throw new Exception("Invalid Owner");

        // Map type to transaction_type
        $transactionType = ($type === 'sell') ? 'purchase' : $type;

        // Create Transaction
        $stmt = $pdo->prepare("
            INSERT INTO transactions (listing_id, borrower_id, lender_id, transaction_type, status, due_date, borrow_date) 
            VALUES (?, ?, ?, ?, 'requested', ?, CURDATE())
        ");
        $stmt->execute([$listingId, $userId, $ownerId, $transactionType, $dueDate]);
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
        $stmt = $pdo->prepare("SELECT t.*, l.book_id, b.title FROM transactions t JOIN listings l ON t.listing_id = l.id JOIN books b ON l.book_id = b.id WHERE t.id = ?");
        $stmt->execute([$transactionId]);
        $transaction = $stmt->fetch();

        if (!$transaction || $transaction['lender_id'] != $userId) {
            throw new Exception("Unauthorized");
        }

        // Update transaction status
        $pdo->prepare("UPDATE transactions SET status = 'approved' WHERE id = ?")
            ->execute([$transactionId]);

        // Send notification to requester
        $msg = "Your request for '{$transaction['title']}' has been ACCEPTED!";
        $pdo->prepare("INSERT INTO notifications (user_id, type, message, reference_id) VALUES (?, 'request_accepted', ?, ?)")
            ->execute([$transaction['borrower_id'], $msg, $transactionId]);

        echo json_encode(['success' => true, 'message' => 'Request accepted!']);

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
    } 
    else {
        echo json_encode(['success' => false, 'message' => 'Invalid Action']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
