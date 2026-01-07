<?php
require_once __DIR__ . '/../includes/db_helper.php';

try {
    $pdo = getDBConnection();
    
    echo "Starting Migration v4: Delivery System updates...\n";

    // 1. Update Users table
    // Adding address, service route coordinates, and acceptance toggle
    $queries = [
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS address TEXT DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS service_start_lat DECIMAL(10, 8) DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS service_start_lng DECIMAL(11, 8) DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS service_end_lat DECIMAL(10, 8) DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS service_end_lng DECIMAL(11, 8) DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS is_accepting_deliveries TINYINT(1) DEFAULT 0",
        
        // 2. Update Transactions table
        "ALTER TABLE transactions ADD COLUMN IF NOT EXISTS delivery_method ENUM('pickup', 'delivery') DEFAULT 'pickup'",
        "ALTER TABLE transactions ADD COLUMN IF NOT EXISTS delivery_agent_id INT DEFAULT NULL",
        "ALTER TABLE transactions ADD COLUMN IF NOT EXISTS delivery_status ENUM('pending', 'picked_up', 'delivered', 'cancelled') DEFAULT 'pending'",
        "ALTER TABLE transactions ADD COLUMN IF NOT EXISTS order_address TEXT DEFAULT NULL",
        "ALTER TABLE transactions ADD COLUMN IF NOT EXISTS order_lat DECIMAL(10, 8) DEFAULT NULL",
        "ALTER TABLE transactions ADD COLUMN IF NOT EXISTS order_lng DECIMAL(11, 8) DEFAULT NULL"
    ];

    foreach ($queries as $query) {
        try {
            $pdo->exec($query);
            echo "Executed: " . substr($query, 0, 50) . "...\n";
        } catch (PDOException $e) {
            // Ignore "column already exists" errors
            if ($e->getCode() != '42S21' && $e->getCode() != '42000') {
                echo "Warning on query: " . $e->getMessage() . "\n";
            }
        }
    }

    // Add foreign key separately to handle potential existing ones
    try {
        $pdo->exec("ALTER TABLE transactions ADD CONSTRAINT fk_delivery_agent FOREIGN KEY (delivery_agent_id) REFERENCES users(id) ON DELETE SET NULL");
        echo "Foreign key constraint added.\n";
    } catch (PDOException $e) {
        echo "Note: Foreign key might already exist or failed: " . $e->getMessage() . "\n";
    }

    echo "Migration v4 completed successfully!\n";

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>
