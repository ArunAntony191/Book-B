<?php
// Fix the status ENUM to include 'delivered'
require_once 'config/database.php';

try {
    $pdo = getDBConnection();
    
    echo "Altering transactions table to add 'delivered' to status ENUM...\n";
    
    $sql = "ALTER TABLE transactions 
            MODIFY COLUMN status 
            ENUM('requested','approved','active','delivered','returned','cancelled') 
            DEFAULT 'requested'";
    
    $pdo->exec($sql);
    
    echo "✓ Successfully added 'delivered' to status ENUM!\n\n";
    
    // Verify the change
    $stmt = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'status'");
    $column = $stmt->fetch();
    
    echo "Updated status column definition:\n";
    echo "Type: {$column['Type']}\n";
    echo "Default: {$column['Default']}\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
