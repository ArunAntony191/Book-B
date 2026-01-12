<?php
require_once 'includes/db_helper.php';

try {
    $pdo = getDBConnection();

    echo "--- Debugging Transactions ---\n";
    $stmt = $pdo->query("SELECT id, status, delivery_method, delivery_agent_id FROM transactions");
    $txs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Total Transactions: " . count($txs) . "\n";
    foreach ($txs as $t) {
        echo "ID: {$t['id']} | Status: {$t['status']} | Method: {$t['delivery_method']} | Agent: {$t['delivery_agent_id']}\n";
    }

    echo "\n--- checking delivery_jobs.php Logic ---\n";
    // Mimic the query from delivery_jobs.php
    $sql = "
        SELECT t.id 
        FROM transactions t
        WHERE t.delivery_method = 'delivery' 
        AND t.status = 'approved' 
        AND t.delivery_agent_id IS NULL
    ";
    $stmt = $pdo->query($sql);
    $jobs = $stmt->fetchAll();
    echo "Query found " . count($jobs) . " eligible jobs.\n";

    if (count($jobs) == 0) {
        echo "POSSIBLE CAUSE: No transactions match (delivery='delivery' AND status='approved' AND agent=NULL).\n";
        echo "If you have 'approved' transactions, they might be marked as 'pickup' (default) or have an agent assigned.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
