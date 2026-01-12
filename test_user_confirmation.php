<?php
// Test Script for Multi-Party Confirmation
require_once 'includes/db_helper.php';
require_once 'includes/class.delivery.php';

session_start();

try {
    $pdo = getDBConnection();
    $dm = new DeliveryManager();

    // 1. Find a transaction that is "In Transit" (active) with an agent assigned
    $stmt = $pdo->query("SELECT id, lender_id, borrower_id, delivery_agent_id FROM transactions WHERE status='active' AND delivery_agent_id IS NOT NULL LIMIT 1");
    $tx = $stmt->fetch();

    if (!$tx) {
        die("No active 'In Transit' transaction found. Please create one ensuring it has an agent assigned and status is 'active'.\n");
    }

    $txId = $tx['id'];
    $lenderId = $tx['lender_id'];
    $borrowerId = $tx['borrower_id'];

    echo "Testing Transaction ID: $txId (Lender: $lenderId, Borrower: $borrowerId)\n";

    // Test 1: Lender Confirms Handover
    echo "1. Lender ($lenderId) confirming handover... ";
    try {
        $dm->confirmHandover($lenderId, $txId);
        echo "SUCCESS\n";
    } catch (Exception $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
    }

    // Now let's move it to delivered to test Borrower
    echo "2. Agent delivering (simulating)... ";
    try {
        $dm->updateStatus($tx['delivery_agent_id'], $txId, 'delivered');
        echo "SUCCESS\n";
    } catch (Exception $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
    }

    // Test 2: Borrower Confirms Receipt
    echo "3. Borrower ($borrowerId) confirming receipt... ";
    try {
        $dm->confirmReceipt($borrowerId, $txId);
        echo "SUCCESS\n";
    } catch (Exception $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
    }

    // Test 3: Double Confirmation (Should fail)
    echo "4. Borrower trying to confirm again... ";
    try {
        $dm->confirmReceipt($borrowerId, $txId);
        echo "FAILED (Unexpected success)\n";
    } catch (Exception $e) {
        echo "SUCCESS (Caught expected error)\n";
    }

} catch (Exception $e) {
    echo "Critical Error: " . $e->getMessage();
}
?>
