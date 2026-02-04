<?php
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDBConnection();
    
    // Check if columns already exist
    $stmt = $pdo->query("SHOW COLUMNS FROM `books` LIKE 'is_rare'");
    $isRareExists = $stmt->fetch();

    $stmt = $pdo->query("SHOW COLUMNS FROM `books` LIKE 'rare_details'");
    $rareDetailsExists = $stmt->fetch();

    if (!$isRareExists) {
        $pdo->exec("ALTER TABLE `books` ADD COLUMN `is_rare` TINYINT(1) DEFAULT 0");
        echo "Column 'is_rare' added to 'books' table successfully.\n";
    } else {
        echo "Column 'is_rare' already exists.\n";
    }

    if (!$rareDetailsExists) {
        $pdo->exec("ALTER TABLE `books` ADD COLUMN `rare_details` TEXT DEFAULT NULL");
        echo "Column 'rare_details' added to 'books' table successfully.\n";
    } else {
        echo "Column 'rare_details' already exists.\n";
    }
    
} catch (Exception $e) {
    echo "Error updating table: " . $e->getMessage() . "\n";
}
?>
