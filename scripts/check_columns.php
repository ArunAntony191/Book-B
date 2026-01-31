<?php
require_once __DIR__ . '/../config/database.php';
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SHOW COLUMNS FROM transactions");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($columns, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
