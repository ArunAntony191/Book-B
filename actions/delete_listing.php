<?php
session_start();
require_once '../includes/db_helper.php';

// Check if logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

$listingId = $_POST['listing_id'] ?? 0;
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];

if (!$listingId) {
    echo json_encode(['success' => false, 'message' => 'Invalid listing ID.']);
    exit();
}

// Authorization check: Admin OR Owner
$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT user_id FROM listings WHERE id = ?");
$stmt->execute([$listingId]);
$listing = $stmt->fetch();

if (!$listing) {
    echo json_encode(['success' => false, 'message' => 'Listing not found.']);
    exit();
}

if ($userRole !== 'admin' && $listing['user_id'] != $userId) {
    echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this listing.']);
    exit();
}

if (deleteListing($listingId)) {
    echo json_encode(['success' => true, 'message' => 'Listing deleted successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete listing. It might have active transactions.']);
}
?>
