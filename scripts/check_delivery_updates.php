<?php
// Quick script to check delivery update status
require_once '../includes/db_helper.php';

try {
    $pdo = getDBConnection();
    
    // Find recent deliveries with status
    $stmt = $pdo->query("
        SELECT 
            t.id,
            t.status,
            t.created_at,
            t.picked_up_at,
            t.delivered_at,
            t.lender_confirm_at,
            t.borrower_confirm_at,
            t.delivery_agent_id,
            b.title,
            u_agent.firstname as agent_name
        FROM transactions t
        JOIN listings l ON t.listing_id = l.id
        JOIN books b ON l.book_id = b.id
        LEFT JOIN users u_agent ON t.delivery_agent_id = u_agent.id
        WHERE t.delivery_method = 'delivery'
        ORDER BY t.id DESC
        LIMIT 5
    ");
    
    $deliveries = $stmt->fetchAll();
    
    echo "Recent Deliveries:\n";
    echo str_repeat("=", 80) . "\n";
    
    foreach ($deliveries as $d) {
        echo "ID: {$d['id']} | Title: {$d['title']}\n";
        echo "Status: {$d['status']}\n";
        echo "Agent: " . ($d['agent_name'] ?? 'None') . " (ID: " . ($d['delivery_agent_id'] ?? 'N/A') . ")\n";
        echo "Created: {$d['created_at']}\n";
        echo "Picked Up: " . ($d['picked_up_at'] ?? 'N/A') . "\n";
        echo "Delivered: " . ($d['delivered_at'] ?? 'N/A') . "\n";
        echo "Lender Confirmed: " . ($d['lender_confirm_at'] ?? 'N/A') . "\n";
        echo "Borrower Confirmed: " . ($d['borrower_confirm_at'] ?? 'N/A') . "\n";
        echo str_repeat("-", 80) . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
