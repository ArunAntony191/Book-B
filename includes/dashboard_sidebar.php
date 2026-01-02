<?php
$current_page = basename($_SERVER['PHP_SELF']);
$user_role = $_SESSION['role'] ?? 'user';
?>
<aside class="sidebar">
    <div class="sidebar-section-title">Main Menu</div>
    
    <!-- Role-Based Dashboard Link -->
    <?php if($user_role == 'admin'): ?>
        <a href="dashboard_admin.php" class="nav-item <?php echo $current_page == 'dashboard_admin.php' ? 'active' : ''; ?>"><i class='bx bxs-dashboard'></i> Admin Console</a>
    <?php elseif($user_role == 'library'): ?>
        <a href="dashboard_library.php" class="nav-item <?php echo $current_page == 'dashboard_library.php' ? 'active' : ''; ?>"><i class='bx bxs-dashboard'></i> Library Panel</a>
    <?php elseif($user_role == 'bookstore'): ?>
        <a href="dashboard_bookstore.php" class="nav-item <?php echo $current_page == 'dashboard_bookstore.php' ? 'active' : ''; ?>"><i class='bx bxs-dashboard'></i> Store Manager</a>
    <?php else: ?>
        <a href="dashboard_user.php" class="nav-item <?php echo $current_page == 'dashboard_user.php' ? 'active' : ''; ?>"><i class='bx bxs-dashboard'></i> My Dashboard</a>
    <?php endif; ?>

    <a href="index.php" class="nav-item"><i class='bx bx-search-alt'></i> Explore Books</a>
    <a href="#" class="nav-item"><i class='bx bx-refresh'></i> Activity</a>
    
    <div class="sidebar-section-title">My Actions</div>
    <a href="#" class="nav-item"><i class='bx bx-book-heart'></i> Watchlist</a>
    <a href="#" class="nav-item"><i class='bx bx-message-square-detail'></i> Messages</a>
    <a href="#" class="nav-item"><i class='bx bx-history'></i> Transactions</a>
    
    <div class="sidebar-section-title">Account</div>
    <a href="#" class="nav-item"><i class='bx bx-user-circle'></i> Edit Profile</a>
    <a href="#" class="nav-item"><i class='bx bx-cog'></i> Settings</a>
    <a href="logout.php" class="nav-item" style="color: #ef4444;"><i class='bx bx-log-out'></i> Logout</a>
</aside>
