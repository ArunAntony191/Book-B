<?php
require_once __DIR__ . '/../includes/db_helper.php';

try {
    $pdo = getDBConnection();
    echo "Connected to database...\n";
    
    // 1. Add phone column if not exists
    echo "Adding phone column...\n";
    $pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(20) DEFAULT NULL AFTER lastname");
    echo "Phone column added (or already existed).\n";
    
    // 2. Update role ENUM
    echo "Updating role ENUM...\n";
    $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('user', 'library', 'bookstore', 'admin', 'delivery_agent') NOT NULL DEFAULT 'user'");
    echo "Role ENUM updated.\n";
    
    // 3. Ensure quantity column in listings
    echo "Ensuring quantity column...\n";
    $pdo->exec("ALTER TABLE listings MODIFY COLUMN quantity INT DEFAULT 1");
    echo "Quantity column verified.\n";
    
    echo "Migration completed successfully!\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "Duplicate column name") !== false) {
        echo "Column already exists, skipping...\n";
        // Continue for other changes
        try {
             $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('user', 'library', 'bookstore', 'admin', 'delivery_agent') NOT NULL DEFAULT 'user'");
             $pdo->exec("ALTER TABLE listings MODIFY COLUMN quantity INT DEFAULT 1");
             echo "Migration completed with minor skips.\n";
        } catch (Exception $ex) {
            echo "Error: " . $ex->getMessage();
        }
    } else {
        echo "Error updating schema: " . $e->getMessage() . "\n";
    }
}
?>
