<?php
// Simulate what track_deliveries.php would show for transactions
require_once '../includes/db_helper.php';

function getStatusProgress($status, $agentId) {
    switch ($status) {
        case 'requested': return 10;
        case 'approved': return $agentId ? 40 : 25;
        case 'active': return 75;
        case 'delivered': return 100;
        default: return 0;
    }
}

function getStatusLabel($status, $agentId) {
    switch ($status) {
        case 'requested': return 'Waiting for Owner';
        case 'approved': return $agentId ? 'Agent Assigned' : 'Finding Agent';
        case 'active': return 'Out for Delivery';
        case 'delivered': return 'Delivered';
        default: return ucfirst($status);
    }
}

try {
    $pdo = getDBConnection();
    
    $stmt = $pdo->query("
        SELECT t.*, b.title
        FROM transactions t
        JOIN listings l ON t.listing_id = l.id
        JOIN books b ON l.book_id = b.id
        WHERE t.id = 4
    ");
    
    $d = $stmt->fetch();
    
    echo "Transaction #TRK-{$d['id']} - {$d['title']}\n";
    echo str_repeat("=", 80) . "\n";
    echo "Database Status: '{$d['status']}'\n";
    echo "Agent ID: " . ($d['delivery_agent_id'] ?? 'N/A') . "\n";
    echo "Picked Up: " . ($d['picked_up_at'] ?? 'N/A') . "\n";
    echo "Delivered: " . ($d['delivered_at'] ?? 'N/A') . "\n";
    echo str_repeat("-", 80) . "\n";
    echo "UI Display:\n";
    echo "  Progress: " . getStatusProgress($d['status'], $d['delivery_agent_id']) . "%\n";
    echo "  Status Label: " . getStatusLabel($d['status'], $d['delivery_agent_id']) . "\n";
    echo "\n";
    
    if ($d['status'] === 'delivered') {
        echo "✓ FIX SUCCESSFUL: Transaction now shows as 'Delivered'\n";
    } else {
        echo "✗ ISSUE: Status is '{$d['status']}', expected 'delivered'\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
