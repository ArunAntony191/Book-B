<?php
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDBConnection();
    
    // Check if column already exists
    $stmt = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'favorite_category'");
    $columnExists = $stmt->fetch();

    if (!$columnExists) {
        $sql = "ALTER TABLE `users` ADD COLUMN `favorite_category` VARCHAR(100) DEFAULT NULL AFTER `profile_picture`";
        $pdo->exec($sql);
        echo "Column 'favorite_category' added to 'users' table successfully.\n";
    } else {
        echo "Column 'favorite_category' already exists in 'users' table.\n";
    }
    
} catch (Exception $e) {
    echo "Error updating table: " . $e->getMessage() . "\n";
}
?>
