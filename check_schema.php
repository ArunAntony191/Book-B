<?php
// Check the transactions table schema
require_once 'config/database.php';

try {
    $pdo = getDBConnection();
    
    // Get column information
    $stmt = $pdo->query("DESCRIBE transactions");
    $columns = $stmt->fetchAll();
    
    echo "Transactions Table Schema:\n";
    echo str_repeat("=", 80) . "\n";
    
    foreach ($columns as $col) {
        echo "Field: {$col['Field']}\n";
        echo "  Type: {$col['Type']}\n";
        echo "  Null: {$col['Null']}\n";
        echo "  Key: {$col['Key']}\n";
        echo "  Default: " . ($col['Default'] ?? 'NULL') . "\n";
        echo "  Extra: {$col['Extra']}\n";
        echo str_repeat("-", 80) . "\n";
    }
    
    // Also check if there are any triggers or constraints
    echo "\n\nChecking actual status values:\n";
    $stmt = $pdo->query("SELECT id, status, LENGTH(status) as status_length FROM transactions ORDER BY id DESC LIMIT 5");
    $rows = $stmt->fetchAll();
    
    foreach ($rows as $row) {
        echo "ID: {$row['id']} | Status: '{$row['status']}' | Length: {$row['status_length']}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
