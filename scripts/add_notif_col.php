<?php
require_once '../includes/db_helper.php';

try {
    $pdo = getDBConnection();
    
    // Add reference_id column if it doesn't exist
    $pdo->exec("
        ALTER TABLE notifications 
        ADD COLUMN reference_id INT DEFAULT NULL AFTER is_read
    ");
    echo "✅ Added reference_id to notifications table.\n";

} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "ℹ️ Column reference_id already exists.\n";
    } else {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
}
?>
