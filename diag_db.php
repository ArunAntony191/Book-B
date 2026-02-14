<?php
require_once 'includes/db_helper.php';

try {
    $pdo = getDBConnection();
    
    echo "Summary of Listings by User:\n";
    $userListings = $pdo->query("
        SELECT u.id, u.email, u.firstname, u.role, COUNT(l.id) as listing_count, SUM(l.quantity) as total_qty
        FROM users u
        LEFT JOIN listings l ON u.id = l.user_id
        WHERE u.role IN ('library', 'bookstore', 'admin')
        GROUP BY u.id
        ORDER BY listing_count DESC
    ")->fetchAll();
    
    foreach ($userListings as $row) {
        echo "ID: {$row['id']} | Email: {$row['email']} | Role: {$row['role']} | Listings: {$row['listing_count']} | Total Qty: {$row['total_qty']}\n";
    }
    
    echo "\nActive/Approved Transactions:\n";
    $activeTrans = $pdo->query("
        SELECT t.id, t.lender_id, t.borrower_id, t.status 
        FROM transactions t 
        WHERE t.status IN ('active', 'approved', 'requested')
    ")->fetchAll();
    print_r($activeTrans);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
