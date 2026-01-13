<?php
require_once 'includes/db_helper.php';

try {
    $pdo = getDBConnection();
    
    // Add agent_confirm_delivery_at column to transactions table
    $sql = "ALTER TABLE transactions ADD COLUMN IF NOT EXISTS agent_confirm_delivery_at DATETIME NULL AFTER delivered_at";
    $pdo->exec($sql);
    
    echo "✓ Successfully added agent_confirm_delivery_at column to transactions table\n";
    echo "This column will track when the delivery agent confirms delivery.\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
