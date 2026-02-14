<?php
require_once 'includes/db_helper.php';

$pdo = getDBConnection();

// Find ALL transactions for listing_id 28
$stmt = $pdo->prepare("SELECT id, borrower_id, status, created_at FROM transactions WHERE listing_id = 28 ORDER BY created_at DESC");
$stmt->execute();
$transactions = $stmt->fetchAll();

if ($transactions) {
    echo "Found " . count($transactions) . " transaction(s) for listing 28:\n\n";
    foreach ($transactions as $tx) {
        echo "ID: " . $tx['id'] . " | Borrower: " . $tx['borrower_id'] . " | Status: " . $tx['status'] . " | Created: " . $tx['created_at'] . "\n";
    }
    
    echo "\nDeleting all transactions...\n";
    
    // Delete ALL transactions for listing 28
    $deleteStmt = $pdo->prepare("DELETE FROM transactions WHERE listing_id = 28");
    $deleteStmt->execute();
    
    echo "Deleted " . $deleteStmt->rowCount() . " transaction(s)!\n";
    
    // Restore the quantity
    $updateQty = $pdo->prepare("UPDATE listings SET quantity = quantity + " . count($transactions) . " WHERE id = 28");
    $updateQty->execute();
    
    echo "Book quantity restored (added " . count($transactions) . " back)!\n";
} else {
    echo "No transactions found for listing_id 28\n";
}
?>
