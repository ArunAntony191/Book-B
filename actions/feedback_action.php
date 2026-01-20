<?php
require_once '../includes/db_helper.php';
session_start();

header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$action = $_POST['action'] ?? '';

if ($action === 'submit_feedback') {
    $transactionId = $_POST['transaction_id'] ?? 0;
    $revieweeId = $_POST['reviewee_id'] ?? 0;
    $rating = $_POST['rating'] ?? 0;
    $comment = trim($_POST['comment'] ?? '');

    if (!$transactionId || !$revieweeId || !$rating) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit();
    }

    // Check if user has already reviewed this person for this transaction
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id FROM reviews WHERE transaction_id = ? AND reviewer_id = ? AND reviewee_id = ?");
        $stmt->execute([$transactionId, $userId, $revieweeId]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'You have already reviewed this person for this transaction.']);
            exit();
        }

        if (addReview($transactionId, $userId, $revieweeId, $rating, $comment)) {
            echo json_encode(['success' => true, 'message' => 'Feedback submitted successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to submit feedback.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
