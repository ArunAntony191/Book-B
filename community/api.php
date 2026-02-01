<?php
require_once '../includes/db_helper.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

// Debug logging
function logDebug($msg) {
    file_put_contents('../debug_log.txt', date('[Y-m-d H:i:s] ') . $msg . "\n", FILE_APPEND);
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($action === 'create') {
            logDebug("Action: create - Start");
            $name = $_POST['name'] ?? '';
            $desc = $_POST['description'] ?? '';
            
            if (empty($name)) { 
                logDebug("Error: Community name is required");
                throw new Exception("Community name is required"); 
            }
            
            logDebug("Creating community: $name");
            
            // Handle Cover Upload
            $cover = null;
            if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../images/communities/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $fileName = time() . '_' . basename($_FILES['cover']['name']);
                if (move_uploaded_file($_FILES['cover']['tmp_name'], $uploadDir . $fileName)) {
                    $cover = 'images/communities/' . $fileName;
                    logDebug("Cover uploaded: $cover");
                }
            }

            try {
                logDebug("Attempting to insert into communities table");
                $stmt = $pdo->prepare("INSERT INTO communities (name, description, created_by, cover_image) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $desc, $userId, $cover]);
                $commId = $pdo->lastInsertId();
                logDebug("Community created with ID: $commId");

                // Auto-join creator
                logDebug("Auto-joining creator");
                $stmt = $pdo->prepare("INSERT INTO community_members (community_id, user_id) VALUES (?, ?)");
                $stmt->execute([$commId, $userId]);
                logDebug("Creator joined successfully");

                echo json_encode(['status' => 'success', 'community_id' => $commId]);
            } catch (PDOException $e) {
                logDebug("Database Error: " . $e->getMessage());
                throw new Exception("Database error: " . $e->getMessage());
            }

        } elseif ($action === 'join') {
            $commId = $_POST['community_id'];
            $stmt = $pdo->prepare("INSERT IGNORE INTO community_members (community_id, user_id) VALUES (?, ?)");
            $stmt->execute([$commId, $userId]);
            echo json_encode(['status' => 'success']);

        } elseif ($action === 'leave') {
            $commId = $_POST['community_id'];
            // Remove user from community members
            $stmt = $pdo->prepare("DELETE FROM community_members WHERE community_id = ? AND user_id = ?");
            $stmt->execute([$commId, $userId]);
            echo json_encode(['status' => 'success']);

        } elseif ($action === 'update_cover') {
            $commId = $_POST['community_id'];
            
            // Check if user is the creator
            $stmt = $pdo->prepare("SELECT created_by FROM communities WHERE id = ?");
            $stmt->execute([$commId]);
            $community = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$community || $community['created_by'] != $userId) {
                http_response_code(403);
                echo json_encode(['error' => 'Only the creator can update the community profile picture']);
                exit;
            }
            
            // Handle Cover Upload
            if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../images/communities/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $fileName = time() . '_' . basename($_FILES['cover']['name']);
                if (move_uploaded_file($_FILES['cover']['tmp_name'], $uploadDir . $fileName)) {
                    $cover = 'images/communities/' . $fileName;
                    
                    // Update community cover image
                    $stmt = $pdo->prepare("UPDATE communities SET cover_image = ? WHERE id = ?");
                    $stmt->execute([$cover, $commId]);
                    echo json_encode(['status' => 'success', 'cover_image' => $cover]);
                } else {
                    throw new Exception("Failed to upload image");
                }
            } else {
                throw new Exception("No image file provided");
            }

        } elseif ($action === 'delete') {
            $commId = $_POST['community_id'];
            
            // Check if user is the creator
            $stmt = $pdo->prepare("SELECT created_by FROM communities WHERE id = ?");
            $stmt->execute([$commId]);
            $community = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$community || $community['created_by'] != $userId) {
                http_response_code(403);
                echo json_encode(['error' => 'Only the creator can delete the community']);
                exit;
            }
            
            // Delete community (CASCADE will handle members and messages)
            $stmt = $pdo->prepare("DELETE FROM communities WHERE id = ?");
            $stmt->execute([$commId]);
            echo json_encode(['status' => 'success']);

        } elseif ($action === 'send_message') {
            $commId = $_POST['community_id'];
            $message = $_POST['message'] ?? '';
            
            $attachment = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../images/chat_uploads/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $fileName = time() . '_' . basename($_FILES['image']['name']);
                if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $fileName)) {
                    $attachment = 'images/chat_uploads/' . $fileName;
                }
            }

            if ($message || $attachment) {
                $stmt = $pdo->prepare("INSERT INTO community_messages (community_id, user_id, message, attachment_url) VALUES (?, ?, ?, ?)");
                $stmt->execute([$commId, $userId, $message, $attachment]);
                echo json_encode(['status' => 'success']);
            }
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if ($action === 'my_communities') {
            $stmt = $pdo->prepare("
                SELECT c.*, 
                (SELECT COUNT(*) FROM community_members cm WHERE cm.community_id = c.id) as member_count
                FROM communities c 
                JOIN community_members cm ON c.id = cm.community_id 
                WHERE cm.user_id = ?
            ");
            $stmt->execute([$userId]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

        } elseif ($action === 'search') {
            $q = $_GET['q'] ?? '';
            $stmt = $pdo->prepare("
                SELECT c.*, 
                (SELECT COUNT(*) FROM community_members cm WHERE cm.community_id = c.id) as member_count,
                (SELECT COUNT(*) FROM community_members cm WHERE cm.community_id = c.id AND cm.user_id = ?) as is_member
                FROM communities c 
                WHERE c.name LIKE ?
            ");
            $stmt->execute([$userId, "%$q%"]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

        } elseif ($action === 'discover') {
            // Get communities user is NOT in
            $stmt = $pdo->prepare("
                SELECT c.*, 
                (SELECT COUNT(*) FROM community_members cm WHERE cm.community_id = c.id) as member_count
                FROM communities c 
                WHERE c.id NOT IN (SELECT community_id FROM community_members WHERE user_id = ?)
                ORDER BY created_at DESC LIMIT 10
            ");
            $stmt->execute([$userId]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            
        } elseif ($action === 'messages') {
            $commId = $_GET['community_id'];
            $stmt = $pdo->prepare("
                SELECT m.*, u.firstname, u.lastname 
                FROM community_messages m 
                JOIN users u ON m.user_id = u.id 
                WHERE m.community_id = ? 
                ORDER BY m.created_at ASC
            ");
            $stmt->execute([$commId]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
