<?php
require_once 'includes/db_helper.php';

try {
    $pdo = getDBConnection();
    echo "Adding timestamps to transactions table...\n";

    $cols = [
        'picked_up_at' => "ALTER TABLE transactions ADD COLUMN picked_up_at DATETIME DEFAULT NULL AFTER delivery_agent_id",
        'delivered_at' => "ALTER TABLE transactions ADD COLUMN delivered_at DATETIME DEFAULT NULL AFTER picked_up_at",
        'lender_confirm_at' => "ALTER TABLE transactions ADD COLUMN lender_confirm_at DATETIME DEFAULT NULL AFTER delivered_at",
        'borrower_confirm_at' => "ALTER TABLE transactions ADD COLUMN borrower_confirm_at DATETIME DEFAULT NULL AFTER lender_confirm_at"
    ];

    foreach ($cols as $name => $sql) {
        try {
            $pdo->query("SELECT $name FROM transactions LIMIT 1");
            echo "Column '$name' already exists.\n";
        } catch (PDOException $e) {
            $pdo->exec($sql);
            echo "Added column '$name'.\n";
        }
    }
    echo "Database update completed.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
