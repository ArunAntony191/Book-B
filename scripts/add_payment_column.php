<?php
require_once '../includes/db_helper.php';

try {
    $pdo = getDBConnection();

    // Check if columns exist
    $columns = [];
    $stmt = $pdo->query("SHOW COLUMNS FROM transactions");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $row['Field'];
    }

    if (!in_array('payment_id', $columns)) {
        $pdo->exec("ALTER TABLE transactions ADD COLUMN payment_id VARCHAR(255) NULL AFTER status");
        echo "Added 'payment_id' column.<br>";
    } else {
        echo "'payment_id' column already exists.<br>";
    }

    if (!in_array('payment_status', $columns)) {
        $pdo->exec("ALTER TABLE transactions ADD COLUMN payment_status VARCHAR(50) DEFAULT 'pending' AFTER payment_id");
        echo "Added 'payment_status' column.<br>";
    } else {
        echo "'payment_status' column already exists.<br>";
    }

    echo "Database schema update complete.";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
