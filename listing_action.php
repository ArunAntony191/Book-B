<?php
require_once 'includes/db_helper.php';
session_start();

header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';
$listingId = $_POST['listing_id'] ?? 0;

try {
    $pdo = getDBConnection();

    if ($action === 'delete_listing') {
        // Check if listing belongs to user
        $stmt = $pdo->prepare("SELECT user_id FROM listings WHERE id = ?");
        $stmt->execute([$listingId]);
        $listing = $stmt->fetch();
        
        if (!$listing || $listing['user_id'] != $userId) {
            throw new Exception("Unauthorized");
        }
        
        // Check if there are any active transactions
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE listing_id = ? AND status IN ('requested', 'approved', 'active')");
        $stmt->execute([$listingId]);
        $activeCount = $stmt->fetchColumn();
        
        if ($activeCount > 0) {
            throw new Exception("Cannot delete listing with active transactions");
        }
        
        // Delete the listing
        $stmt = $pdo->prepare("DELETE FROM listings WHERE id = ?");
        $stmt->execute([$listingId]);
        
        echo json_encode(['success' => true, 'message' => 'Listing deleted successfully']);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
