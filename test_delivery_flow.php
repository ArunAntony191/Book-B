<?php
// Test Script for Delivery Logic
require_once 'includes/db_helper.php';
require_once 'includes/class.delivery.php';

session_start();

// Mock User Session (Simulating Agent)
// In a real scenario, we'd need a real user ID.
// This script assumes there is a user with ID 1 (Admin/Agent) or similar.
// Better to run this where we can control the DB state.

try {
    $pdo = getDBConnection();
    $dm = new DeliveryManager();

    // 1. Create a dummy transaction
    $stmt = $pdo->query("SELECT id FROM users WHERE role='delivery_agent' LIMIT 1");
    $agentId = $stmt->fetchColumn(); 
    
    if (!$agentId) die("No agent found for testing.");

    $stmt = $pdo->query("SELECT id FROM transactions WHERE status='approved' AND delivery_agent_id IS NULL LIMIT 1");
    $txId = $stmt->fetchColumn();

    if (!$txId) {
        die("No available job found to test. Please create a request first.");
    }

    echo "Testing with Agent ID: $agentId and Transaction ID: $txId\n";

    // Test 1: Claim Job
    echo "1. Claiming Job... ";
    try {
        $dm->claimJob($agentId, $txId);
        echo "SUCCESS\n";
    } catch (Exception $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
    }

    // Test 2: Try to deliver before picking up (Should Fail)
    echo "2. Testing premature delivery (should fail)... ";
    try {
        $dm->updateStatus($agentId, $txId, 'delivered');
        echo "FAILED (Unexpected success)\n";
    } catch (Exception $e) {
        echo "SUCCESS (Caught expected error: " . $e->getMessage() . ")\n";
    }

    // Test 3: Pickup (Active)
    echo "3. Confirming Pickup... ";
    try {
        $dm->updateStatus($agentId, $txId, 'active');
        echo "SUCCESS\n";
    } catch (Exception $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
    }

    // Test 4: Verify Timestamp
    $stmt = $pdo->prepare("SELECT picked_up_at FROM transactions WHERE id = ?");
    $stmt->execute([$txId]);
    $ts = $stmt->fetchColumn();
    echo "4. Checking timestamp... " . ($ts ? "SUCCESS ($ts)" : "FAILED") . "\n";

    // Test 5: Complete Delivery
    echo "5. Completing Delivery... ";
    try {
        $dm->updateStatus($agentId, $txId, 'delivered');
        echo "SUCCESS\n";
    } catch (Exception $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
    }

} catch (Exception $e) {
    echo "Critical Error: " . $e->getMessage();
}
?>
