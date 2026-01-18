<?php
require_once 'includes/db_helper.php';
require_once 'paths.php';
session_start();

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) { header("Location: login.php"); exit(); }

// Handle Mark All as Read
if (isset($_POST['mark_read'])) {
    markAllNotificationsAsRead($userId);
    header("Location: notifications.php");
    exit();
}

$filter = $_GET['filter'] ?? 'all';
$status = $_GET['status'] ?? 'all';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications | BOOK-B</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        .filter-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            align-items: center;
            flex-wrap: wrap;
        }
        .filter-pill {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            background: #f1f5f9;
            color: var(--text-muted);
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid transparent;
            text-decoration: none;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .filter-pill:hover { background: #e2e8f0; }
        .filter-pill.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        .filter-badge {
            background: var(--primary);
            color: white;
            font-size: 0.7rem;
            padding: 0.1rem 0.5rem;
            border-radius: 10px;
            min-width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }
        .filter-pill.active .filter-badge {
            background: white;
            color: var(--primary);
        }
        .mark-read-btn {
            margin-left: auto;
            background: none;
            border: none;
            color: var(--primary);
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .mark-read-btn:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/dashboard_sidebar.php'; ?>
        
        <main class="main-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <h1 style="font-size: 1.8rem; font-weight: 800; margin: 0;">Notifications</h1>
                <form method="POST">
                    <button type="submit" name="mark_read" class="mark-read-btn">
                        <i class='bx bx-check-double'></i> Mark all as Read
                    </button>
                </form>
            </div>

            <div class="filter-bar">
                <?php
                // Get unread counts for each category
                $unreadCounts = [
                    'all' => getUnreadNotificationsCount($userId),
                    'requests' => 0,
                    'delivery' => 0,
                    'system' => 0,
                    'action' => 0
                ];
                
                try {
                    $pdo = getDBConnection();
                    // 1. Unread counts by type
                    $stmt = $pdo->prepare("
                        SELECT type, COUNT(*) as count 
                        FROM notifications 
                        WHERE user_id = ? AND is_read = 0 
                        GROUP BY type
                    ");
                    $stmt->execute([$userId]);
                    $countsByType = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                    
                    $requestTypes = ['borrow_request', 'sell_request', 'exchange_request', 'request_accepted', 'request_declined'];
                    $deliveryTypes = ['delivery_assigned', 'delivery_cancelled', 'delivery_pending_confirmation', 'delivery_update', 'receipt_confirmed', 'borrower_confirmed'];
                    
                    foreach ($countsByType as $type => $count) {
                        if (in_array($type, $requestTypes)) {
                            $unreadCounts['requests'] += $count;
                        } elseif (in_array($type, $deliveryTypes)) {
                            $unreadCounts['delivery'] += $count;
                        } else {
                            $unreadCounts['system'] += $count;
                        }
                    }

                    // 2. Action Required Count (Pending requests)
                    $stmt = $pdo->prepare("
                        SELECT COUNT(DISTINCT n.id) 
                        FROM notifications n
                        JOIN transactions t ON n.reference_id = t.id
                        WHERE n.user_id = ? 
                        AND n.type LIKE '%_request' 
                        AND t.status = 'requested'
                    ");
                    $stmt->execute([$userId]);
                    $unreadCounts['action'] = (int)$stmt->fetchColumn();

                } catch (Exception $e) {}
                ?>

                <a href="?filter=all&status=<?php echo $status; ?>" class="filter-pill <?php echo $filter == 'all' ? 'active' : ''; ?>">
                    All <?php echo $unreadCounts['all'] > 0 ? "<span class='filter-badge'>{$unreadCounts['all']}</span>" : ''; ?>
                </a>
                <a href="?filter=requests&status=<?php echo $status; ?>" class="filter-pill <?php echo $filter == 'requests' ? 'active' : ''; ?>">
                    Requests <?php echo $unreadCounts['requests'] > 0 ? "<span class='filter-badge'>{$unreadCounts['requests']}</span>" : ''; ?>
                </a>
                <a href="?filter=delivery&status=<?php echo $status; ?>" class="filter-pill <?php echo $filter == 'delivery' ? 'active' : ''; ?>">
                    Delivery <?php echo $unreadCounts['delivery'] > 0 ? "<span class='filter-badge'>{$unreadCounts['delivery']}</span>" : ''; ?>
                </a>
                <a href="?filter=system&status=<?php echo $status; ?>" class="filter-pill <?php echo $filter == 'system' ? 'active' : ''; ?>">
                    System <?php echo $unreadCounts['system'] > 0 ? "<span class='filter-badge'>{$unreadCounts['system']}</span>" : ''; ?>
                </a>
                
                <div style="width: 1px; height: 20px; background: var(--border-color); margin: 0 0.5rem;"></div>
                
                <a href="?filter=<?php echo $filter; ?>&status=action" class="filter-pill <?php echo $status == 'action' ? 'active' : ''; ?>">
                    Action Required <?php echo $unreadCounts['action'] > 0 ? "<span class='filter-badge'>{$unreadCounts['action']}</span>" : ''; ?>
                </a>
                <a href="?filter=<?php echo $filter; ?>&status=unread" class="filter-pill <?php echo $status == 'unread' ? 'active' : ''; ?>">Unread Only</a>
            </div>
            
            <div style="background: white; border-radius: var(--radius-lg); border: 1px solid var(--border-color); overflow: hidden;">
                <?php
                try {
                    $pdo = getDBConnection();
                    
                    $conditions = ["n.user_id = ?"];
                    $params = [$userId];

                    if ($status === 'unread') {
                        $conditions[] = "n.is_read = 0";
                    } elseif ($status === 'action') {
                        $conditions[] = "n.type LIKE '%_request' AND t.status = 'requested'";
                    }

                    if ($filter === 'requests') {
                        $conditions[] = "n.type IN ('borrow_request', 'exchange_request', 'sell_request', 'request_accepted', 'request_declined')";
                    } elseif ($filter === 'delivery') {
                        $conditions[] = "n.type IN ('delivery_assigned', 'delivery_cancelled', 'delivery_pending_confirmation', 'delivery_update', 'receipt_confirmed', 'borrower_confirmed')";
                    } elseif ($filter === 'system') {
                        $conditions[] = "n.type NOT IN ('borrow_request', 'exchange_request', 'sell_request', 'request_accepted', 'request_declined', 'delivery_assigned', 'delivery_cancelled', 'delivery_pending_confirmation', 'delivery_update', 'receipt_confirmed', 'borrower_confirmed')";
                    }

                    $whereClause = implode(" AND ", $conditions);

                    $stmt = $pdo->prepare("
                        SELECT n.*, t.status as transaction_status 
                        FROM notifications n 
                        LEFT JOIN transactions t ON n.reference_id = t.id 
                        WHERE $whereClause 
                        ORDER BY n.created_at DESC
                    ");
                    $stmt->execute($params);
                    $notifs = $stmt->fetchAll();
                    
                    if (count($notifs) > 0) {
                        foreach ($notifs as $n) {
                            $highlight = $n['is_read'] ? '' : 'background: #f0f9ff;';
                            $isRequest = strpos($n['type'], '_request') !== false;
                            $canAction = ($isRequest && $n['reference_id'] && $n['transaction_status'] === 'requested');
                            
                            // Map icons and colors
                            $icon = 'bx-bell';
                            $iconColor = 'var(--text-main)';
                            $bgType = '#e2e8f0';

                            if (strpos($n['type'], 'request') !== false) {
                                $icon = 'bx-git-pull-request';
                                $iconColor = '#3b82f6';
                                $bgType = '#eff6ff';
                            } elseif (strpos($n['type'], 'delivery') !== false) {
                                $icon = 'bx-package';
                                $iconColor = '#f59e0b';
                                $bgType = '#fffbeb';
                            } elseif ($n['type'] === 'system') {
                                $icon = 'bx-cog';
                                $iconColor = '#64748b';
                                $bgType = '#f1f5f9';
                            } elseif (strpos($n['type'], 'credit') !== false) {
                                $icon = 'bx-coin-stack';
                                $iconColor = '#10b981';
                                $bgType = '#ecfdf5';
                            } elseif ($n['type'] === 'support') {
                                $icon = 'bx-support';
                                $iconColor = 'var(--primary)';
                                $bgType = 'var(--primary-light)15';
                            } elseif ($n['type'] === 'message') {
                                $icon = 'bx-message-square-dots';
                                $iconColor = '#6366f1';
                                $bgType = '#eef2ff';
                            } elseif ($n['type'] === 'support_reply') {
                                $icon = 'bx-support';
                                $iconColor = 'var(--primary)';
                                $bgType = 'var(--primary-light)15';
                            }

                            // Determine Link
                            $link = '#';
                            if ($n['type'] === 'message' || $n['type'] === 'support' || $n['type'] === 'support_reply') {
                                $link = "chat/index.php?user=" . $n['reference_id'];
                            } elseif (preg_match('/request|delivery|receipt|borrower_confirmed/', $n['type'])) {
                                $link = "delivery_details.php?id=" . $n['reference_id'];
                            }

                            echo "
                            <a href='{$link}' style='text-decoration: none; display: block; padding: 1.5rem; border-bottom: 1px solid var(--border-color); $highlight transition: all 0.2s;' id='notif-{$n['id']}'>
                                <div style='display: flex; gap: 1rem; align-items: start;'>
                                    <div style='width: 45px; height: 45px; background: {$bgType}; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: 1px solid rgba(0,0,0,0.05);'>
                                        <i class='bx {$icon}' style='font-size: 1.4rem; color: {$iconColor};'></i>
                                    </div>
                                    <div style='flex: 1;'>
                                        <div style='display: flex; justify-content: space-between; align-items: start; gap: 1rem;'>
                                            <div style='font-size: 0.95rem; color: var(--text-main); margin-bottom: 0.25rem; line-height: 1.5; font-weight: 500;'>{$n['message']}</div>
                                            " . ($n['transaction_status'] && $n['transaction_status'] !== 'requested' && (strpos($n['type'], 'request') !== false || strpos($n['type'], 'delivery') !== false) ? "
                                                <span class='badge badge-{$n['transaction_status']}' style='font-size: 0.7rem; text-transform: uppercase;'>{$n['transaction_status']}</span>
                                            " : "") . "
                                        </div>
                                        <div style='display: flex; align-items: center; gap: 0.5rem; font-size: 0.8rem; color: var(--text-muted);'>
                                            <i class='bx bx-time-five'></i>
                                            " . date('M d, h:i A', strtotime($n['created_at'])) . "
                                        </div>
                                    </div>
                                </div>";
                            
                            if ($canAction) {
                                echo "
                                <div style='display: flex; gap: 0.75rem; margin-top: 1rem;'>
                                    <button class='btn btn-primary btn-sm' onclick='handleRequest({$n['reference_id']}, \"accept\", {$n['id']})'>Accept</button>
                                    <button class='btn btn-outline btn-sm' onclick='handleRequest({$n['reference_id']}, \"decline\", {$n['id']})'>Decline</button>
                                </div>";
                            }
                            
                            echo "</a>";
                        }
                    } else {
                        echo "<div style='padding: 4rem; text-align: center; color: var(--text-muted);'>
                                <i class='bx bx-notification-off' style='font-size: 3rem; opacity: 0.2; margin-bottom: 1rem; display: block;'></i>
                                No notifications found for this filter.
                              </div>";
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
