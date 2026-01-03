<?php
require_once 'includes/db_helper.php';

try {
    $pdo = getDBConnection();
    
    // 1. Wishlist Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS wishlist (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            listing_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
            UNIQUE KEY unique_wishlist (user_id, listing_id)
        )
    ");
    echo "✅ Wishlist table created.\n";

    // 2. Notifications Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            type VARCHAR(50) NOT NULL,
            message TEXT NOT NULL,
            link VARCHAR(255),
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    echo "✅ Notifications table created.\n";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
