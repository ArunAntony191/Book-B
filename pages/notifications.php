<?php
require_once '../includes/db_helper.php';
require_once '../paths.php';
session_start();

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) { header("Location: login.php"); exit(); }

// Notification filter logic
$filter = $_GET['filter'] ?? 'all';
$status = $_GET['status'] ?? 'all';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications | BOOK-B</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=1.2">
    <link rel="stylesheet" href="../assets/css/toast.css">
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
            background: var(--bg-body);
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
        .filter-pill:hover { background: var(--border-color); }
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
        
        .delete-notif-btn {
            padding: 0.4rem;
            border-radius: 8px;
            color: #ef4444;
            background: transparent;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.2s;
            opacity: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        [id^="notif-container-"]:hover .delete-notif-btn {
            opacity: 1;
        }
        .delete-notif-btn:hover {
            background: rgba(239, 68, 68, 0.1);
            border-color: rgba(239, 68, 68, 0.2);
        }
    </style>
</head>
<body>
    <?php include '../includes/dashboard_header.php'; ?>
    <div class="dashboard-wrapper">
        <?php include '../includes/dashboard_sidebar.php'; ?>
        
        <main class="main-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <h1 style="font-size: 1.8rem; font-weight: 800; margin: 0;">Notifications</h1>
                <button type="button" onclick="markAllAsRead()" class="mark-read-btn" id="mark-all-read-btn">
                    <i class='bx bx-check-double'></i> Mark all as Read
                </button>
            </div>

            <div class="filter-bar">
                <?php
                // Get unread counts for each category
                $unreadCounts = [
                    'all' => getUnreadNotificationsCount($userId),
                    'requests' => 0,
                    'delivery' => 0,
                    'system' => 0,
                    'action' => 0,
                    'messages' => 0,
                    'credits' => 0,
                    'listings' => 0,
                    'support' => 0
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
                    
                    $requestTypes = ['borrow_request', 'sell_request', 'request_accepted', 'request_declined', 'extension_request'];
                    $deliveryTypes = ['delivery_assigned', 'delivery_cancelled', 'delivery_pending_confirmation', 'delivery_update', 'receive_confirmed', 'borrower_confirmed'];
                    $messageTypes = ['message'];
                    $creditTypes = ['credit_earned', 'credit_spent', 'credit_refund'];
                    $listingTypes = ['new_listing'];
                    $supportTypes = ['support', 'support_reply'];
                    
                    foreach ($countsByType as $type => $count) {
                        if (in_array($type, $requestTypes)) {
                            $unreadCounts['requests'] += $count;
                        } elseif (in_array($type, $deliveryTypes)) {
                            $unreadCounts['delivery'] += $count;
                        } elseif (in_array($type, $creditTypes)) {
                            $unreadCounts['credits'] += $count;
                        } elseif (in_array($type, $listingTypes)) {
                            $unreadCounts['listings'] += $count;
                        } elseif (in_array($type, $supportTypes) || !in_array($type, array_merge($requestTypes, $deliveryTypes, $messageTypes, $creditTypes, $listingTypes))) {
                            $unreadCounts['support'] += $count; // Actually 'support_system' now
                        }
                    }

                    // 2. Action Required Count (Pending requests)
                    $stmt = $pdo->prepare("
                        SELECT COUNT(DISTINCT n.id) 
                        FROM notifications n
                        JOIN transactions t ON n.reference_id = t.id
                        WHERE n.user_id = ? 
                        AND (
                            (n.type IN ('borrow_request', 'sell_request') AND t.status = 'requested')
                            OR (n.type = 'extension_request' AND t.pending_due_date IS NOT NULL)
                        )
                    ");
                    $stmt->execute([$userId]);
                    $unreadCounts['action'] = (int)$stmt->fetchColumn();

                } catch (Exception $e) {}
                ?>

                <a href="?filter=all&status=<?php echo $status; ?>" class="filter-pill <?php echo $filter == 'all' ? 'active' : ''; ?>" id="filter-pill-all">
                    All <?php echo $unreadCounts['all'] > 0 ? "<span class='filter-badge'>{$unreadCounts['all']}</span>" : ''; ?>
                </a>
                <a href="?filter=requests&status=<?php echo $status; ?>" class="filter-pill <?php echo $filter == 'requests' ? 'active' : ''; ?>" id="filter-pill-requests">
                    Requests <?php echo $unreadCounts['requests'] > 0 ? "<span class='filter-badge'>{$unreadCounts['requests']}</span>" : ''; ?>
                </a>
                <a href="?filter=delivery&status=<?php echo $status; ?>" class="filter-pill <?php echo $filter == 'delivery' ? 'active' : ''; ?>" id="filter-pill-delivery">
                    Delivery <?php echo $unreadCounts['delivery'] > 0 ? "<span class='filter-badge'>{$unreadCounts['delivery']}</span>" : ''; ?>
                </a>
                <a href="?filter=listings&status=<?php echo $status; ?>" class="filter-pill <?php echo $filter == 'listings' ? 'active' : ''; ?>" id="filter-pill-listings">
                    Listings <?php echo $unreadCounts['listings'] > 0 ? "<span class='filter-badge'>{$unreadCounts['listings']}</span>" : ''; ?>
                </a>
                <a href="?filter=support&status=<?php echo $status; ?>" class="filter-pill <?php echo $filter == 'support' ? 'active' : ''; ?>" id="filter-pill-support">
                    Support & System <?php echo ($unreadCounts['support'] + $unreadCounts['system']) > 0 ? "<span class='filter-badge'>" . ($unreadCounts['support'] + $unreadCounts['system']) . "</span>" : ''; ?>
                </a>
                
                <div style="width: 1px; height: 20px; background: var(--border-color); margin: 0 0.5rem;"></div>
                
                <a href="?filter=<?php echo $filter; ?>&status=action" class="filter-pill <?php echo $status == 'action' ? 'active' : ''; ?>">
                    Action Required <?php echo $unreadCounts['action'] > 0 ? "<span class='filter-badge'>{$unreadCounts['action']}</span>" : ''; ?>
                </a>
                <a href="?filter=<?php echo $filter; ?>&status=unread" class="filter-pill <?php echo $status == 'unread' ? 'active' : ''; ?>">Unread Only</a>
            </div>
            
            <div id="notif-list-container" style="background: var(--bg-card); border-radius: var(--radius-lg); border: 1px solid var(--border-color); overflow: hidden; min-height: 200px;">
                <?php
                try {
                    $pdo = getDBConnection();
                    
                    $conditions = ["n.user_id = ?"];
                    $params = [$userId];

                    if ($status === 'unread') {
                        $conditions[] = "n.is_read = 0";
                    } elseif ($status === 'action') {
                        $conditions[] = "(
                            (n.type IN ('borrow_request', 'sell_request') AND t.status = 'requested')
                            OR (n.type = 'extension_request' AND t.pending_due_date IS NOT NULL)
                        )";
                    }

                    if ($filter === 'requests') {
                        $conditions[] = "n.type IN ('borrow_request', 'sell_request', 'request_accepted', 'request_declined', 'extension_request')";
                    } elseif ($filter === 'delivery') {
                        $conditions[] = "n.type IN ('delivery_assigned', 'delivery_cancelled', 'delivery_pending_confirmation', 'delivery_update', 'receive_confirmed', 'borrower_confirmed')";
                    } elseif ($filter === 'credits') {
                        $conditions[] = "n.type IN ('credit_earned', 'credit_spent', 'credit_refund')";
                    } elseif ($filter === 'listings') {
                        $conditions[] = "n.type IN ('new_listing')";
                    } elseif ($filter === 'support') {
                        $conditions[] = "n.type NOT IN ('borrow_request', 'sell_request', 'request_accepted', 'request_declined', 'delivery_assigned', 'delivery_cancelled', 'delivery_pending_confirmation', 'delivery_update', 'receive_confirmed', 'borrower_confirmed', 'message', 'credit_earned', 'credit_spent', 'credit_refund', 'new_listing', 'extension_request')";
                    }

                    $whereClause = implode(" AND ", $conditions);

                    $stmt = $pdo->prepare("
                        SELECT n.*, t.status as transaction_status, t.pending_due_date 
                        FROM notifications n 
                        LEFT JOIN transactions t ON n.reference_id = t.id 
                        WHERE $whereClause 
                        ORDER BY n.created_at DESC
                    ");
                    $stmt->execute($params);
                    $notifs = $stmt->fetchAll();
                    
                    // Get latest extension request notification ID for each transaction
                    $latestExtensionNotifs = [];
                    if (count($notifs) > 0) {
                        $stmt2 = $pdo->prepare("
                            SELECT reference_id, MAX(id) as latest_notif_id
                            FROM notifications
                            WHERE user_id = ? AND type = 'extension_request'
                            GROUP BY reference_id
                        ");
                        $stmt2->execute([$userId]);
                        while ($row = $stmt2->fetch()) {
                            $latestExtensionNotifs[$row['reference_id']] = $row['latest_notif_id'];
                        }
                    }
                    
                    if (count($notifs) > 0) {
                        foreach ($notifs as $n) {
                            $highlight = $n['is_read'] ? '' : 'background: var(--bg-unread);';
                            $isExtension = $n['type'] === 'extension_request';
                            $isRequest = strpos($n['type'], '_request') !== false;
                            
                            // For extension requests, only allow action if this is the LATEST notification for this transaction
                            $isLatestExtension = true;
                            if ($isExtension && $n['reference_id']) {
                                $isLatestExtension = isset($latestExtensionNotifs[$n['reference_id']]) && 
                                                     $latestExtensionNotifs[$n['reference_id']] == $n['id'];
                            }
                            
                            $canAction = ($n['reference_id'] && (
                                ($isRequest && !$isExtension && $n['transaction_status'] === 'requested') ||
                                ($isExtension && $n['pending_due_date'] && $isLatestExtension)
                            ));
                            
                            // Map icons and colors
                            $icon = 'bx-bell';
                            $iconColor = 'var(--text-main)';
                            $bgType = '#e2e8f0';

                            if (strpos($n['type'], 'request') !== false) {
                                $icon = 'bx-git-pull-request';
                                $iconColor = '#3b82f6';
                                $bgType = 'rgba(59, 130, 246, 0.1)';
                            } elseif (strpos($n['type'], 'delivery') !== false) {
                                $icon = 'bx-package';
                                $iconColor = '#f59e0b';
                                $bgType = 'rgba(245, 158, 11, 0.1)';
                            } elseif ($n['type'] === 'system') {
                                $icon = 'bx-cog';
                                $iconColor = '#64748b';
                                $bgType = 'rgba(148, 163, 184, 0.1)';
                            } elseif (strpos($n['type'], 'credit') !== false) {
                                $icon = 'bx-coin-stack';
                                $iconColor = '#10b981';
                                $bgType = 'rgba(16, 185, 129, 0.1)';
                            } elseif ($n['type'] === 'support') {
                                $icon = 'bx-support';
                                $iconColor = 'var(--primary)';
                                $bgType = 'rgba(88, 66, 227, 0.1)';
                            } elseif ($n['type'] === 'new_listing') {
                                $icon = 'bx-book-add';
                                $iconColor = '#8b5cf6';
                                $bgType = 'rgba(139, 92, 246, 0.1)';
                            } elseif ($n['type'] === 'message') {
                                $icon = 'bx-message-square-dots';
                                $iconColor = '#6366f1';
                                $bgType = 'rgba(99, 102, 241, 0.1)';
                            } elseif ($n['type'] === 'support_reply') {
                                $icon = 'bx-support';
                                $iconColor = 'var(--primary)';
                                $bgType = 'rgba(88, 66, 227, 0.1)';
                            }


                            // Determine Link
                            $link = '#';
                            if ($n['type'] === 'message' || $n['type'] === 'support' || $n['type'] === 'support_reply') {
                                // Prevent self-chat links
                                if ($n['reference_id'] != $userId) {
                                    $link = APP_URL . "/chat/index.php?user=" . $n['reference_id'];
                                }
                            } elseif ($n['type'] === 'new_listing' && $n['reference_id']) {
                                $link = "book_details.php?id=" . $n['reference_id'];
                            } elseif (preg_match('/request|delivery|receive|borrower_confirmed/', $n['type'])) {
                                $link = "delivery_details.php?id=" . $n['reference_id'];
                            }

                             // Determine Category for filter pill updates
                             $category = 'system';
                             if (in_array($n['type'], $requestTypes)) $category = 'requests';
                             elseif (in_array($n['type'], $deliveryTypes)) $category = 'delivery';
                             elseif (in_array($n['type'], $messageTypes)) $category = 'messages';
                             elseif (in_array($n['type'], $creditTypes)) $category = 'credits';
                             elseif (in_array($n['type'], $listingTypes)) $category = 'listings';
                             elseif (in_array($n['type'], $supportTypes)) $category = 'support';

                             echo "
                             <div style='position: relative;' id='notif-container-{$n['id']}' data-unread='" . ($n['is_read'] ? '0' : '1') . "' data-category='{$category}'>
                                 <a href='{$link}' style='text-decoration: none; display: block; padding: 1.5rem; border-bottom: 1px solid var(--border-color); $highlight transition: all 0.2s;' id='notif-{$n['id']}'>
                                    <div style='display: flex; gap: 1rem; align-items: start;'>
                                        <div style='width: 45px; height: 45px; background: {$bgType}; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: 1px solid rgba(0,0,0,0.05);'>
                                            <i class='bx {$icon}' style='font-size: 1.4rem; color: {$iconColor};'></i>
                                        </div>
                                        <div style='flex: 1;'>
                                            <div style='display: flex; justify-content: space-between; align-items: start; gap: 1rem;'>
                                                <div style='font-size: 0.95rem; color: var(--text-main); margin-bottom: 0.25rem; line-height: 1.5; font-weight: 500;'>{$n['message']}</div>
                                                <div style='display: flex; align-items: center; gap: 0.5rem;'>
                                                    " . ($n['type'] === 'support' && ($_SESSION['role'] ?? '') === 'admin' ? "
                                                        <button onclick=\"event.stopPropagation(); window.location.href='" . APP_URL . "/chat/index.php?user=" . $n['reference_id'] . "'\" class='btn btn-primary btn-sm' style='padding: 0.25rem 0.6rem; font-size: 0.75rem; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px; margin-right: 0.5rem;'>
                                                            <i class='bx bx-message-square-dots'></i> Chat
                                                        </button>
                                                    " : "") . "
                                                    " . ($n['transaction_status'] && $n['transaction_status'] !== 'requested' && (strpos($n['type'], 'request') !== false || strpos($n['type'], 'delivery') !== false) ? "
                                                        <span class='badge badge-{$n['transaction_status']}' style='font-size: 0.7rem; text-transform: uppercase;'>{$n['transaction_status']}</span>
                                                    " : "") . "
                                                    " . ($isExtension && !$isLatestExtension && $n['pending_due_date'] ? "
                                                        <span class='badge' style='font-size: 0.7rem; text-transform: uppercase; background: #94a3b8; color: white;'>SUPERSEDED</span>
                                                    " : "") . "
                                                    <button class='delete-notif-btn' onclick='deleteNotification(event, {$n['id']})' title='Delete Notification'>
                                                        <i class='bx bx-trash' style='font-size: 1.1rem;'></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div style='display: flex; align-items: center; gap: 0.5rem; font-size: 0.8rem; color: var(--text-muted);'>
                                                <i class='bx bx-time-five'></i>
                                                " . date('M d, h:i A', strtotime($n['created_at'])) . "
                                            </div>
                                        </div>
                                    </div>";
                            
                            if ($canAction) {
                                if ($isExtension) {
                                    echo "
                                    <div style='display: flex; gap: 0.75rem; margin-top: 1rem;'>
                                        <button class='btn btn-primary btn-sm' onclick='handleRequest({$n['reference_id']}, \"approve_extension\", {$n['id']})'>Approve</button>
                                        <button class='btn btn-outline btn-sm' onclick='handleRequest({$n['reference_id']}, \"decline_extension\", {$n['id']})'>Decline</button>
                                    </div>";
                                } else {
                                    echo "
                                    <div style='display: flex; gap: 0.75rem; margin-top: 1rem;'>
                                        <button class='btn btn-primary btn-sm' onclick='handleRequest({$n['reference_id']}, \"accept\", {$n['id']})'>Accept</button>
                                        <button class='btn btn-outline btn-sm' onclick='handleRequest({$n['reference_id']}, \"decline\", {$n['id']})'>Decline</button>
                                    </div>";
                                }
                            }

                            
                            echo "</a></div>";
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
                const bodyAction = action.includes('extension') ? action : `${action}_request`;
                const response = await fetch('../actions/request_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=${bodyAction}&transaction_id=${transactionId}`
                });
                
                const data = await response.json();
                if (data.success) {
                    if (typeof showToast === 'function') showToast(data.message, 'success');
                    const element = document.getElementById('notif-container-' + notifId);
                    if (element) {
                        const isUnread = element.getAttribute('data-unread') === '1';
                        const category = element.getAttribute('data-category');
                        
                        element.style.opacity = '0';
                        element.style.transform = 'translateY(-20px)';
                        
                        setTimeout(() => {
                            element.remove();
                            if (isUnread) {
                                updateFilterCount('all');
                                updateFilterCount(category);
                                if (typeof refreshSidebarUnread === 'function') {
                                    refreshSidebarUnread();
                                }
                            }
                            checkIfEmpty();
                        }, 300);
                    }
                } else {
                    if (typeof showToast === 'function') showToast('Error: ' + data.message, 'error');
                }
            } catch (err) {
                if (typeof showToast === 'function') showToast('Failed to process request', 'error');
                console.error(err);
            }
        }

        async function deleteNotification(event, notifId) {
            event.preventDefault();
            event.stopPropagation();

            if (!confirm('Are you sure you want to delete this notification?')) return;

            try {
                const response = await fetch('../actions/delete_notification.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `notification_id=${notifId}`
                });
                
                const data = await response.json();
                if (data.success) {
                    if (typeof showToast === 'function') showToast(data.message, 'success');
                    const element = document.getElementById('notif-container-' + notifId);
                    if (element) {
                        const isUnread = element.getAttribute('data-unread') === '1';
                        const category = element.getAttribute('data-category');

                        element.style.opacity = '0';
                        element.style.transform = 'translateX(20px)';
                        
                        setTimeout(() => {
                            element.remove();
                            
                            // Update UI counts if unread
                            if (isUnread) {
                                updateFilterCount('all');
                                updateFilterCount(category);
                                // Refresh sidebar badge
                                if (typeof refreshSidebarUnread === 'function') {
                                    refreshSidebarUnread();
                                }
                            }

                            checkIfEmpty();
                        }, 300);
                    }
                } else {
                    if (typeof showToast === 'function') showToast('Error: ' + data.message, 'error');
                }
            } catch (err) {
                if (typeof showToast === 'function') showToast('Failed to delete notification', 'error');
                console.error(err);
            }
        }

        async function markAllAsRead() {
            try {
                const response = await fetch('../actions/mark_all_read.php', { method: 'POST' });
                const data = await response.json();
                
                if (data.success) {
                    if (typeof showToast === 'function') showToast(data.message, 'success');
                    
                    // Reset all badges to zero/remove them
                    document.querySelectorAll('.filter-badge').forEach(badge => badge.remove());
                    
                    // Update all notification styles (remove highlights)
                    const statusFilter = new URLSearchParams(window.location.search).get('status') || 'all';
                    
                    document.querySelectorAll('[id^="notif-container-"]').forEach(container => {
                        const el = container.querySelector('[id^="notif-"]');
                        if (el) el.style.background = 'transparent';
                        container.setAttribute('data-unread', '0');
                        
                        // If we are in "Unread Only" view, remove them all with animation
                        if (statusFilter === 'unread') {
                            container.style.opacity = '0';
                            container.style.transform = 'scale(0.95)';
                            setTimeout(() => {
                                container.remove();
                                checkIfEmpty();
                            }, 300);
                        }
                    });

                    // Refresh sidebar badge
                    if (typeof refreshSidebarUnread === 'function') {
                        refreshSidebarUnread();
                    }
                } else {
                    if (typeof showToast === 'function') showToast('Error: ' + data.message, 'error');
                }
            } catch (err) {
                if (typeof showToast === 'function') showToast('Failed to mark as read', 'error');
                console.error(err);
            }
        }

        function checkIfEmpty() {
            const containers = document.querySelectorAll('[id^="notif-container-"]');
            if (containers.length === 0) {
                const list = document.getElementById('notif-list-container');
                list.innerHTML = `
                    <div style='padding: 4rem; text-align: center; color: var(--text-muted); opacity: 0; transform: scale(0.9); transition: all 0.5s ease; width: 100%;' id='empty-state'>
                        <i class='bx bx-notification-off' style='font-size: 3rem; opacity: 0.2; margin-bottom: 1rem; display: block;'></i>
                        No notifications found for this filter.
                    </div>
                `;
                setTimeout(() => {
                    const emptyState = document.getElementById('empty-state');
                    if (emptyState) {
                        emptyState.style.opacity = '1';
                        emptyState.style.transform = 'scale(1)';
                    }
                }, 10);
            }
        }

        function updateFilterCount(category) {
            const pill = document.getElementById('filter-pill-' + category);
            if (!pill) return;
            
            const badge = pill.querySelector('.filter-badge');
            if (badge) {
                let currentCount = parseInt(badge.textContent);
                if (currentCount > 1) {
                    badge.textContent = currentCount - 1;
                } else {
                    badge.remove();
                }
            }
        }
    </script>
    <script src="../assets/js/toast.js"></script>
</body>
</html>
