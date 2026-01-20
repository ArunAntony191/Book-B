<?php
require_once '../includes/db_helper.php';

$sql = "
-- Credit Transactions Table
CREATE TABLE IF NOT EXISTS credit_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    transaction_id INT DEFAULT NULL,
    amount INT NOT NULL,
    balance_after INT NOT NULL,
    type ENUM('gift', 'earn', 'spend', 'penalty', 'refund') NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Penalties Table
CREATE TABLE IF NOT EXISTS penalties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    transaction_id INT NOT NULL,
    penalty_type ENUM('late_return', 'damaged_book', 'other') NOT NULL,
    amount INT NOT NULL,
    trust_penalty INT DEFAULT 10,
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ensure Users table has correct defaults
ALTER TABLE users MODIFY COLUMN credits INT DEFAULT 100;
ALTER TABLE users MODIFY COLUMN trust_score INT DEFAULT 100;
";

try {
    $pdo = getDBConnection();
    $pdo->exec($sql);
    echo "Credit and Penalty tables created successfully. User defaults updated.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>
