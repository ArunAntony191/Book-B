<?php
require_once '../includes/db_helper.php';

try {
    $pdo = getDBConnection();
    echo "Starting schema update...\n";

    // 1. Update Users Table
    $userColumns = [
        'phone' => "ALTER TABLE users ADD COLUMN phone VARCHAR(20) DEFAULT NULL AFTER lastname",
        'address' => "ALTER TABLE users ADD COLUMN address TEXT DEFAULT NULL AFTER phone",
        'is_accepting_deliveries' => "ALTER TABLE users ADD COLUMN is_accepting_deliveries TINYINT(1) DEFAULT 0 AFTER role",
        'service_start_lat' => "ALTER TABLE users ADD COLUMN service_start_lat DECIMAL(10, 8) DEFAULT NULL",
        'service_start_lng' => "ALTER TABLE users ADD COLUMN service_start_lng DECIMAL(11, 8) DEFAULT NULL",
        'service_end_lat' => "ALTER TABLE users ADD COLUMN service_end_lat DECIMAL(10, 8) DEFAULT NULL",
        'service_end_lng' => "ALTER TABLE users ADD COLUMN service_end_lng DECIMAL(11, 8) DEFAULT NULL"
    ];

    foreach ($userColumns as $col => $sql) {
        try {
            $pdo->query("SELECT $col FROM users LIMIT 1");
            echo "Column '$col' already exists in users.\n";
        } catch (PDOException $e) {
            $pdo->exec($sql);
            echo "Added column '$col' to users.\n";
        }
    }

    // 2. Update Transactions Table
    $transColumns = [
        'delivery_method' => "ALTER TABLE transactions ADD COLUMN delivery_method ENUM('pickup', 'delivery') DEFAULT 'pickup' AFTER status",
        'delivery_agent_id' => "ALTER TABLE transactions ADD COLUMN delivery_agent_id INT DEFAULT NULL AFTER delivery_method",
        'order_address' => "ALTER TABLE transactions ADD COLUMN order_address TEXT DEFAULT NULL",
        'order_lat' => "ALTER TABLE transactions ADD COLUMN order_lat DECIMAL(10, 8) DEFAULT NULL",
        'order_lng' => "ALTER TABLE transactions ADD COLUMN order_lng DECIMAL(11, 8) DEFAULT NULL"
    ];

    foreach ($transColumns as $col => $sql) {
        try {
            $pdo->query("SELECT $col FROM transactions LIMIT 1");
            echo "Column '$col' already exists in transactions.\n";
        } catch (PDOException $e) {
            $pdo->exec($sql);
            echo "Added column '$col' to transactions.\n";
        }
    }

    echo "Schema update completed successfully.\n";

} catch (Exception $e) {
    echo "Error updating schema: " . $e->getMessage() . "\n";
}
?>
