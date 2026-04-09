<?php
/**
 * Delivery Agent Diagnostics
 * Check what delivery agents exist and their service areas
 */

require_once '../includes/db_helper.php';

echo "=== DELIVERY AGENT DIAGNOSTICS ===\n\n";

try {
    $pdo = getDBConnection();
    
    // Get all delivery agents
    $stmt = $pdo->prepare("
        SELECT id, email, firstname, lastname, 
               service_start_lat, service_start_lng, 
               service_end_lat, service_end_lng,
               is_accepting_deliveries, is_banned
        FROM users 
        WHERE role = 'delivery_agent'
    ");
    $stmt->execute();
    $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($agents)) {
        echo "❌ NO DELIVERY AGENTS FOUND\n\n";
    } else {
        echo "✅ Found " . count($agents) . " delivery agent(s):\n\n";
        
        foreach ($agents as $agent) {
            echo "Agent #{$agent['id']}: {$agent['firstname']} {$agent['lastname']}\n";
            echo "   Email: {$agent['email']}\n";
            echo "   Status: " . ($agent['is_banned'] ? '🚫 BANNED' : '✅ Active') . "\n";
            echo "   Accepting Deliveries: " . ($agent['is_accepting_deliveries'] ? '✅ YES' : '❌ NO') . "\n";
            
            if ($agent['service_start_lat'] && $agent['service_start_lng']) {
                echo "   Service Start: ({$agent['service_start_lat']}, {$agent['service_start_lng']})\n";
            } else {
                echo "   Service Start: ❌ NOT SET\n";
            }
            
            if ($agent['service_end_lat'] && $agent['service_end_lng']) {
                echo "   Service End: ({$agent['service_end_lat']}, {$agent['service_end_lng']})\n";
            } else {
                echo "   Service End: ❌ NOT SET\n";
            }
            
            echo "\n";
        }
        
        // Count agents that are ready
        $ready = array_filter($agents, function($a) {
            return !$a['is_banned'] && 
                   $a['is_accepting_deliveries'] && 
                   $a['service_start_lat'] && 
                   $a['service_end_lat'];
        });
        
        echo "📊 Summary:\n";
        echo "   Total agents: " . count($agents) . "\n";
        echo "   Ready for deliveries: " . count($ready) . "\n";
        echo "   Not accepting: " . count(array_filter($agents, fn($a) => !$a['is_accepting_deliveries'])) . "\n";
        echo "   Missing coordinates: " . count(array_filter($agents, fn($a) => !$a['service_start_lat'] || !$a['service_end_lat'])) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
