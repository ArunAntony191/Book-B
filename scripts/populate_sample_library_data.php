<?php
require_once 'includes/db_helper.php';

try {
    $pdo = getDBConnection();
    $libraryId = 3; // library@test.com
    
    // 1. Add some listings if they don't exist
    $books = $pdo->query("SELECT id FROM books LIMIT 4")->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($books as $index => $bookId) {
        $quantity = ($index == 0) ? 2 : 10; // First book low stock
        $stmt = $pdo->prepare("
            INSERT INTO listings (user_id, book_id, listing_type, quantity, availability_status, location, credit_cost)
            VALUES (?, ?, 'borrow', ?, 'available', 'City Library', 10)
            ON DUPLICATE KEY UPDATE quantity = ?
        ");
        $stmt->execute([$libraryId, $bookId, $quantity, $quantity]);
    }
    
    echo "Sample listings created/updated for Library ID $libraryId.\n";
    
    // 2. Create some transactions
    $borrowerId = 2; // user@test.com (John Doe)
    $listings = $pdo->query("SELECT id FROM listings WHERE user_id = $libraryId")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($listings)) {
        // Active Borrow
        $stmt = $pdo->prepare("
            INSERT INTO transactions (listing_id, borrower_id, lender_id, transaction_type, status, borrow_date, due_date)
            VALUES (?, ?, ?, 'borrow', 'active', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY))
        ");
        $stmt->execute([$listings[0], $borrowerId, $libraryId]);
        
        // Requested Borrow
        $stmt = $pdo->prepare("
            INSERT INTO transactions (listing_id, borrower_id, lender_id, transaction_type, status)
            VALUES (?, ?, ?, 'borrow', 'requested')
        ");
        $stmt->execute([$listings[1], $borrowerId, $libraryId]);
        
        // Returned Borrow
        $stmt = $pdo->prepare("
            INSERT INTO transactions (listing_id, borrower_id, lender_id, transaction_type, status, borrow_date, due_date, return_date)
            VALUES (?, ?, ?, 'borrow', 'returned', DATE_SUB(CURDATE(), INTERVAL 20 DAY), DATE_SUB(CURDATE(), INTERVAL 6 DAY), DATE_SUB(CURDATE(), INTERVAL 5 DAY))
        ");
        $stmt->execute([$listings[2], $borrowerId, $libraryId]);
    }
    
    echo "Sample transactions created for Library ID $libraryId.\n";
    
    // 3. Add some reviews
    $stmt = $pdo->prepare("SELECT id FROM transactions WHERE lender_id = ? AND status = 'returned' LIMIT 1");
    $stmt->execute([$libraryId]);
    $transId = $stmt->fetchColumn();
    
    if ($transId) {
        $stmt = $pdo->prepare("
            INSERT INTO reviews (transaction_id, reviewer_id, reviewee_id, rating, comment)
            VALUES (?, ?, ?, 5, 'Excellent service and great collection!')
            ON DUPLICATE KEY UPDATE rating = 5
        ");
        $stmt->execute([$transId, $borrowerId, $libraryId]);
        
        // Update user stats (usually handled by addReview but we are doing raw SQL)
        $pdo->prepare("UPDATE users SET average_rating = 5.0, total_ratings = 1, credits = credits + 100 WHERE id = ?")
            ->execute([$libraryId]);
    }
    
    echo "Sample reviews and credits added for Library ID $libraryId.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
