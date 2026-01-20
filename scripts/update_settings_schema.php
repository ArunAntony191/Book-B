<?php
require_once '../includes/db_helper.php';

try {
    $pdo = getDBConnection();
    
    echo "Updating users table schema...\n";
    
    // Add email_notifications
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS email_notifications BOOLEAN DEFAULT 1");
    echo "Added email_notifications column.\n";
    
    // Add theme_mode
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS theme_mode ENUM('light', 'dark') DEFAULT 'light'");
    echo "Added theme_mode column.\n";
    
    echo "Schema update completed successfully!\n";
} catch (Exception $e) {
    echo "Error updating schema: " . $e->getMessage() . "\n";
}
