<?php 
require_once '../includes/db_helper.php';
require_once '../paths.php';
include '../includes/dashboard_header.php'; 

// Fetch enhanced stats with credits and trust
$userId = $_SESSION['user_id'] ?? 0;
$stats = getUserStatsEnhanced($userId);
$books = getAllBooks(4);
$trustRating = $stats['trust_rating'] ?? getTrustScoreRating(50);
$hasMinTokens = hasMinimumTokens($userId);

// Fetch latest reviews for the modal
$userReviews = getUserReviews($userId, 5);

// Fetch pending dues (library fines)
$pdo = getDBConnection();
$fineStmt = $pdo->prepare("
    SELECT COALESCE(SUM(monetary_penalty), 0) as total_due
    FROM penalties
    WHERE user_id = ? AND status = 'pending' AND penalty_type = 'damage_fine'
");
$fineStmt->execute([$userId]);
$totalDue = (float)$fineStmt->fetchColumn();

$fineDetailsStmt = $pdo->prepare("
    SELECT p.*, b.title as book_title
    FROM penalties p
    JOIN transactions t ON p.transaction_id = t.id
    JOIN listings l ON t.listing_id = l.id
    JOIN books b ON l.book_id = b.id
    WHERE p.user_id = ? AND p.status = 'pending' AND p.penalty_type = 'damage_fine'
    ORDER BY p.created_at DESC
");
$fineDetailsStmt->execute([$userId]);
$pendingFinesList = $fineDetailsStmt->fetchAll();

// Fetch active deliveries (as borrower/buyer)
$deliveryStmt = $pdo->prepare("
    SELECT COUNT(*) FROM transactions
    WHERE borrower_id = ? AND delivery_method = 'delivery' AND status IN ('assigned', 'active')
");
$deliveryStmt->execute([$userId]);
$activeDeliveries = (int)$deliveryStmt->fetchColumn();

?>

<style>
    .dashboard-header-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }

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
        background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
        pointer-events: none;
    }

    .widget-card {
        background: var(--bg-card);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        height: 100%;
    }
    .widget-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
    }
    .widget-title {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.9rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        width: 100%;
        justify-content: center;
    }

    /* Pending Dues badge style */
    .dues-widget {
        border: 2px solid #ef4444 !important;
        background: linear-gradient(135deg, #ef444415 0%, #ef444405 100%) !important;
    }
    .dues-amount {
        font-size: 2.5rem;
        font-weight: 900;
        color: #ef4444;
        margin: 1rem 0 0.5rem;
    }
    .dues-subtitle {
        font-size: 0.85rem;
        font-weight: 700;
        color: #ef4444;
    }

    .section-title {
        font-size: 1.1rem;
        font-weight: 700;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    /* Book card styles */
    .book-card {
        border: 2px solid transparent;
        transition: all 0.3s;
    }
    .book-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 25px -5px rgba(0,0,0,0.15);
        border-color: var(--primary);
    }
    .book-card.rare-card {
        border-color: #f59e0b;
        background: rgba(245,158,11,0.05);
    }
    .book-card.rare-card:hover {
        border-color: #d97706;
    }
    .rare-badge {
        position: absolute;
        top: 10px;
        left: 10px;
        background: #f59e0b;
        color: white;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 700;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        z-index: 10;
    }

    /* Modal Styles */
    .modal-overlay {
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background: rgba(15,23,42,0.5);
        backdrop-filter: blur(4px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 2000;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    }
    .modal-overlay.active {
        opacity: 1;
        visibility: visible;
    }
    .modal-content {
        background: var(--bg-card);
        width: 90%;
        max-width: 500px;
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-lg);
        border: 1px solid var(--border-color);
        transform: translateY(20px);
        transition: all 0.3s ease;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
        color: var(--text-body);
    }
    .modal-overlay.active .modal-content {
        transform: translateY(0);
    }
    .modal-header {
        padding: 1.5rem;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .modal-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        color: var(--text-muted);
        cursor: pointer;
        line-height: 1;
    }
    .modal-body {
        padding: 1.5rem;
        overflow-y: auto;
    }
    .reviews-list {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }
    .review-item {
        padding-bottom: 1.5rem;
        border-bottom: 1px solid var(--border-color);
        text-align: left;
    }
    .review-item:last-child {
        border-bottom: none;
    }
</style>

<div class="dashboard-wrapper">
    <?php include '../includes/dashboard_sidebar.php'; ?>

    <main class="main-content">
        <?php include '../includes/due_date_reminder.php'; ?>
        <?php include '../includes/announcements_component.php'; ?>

        <!-- Header -->
        <div class="dashboard-header-bar">
            <div>
                <h1 style="margin:0; font-size: 1.8rem;">My Dashboard</h1>
                <p style="margin:0; color: var(--text-muted);">Welcome back, <?php echo htmlspecialchars($user['firstname']); ?>! 👋</p>
            </div>
            <div style="display: flex; gap: 1rem; align-items: center;">
                <?php if (!$hasMinTokens): ?>
                    <div style="background: #fef2f2; border: 1px solid #fee2e2; color: #991b1b; padding: 0.5rem 1rem; border-radius: 8px; font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class='bx bx-error-circle'></i>
                        Min. <?php echo MIN_TOKEN_LIMIT; ?> credits needed to list/borrow.
                    </div>
                <?php endif; ?>
                <a href="add_listing.php" class="btn btn-primary <?php echo !$hasMinTokens ? 'disabled' : ''; ?>" <?php echo !$hasMinTokens ? 'style="opacity: 0.6; pointer-events: none;"' : ''; ?>>
                    <i class='bx bx-plus-circle'></i> List a Book
                </a>
            </div>
        </div>

        <!-- Widgets Grid -->
        <div class="widgets-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem;">

            <!-- Token Balance -->
            <div class="widget-card gradient-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none;">
                <div class="widget-title" style="color: rgba(255,255,255,0.9);">
                    <span><i class='bx bx-wallet'></i> Token Balance</span>
                </div>
                <div style="font-size: 3rem; font-weight: 900; margin: 1rem 0;">
                    <?php echo $stats['credits'] ?? 100; ?>
                </div>
                <div style="opacity: 0.9; font-size: 0.85rem;">
                    Minimum required: <?php echo MIN_TOKEN_LIMIT; ?>
                </div>
                <a href="credit_history.php" style="display: block; margin-top: 1rem; color: white; text-decoration: underline; font-size: 0.85rem;">
                    View History →
                </a>
            </div>

            <!-- Trust Score -->
            <div class="widget-card" style="background: linear-gradient(135deg, <?php echo $trustRating['color']; ?>15 0%, <?php echo $trustRating['color']; ?>05 100%); border: 2px solid <?php echo $trustRating['color']; ?>;">
                <div class="widget-title">
                    <span><i class='bx bx-shield-alt-2'></i> Trust Score</span>
                </div>
                <div style="margin: 1rem 0;">
                    <div style="font-size: 2.5rem; font-weight: 900; color: <?php echo $trustRating['color']; ?>;">
                        <?php echo $stats['trust_score'] ?? 50; ?>/100
                    </div>
                    <div style="margin-top: 0.5rem; padding: 0.4rem 1.2rem; background: <?php echo $trustRating['color']; ?>; color: white; border-radius: 20px; display: inline-block; font-weight: 700; font-size: 0.85rem;">
                        <?php echo $trustRating['label']; ?>
                    </div>
                </div>
                <div style="color: var(--text-muted); font-size: 0.8rem;">Built on reliability</div>
            </div>

            <!-- Your Rating -->
            <div class="widget-card" style="background: linear-gradient(135deg, #fbbf2415 0%, #fbbf2405 100%); border: 2px solid #fbbf24; cursor: pointer;" onclick="openReviewsModal()">
                <div class="widget-title">
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
                <div style="color: var(--text-muted); font-size: 0.8rem;"><?php echo $stats['total_ratings'] ?? 0; ?> reviews</div>
            </div>

            <!-- Pending Dues -->
            <div class="widget-card dues-widget" style="cursor: pointer;" onclick="openFinesModal()">
                <div class="widget-title" style="color: #ef4444;">
                    <span><i class='bx bx-receipt'></i> Pending Dues</span>
                </div>
                <div class="dues-amount">₹<?php echo number_format($totalDue, 2); ?></div>
                <div class="dues-subtitle">
                    <?php echo $totalDue > 0 ? 'Outstanding fines' : 'No outstanding fines'; ?>
                </div>
                <?php if ($totalDue > 0): ?>
                    <div style="margin-top: 1rem; font-size: 0.8rem; color: #ef4444; text-decoration: underline;">Pay Now →</div>
                <?php endif; ?>
            </div>

            <!-- My Listings -->
            <div class="widget-card" style="cursor: pointer;" onclick="window.location.href='deals.php?tab=listings'">
                <div class="widget-title">
                    <span><i class='bx bx-book-bookmark'></i> My Listings</span>
                </div>
                <div style="font-size: 2.5rem; font-weight: 800; color: var(--primary); margin: 1rem 0;">
                    <?php echo $stats['total_listings'] ?? 0; ?>
                </div>
                <div style="color: var(--text-muted); font-size: 0.8rem;">Books shared</div>
            </div>

            <!-- Active Borrows -->
            <div class="widget-card" style="cursor: pointer;" onclick="window.location.href='deals.php'">
                <div class="widget-title">
                    <span><i class='bx bx-book-reader'></i> Active Borrows</span>
                </div>
                <div style="font-size: 2.5rem; font-weight: 800; color: var(--success); margin: 1rem 0;">
                    <?php echo $stats['active_borrows'] ?? 0; ?>
                </div>
                <div style="color: var(--text-muted); font-size: 0.8rem;">Currently reading</div>
            </div>

            <!-- Pending Requests -->
            <div class="widget-card" style="cursor: pointer;" onclick="window.location.href='deals.php?filter=pending'">
                <div class="widget-title">
                    <span><i class='bx bx-time-five'></i> Pending Requests</span>
                </div>
                <div style="font-size: 2.5rem; font-weight: 800; color: #f59e0b; margin: 1rem 0;">
                    <?php echo $stats['pending_requests'] ?? 0; ?>
                </div>
                <div style="color: var(--text-muted); font-size: 0.8rem;">Awaiting response</div>
            </div>

            <!-- Active Deliveries -->
            <div class="widget-card" style="cursor: pointer;" onclick="window.location.href='track_deliveries.php'">
                <div class="widget-title">
                    <span><i class='bx bx-package'></i> Deliveries</span>
                </div>
                <div style="font-size: 2.5rem; font-weight: 800; color: #2563eb; margin: 1rem 0;">
                    <?php echo $activeDeliveries; ?>
                </div>
                <div style="color: var(--text-muted); font-size: 0.8rem;">Active shipments</div>
            </div>

        </div>

        <!-- Your Interests Books Section -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem;">
                <i class='bx bx-heart' style="color: #ef4444;"></i>
                Your Interests Books
            </h2>
            <a href="explore.php" class="btn btn-outline" style="font-weight: 600;">
                View All <i class='bx bx-right-arrow-alt'></i>
            </a>
        </div>

        <?php 
        $favoriteCategory = $user['favorite_category'] ?? '';
        $categoryList = !empty($favoriteCategory) ? array_map('trim', explode(',', $favoriteCategory)) : [];
        $categoryFilter = !empty($categoryList) ? [
            'category' => $categoryList,
            'exclude_user_id' => $userId
        ] : [];
        $listings = !empty($categoryFilter) ? searchListingsAdvanced($categoryFilter, 4) : []; 
        ?>
        <div class="book-grid">
            <?php if (empty($favoriteCategory)): ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 2rem; background: var(--bg-card); border-radius: var(--radius-lg); border: 1px dashed var(--border-color);">
                    <i class='bx bx-heart' style="font-size: 3rem; color: #cbd5e1; margin-bottom: 0.5rem;"></i>
                    <p style="color: var(--text-muted); margin-bottom: 1rem;">Set your interests to see personalized recommendations!</p>
                    <a href="profile.php" class="btn btn-primary btn-sm">Set Interests</a>
                </div>
            <?php elseif (empty($listings)): ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 2rem; background: var(--bg-card); border-radius: var(--radius-lg); border: 1px dashed var(--border-color);">
                    <i class='bx bx-search' style="font-size: 3rem; color: #cbd5e1; margin-bottom: 0.5rem;"></i>
                    <p style="color: var(--text-muted);">No books matching your specific interests right now.</p>
                </div>
            <?php endif; ?>
            <?php foreach ($listings as $item): 
                $isRare = $item['is_rare'] ?? 0;
            ?>
            <div class="book-card <?php echo $isRare ? 'rare-card' : ''; ?>" style="cursor: pointer;" onclick="window.location.href='book_details.php?id=<?php echo $item['id']; ?>'">
                <div class="book-cover">
                    <?php if ($isRare): ?>
                        <span class="rare-badge">RARE</span>
                    <?php endif; ?>
                    <span style="position: absolute; top: 10px; right: 10px; background: var(--bg-card); color: var(--text-main); padding: 4px 10px; border-radius:12px; font-size:0.7rem; font-weight:700; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border: 1px solid var(--border-color);">Available</span>
                    <?php 
                        $cover = $item['cover_image'];
                        $fallback = 'https://images.unsplash.com/photo-1543004218-ee141104975a?auto=format&fit=crop&q=80&w=800';
                        $cover = $cover ?: $fallback;
                    ?>
                    <img src="<?php echo htmlspecialchars(html_entity_decode($cover), ENT_QUOTES, 'UTF-8'); ?>" 
                         alt="<?php echo htmlspecialchars($item['title']); ?>"
                         onerror="this.onerror=null; this.src='<?php echo $fallback; ?>';">
                </div>
                <div class="book-info">
                    <div class="book-title"><?php echo htmlspecialchars($item['title']); ?></div>
                    <div class="book-author"><?php echo htmlspecialchars($item['author']); ?></div>
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid var(--border-color);">
                        <span style="color: var(--primary); font-weight: 700; font-size: 0.9rem;">
                            <?php if ($item['listing_type'] === 'sell'): ?>
                                ₹<?php echo number_format($item['price'], 2); ?>
                            <?php else: ?>
                                <i class='bx bx-wallet'></i> <?php echo $item['credit_cost'] ?: 10; ?> credits
                            <?php endif; ?>
                        </span>
                        <button class="btn btn-primary btn-sm" style="padding: 0.4rem 1rem;" onclick="event.stopPropagation(); window.location.href='book_details.php?id=<?php echo $item['id']; ?>'">
                            <?php echo $item['listing_type'] === 'sell' ? 'Buy' : 'Borrow'; ?>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Community Books Section -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; margin-top: 3rem;">
            <h2 style="font-size: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem;">
                <i class='bx bx-group' style="color: var(--primary);"></i>
                Community Books
            </h2>
            <a href="community.php" class="btn btn-outline" style="font-weight: 600;">
                View Communities <i class='bx bx-right-arrow-alt'></i>
            </a>
        </div>

        <?php 
        $communityBooks = getUserCommunityBooks($userId, 4);
        ?>
        <div class="book-grid">
            <?php if (empty($communityBooks)): ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 2rem; background: var(--bg-card); border-radius: var(--radius-lg); border: 1px dashed var(--border-color);">
                    <i class='bx bx-group' style="font-size: 3rem; color: #cbd5e1; margin-bottom: 0.5rem;"></i>
                    <p style="color: var(--text-muted); margin-bottom: 1rem;">No books from your communities yet.</p>
                    <a href="community.php" class="btn btn-primary btn-sm">Join Communities</a>
                </div>
            <?php endif; ?>
            <?php foreach ($communityBooks as $item): 
                $isRare = $item['is_rare'] ?? 0;
            ?>
            <div class="book-card <?php echo $isRare ? 'rare-card' : ''; ?>" style="cursor: pointer;" onclick="window.location.href='book_details.php?id=<?php echo $item['id']; ?>'">
                <div class="book-cover">
                    <?php if ($isRare): ?>
                        <span class="rare-badge">RARE</span>
                    <?php endif; ?>
                    <span style="position: absolute; top: 10px; right: 10px; background: var(--primary); color: white; padding: 4px 10px; border-radius:12px; font-size:0.7rem; font-weight:700; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"><?php echo htmlspecialchars($item['community_name']); ?></span>
                    <?php 
                        $cover = $item['cover_image'];
                        $fallback = 'https://images.unsplash.com/photo-1543004218-ee141104975a?auto=format&fit=crop&q=80&w=800';
                        $cover = $cover ?: $fallback;
                    ?>
                    <img src="<?php echo htmlspecialchars(html_entity_decode($cover), ENT_QUOTES, 'UTF-8'); ?>" 
                         alt="<?php echo htmlspecialchars($item['title']); ?>"
                         onerror="this.onerror=null; this.src='<?php echo $fallback; ?>';">
                </div>
                <div class="book-info">
                    <div class="book-title"><?php echo htmlspecialchars($item['title']); ?></div>
                    <div class="book-author"><?php echo htmlspecialchars($item['author']); ?></div>
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid var(--border-color);">
                        <span style="color: var(--primary); font-weight: 700; font-size: 0.9rem;">
                            <?php if ($item['listing_type'] === 'sell'): ?>
                                ₹<?php echo number_format($item['price'], 2); ?>
                            <?php else: ?>
                                <i class='bx bx-wallet'></i> <?php echo $item['credit_cost'] ?: 10; ?> credits
                            <?php endif; ?>
                        </span>
                        <button class="btn btn-primary btn-sm" style="padding: 0.4rem 1rem;" onclick="event.stopPropagation(); window.location.href='book_details.php?id=<?php echo $item['id']; ?>'">
                            <?php echo $item['listing_type'] === 'sell' ? 'Buy' : 'View'; ?>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

    </main>

    <!-- Reviews Modal -->
    <div id="reviewsModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2 style="font-weight: 800; display: flex; align-items: center; gap: 0.5rem; color: var(--text-main);">
                    <i class='bx bxs-star' style="color: #fbbf24;"></i> Your Reviews
                </h2>
                <button class="modal-close" onclick="closeReviewsModal()">&times;</button>
            </div>
            <div class="modal-body">
                <?php if (empty($userReviews)): ?>
                    <div style="text-align: center; padding: 3rem; color: var(--text-muted);">
                        <i class='bx bx-message-rounded-dots' style="font-size: 3rem; opacity: 0.3;"></i>
                        <p>No reviews received yet.</p>
                    </div>
                <?php else: ?>
                    <div class="reviews-list">
                        <?php foreach ($userReviews as $r): ?>
                            <div class="review-item">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                                    <span style="font-weight: 700; color: var(--text-main);">
                                        <?php echo htmlspecialchars($r['firstname'] . ' ' . $r['lastname']); ?>
                                    </span>
                                    <span style="font-size: 0.75rem; color: var(--text-muted);">
                                        <?php echo date('M d, Y', strtotime($r['created_at'])); ?>
                                    </span>
                                </div>
                                <div style="color: #fbbf24; font-size: 0.85rem; margin-bottom: 0.5rem;">
                                    <?php for($i=1; $i<=5; $i++): ?>
                                        <i class='bx <?php echo $i <= $r['rating'] ? "bxs-star" : "bx-star"; ?>'></i>
                                    <?php endfor; ?>
                                </div>
                                <?php if ($r['comment']): ?>
                                    <p style="font-size: 0.9rem; color: var(--text-body); line-height: 1.5;">
                                        <?php echo nl2br(htmlspecialchars($r['comment'])); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div style="margin-top: 2rem; text-align: center;">
                    <a href="user_profile.php?id=<?php echo $userId; ?>#reviews" class="btn btn-outline btn-sm">View All Reviews on Profile</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Fines Modal -->
    <div id="finesModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2 style="font-weight: 800; display: flex; align-items: center; gap: 0.5rem; color: #ef4444;">
                    <i class='bx bx-receipt'></i> Outstanding Fines
                </h2>
                <button class="modal-close" onclick="closeFinesModal()">&times;</button>
            </div>
            <div class="modal-body">
                <?php if (empty($pendingFinesList)): ?>
                    <div style="text-align: center; padding: 3rem; color: var(--text-muted);">
                        <i class='bx bx-check-circle' style="font-size: 3rem; color: #10b981; opacity: 0.5;"></i>
                        <p style="margin-top: 1rem; font-weight: 600;">You have no outstanding dues. Great job!</p>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; margin-bottom: 2rem; color: var(--text-muted);">Please review and settle your pending dues for damaged books.</p>
                    <div class="reviews-list">
                        <?php foreach ($pendingFinesList as $fine): ?>
                            <div class="review-item" style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <div style="font-weight: 700; color: var(--text-main); font-size: 1.1rem;">
                                        <?php echo htmlspecialchars($fine['book_title']); ?>
                                    </div>
                                    <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.3rem;">
                                        Reason: <span style="color: #ef4444; font-weight: 600;"><?php echo htmlspecialchars($fine['reason']); ?></span>
                                    </div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.3rem;">
                                        Applied on <?php echo date('M d, Y', strtotime($fine['created_at'])); ?>
                                    </div>
                                </div>
                                <div style="font-size: 1.3rem; font-weight: 900; color: #ef4444;">
                                    ₹<?php echo number_format($fine['monetary_penalty'], 2); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 2px dashed var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-weight: 800; font-size: 1.2rem; color: var(--text-main);">Total Due:</span>
                        <span style="font-weight: 900; font-size: 1.5rem; color: #ef4444;">₹<?php echo number_format($totalDue, 2); ?></span>
                    </div>
                    <div style="margin-top: 1.5rem;">
                        <button id="btn-pay-fines" class="btn btn-primary w-full" style="font-size: 1.1rem; padding: 1rem;" onclick="payFines()">
                            Pay ₹<?php echo number_format($totalDue, 2); ?> Now
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
    // Reviews Modal Logic
    function openReviewsModal() {
        document.getElementById('reviewsModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeReviewsModal() {
        document.getElementById('reviewsModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    document.getElementById('reviewsModal').addEventListener('click', function(e) {
        if (e.target === this) closeReviewsModal();
    });

    // Fines Modal Logic
    function openFinesModal() {
        document.getElementById('finesModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeFinesModal() {
        document.getElementById('finesModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    document.getElementById('finesModal').addEventListener('click', function(e) {
        if (e.target === this) closeFinesModal();
    });

    // Razorpay Integration for Fines
    function payFines() {
        const btn = document.getElementById('btn-pay-fines');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = "<i class='bx bx-loader-alt bx-spin'></i> Processing...";

        fetch('../actions/payment_action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=create_fine_order`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const options = {
                    "key": data.key_id,
                    "amount": data.amount,
                    "currency": "INR",
                    "name": "BOOK-B Fines",
                    "description": "Payment for damaged book dues",
                    "order_id": data.order_id,
                    "handler": function (response) {
                        verifyFinePayment(response);
                    },
                    "prefill": {
                        "name": data.name,
                        "email": data.email
                    },
                    "theme": {
                        "color": "#ef4444"
                    },
                    "modal": {
                        "ondismiss": function() {
                            btn.disabled = false;
                            btn.innerHTML = originalText;
                        }
                    }
                };
                const rzp1 = new Razorpay(options);
                rzp1.open();
            } else {
                showToast('Error initializing payment: ' + data.message, 'error');
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Payment error. Please try again later.', 'error');
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
    }

    function verifyFinePayment(paymentResponse) {
        const formData = new URLSearchParams();
        formData.append('action', 'verify_fine_payment');
        formData.append('razorpay_payment_id', paymentResponse.razorpay_payment_id);
        formData.append('razorpay_order_id', paymentResponse.razorpay_order_id);
        formData.append('razorpay_signature', paymentResponse.razorpay_signature);

        fetch('../actions/payment_action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('Payment successful! Your fines have been cleared.', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('Payment verification failed: ' + data.message, 'error');
                document.getElementById('btn-pay-fines').disabled = false;
                document.getElementById('btn-pay-fines').innerText = 'Retry Payment';
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Server error verifying payment.', 'error');
            document.getElementById('btn-pay-fines').disabled = false;
            document.getElementById('btn-pay-fines').innerText = 'Retry Payment';
        });
    }
</script>

</body>
</html>
