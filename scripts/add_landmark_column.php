<?php
require_once '../includes/db_helper.php';

try {
    $pdo = getDBConnection();
    echo "Starting Landmark Schema Update...\n";

    // 1. Update Users Table
    try {
        $pdo->query("SELECT landmark FROM users LIMIT 1");
        echo "Column 'landmark' already exists in users.\n";
    } catch (PDOException $e) {
        $pdo->exec("ALTER TABLE users ADD COLUMN landmark TEXT DEFAULT NULL AFTER address");
        echo "Added 'landmark' to users.\n";
    }

    // 2. Update Listings Table
    try {
        $pdo->query("SELECT landmark FROM listings LIMIT 1");
        echo "Column 'landmark' already exists in listings.\n";
    } catch (PDOException $e) {
        $pdo->exec("ALTER TABLE listings ADD COLUMN landmark TEXT DEFAULT NULL AFTER location");
        echo "Added 'landmark' to listings.\n";
    }

    // 3. Update Transactions Table
    try {
        $pdo->query("SELECT order_landmark FROM transactions LIMIT 1");
        echo "Column 'order_landmark' already exists in transactions.\n";
    } catch (PDOException $e) {
        $pdo->exec("ALTER TABLE transactions ADD COLUMN order_landmark TEXT DEFAULT NULL AFTER order_address");
        echo "Added 'order_landmark' to transactions.\n";
    }

    echo "Landmark schema update completed successfully.\n";

} catch (Exception $e) {
    echo "Error updating schema: " . $e->getMessage() . "\n";
}
?>
