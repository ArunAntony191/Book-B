<?php
// Update existing records with empty status based on their timestamps
require_once 'config/database.php';

try {
    $pdo = getDBConnection();
    
    echo "Updating existing records with correct statuses...\n\n";
    
    // Set to 'delivered' if delivered_at is set
    $stmt = $pdo->exec("
        UPDATE transactions 
        SET status = 'delivered' 
        WHERE status = '' 
        AND delivered_at IS NOT NULL
    ");
    echo "✓ Updated {$stmt} records to 'delivered'\n";
    
    // Set to 'active' if picked_up_at is set but not delivered
    $stmt = $pdo->exec("
        UPDATE transactions 
        SET status = 'active' 
        WHERE status = '' 
        AND picked_up_at IS NOT NULL 
        AND delivered_at IS NULL
    ");
    echo "✓ Updated {$stmt} records to 'active'\n";
    
    // Set to 'approved' if agent assigned but not picked up
    $stmt = $pdo->exec("
        UPDATE transactions 
        SET status = 'approved' 
        WHERE status = '' 
        AND delivery_agent_id IS NOT NULL 
        AND picked_up_at IS NULL
    ");
    echo "✓ Updated {$stmt} records to 'approved'\n";
    
    // Set to 'requested' for any remaining empty statuses
    $stmt = $pdo->exec("
        UPDATE transactions 
        SET status = 'requested' 
        WHERE status = ''
    ");
    echo "✓ Updated {$stmt} records to 'requested'\n";
    
    echo "\nVerifying updates...\n";
    $stmt = $pdo->query("
        SELECT id, status, picked_up_at, delivered_at 
        FROM transactions 
        WHERE delivery_method = 'delivery'
        ORDER BY id DESC 
        LIMIT 5
    ");
    
    $records = $stmt->fetchAll();
    foreach ($records as $r) {
        echo "ID {$r['id']}: status='{$r['status']}', picked_up={$r['picked_up_at']}, delivered={$r['delivered_at']}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
