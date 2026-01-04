<?php
require_once 'includes/db_helper.php';
try {
    $pdo = getDBConnection();
    
    // Add columns to listings
    $pdo->exec("ALTER TABLE listings ADD COLUMN IF NOT EXISTS quantity INT DEFAULT 1");
    $pdo->exec("ALTER TABLE listings ADD COLUMN IF NOT EXISTS credit_cost INT DEFAULT 10");
    
    // Add is_read to notifications if missing
    $pdo->exec("ALTER TABLE notifications ADD COLUMN IF NOT EXISTS is_read TINYINT(1) DEFAULT 0");
    
    echo "Database updated successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
