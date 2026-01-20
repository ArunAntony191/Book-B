<?php
require_once '../includes/db_helper.php';
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SHOW TABLES LIKE 'reviews'");
    if ($stmt->fetch()) {
        $stmt = $pdo->query("SHOW COLUMNS FROM reviews");
        echo "reviews columns: " . implode(", ", $stmt->fetchAll(PDO::FETCH_COLUMN)) . "\n";
    } else {
        echo "reviews table DOES NOT EXIST.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
