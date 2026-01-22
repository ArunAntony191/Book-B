<?php
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDBConnection();
    
    // Add pending_due_date column to transactions table
    $pdo->exec("ALTER TABLE transactions ADD COLUMN IF NOT EXISTS pending_due_date DATE DEFAULT NULL");
    
    echo "Database schema updated successfully: Added 'pending_due_date' to 'transactions'.\n";
} catch (Exception $e) {
    echo "Error updating database: " . $e->getMessage() . "\n";
}
?>
