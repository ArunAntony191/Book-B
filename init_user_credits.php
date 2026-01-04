<?php
require_once 'includes/db_helper.php';
try {
    $pdo = getDBConnection();
    // Set credits and trust to 100 for any user that has NULL or 0 (assuming newly added columns)
    $stmt = $pdo->exec("UPDATE users SET credits = 100 WHERE credits IS NULL OR credits = 0");
    $stmt2 = $pdo->exec("UPDATE users SET trust_score = 100 WHERE trust_score IS NULL OR trust_score = 0");
    echo "Initialized credits for " . ($stmt + $stmt2) . " user records.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
