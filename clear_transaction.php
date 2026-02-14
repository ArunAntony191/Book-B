<?php
require_once 'includes/db_helper.php';

$pdo = getDBConnection();

// Find the transaction for listing_id 28
$stmt = $pdo->prepare("SELECT id, borrower_id, status FROM transactions WHERE listing_id = 28 ORDER BY created_at DESC LIMIT 1");
$stmt->execute();
$transaction = $stmt->fetch();

if ($transaction) {
    echo "Found transaction:\n";
    echo "ID: " . $transaction['id'] . "\n";
    echo "Borrower ID: " . $transaction['borrower_id'] . "\n";
    echo "Status: " . $transaction['status'] . "\n\n";
    
    // Delete the transaction
    $deleteStmt = $pdo->prepare("DELETE FROM transactions WHERE id = ?");
    $deleteStmt->execute([$transaction['id']]);
    
    echo "Transaction deleted successfully!\n";
    
    // Restore the quantity if it was decremented
    $updateQty = $pdo->prepare("UPDATE listings SET quantity = quantity + 1 WHERE id = 28");
    $updateQty->execute();
    
    echo "Book quantity restored!\n";
} else {
    echo "No transaction found for listing_id 28\n";
}
?>
