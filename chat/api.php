<?php
require_once '../includes/db_helper.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();

// Ensure the messages table exists and has all required columns
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        message TEXT DEFAULT NULL,
        attachment_url VARCHAR(255) DEFAULT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Individually ensure new columns exist for older installations
    $columns = [
        'attachment_url' => "ALTER TABLE messages ADD COLUMN attachment_url VARCHAR(255) DEFAULT NULL AFTER message",
        'is_read' => "ALTER TABLE messages ADD COLUMN is_read TINYINT(1) DEFAULT 0 AFTER attachment_url",
        'sender_deleted' => "ALTER TABLE messages ADD COLUMN sender_deleted TINYINT(1) DEFAULT 0",
        'receiver_deleted' => "ALTER TABLE messages ADD COLUMN receiver_deleted TINYINT(1) DEFAULT 0"
    ];
    
    foreach ($columns as $col => $sql) {
        try {
            $pdo->query("SELECT $col FROM messages LIMIT 1");
        } catch (PDOException $e) {
            $pdo->exec($sql);
        }
    }
} catch (PDOException $e) {
    // Non-blocking but log it
    error_log("Schema sync error: " . $e->getMessage());
}

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'messages';
    
    if ($action === 'unread_counts') {
        $userId = $_GET['user_id'] ?? 0;
        if ($userId) {
            // Clean up self-messages just in case
            $pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = receiver_id AND receiver_id = ? AND is_read = 0")->execute([$userId]);
            
            $stmt = $pdo->prepare("SELECT sender_id, COUNT(*) as count FROM messages WHERE receiver_id = ? AND is_read = 0 AND receiver_deleted = 0 GROUP BY sender_id");
            $stmt->execute([$userId]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } else {
            echo json_encode([]);
        }
        exit();
    }

    if ($action === 'get_user_info') {
        $id = $_GET['id'] ?? 0;
        if($id) {
            $stmt = $pdo->prepare("SELECT id, firstname, lastname FROM users WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
        } else {
            echo json_encode(['error' => 'No ID provided']);
        }
        exit();
    }

    if ($action === 'search_contacts') {
        $query = $_GET['q'] ?? '';
        $excludeId = $_GET['user_id'] ?? 0;
        if (strlen($query) >= 2) {
            $users = searchUsers($query, $excludeId);
            echo json_encode($users);
        } else {
            echo json_encode([]);
        }
        exit();
    }

    $user1 = $_GET['user1'] ?? 0;
    $user2 = $_GET['user2'] ?? 0;

    if ($user1 && $user2) {
        // Mark as read (user1 is usually the receiver fetching the messages)
        $stmtMark = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
        $stmtMark->execute([$user2, $user1]);
        
        // Also mark related notifications as read
        $stmtNotif = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND reference_id = ? AND (type = 'message' OR type = 'support' OR type = 'support_reply')");
        $stmtNotif->execute([$user1, $user2]);

        $stmt = $pdo->prepare("SELECT * FROM messages WHERE 
            (sender_id = ? AND receiver_id = ? AND sender_deleted = 0) OR 
            (sender_id = ? AND receiver_id = ? AND receiver_deleted = 0) 
            ORDER BY created_at ASC");
        $stmt->execute([$user1, $user2, $user2, $user1]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } else {
        echo json_encode([]);
    }
} elseif ($method === 'POST') {
    $sender_id = $_POST['sender_id'] ?? 0;
    $receiver_id = $_POST['receiver_id'] ?? 0;
    $message = $_POST['message'] ?? '';
    $attachment_url = null;

    // Handle File Upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../images/chat_uploads/';
        $fileName = time() . '_' . basename($_FILES['image']['name']);
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
            $attachment_url = 'images/chat_uploads/' . $fileName;
        }
    }

    if ($sender_id && $receiver_id && ($message || $attachment_url)) {
        // Block non-admin users from sending messages to admins (Prevent spam)
        $stmtCheck = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmtCheck->execute([$receiver_id]);
        $receiverRole = $stmtCheck->fetchColumn();
        
        if ($receiverRole === 'admin') {
            $stmtSender = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmtSender->execute([$sender_id]);
            if ($stmtSender->fetchColumn() !== 'admin') {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Direct messages to admin are disabled. Please use the Support section in Settings.']);
                exit();
            }
        }

        $is_read = ($sender_id === $receiver_id) ? 1 : 0;
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message, attachment_url, is_read) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$sender_id, $receiver_id, $message, $attachment_url, $is_read])) {
            // Add notification for receiver (if not a self-message)
            if ($sender_id !== $receiver_id) {
                $stmtUser = $pdo->prepare("SELECT firstname, lastname FROM users WHERE id = ?");
                $stmtUser->execute([$sender_id]);
                $sender = $stmtUser->fetch();
                $senderName = $sender ? ($sender['firstname'] . ' ' . $sender['lastname']) : 'Someone';
                
                $notifType = 'message';
                $msgSnippet = $message ? mb_strimwidth($message, 0, 80, "...") : 'sent an image';
                $notifMsg = "$senderName: $msgSnippet";
                
                // Special handling for admin messages
                $stmtAdmin = $pdo->prepare("SELECT role FROM users WHERE id = ?");
                $stmtAdmin->execute([$sender_id]);
                if ($stmtAdmin->fetchColumn() === 'admin') {
                    $notifType = 'support_reply';
                    $notifMsg = "Admin response: $msgSnippet";
                }
                
                createNotification($receiver_id, $notifType, $notifMsg, $sender_id);
            }
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to send message']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid data or empty message']);
    }
} elseif ($method === 'DELETE') {
    $user1 = $_GET['user1'] ?? 0;
    $user2 = $_GET['user2'] ?? 0;

    if ($user1 && $user2) {
        // Soft Delete for User 1 (Current User)
        // 1. messages sent by user1 to user2 -> set sender_deleted = 1
        $pdo->prepare("UPDATE messages SET sender_deleted = 1 WHERE sender_id = ? AND receiver_id = ?")->execute([$user1, $user2]);
        
        // 2. messages received by user1 from user2 -> set receiver_deleted = 1
        $pdo->prepare("UPDATE messages SET receiver_deleted = 1 WHERE receiver_id = ? AND sender_id = ?")->execute([$user1, $user2]);

        echo json_encode(['status' => 'success', 'message' => 'Chat cleared']);
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing user IDs']);
    }
}
?>
