<?php
$current_page = basename($_SERVER['PHP_SELF']);
$user_role = $_SESSION['role'] ?? 'user';
?>
<aside class="sidebar">
    <div class="sidebar-section-title">Main Menu</div>
    
    <!-- Role-Based Dashboard Link -->
    <?php if($user_role == 'admin'): ?>
        <a href="<?php echo APP_URL; ?>/dashboard_admin.php" class="nav-item <?php echo $current_page == 'dashboard_admin.php' ? 'active' : ''; ?>"><i class='bx bxs-dashboard'></i> Admin Console</a>
    <?php elseif($user_role == 'library'): ?>
        <a href="<?php echo APP_URL; ?>/dashboard_library.php" class="nav-item <?php echo $current_page == 'dashboard_library.php' ? 'active' : ''; ?>"><i class='bx bxs-dashboard'></i> Library Panel</a>
    <?php elseif($user_role == 'bookstore'): ?>
        <a href="<?php echo APP_URL; ?>/dashboard_bookstore.php" class="nav-item <?php echo $current_page == 'dashboard_bookstore.php' ? 'active' : ''; ?>"><i class='bx bxs-dashboard'></i> Store Manager</a>
    <?php else: ?>
        <a href="<?php echo APP_URL; ?>/dashboard_user.php" class="nav-item <?php echo $current_page == 'dashboard_user.php' ? 'active' : ''; ?>"><i class='bx bxs-dashboard'></i> My Dashboard</a>
    <?php endif; ?>

    <a href="<?php echo APP_URL; ?>/explore.php" class="nav-item <?php echo $current_page == 'explore.php' ? 'active' : ''; ?>"><i class='bx bx-search-alt'></i> Explore Books</a>
    <a href="<?php echo APP_URL; ?>/deals.php" class="nav-item <?php echo $current_page == 'deals.php' ? 'active' : ''; ?>"><i class='bx bx-git-compare'></i> My Deals</a>

    <a href="#" class="nav-item"><i class='bx bx-refresh'></i> Activity</a>
    
    <div class="sidebar-section-title">My Actions</div>
    <a href="<?php echo APP_URL; ?>/add_listing.php" class="nav-item <?php echo $current_page == 'add_listing.php' ? 'active' : ''; ?>"><i class='bx bx-plus-circle'></i> Add Listing</a>
    <a href="#" class="nav-item"><i class='bx bx-book-heart'></i> Watchlist</a>
    <a href="<?php echo APP_URL; ?>/chat/index.php" class="nav-item <?php echo strpos($current_page, 'chat') !== false ? 'active' : ''; ?>"><i class='bx bx-message-square-detail'></i> Messages</a>
    <a href="<?php echo APP_URL; ?>/listings.php" class="nav-item <?php echo $current_page == 'listings.php' ? 'active' : ''; ?>"><i class='bx bx-list-ul'></i> My Listings</a>

    
    <div class="sidebar-section-title">Account</div>
    <a href="#" class="nav-item"><i class='bx bx-user-circle'></i> Edit Profile</a>
    <a href="#" class="nav-item"><i class='bx bx-cog'></i> Settings</a>
    <a href="<?php echo APP_URL; ?>/logout.php" class="nav-item" style="color: #ef4444;"><i class='bx bx-log-out'></i> Logout</a>

</aside>
