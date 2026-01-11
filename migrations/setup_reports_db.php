<?php
require_once __DIR__ . '/../includes/db_helper.php';

try {
    $pdo = getDBConnection();
    
    echo "Starting database update for Reports system...\n";
    
    // 1. Add is_banned column to users table if it doesn't exist
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_banned'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_banned BOOLEAN DEFAULT 0");
        echo "Added 'is_banned' column to users table.\n";
    } else {
        echo "'is_banned' column already exists.\n";
    }

    // 2. Create reports table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reporter_id INT NOT NULL,
            reported_id INT NOT NULL,
            reason VARCHAR(50) NOT NULL,
            description TEXT,
            status ENUM('pending', 'resolved', 'dismissed') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (reported_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Created 'reports' table (if not exists).\n";

    echo "Database update completed successfully!\n";

} catch (PDOException $e) {
    die("Database update failed: " . $e->getMessage() . "\n");
}
?>
