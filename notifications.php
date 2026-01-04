<?php
require_once 'includes/db_helper.php';
require_once 'paths.php';
session_start();

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) { header("Location: login.php"); exit(); }

// Mark all as read when page is visited
markAllNotificationsAsRead($userId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications | BOOK-B</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/dashboard_sidebar.php'; ?>
        
        <main class="main-content">
            <h1 style="font-size: 1.8rem; font-weight: 800; margin-bottom: 2rem;">Notifications</h1>
            
            <div style="background: white; border-radius: var(--radius-lg); border: 1px solid var(--border-color); overflow: hidden;">
                <?php
                try {
                    $pdo = getDBConnection();
                    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
                    $stmt->execute([$userId]);
                    $notifs = $stmt->fetchAll();
                    
                    if (count($notifs) > 0) {
                        foreach ($notifs as $n) {
                            $highlight = $n['is_read'] ? '' : 'background: #f0f9ff;';
                            $isRequest = strpos($n['type'], '_request') !== false;
                            
                            echo "
                            <div style='padding: 1.5rem; border-bottom: 1px solid var(--border-color); $highlight' id='notif-{$n['id']}'>
                                <div style='display: flex; gap: 1rem; align-items: start;'>
                                    <div style='width: 40px; height: 40px; background: #e2e8f0; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;'>
                                        <i class='bx bx-bell' style='font-size: 1.2rem; color: var(--text-main);'></i>
                                    </div>
                                    <div style='flex: 1;'>
                                        <div style='font-size: 1rem; color: var(--text-main); margin-bottom: 0.25rem;'>{$n['message']}</div>
                                        <div style='font-size: 0.8rem; color: var(--text-muted);'>" . date('M d, h:i A', strtotime($n['created_at'])) . "</div>
                                    </div>
                                </div>";
                            
                            if ($isRequest && $n['reference_id']) {
                                echo "
                                <div style='display: flex; gap: 0.75rem; margin-top: 1rem;'>
                                    <button class='btn btn-primary btn-sm' onclick='handleRequest({$n['reference_id']}, \"accept\", {$n['id']})'>Accept</button>
                                    <button class='btn btn-outline btn-sm' onclick='handleRequest({$n['reference_id']}, \"decline\", {$n['id']})'>Decline</button>
                                </div>";
                            }
                            
                            echo "</div>";
                        }
                    } else {
                        echo "<div style='padding: 2rem; text-align: center; color: var(--text-muted);'>No new notifications.</div>";
                    }
                } catch (Exception $e) {
                    echo "<div style='padding: 1rem; color: red;'>Error loading notifications.</div>";
                }
                ?>
            </div>
        </main>
    </div>
    
    <script>
        async function handleRequest(transactionId, action, notifId) {
            try {
                const response = await fetch('request_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=${action}_request&transaction_id=${transactionId}`
                });
                
                const data = await response.json();
                if (data.success) {
                    alert(data.message);
                    document.getElementById('notif-' + notifId).remove();
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (err) {
                alert('Failed to process request');
                console.error(err);
            }
        }
    </script>
</body>
</html>
