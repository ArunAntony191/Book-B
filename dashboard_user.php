<?php 
require_once 'includes/db_helper.php';
require_once 'paths.php';
include 'includes/dashboard_header.php'; 

// Fetch enhanced stats with credits and trust
$userId = $_SESSION['user_id'] ?? 0;
$stats = getUserStatsEnhanced($userId);
$books = getAllBooks(4);
$trustRating = $stats['trust_rating'] ?? getTrustScoreRating(50);
?>

<div class="dashboard-wrapper">
    <?php include 'includes/dashboard_sidebar.php'; ?>

    <main class="main-content">
        <div class="section-header">
            <div>
                <h1>Welcome back, <?php echo $user['firstname']; ?>! 👋</h1>
                <p>Here's your reading journey and community impact today.</p>
            </div>
            <a href="add_listing.php" class="btn btn-primary">
                <i class='bx bx-plus-circle'></i> List a Book
            </a>
        </div>

        <!-- Enhanced Widgets Grid -->
        <div class="widgets-grid" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
            <!-- Credits Widget -->
            <div class="widget-card gradient-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none;">
                <div class="widget-title" style="justify-content: center; color: rgba(255,255,255,0.9);">
                    <span><i class='bx bx-wallet'></i> Credit Balance</span>
                </div>
                <div style="font-size: 3rem; font-weight: 900; text-align: center; margin: 1rem 0;">
                    <?php echo $stats['credits'] ?? 100; ?>
                </div>
                <div style="text-align: center; opacity: 0.9; font-size: 0.85rem;">
                    Use to borrow books
                </div>
                <a href="credit_history.php" style="display: block; text-align: center; margin-top: 1rem; color: white; text-decoration: underline; font-size: 0.85rem;">
                    View History →
                </a>
            </div>

            <!-- Trust Score Widget -->
            <div class="widget-card" style="background: linear-gradient(135deg, <?php echo $trustRating['color']; ?>15 0%, <?php echo $trustRating['color']; ?>05 100%); border: 2px solid <?php echo $trustRating['color']; ?>;">
                <div class="widget-title" style="justify-content: center;">
                    <span><i class='bx bx-shield-alt-2'></i> Trust Score</span>
                </div>
                <div style="text-align: center; margin: 1rem 0;">
                    <div style="font-size: 2.5rem; font-weight: 900; color: <?php echo $trustRating['color']; ?>;">
                        <?php echo $stats['trust_score'] ?? 50; ?>/100
                    </div>
                    <div style="margin-top: 0.5rem; padding: 0.4rem 1.2rem; background: <?php echo $trustRating['color']; ?>; color: white; border-radius: 20px; display: inline-block; font-weight: 700; font-size: 0.85rem;">
                        <?php echo $trustRating['label']; ?>
                    </div>
                </div>
                <div style="text-align: center; color: var(--text-muted); font-size: 0.8rem;">
                    Built on reliability
                </div>
            </div>

            <!-- Rating Widget -->
            <div class="widget-card" style="text-align: center; background: linear-gradient(135deg, #fbbf2415 0%, #fbbf2405 100%); border: 2px solid #fbbf24;">
                <div class="widget-title" style="justify-content: center;">
                    <span><i class='bx bxs-star'></i> Your Rating</span>
                </div>
                <div style="margin: 1rem 0;">
                    <div style="font-size: 2.5rem; font-weight: 900; color: #fbbf24;">
                        <?php 
                        $avgRating = $stats['average_rating'] ?? 0;
                        echo $avgRating > 0 ? number_format($avgRating, 1) : '—'; 
                        ?>
                    </div>
                    <div style="color: #fbbf24; font-size: 1.2rem; margin-top: 0.5rem;">
                        <?php for($i = 1; $i <= 5; $i++): ?>
                            <i class='bx <?php echo $i <= round($avgRating) ? "bxs-star" : "bx-star"; ?>'></i>
                        <?php endfor; ?>
                    </div>
                </div>
                <div style="color: var(--text-muted); font-size: 0.8rem;">
                    <?php echo $stats['total_ratings'] ?? 0; ?> reviews
                </div>
            </div>

            <!-- My Listings -->
            <div class="widget-card" style="text-align: center; cursor: pointer; transition: all 0.3s;" onclick="window.location.href='listings.php'">
                <div class="widget-title" style="justify-content: center;">
                    <span><i class='bx bx-book-bookmark'></i> My Listings</span>
                </div>
                <div style="font-size: 2.5rem; font-weight: 800; color: var(--primary); margin: 1rem 0;">
                    <?php echo $stats['total_listings'] ?? 0; ?>
                </div>
                <div style="color: var(--text-muted); font-size: 0.8rem;">Books shared</div>
            </div>

            <!-- Active Borrows -->
            <div class="widget-card" style="text-align: center; cursor: pointer; transition: all 0.3s;" onclick="window.location.href='deals.php'">
                <div class="widget-title" style="justify-content: center;">
                    <span><i class='bx bx-book-reader'></i> Active Borrows</span>
                </div>
                <div style="font-size: 2.5rem; font-weight: 800; color: var(--success); margin: 1rem 0;">
                    <?php echo $stats['active_borrows'] ?? 0; ?>
                </div>
                <div style="color: var(--text-muted); font-size: 0.8rem;">Currently reading</div>
            </div>

            <!-- Pending Requests -->
            <div class="widget-card" style="text-align: center; cursor: pointer; transition: all 0.3s;" onclick="window.location.href='deals.php?filter=pending'">
                <div class="widget-title" style="justify-content: center;">
                    <span><i class='bx bx-time-five'></i> Pending Requests</span>
                </div>
                <div style="font-size: 2.5rem; font-weight: 800; color: #f59e0b; margin: 1rem 0;">
                    <?php echo $stats['pending_requests'] ?? 0; ?>
                </div>
                <div style="color: var(--text-muted); font-size: 0.8rem;">Awaiting response</div>
            </div>
        </div>

        <!-- Community Books Section -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem;">
                <i class='bx bx-globe' style="color: var(--primary);"></i>
                Explore Community Books
            </h2>
            <a href="explore.php" class="btn btn-outline" style="font-weight: 600;">
                View All <i class='bx bx-right-arrow-alt'></i>
            </a>
        </div>

        <?php 
        $listings = searchListingsAdvanced([], 4); 
        ?>
        <div class="book-grid">
            <?php if (empty($listings)): ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 2rem; background: white; border-radius: var(--radius-lg); border: 1px dashed var(--border-color);">
                    <p style="color: var(--text-muted);">No books available in the community right now.</p>
                </div>
            <?php endif; ?>
            <?php foreach ($listings as $item): ?>
            <div class="book-card" style="transition: all 0.3s; cursor: pointer;" onclick="window.location.href='book_details.php?id=<?php echo $item['id']; ?>'">
                <div class="book-cover">
                    <span style="position: absolute; top: 10px; right: 10px; background:white; padding: 4px 10px; border-radius:12px; font-size:0.7rem; font-weight:700; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">Available</span>
                    <img src="<?php echo $item['cover_image'] ?: 'https://images.unsplash.com/photo-1543004218-ee141104975a?auto=format&fit=crop&q=80&w=800'; ?>" 
                         alt="<?php echo htmlspecialchars($item['title']); ?>"
                         onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1543004218-ee141104975a?auto=format&fit=crop&q=80&w=800';">
                </div>
                <div class="book-info">
                    <div class="book-title"><?php echo htmlspecialchars($item['title']); ?></div>
                    <div class="book-author"><?php echo htmlspecialchars($item['author']); ?></div>
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid var(--border-color);">
                        <span style="color: var(--primary); font-weight: 700; font-size: 0.9rem;">
                            <i class='bx bx-wallet'></i> <?php echo $item['credit_cost'] ?: 10; ?> credits
                        </span>
                        <button class="btn btn-primary btn-sm" style="padding: 0.4rem 1rem;" onclick="event.stopPropagation(); window.location.href='book_details.php?id=<?php echo $item['id']; ?>'">
                            Borrow
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>
</div>

<style>
.gradient-card {
    position: relative;
    overflow: hidden;
}
.gradient-card::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    pointer-events: none;
}
.widget-card {
    transition: all 0.3s ease;
}
.widget-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}
.book-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15);
}
</style>

</body>
</html>

