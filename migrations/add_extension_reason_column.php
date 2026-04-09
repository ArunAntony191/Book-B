<?php
require_once __DIR__ . '/../includes/db_helper.php';

try {
    $pdo = getDBConnection();
    $pdo->exec("ALTER TABLE transactions ADD COLUMN IF NOT EXISTS pending_extension_reason VARCHAR(255) DEFAULT NULL;");
    echo "Successfully added pending_extension_reason column.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
