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

try {
    $success = markAllNotificationsAsRead($userId);
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update notifications']);
    }
} catch (Exception $e) {
    error_log("Mark all read error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
