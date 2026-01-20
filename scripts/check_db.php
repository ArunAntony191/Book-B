<?php
require_once '../includes/db_helper.php';
try {
    $pdo = getDBConnection();
    echo "--- Users Table ---\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo implode(", ", $columns) . "\n\n";

    echo "--- Penalties Table ---\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM penalties");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo implode(", ", $columns) . "\n\n";

    echo "--- Transactions Table ---\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM transactions");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo implode(", ", $columns) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
