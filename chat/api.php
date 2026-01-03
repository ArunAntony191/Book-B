<?php
require_once '../includes/db_helper.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();

// Ensure the messages table exists in the main database
try {
    $pdo->query("SELECT 1 FROM messages LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
}

if ($method === 'GET') {
    $user1 = $_GET['user1'] ?? 0;
    $user2 = $_GET['user2'] ?? 0;

    if ($user1 && $user2) {
        $stmt = $pdo->prepare("SELECT * FROM messages WHERE 
            (sender_id = ? AND receiver_id = ?) OR 
            (sender_id = ? AND receiver_id = ?) 
            ORDER BY created_at ASC");
        $stmt->execute([$user1, $user2, $user2, $user1]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } else {
        echo json_encode([]);
    }
} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $sender_id = $data['sender_id'] ?? 0;
    $receiver_id = $data['receiver_id'] ?? 0;
    $message = $data['message'] ?? '';

    if ($sender_id && $receiver_id && $message) {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $stmt->execute([$sender_id, $receiver_id, $message]);
        echo json_encode(['status' => 'success']);
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
    }
}
?>
