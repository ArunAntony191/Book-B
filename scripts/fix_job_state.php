<?php
require_once '../includes/db_helper.php';

try {
    $pdo = getDBConnection();
    
    // reset ID 1 to approved and remove agent if any
    $pdo->exec("UPDATE transactions SET status = 'approved', delivery_agent_id = NULL, delivery_method = 'delivery' WHERE id = 1");
    
    echo "Transaction #1 updated to 'approved' and unassigned. checking...\n";
    
    $stmt = $pdo->query("SELECT * FROM transactions WHERE id = 1");
    $t = $stmt->fetch(PDO::FETCH_ASSOC);
    print_r($t);
    
    echo "\nNow check 'Find Jobs' page.";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
