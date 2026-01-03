<?php
require_once 'includes/db_helper.php';
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("DESCRIBE books");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Books table columns:\n";
    foreach ($columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
