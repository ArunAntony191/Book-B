<?php
require_once 'includes/db_helper.php';
try {
    $pdo = getDBConnection();
    $pdo->exec("ALTER TABLE reviews ADD COLUMN IF NOT EXISTS trust_impact INT DEFAULT 0 AFTER comment");
    echo "Added trust_impact column to reviews table.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
