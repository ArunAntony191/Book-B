<?php
require_once 'includes/db_helper.php';
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SHOW TABLES LIKE 'credit_transactions'");
    if ($stmt->fetch()) {
        $stmt = $pdo->query("SHOW COLUMNS FROM credit_transactions");
        echo "credit_transactions columns: " . implode(", ", $stmt->fetchAll(PDO::FETCH_COLUMN)) . "\n";
    } else {
        echo "credit_transactions table DOES NOT EXIST.\n";
    }
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'penalties'");
    if ($stmt->fetch()) {
        $stmt = $pdo->query("SHOW COLUMNS FROM penalties");
        echo "penalties columns: " . implode(", ", $stmt->fetchAll(PDO::FETCH_COLUMN)) . "\n";
    } else {
        echo "penalties table DOES NOT EXIST.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
