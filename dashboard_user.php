<?php 
require_once 'includes/db_helper.php';
require_once 'paths.php';
include 'includes/dashboard_header.php'; 

// Fetch real stats
$userId = $_SESSION['user_id'] ?? 0; // Ensure user_id is in session
$stats = getUserStats($userId);
$books = getAllBooks(4);
?>

<div class="dashboard-wrapper">
    <?php include 'includes/dashboard_sidebar.php'; ?>

    <main class="main-content">
        <div class="section-header">
            <h1>Welcome back, <?php echo $user['firstname']; ?>! 👋</h1>
            <p>Here's what's happening with your books and activities today.</p>
        </div>

        <!-- Widgets -->
        <div class="widgets-grid">
            <div class="widget-card" style="text-align: center;">
                <div class="widget-title" style="justify-content: center;">
                    <span><i class='bx bx-upload'></i> My Listings</span>
                </div>
                <div style="font-size: 2.5rem; font-weight: 800; color: var(--primary);">
                    <?php echo $stats['total_listings'] ?? 0; ?>
                </div>
                <div style="color: var(--text-muted); font-size: 0.8rem;">Books you've shared</div>
            </div>

            <div class="widget-card" style="text-align: center;">
                <div class="widget-title" style="justify-content: center;">
                    <span><i class='bx bx-book-reader'></i> Active Borrows</span>
                </div>
                <div style="font-size: 2.5rem; font-weight: 800; color: var(--success);">
                    <?php echo $stats['active_borrows'] ?? 0; ?>
                </div>
                <div style="color: var(--text-muted); font-size: 0.8rem;">Books you have right now</div>
            </div>

            <div class="widget-card" style="text-align: center;">
                <div class="widget-title" style="justify-content: center;">
                    <span><i class='bx bx-star'></i> Reputation</span>
                </div>
                <div style="font-size: 2.5rem; font-weight: 800; color: #f59e0b;">
                    95%
                </div>
                <div style="color: #f59e0b; font-weight:700;">Excellent</div>
            </div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 2rem; margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.25rem; font-weight: 700;">Explore Community Books</h2>
            <a href="index.php" style="color: var(--primary); font-size: 0.9rem; text-decoration: none; font-weight: 600;">View All</a>
        </div>

        <div class="book-grid">
            <?php foreach ($books as $book): ?>
            <div class="book-card">
                <div class="book-cover">
                    <span style="position: absolute; top: 10px; right: 10px; background:white; padding: 4px 10px; border-radius:12px; font-size:0.7rem; font-weight:700; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">Available</span>
                    <img src="<?php echo $book['cover_image'] ?: 'https://images.unsplash.com/photo-1543004218-ee141104975a?auto=format&fit=crop&q=80&w=800'; ?>" alt="<?php echo $book['title']; ?>">
                </div>
                <div class="book-info">
                    <div class="book-title"><?php echo $book['title']; ?></div>
                    <div class="book-author"><?php echo $book['author']; ?></div>
                    <button class="btn btn-primary btn-sm w-full mt-4">Borrow Now</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>
</div>
</body>
</html>

