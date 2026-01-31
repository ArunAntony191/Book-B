<?php
require_once __DIR__ . '/../config/database.php';
try {
    $pdo = getDBConnection();
    echo "Adding 'is_restocked' column to transactions table...\n";
    $sql = "ALTER TABLE transactions ADD COLUMN is_restocked TINYINT(1) DEFAULT 0 AFTER status";
    $pdo->exec($sql);
    echo "✓ Successfully added 'is_restocked' column!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
