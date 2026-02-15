<?php
// Use __DIR__ to ensure correct path resolution
$dbHelperPath = __DIR__ . '/../includes/db_helper.php';

if (!file_exists($dbHelperPath)) {
    die("Error: db_helper.php not found at $dbHelperPath\n");
}

require_once $dbHelperPath;

try {
    $pdo = getDBConnection();
    
    // Check if columns exist
    $columns = $pdo->query("SHOW COLUMNS FROM transactions")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('quantity', $columns)) {
        $pdo->exec("ALTER TABLE transactions ADD COLUMN quantity INT DEFAULT 1");
        echo "Added quantity column.\n";
    } else {
        echo "quantity column exists.\n";
    }

    if (!in_array('request_message', $columns)) {
        $pdo->exec("ALTER TABLE transactions ADD COLUMN request_message TEXT DEFAULT NULL");
        echo "Added request_message column.\n";
    } else {
        echo "request_message column exists.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
