<?php
require_once '../includes/db_helper.php';
session_start();

header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$notificationId = $_POST['notification_id'] ?? 0;

if (!$notificationId) {
    echo json_encode(['success' => false, 'message' => 'Notification ID required']);
    exit();
}

try {
    $pdo = getDBConnection();
    
    // Verify notification belongs to user before deleting
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
    $success = $stmt->execute([$notificationId, $userId]);
    
    if ($success && $stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Notification deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Notification not found or already deleted']);
    }
} catch (Exception $e) {
    error_log("Delete notification error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to delete notification']);
}
