<?php
require_once __DIR__ . '/db_helper.php';
$current_page = basename($_SERVER['PHP_SELF']);
$user_role = $_SESSION['role'] ?? 'user';
$total_unread = isset($_SESSION['user_id']) ? getTotalUnreadCount($_SESSION['user_id']) : 0;
$total_notifs = isset($_SESSION['user_id']) ? getUnreadNotificationsCount($_SESSION['user_id']) : 0;
?>
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
    <div class="sidebar-section-title">Main Menu</div>
    
    <!-- Role-Based Dashboard Link -->
    <?php if($user_role == 'admin'): ?>
        <a href="<?php echo APP_URL; ?>/dashboard_admin.php" class="nav-item <?php echo $current_page == 'dashboard_admin.php' ? 'active' : ''; ?>"><i class='bx bxs-dashboard'></i> Admin Console</a>
    <?php elseif($user_role == 'library'): ?>
        <a href="<?php echo APP_URL; ?>/dashboard_library.php" class="nav-item <?php echo $current_page == 'dashboard_library.php' ? 'active' : ''; ?>"><i class='bx bxs-dashboard'></i> Library Panel</a>
    <?php elseif($user_role == 'bookstore'): ?>
        <a href="<?php echo APP_URL; ?>/dashboard_bookstore.php" class="nav-item <?php echo $current_page == 'dashboard_bookstore.php' ? 'active' : ''; ?>"><i class='bx bxs-dashboard'></i> Store Manager</a>
    <?php elseif($user_role == 'delivery_agent'): ?>
        <a href="<?php echo APP_URL; ?>/dashboard_delivery_agent.php" class="nav-item <?php echo $current_page == 'dashboard_delivery_agent.php' ? 'active' : ''; ?>"><i class='bx bxs-truck'></i> Agent Hub</a>
    <?php else: ?>
        <a href="<?php echo APP_URL; ?>/dashboard_user.php" class="nav-item <?php echo $current_page == 'dashboard_user.php' ? 'active' : ''; ?>"><i class='bx bxs-dashboard'></i> My Dashboard</a>
    <?php endif; ?>

    <a href="<?php echo APP_URL; ?>/explore.php" class="nav-item <?php echo $current_page == 'explore.php' ? 'active' : ''; ?>"><i class='bx bx-search-alt'></i> Explore Books</a>
    <a href="<?php echo APP_URL; ?>/community.php" class="nav-item <?php echo $current_page == 'community.php' ? 'active' : ''; ?>"><i class='bx bx-world'></i> Community</a>

    
    <?php if($user_role == 'admin'): ?>
    <div class="sidebar-section-title">Admin Controls</div>
    <a href="<?php echo APP_URL; ?>/admin_users.php" class="nav-item <?php echo $current_page == 'admin_users.php' ? 'active' : ''; ?>"><i class='bx bx-group'></i> Manage Users</a>
    <a href="<?php echo APP_URL; ?>/admin_reports.php" class="nav-item <?php echo $current_page == 'admin_reports.php' ? 'active' : ''; ?>"><i class='bx bx-flag'></i> Reports</a>
    <?php endif; ?>

    <div class="sidebar-section-title">My Actions</div>
    <?php if($user_role != 'delivery_agent'): ?>
        <a href="<?php echo APP_URL; ?>/add_listing.php" class="nav-item <?php echo $current_page == 'add_listing.php' ? 'active' : ''; ?>"><i class='bx bx-plus-circle'></i> Add Listing</a>
        <a href="<?php echo APP_URL; ?>/wishlist.php" class="nav-item <?php echo $current_page == 'wishlist.php' ? 'active' : ''; ?>"><i class='bx bx-book-heart'></i> Wishlist</a>
    <?php endif; ?>

    <?php if($user_role == 'delivery_agent' || $user_role == 'admin'): ?>
        <a href="<?php echo APP_URL; ?>/delivery_jobs.php" class="nav-item <?php echo $current_page == 'delivery_jobs.php' ? 'active' : ''; ?>"><i class='bx bx-radar'></i> Find Jobs</a>
        <a href="<?php echo APP_URL; ?>/agent_route.php" class="nav-item <?php echo $current_page == 'agent_route.php' ? 'active' : ''; ?>"><i class='bx bx-map-alt'></i> Service Route</a>
    <?php endif; ?>
    <a href="<?php echo APP_URL; ?>/chat/index.php" class="nav-item <?php echo strpos($current_page, 'chat') !== false ? 'active' : ''; ?>">
        <i class='bx bx-message-square-detail'></i> Messages
        <?php if ($total_unread > 0): ?>
            <span class="nav-badge msg-badge"><?php echo $total_unread; ?></span>
        <?php endif; ?>
    </a>
    <?php if($user_role != 'delivery_agent'): ?>
        <a href="<?php echo APP_URL; ?>/deals.php" class="nav-item <?php echo $current_page == 'deals.php' ? 'active' : ''; ?>"><i class='bx bx-git-compare'></i> My Deals</a>
    <?php endif; ?>

    
    <div class="sidebar-section-title">Account</div>
    <a href="<?php echo APP_URL; ?>/notifications.php" class="nav-item <?php echo $current_page == 'notifications.php' ? 'active' : ''; ?>">
        <i class='bx bx-bell'></i> Notifications
        <?php if ($total_notifs > 0): ?>
            <span class="nav-badge notif-badge"><?php echo $total_notifs; ?></span>
        <?php endif; ?>
    </a>
    <a href="<?php echo APP_URL; ?>/profile.php" class="nav-item <?php echo $current_page == 'profile.php' ? 'active' : ''; ?>"><i class='bx bx-user-circle'></i> Edit Profile</a>
    <a href="#" class="nav-item"><i class='bx bx-cog'></i> Settings</a>
    <a href="<?php echo APP_URL; ?>/logout.php" class="nav-item" style="color: #ef4444;"><i class='bx bx-log-out'></i> Logout</a>

</aside>

<script>
    // Global function to update sidebar unread badge
    async function refreshSidebarUnread() {
        try {
            const response = await fetch('<?php echo APP_URL; ?>/chat/api.php?action=unread_counts&user_id=<?php echo $_SESSION['user_id'] ?? 0; ?>');
            const counts = await response.json();
            const total = counts.reduce((sum, item) => sum + parseInt(item.count), 0);
            
            const badge = document.querySelector('.msg-badge');
            const msgLink = document.querySelector('a[href*="chat/index.php"]');
            
            if (total > 0) {
                if (badge) {
                    badge.textContent = total;
                } else if (msgLink) {
                    const newBadge = document.createElement('span');
                    newBadge.className = 'nav-badge msg-badge';
                    newBadge.textContent = total;
                    msgLink.appendChild(newBadge);
                }
            } else if (badge) {
                badge.remove();
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
