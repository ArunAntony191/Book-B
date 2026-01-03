<?php
require_once 'includes/db_helper.php';
try {
    $pdo = getDBConnection();
    echo "Connection successful!\n";
    $stmt = $pdo->query("DESCRIBE listings");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Listings table columns:\n";
    foreach ($columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
