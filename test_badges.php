<?php
require_once 'includes/db_helper.php';
session_start();

// Mock user ID (change to an actual user ID if testing with real data)
$userId = 8; 
$_SESSION['user_id'] = $userId;

echo "--- Badge Verification Test ---\n";

$dealsCount = getUnreadRequestsCount($userId);
$deliveryCount = getUnreadDeliveryUpdatesCount($userId);

echo "Initial Counts:\n";
echo " - Deals: $dealsCount\n";
echo " - Delivery: $deliveryCount\n\n";

// Find an unread notification for this user to test clearing
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT reference_id, type FROM notifications WHERE user_id = ? AND is_read = 0 LIMIT 1");
    $stmt->execute([$userId]);
    $notif = $stmt->fetch();

    if ($notif) {
        $txId = $notif['reference_id'];
        echo "Found unread notification (Type: {$notif['type']}, TxID: $txId). Clearing...\n";
        
        markTxNotificationsRead($userId, $txId);
        
        $newDeals = getUnreadRequestsCount($userId);
        $newDelivery = getUnreadDeliveryUpdatesCount($userId);
        
        echo "Updated Counts:\n";
        echo " - Deals: $newDeals\n";
        echo " - Delivery: $newDelivery\n";
        
        if ($newDeals < $dealsCount || $newDelivery < $deliveryCount) {
            echo "SUCCESS: Count decremented correctly.\n";
        } else {
            echo "INFO: Count check complete (might not change if notification wasn't in tracked categories).\n";
        }
    } else {
        echo "No unread notifications found for User $userId. Try requesting a book first.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
