<?php
require_once __DIR__ . '/../includes/db_helper.php';

try {
    $pdo = getDBConnection();
    
    // 1. Add 'is_banned' to users table
    echo "Adding 'is_banned' column to users table...\n";
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_banned TINYINT(1) DEFAULT 0 AFTER is_accepting_deliveries");
        echo " - Column 'is_banned' added successfully.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), "Duplicate column name") !== false) {
            echo " - Column 'is_banned' already exists.\n";
        } else {
            throw $e;
        }
    }

    // 2. Create 'reports' table
    echo "Creating 'reports' table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reporter_id INT NOT NULL,
            reported_id INT NOT NULL,
            reason VARCHAR(100) NOT NULL,
            description TEXT,
            status ENUM('pending', 'resolved', 'dismissed') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (reported_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo " - Table 'reports' created successfully.\n";

    echo "\nMigration v5 (Admin Safety) completed successfully!\n";

} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}
