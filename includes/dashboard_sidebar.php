<?php
require_once __DIR__ . '/db_helper.php';
$current_page = basename($_SERVER['PHP_SELF']);
$user_id = $_SESSION['user_id'] ?? 0;

// Sync session role with DB
if ($user_id) {
    syncSessionRole($user_id);
}

$user_role = $_SESSION['role'] ?? 'user';

// Check for due soon books and notify
if ($user_id) {
    checkAndNotifyDueSoon($user_id);
}

$total_unread = $user_id ? getTotalUnreadCount($user_id) : 0;
$total_notifs = $user_id ? getUnreadSystemNotificationsCount($user_id) : 0;
$deals_notifs = $user_id ? getUnreadRequestsCount($user_id) : 0;
$delivery_notifs = $user_id ? getUnreadDeliveryUpdatesCount($user_id) : 0;
$sidebar_available_jobs_count = ($user_role == 'delivery_agent') ? getAvailableDeliveryJobsCount() : 0;
$sidebar_active_jobs_count = ($user_role == 'delivery_agent') ? getActiveAgentJobsCount($user_id) : 0;
$sidebar_role_requests_count = ($user_role == 'admin') ? getPendingRoleRequestsCount() : 0;
$sidebar_reports_count = ($user_role == 'admin') ? getPendingReportsCount() : 0;
$theme_mode = $_SESSION['theme_mode'] ?? 'light';
?>

<script>
    document.documentElement.setAttribute('data-theme', '<?php echo $theme_mode; ?>');
</script>
<style>
    .nav-item { position: relative; }
    .nav-badge {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        background: #ef4444;
        color: white;
        padding: 2px 6px;
        border-radius: 10px;
        font-size: 0.7rem;
        font-weight: 700;
        line-height: 1;
        box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3);
    }
</style>
<aside class="sidebar">
    <?php if($user_role == 'delivery_agent'): ?>
    <div class="sidebar-section-title">Logistics</div>
    <a href="<?php echo APP_URL; ?>/pages/dashboard_delivery_agent.php" class="nav-item <?php echo $current_page == 'dashboard_delivery_agent.php' ? 'active' : ''; ?>"><i class='bx bx-grid-alt'></i> Agent Dashboard</a>
    <a href="<?php echo APP_URL; ?>/pages/current_jobs.php" class="nav-item <?php echo $current_page == 'current_jobs.php' ? 'active' : ''; ?>">
        <i class='bx bx-briefcase'></i> My Current Jobs
        <?php if ($sidebar_active_jobs_count > 0): ?>
            <span class="nav-badge active-jobs-badge"><?php echo $sidebar_active_jobs_count; ?></span>
        <?php endif; ?>
    </a>
    <a href="<?php echo APP_URL; ?>/pages/delivery_jobs.php" class="nav-item <?php echo $current_page == 'delivery_jobs.php' ? 'active' : ''; ?>">
        <i class='bx bx-radar'></i> Find New Jobs
        <?php if ($sidebar_available_jobs_count > 0): ?>
            <span class="nav-badge available-jobs-badge"><?php echo $sidebar_available_jobs_count; ?></span>
        <?php endif; ?>
    </a>
    <a href="<?php echo APP_URL; ?>/pages/agent_history.php" class="nav-item <?php echo $current_page == 'agent_history.php' ? 'active' : ''; ?>"><i class='bx bx-wallet'></i> Wallet & History</a>
    <a href="<?php echo APP_URL; ?>/pages/agent_reports.php" class="nav-item <?php echo $current_page == 'agent_reports.php' ? 'active' : ''; ?>"><i class='bx bx-file'></i> Performance Reports</a>
    <?php endif; ?>

    <div class="sidebar-section-title">Main Menu</div>
    
    <!-- Role-Based Dashboard Link -->
    <?php if($user_role == 'admin'): ?>
        <a href="<?php echo APP_URL; ?>/admin/dashboard_admin.php" class="nav-item <?php echo $current_page == 'dashboard_admin.php' ? 'active' : ''; ?>"><i class='bx bxs-dashboard'></i> Admin Console</a>
    <?php elseif($user_role == 'library'): ?>
        <a href="<?php echo APP_URL; ?>/pages/dashboard_library.php" class="nav-item <?php echo $current_page == 'dashboard_library.php' ? 'active' : ''; ?>"><i class='bx bxs-dashboard'></i> Library Panel</a>
    <?php elseif($user_role == 'bookstore'): ?>
        <a href="<?php echo APP_URL; ?>/pages/dashboard_bookstore.php" class="nav-item <?php echo $current_page == 'dashboard_bookstore.php' ? 'active' : ''; ?>"><i class='bx bxs-dashboard'></i> Store Manager</a>
    <?php elseif($user_role !== 'delivery_agent'): ?>
        <a href="<?php echo APP_URL; ?>/pages/dashboard_user.php" class="nav-item <?php echo $current_page == 'dashboard_user.php' ? 'active' : ''; ?>"><i class='bx bxs-dashboard'></i> My Dashboard</a>
    <?php endif; ?>

    <a href="<?php echo APP_URL; ?>/pages/explore.php" class="nav-item <?php echo $current_page == 'explore.php' ? 'active' : ''; ?>"><i class='bx bx-search-alt'></i> Explore Books</a>
    <a href="<?php echo APP_URL; ?>/pages/community.php" class="nav-item <?php echo $current_page == 'community.php' ? 'active' : ''; ?>"><i class='bx bx-world'></i> Community</a>

    
    <?php if($user_role == 'admin'): ?>
    <div class="sidebar-section-title">Admin Controls</div>
    <a href="<?php echo APP_URL; ?>/admin/admin_users.php" class="nav-item <?php echo $current_page == 'admin_users.php' ? 'active' : ''; ?>"><i class='bx bx-group'></i> Manage Users</a>
    <a href="<?php echo APP_URL; ?>/admin/admin_listings.php" class="nav-item <?php echo $current_page == 'admin_listings.php' ? 'active' : ''; ?>"><i class='bx bx-book'></i> Manage Listings</a>
    <a href="<?php echo APP_URL; ?>/admin/role_requests.php" class="nav-item <?php echo $current_page == 'role_requests.php' ? 'active' : ''; ?>">
        <i class='bx bx-git-branch'></i> Role Requests
        <?php if ($sidebar_role_requests_count > 0): ?>
            <span class="nav-badge role-requests-badge"><?php echo $sidebar_role_requests_count; ?></span>
        <?php endif; ?>
    </a>
    <a href="<?php echo APP_URL; ?>/admin/admin_reports.php" class="nav-item <?php echo $current_page == 'admin_reports.php' ? 'active' : ''; ?>">
        <i class='bx bx-flag'></i> Reports
        <?php if ($sidebar_reports_count > 0): ?>
            <span class="nav-badge reports-badge"><?php echo $sidebar_reports_count; ?></span>
        <?php endif; ?>
    </a>
    <?php endif; ?>

    <?php if(!in_array($user_role, ['delivery_agent', 'admin'])): ?>
    <div class="sidebar-section-title">My Actions</div>
        <a href="<?php echo APP_URL; ?>/pages/add_listing.php" class="nav-item <?php echo $current_page == 'add_listing.php' ? 'active' : ''; ?>"><i class='bx bx-plus-circle'></i> Add Listing</a>
        <a href="<?php echo APP_URL; ?>/pages/wishlist.php" class="nav-item <?php echo $current_page == 'wishlist.php' ? 'active' : ''; ?>"><i class='bx bx-book-heart'></i> Wishlist</a>
        <?php if(in_array($user_role, ['library', 'bookstore'])): ?>
            <a href="<?php echo APP_URL; ?>/pages/business_reports.php" class="nav-item <?php echo $current_page == 'business_reports.php' ? 'active' : ''; ?>"><i class='bx bx-line-chart'></i> Business Reports</a>
            <?php if ($user_role == 'library'): ?>
                <a href="<?php echo APP_URL; ?>/pages/library_fines.php" class="nav-item <?php echo $current_page == 'library_fines.php' ? 'active' : ''; ?>"><i class='bx bx-coin-stack'></i> Manage Fines</a>
            <?php endif; ?>
            <?php if($user_role == 'bookstore'): ?>
                <a href="<?php echo APP_URL; ?>/pages/manage_announcements.php" class="nav-item <?php echo $current_page == 'manage_announcements.php' ? 'active' : ''; ?>"><i class='bx bxs-megaphone'></i> Manage Announcements</a>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>

    <a href="<?php echo APP_URL; ?>/chat/index.php" class="nav-item <?php echo strpos($current_page, 'chat') !== false ? 'active' : ''; ?>">
        <i class='bx bx-message-square-detail'></i> Messages
        <?php if ($total_unread > 0): ?>
            <span class="nav-badge msg-badge"><?php echo $total_unread; ?></span>
        <?php endif; ?>
    </a>
    <?php if(!in_array($user_role, ['delivery_agent', 'admin'])): ?>
        <a href="<?php echo APP_URL; ?>/pages/deals.php" class="nav-item <?php echo $current_page == 'deals.php' ? 'active' : ''; ?>">
            <i class='bx bx-git-compare'></i> My Deals
            <?php if ($deals_notifs > 0): ?>
                <span class="nav-badge deals-badge"><?php echo $deals_notifs; ?></span>
            <?php endif; ?>
        </a>
        <a href="<?php echo APP_URL; ?>/pages/track_deliveries.php" class="nav-item <?php echo $current_page == 'track_deliveries.php' ? 'active' : ''; ?>">
            <i class='bx bx-package'></i> Delivery Details
            <?php if ($delivery_notifs > 0): ?>
                <span class="nav-badge delivery-badge"><?php echo $delivery_notifs; ?></span>
            <?php endif; ?>
        </a>
    <?php endif; ?>

    
    <div class="sidebar-section-title">Account</div>
    <a href="<?php echo APP_URL; ?>/pages/notifications.php" class="nav-item <?php echo $current_page == 'notifications.php' ? 'active' : ''; ?>">
        <i class='bx bx-bell'></i> Notifications
        <?php if ($total_notifs > 0): ?>
            <span class="nav-badge notif-badge"><?php echo $total_notifs; ?></span>
        <?php endif; ?>
    </a>
    <?php if($user_role !== 'admin'): ?>
    <a href="<?php echo APP_URL; ?>/pages/profile.php" class="nav-item <?php echo $current_page == 'profile.php' ? 'active' : ''; ?>"><i class='bx bx-user-circle'></i> Edit Profile</a>
    <?php endif; ?>
    <a href="<?php echo APP_URL; ?>/pages/settings.php" class="nav-item <?php echo $current_page == 'settings.php' ? 'active' : ''; ?>"><i class='bx bx-cog'></i> Settings</a>
    <a href="<?php echo APP_URL; ?>/actions/logout.php" class="nav-item" style="color: #ef4444;"><i class='bx bx-log-out'></i> Logout</a>

</aside>

<script>
    // Global function to update sidebar unread badges
    async function refreshSidebarUnread() {
        try {
            const response = await fetch('<?php echo APP_URL; ?>/chat/api.php?action=all_counts&user_id=<?php echo $_SESSION['user_id'] ?? 0; ?>');
            const data = await response.json();
            
            // 1. Update Message Badge
            const msgBadge = document.querySelector('.msg-badge');
            const msgLink = document.querySelector('a[href*="chat/index.php"]');
            
            if (data.messages > 0) {
                if (msgBadge) {
                    msgBadge.textContent = data.messages;
                } else if (msgLink) {
                    const newBadge = document.createElement('span');
                    newBadge.className = 'nav-badge msg-badge';
                    newBadge.textContent = data.messages;
                    msgLink.appendChild(newBadge);
                }
            } else if (msgBadge) {
                msgBadge.remove();
            }

            // 2. Update Notification Badge
            const notifBadge = document.querySelector('.notif-badge');
            const notifLink = document.querySelector('a[href*="notifications.php"]');
            
            if (data.notifications > 0) {
                if (notifBadge) {
                    notifBadge.textContent = data.notifications;
                } else if (notifLink) {
                    const newBadge = document.createElement('span');
                    newBadge.className = 'nav-badge notif-badge';
                    newBadge.textContent = data.notifications;
                    notifLink.appendChild(newBadge);
                }
            } else if (notifBadge) {
                notifBadge.remove();
            }

            // 3. Update Deals Badge
            const dealsBadge = document.querySelector('.deals-badge');
            const dealsLink = document.querySelector('a[href*="deals.php"]');
            
            if (data.deals > 0) {
                if (dealsBadge) {
                    dealsBadge.textContent = data.deals;
                } else if (dealsLink) {
                    const newBadge = document.createElement('span');
                    newBadge.className = 'nav-badge deals-badge';
                    newBadge.textContent = data.deals;
                    dealsLink.appendChild(newBadge);
                }
            } else if (dealsBadge) {
                dealsBadge.remove();
            }

            // 4. Update Delivery Badge
            const deliveryBadge = document.querySelector('.delivery-badge');
            const deliveryLink = document.querySelector('a[href*="track_deliveries.php"]');
            
            if (data.delivery > 0) {
                if (deliveryBadge) {
                    deliveryBadge.textContent = data.delivery;
                } else if (deliveryLink) {
                    const newBadge = document.createElement('span');
                    newBadge.className = 'nav-badge delivery-badge';
                    newBadge.textContent = data.delivery;
                    deliveryLink.appendChild(newBadge);
                }
            } else if (deliveryBadge) {
                deliveryBadge.remove();
            }

            // 5. Update Active Jobs Badge
            const activeJobsBadge = document.querySelector('.active-jobs-badge');
            const activeJobsLink = document.querySelector('a[href*="current_jobs.php"]');
            
            if (data.active_jobs > 0) {
                if (activeJobsBadge) {
                    activeJobsBadge.textContent = data.active_jobs;
                } else if (activeJobsLink) {
                    const newBadge = document.createElement('span');
                    newBadge.className = 'nav-badge active-jobs-badge';
                    newBadge.textContent = data.active_jobs;
                    activeJobsLink.appendChild(newBadge);
                }
            } else if (activeJobsBadge) {
                activeJobsBadge.remove();
            }

            // 6. Update Available Jobs Badge
            const availableJobsBadge = document.querySelector('.available-jobs-badge');
            const availableJobsLink = document.querySelector('a[href*="delivery_jobs.php"]');
            
            if (data.available_jobs > 0) {
                if (availableJobsBadge) {
                    availableJobsBadge.textContent = data.available_jobs;
                } else if (availableJobsLink) {
                    const newBadge = document.createElement('span');
                    newBadge.className = 'nav-badge available-jobs-badge';
                    newBadge.textContent = data.available_jobs;
                    availableJobsLink.appendChild(newBadge);
                }
            } else if (availableJobsBadge) {
                availableJobsBadge.remove();
            }

            // 7. Update Role Requests Badge
            const roleReqBadge = document.querySelector('.role-requests-badge');
            const roleReqLink = document.querySelector('a[href*="role_requests.php"]');
            
            if (data.role_requests > 0) {
                if (roleReqBadge) {
                    roleReqBadge.textContent = data.role_requests;
                } else if (roleReqLink) {
                    const newBadge = document.createElement('span');
                    newBadge.className = 'nav-badge role-requests-badge';
                    newBadge.textContent = data.role_requests;
                    roleReqLink.appendChild(newBadge);
                }
            } else if (roleReqBadge) {
                roleReqBadge.remove();
            }

            // 8. Update Reports Badge
            const reportsBadge = document.querySelector('.reports-badge');
            const reportsLink = document.querySelector('a[href*="admin_reports.php"]');
            
            if (data.reports > 0) {
                if (reportsBadge) {
                    reportsBadge.textContent = data.reports;
                } else if (reportsLink) {
                    const newBadge = document.createElement('span');
                    newBadge.className = 'nav-badge reports-badge';
                    newBadge.textContent = data.reports;
                    reportsLink.appendChild(newBadge);
                }
            } else if (reportsBadge) {
                reportsBadge.remove();
            }
        } catch (err) {
            console.error("Sidebar unread refresh failed", err);
        }
    }

    // Poll every 2 seconds
    if (<?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>) {
        setInterval(refreshSidebarUnread, 2000);
    }
</script>
