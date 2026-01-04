<?php
/**
 * Migration: Add Credit/Trust System & Rating Integration
 * Run this to update existing database with new features
 */

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDBConnection();
    
    echo "Starting migration...\n";
    
    // 1. Update users table
    echo "Updating users table...\n";
    $pdo->exec("
        ALTER TABLE users 
        ADD COLUMN IF NOT EXISTS credits INT DEFAULT 100,
        ADD COLUMN IF NOT EXISTS trust_score INT DEFAULT 50,
        ADD COLUMN IF NOT EXISTS total_lends INT DEFAULT 0,
        ADD COLUMN IF NOT EXISTS total_borrows INT DEFAULT 0,
        ADD COLUMN IF NOT EXISTS late_returns INT DEFAULT 0,
        ADD COLUMN IF NOT EXISTS average_rating DECIMAL(3,2) DEFAULT 0.00,
        ADD COLUMN IF NOT EXISTS total_ratings INT DEFAULT 0,
        ADD INDEX IF NOT EXISTS idx_trust_score (trust_score)
    ");
    
    // 2. Update listings table
    echo "Updating listings table...\n";
    $pdo->exec("
        ALTER TABLE listings 
        ADD COLUMN IF NOT EXISTS quantity INT DEFAULT 1,
        ADD COLUMN IF NOT EXISTS credit_cost INT DEFAULT 10,
        ADD COLUMN IF NOT EXISTS visibility ENUM('public', 'community') DEFAULT 'public',
        ADD COLUMN IF NOT EXISTS community_id INT DEFAULT NULL
    ");
    
    // 3. Update reviews table
    echo "Updating reviews table...\n";
    $pdo->exec("
        ALTER TABLE reviews 
        ADD COLUMN IF NOT EXISTS trust_impact INT DEFAULT 0,
        ADD INDEX IF NOT EXISTS idx_transaction (transaction_id),
        ADD UNIQUE KEY IF NOT EXISTS unique_review (transaction_id, reviewer_id)
    ");
    
    // 4. Create credit_transactions table
    echo "Creating credit_transactions table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS credit_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            transaction_id INT NULL,
            amount INT NOT NULL,
            balance_after INT NOT NULL,
            type ENUM('earn', 'spend', 'penalty', 'bonus', 'rating_bonus') NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user (user_id),
            INDEX idx_type (type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // 5. Create penalties table
    echo "Creating penalties table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS penalties (
            id INT AUTO_INCREMENT PRIMARY KEY,
            transaction_id INT NOT NULL,
            user_id INT NOT NULL,
            days_overdue INT NOT NULL,
            credit_penalty INT NOT NULL,
            trust_penalty INT NOT NULL,
            status ENUM('pending', 'applied', 'waived') DEFAULT 'applied',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user (user_id),
            INDEX idx_transaction (transaction_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // 6. Update existing users with default values
    echo "Setting default values for existing users...\n";
    $pdo->exec("
        UPDATE users 
        SET credits = COALESCE(credits, 100),
            trust_score = COALESCE(trust_score, 50),
            total_lends = COALESCE(total_lends, 0),
            total_borrows = COALESCE(total_borrows, 0),
            late_returns = COALESCE(late_returns, 0),
            average_rating = COALESCE(average_rating, 0.00),
            total_ratings = COALESCE(total_ratings, 0)
        WHERE credits IS NULL OR trust_score IS NULL
    ");
    
    // 7. Update existing listings with default values
    echo "Setting default values for existing listings...\n";
    $pdo->exec("
        UPDATE listings 
        SET quantity = COALESCE(quantity, 1),
            credit_cost = COALESCE(credit_cost, 10),
            visibility = COALESCE(visibility, 'public')
        WHERE quantity IS NULL OR credit_cost IS NULL
    ");
    
    echo "\n✓ Migration completed successfully!\n";
    echo "New features added:\n";
    echo "  - Credit system (100 starting credits per user)\n";
    echo "  - Trust score system (50 starting score)\n";
    echo "  - Rating system with trust impact\n";
    echo "  - Quantity management for listings\n";
    echo "  - Penalty tracking for late returns\n";
    
} catch (PDOException $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
