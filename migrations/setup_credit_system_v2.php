<?php
require_once '../includes/db_helper.php';

$sql = "
-- Add missing columns to users table
ALTER TABLE users ADD COLUMN IF NOT EXISTS credits INT DEFAULT 100 AFTER role;
ALTER TABLE users ADD COLUMN IF NOT EXISTS trust_score INT DEFAULT 100 AFTER credits;
ALTER TABLE users ADD COLUMN IF NOT EXISTS average_rating DECIMAL(3,2) DEFAULT 0.00 AFTER trust_score;
ALTER TABLE users ADD COLUMN IF NOT EXISTS total_ratings INT DEFAULT 0 AFTER average_rating;
ALTER TABLE users ADD COLUMN IF NOT EXISTS total_lends INT DEFAULT 0 AFTER total_ratings;
ALTER TABLE users ADD COLUMN IF NOT EXISTS total_borrows INT DEFAULT 0 AFTER total_lends;
ALTER TABLE users ADD COLUMN IF NOT EXISTS late_returns INT DEFAULT 0 AFTER total_borrows;

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
";

try {
    $pdo = getDBConnection();
    // Executing each statement separately to avoid errors with IF NOT EXISTS on ALTER TABLE in some MySQL versions
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($statements as $stmt) {
        if (!empty($stmt)) {
            $pdo->exec($stmt);
        }
    }
    echo "Database successfully updated for Credit/Rating system.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>
