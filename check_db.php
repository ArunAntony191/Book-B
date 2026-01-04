<?php
require_once 'includes/db_helper.php';
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SHOW COLUMNS FROM listings");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Columns: " . implode(", ", $columns) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
