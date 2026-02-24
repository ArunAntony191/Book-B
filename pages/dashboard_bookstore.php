<?php 
require_once '../includes/db_helper.php';
require_once '../paths.php';
include '../includes/dashboard_header.php'; 

// Ensure only bookstore users can see this
if ($user['role'] !== 'bookstore' && $user['role'] !== 'admin') {
    header("Location: dashboard_user.php");
    exit();
}

$userId = $_SESSION['user_id'] ?? 0;
$stats = getUserStatsEnhanced($userId);
$storeStats = getStoreStats($userId);
$userReviews = getUserReviews($userId, 5);
?>

<div class="dashboard-wrapper">
    <?php include '../includes/dashboard_sidebar.php'; ?>

    <main class="main-content">
        <?php include '../includes/announcements_component.php'; ?>

        <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <div>
                <h1 style="font-size: 2rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.5rem;">Bookstore Management 📚<br><small style="font-size: 1rem; color: var(--text-muted); font-weight: 500;">Welcome, <strong><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></strong></small></h1>
                <p style="color: var(--text-muted); margin-top: -0.5rem;">Manage inventory, track sales, and grow your book business.</p>
            </div>
            <a href="add_listing.php" class="btn btn-primary" style="white-space: nowrap;">
                <i class='bx bx-plus-circle'></i> Add Books
            </a>
        </div>

        <!-- Symmetrical Widgets Grid -->
        <style>
            .bookstore-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 1.5rem;
                margin-bottom: 2rem;
            }
            @media (max-width: 1100px) {
                .bookstore-grid { grid-template-columns: repeat(2, 1fr); }
            }
            @media (max-width: 600px) {
                .bookstore-grid { grid-template-columns: 1fr; }
            }
        </style>
        <div class="bookstore-grid">
            <!-- Total Inventory -->
            <div class="widget-card" onclick="window.location.href='listings.php'" style="background: linear-gradient(135deg, #8b5cf615 0%, #8b5cf605 100%); border: 2px solid #8b5cf6; cursor: pointer;">
                <div class="widget-title" style="justify-content: center;">
                    <span><i class='bx bx-package'></i> Total Inventory</span>
                </div>
                <div style="font-size: 2.8rem; font-weight: 900; color: #8b5cf6; text-align: center; margin: 1rem 0;">
                    <?php echo number_format($storeStats['total_inventory'] ?? 0); ?>
                </div>
                <div style="text-align: center; color: var(--text-muted); font-size: 0.8rem;">
                    <?php echo $storeStats['unique_titles'] ?? 0; ?> unique titles
                </div>
            </div>

            <!-- Currently Lent/Sold -->
            <div class="widget-card" onclick="window.location.href='business_reports.php'" style="background: linear-gradient(135deg, #10b98115 0%, #10b98105 100%); border: 2px solid #10b981; cursor: pointer;">
                <div class="widget-title" style="justify-content: center;">
                    <span><i class='bx bx-trending-up'></i> Active Sales</span>
                </div>
                <div style="font-size: 2.8rem; font-weight: 900; color: #10b981; text-align: center; margin: 1rem 0;">
                    <?php echo $storeStats['currently_lent'] ?? 0; ?>
                </div>
                <div style="text-align: center; color: var(--text-muted); font-size: 0.8rem;">Books in circulation</div>
            </div>

            <!-- Revenue (Credits Earned) -->
            <div class="widget-card gradient-card" onclick="window.location.href='credit_history.php'" style="background: linear-gradient(135deg, #059669 0%, #10b981 100%); color: white; border: none; cursor: pointer;">
                <div class="widget-title" style="justify-content: center; color: rgba(255,255,255,0.9);">
                    <span><i class='bx bx-dollar-circle'></i> Credits Earned</span>
                </div>
                <div style="font-size: 2.8rem; font-weight: 900; text-align: center; margin: 1rem 0;">
                    <?php echo $stats['credits'] ?? 0; ?>
                </div>
                <div style="text-align: center; opacity: 0.9; font-size: 0.8rem;">Total revenue</div>
            </div>

            <!-- Available Stock -->
            <div class="widget-card" onclick="window.location.href='listings.php'" style="background: linear-gradient(135deg, #3b82f615 0%, #3b82f605 100%); border: 2px solid #3b82f6; cursor: pointer;">
                <div class="widget-title" style="justify-content: center;">
                    <span><i class='bx bx-check-circle'></i> Available</span>
                </div>
                <div style="font-size: 2.8rem; font-weight: 900; color: #3b82f6; text-align: center; margin: 1rem 0;">
                    <?php echo ($storeStats['total_inventory'] ?? 0) - ($storeStats['currently_lent'] ?? 0); ?>
                </div>
                <div style="text-align: center; color: var(--text-muted); font-size: 0.8rem;">Ready to sell</div>
            </div>

            <!-- Low Stock Alert -->
            <div class="widget-card" onclick="window.location.href='listings.php?stock=low'" style="background: linear-gradient(135deg, #f59e0b15 0%, #f59e0b05 100%); border: 2px solid #f59e0b; cursor: pointer;">
                <div class="widget-title" style="justify-content: center;">
                    <span><i class='bx bx-error-circle'></i> Low Stock</span>
                </div>
                <div style="font-size: 2.8rem; font-weight: 900; color: #f59e0b; text-align: center; margin: 1rem 0;">
                    <?php echo $storeStats['low_stock_items'] ?? 0; ?>
                </div>
                <div style="text-align: center; color: var(--text-muted); font-size: 0.8rem;">Items below 3 copies</div>
            </div>

            <!-- Out of Stock -->
            <div class="widget-card" style="background: linear-gradient(135deg, #ef444415 0%, #ef444405 100%); border: 2px solid #ef4444; cursor: pointer;" onclick="window.location.href='listings.php?stock=out'">
                <div class="widget-title" style="justify-content: center;">
                    <span><i class='bx bx-x-circle'></i> Out of Stock</span>
                </div>
                <div style="font-size: 2.8rem; font-weight: 900; color: #ef4444; text-align: center; margin: 1rem 0;">
                    <?php echo $storeStats['out_of_stock_items'] ?? 0; ?>
                </div>
                <div style="text-align: center; color: var(--text-muted); font-size: 0.8rem;">Need restock</div>
            </div>

            <!-- Trust Score -->
            <div class="widget-card" onclick="window.location.href='profile.php'" style="background: linear-gradient(135deg, <?php echo getTrustScoreRating($stats['trust_score'] ?? 50)['color']; ?>15 0%, <?php echo getTrustScoreRating($stats['trust_score'] ?? 50)['color']; ?>05 100%); border: 2px solid <?php echo getTrustScoreRating($stats['trust_score'] ?? 50)['color']; ?>; cursor: pointer;">
                <div class="widget-title" style="justify-content: center;">
                    <span><i class='bx bx-shield-alt-2'></i> Trust Score</span>
                </div>
                <div style="font-size: 2.5rem; font-weight: 900; text-align: center; margin: 1rem 0; color: <?php echo getTrustScoreRating($stats['trust_score'] ?? 50)['color']; ?>;">
                    <?php echo $stats['trust_score'] ?? 50; ?>/100
                </div>
                <div style="text-align: center;">
                    <span style="padding: 0.4rem 1.2rem; background: <?php echo getTrustScoreRating($stats['trust_score'] ?? 50)['color']; ?>; color: white; border-radius: 20px; display: inline-block; font-weight: 700; font-size: 0.75rem;">
                        <?php echo getTrustScoreRating($stats['trust_score'] ?? 50)['label']; ?>
                    </span>
                </div>
            </div>

            <!-- Customer Rating -->
            <div class="widget-card" onclick="openReviewsModal()" style="text-align: center; background: linear-gradient(135deg, #fbbf2415 0%, #fbbf2405 100%); border: 2px solid #fbbf24; cursor: pointer;">
                <div class="widget-title" style="justify-content: center;">
                    <span><i class='bx bxs-star'></i> Rating</span>
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
        </div>

        <!-- Quick Actions -->
        <div style="background: var(--section-bg); padding: 1.5rem; border-radius: var(--radius-lg); margin-bottom: 2rem; border: 1px solid var(--border-color);">
            <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class='bx bx-zap' style="color: #f59e0b;"></i> Quick Actions
            </h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <button class="btn btn-outline" onclick="window.location.href='add_listing.php'" style="justify-content: center; padding: 0.75rem;">
                    <i class='bx bx-plus-circle'></i> Add New Books
                </button>
                <button class="btn btn-outline" onclick="window.location.href='listings.php'" style="justify-content: center; padding: 0.75rem;">
                    <i class='bx bx-package'></i> Manage Inventory
                </button>
                <button class="btn btn-outline" onclick="window.location.href='business_reports.php'" style="justify-content: center; padding: 0.75rem;">
                    <i class='bx bx-list-ul'></i> View All Sales
                </button>
                <button class="btn btn-primary" onclick="window.location.href='deals.php?filter=requested'" style="justify-content: center; padding: 0.75rem;">
                    <i class='bx bx-time-five'></i> Pending Orders (<?php echo $stats['pending_requests'] ?? 0; ?>)
                </button>
            </div>
        </div>

        <!-- Recent Activity -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem;">
                <i class='bx bx-history' style="color: var(--primary);"></i>
                Recent Sales Activity
            </h2>
            <a href="deals.php" class="btn btn-outline" style="font-weight: 600;">
                View All <i class='bx bx-right-arrow-alt'></i>
            </a>
        </div>
        
        <?php
        // Get recent deals for bookstore
        $recentDeals = getUserDeals($userId);
        $recentDeals = array_slice($recentDeals, 0, 5); // Show only 5 most recent
        ?>

        <?php if (count($recentDeals) > 0): ?>
        <div style="background: var(--bg-card); border-radius: var(--radius-lg); border: 1px solid var(--border-color); overflow: hidden; box-shadow: var(--shadow-sm);">
            <table style="width: 100%; border-collapse: collapse;">
                <thead style="background: var(--bg-body); border-bottom: 1px solid var(--border-color);">
                    <tr>
                        <th style="padding: 1rem; text-align: left; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Book</th>
                        <th style="padding: 1rem; text-align: left; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Customer</th>
                        <th style="padding: 1rem; text-align: left; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Type</th>
                        <th style="padding: 1rem; text-align: left; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Status</th>
                        <th style="padding: 1rem; text-align: right; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentDeals as $deal): 
                        $statusColors = [
                            'requested' => ['bg' => 'var(--alert-warning-bg, #fef3c7)', 'text' => 'var(--warning)', 'label' => 'Pending'],
                            'approved' => ['bg' => 'var(--alert-info-bg, #dbeafe)', 'text' => 'var(--info)', 'label' => 'Approved'],
                            'active' => ['bg' => 'var(--alert-success-bg, #d1fae5)', 'text' => 'var(--success)', 'label' => 'Active'],
                            'returned' => ['bg' => 'var(--alert-success-bg, #e0e7ff)', 'text' => 'var(--primary)', 'label' => 'Completed'],
                            'cancelled' => ['bg' => 'var(--alert-danger-bg, #fee2e2)', 'text' => 'var(--danger)', 'label' => 'Cancelled']
                        ];
                        $statusInfo = $statusColors[$deal['status']] ?? ['bg' => 'var(--bg-body)', 'text' => 'var(--text-muted)', 'label' => ucfirst($deal['status'])];
                    ?>
                    <tr onclick="window.location.href='track_deliveries.php'" style="border-bottom: 1px solid var(--border-color); transition: background 0.2s; cursor: pointer;" onmouseover="this.style.background='var(--bg-body)'" onmouseout="this.style.background='transparent'">
                        <td style="padding: 1.25rem;">
                            <div style="font-weight: 700; color: var(--text-main);"><?php echo htmlspecialchars($deal['title']); ?></div>
                            <div style="font-size: 0.8rem; color: var(--text-muted);"><?php echo htmlspecialchars($deal['author']); ?></div>
                        </td>
                        <td style="padding: 1.25rem;"><?php echo htmlspecialchars($deal['borrower_name']); ?></td>
                        <td style="padding: 1.25rem; text-transform: capitalize;"><?php echo $deal['listing_type']; ?></td>
                        <td style="padding: 1.25rem;">
                            <span style="color: <?php echo $statusInfo['text']; ?>; background: <?php echo $statusInfo['bg']; ?>; padding: 4px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: 600;">
                                <?php echo $statusInfo['label']; ?>
                            </span>
                        </td>
                        <td style="padding: 1.25rem; text-align: right; font-size: 0.85rem; color: var(--text-muted);">
                            <?php echo date('M d, Y', strtotime($deal['created_at'])); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div style="background: var(--bg-card); border-radius: var(--radius-lg); border: 1px solid var(--border-color); padding: 3rem; text-align: center;">
            <i class='bx bx-store' style="font-size: 4rem; color: var(--text-muted); opacity: 0.5;"></i>
            <p style="color: var(--text-muted); margin-top: 1rem; font-size: 1.1rem;">No sales activity yet</p>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.5rem;">Start by adding books to your inventory</p>
        </div>
        <?php endif; ?>
    </main>
</div>

<!-- Reviews Modal -->
<div id="reviewsModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h2 style="font-weight: 800; display: flex; align-items: center; gap: 0.75rem; color: var(--text-main);">
                <i class='bx bxs-star' style="color: #fbbf24;"></i> Bookstore Reviews
            </h2>
            <button class="modal-close" onclick="closeReviewsModal()"><i class='bx bx-x'></i></button>
        </div>
        <div class="modal-body">
            <?php if (empty($userReviews)): ?>
                <div class="empty-reviews">
                    <i class='bx bx-chat'></i>
                    <p>No customer reviews yet. Build your reputation by fulfilling orders!</p>
                </div>
            <?php else: ?>
                <div class="reviews-list">
                    <?php foreach ($userReviews as $r): ?>
                        <div class="review-item">
                            <div class="review-meta">
                                <div class="reviewer-info">
                                    <div class="reviewer-avatar">
                                        <?php echo strtoupper(substr($r['firstname'], 0, 1)); ?>
                                    </div>
                                    <span style="font-weight: 700; color: #1e293b; font-size: 0.95rem;">
                                        <?php echo htmlspecialchars($r['firstname'] . ' ' . $r['lastname']); ?>
                                    </span>
                                </div>
                                <span style="font-size: 0.75rem; color: #94a3b8; font-weight: 600;">
                                    <?php echo date('M d, Y', strtotime($r['created_at'])); ?>
                                </span>
                            </div>
                            <div class="rating-stars" style="margin-bottom: 0.75rem;">
                                <?php for($i=1; $i<=5; $i++): ?>
                                    <i class='bx <?php echo $i <= $r['rating'] ? "bxs-star" : "bx-star"; ?>'></i>
                                <?php endfor; ?>
                            </div>
                            <?php if ($r['comment']): ?>
                                <p style="font-size: 0.9rem; color: #475569; line-height: 1.6; margin: 0;">
                                    "<?php echo nl2br(htmlspecialchars($r['comment'])); ?>"
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #f1f5f9; text-align: center;">
                <a href="user_profile.php?id=<?php echo $userId; ?>#reviews" class="btn btn-outline" style="width: 100%; justify-content: center; border-radius: 14px;">
                    View All Reviews on Profile <i class='bx bx-right-arrow-alt'></i>
                </a>
            </div>
        </div>
    </div>
</div>

<style>
    /* Premium Modal Styles */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(15, 23, 42, 0.4);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        opacity: 0;
        visibility: hidden;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .modal-overlay.active {
        opacity: 1;
        visibility: visible;
    }

    .modal-content {
        background: var(--bg-card);
        width: 90%;
        max-width: 600px;
        max-height: 85vh;
        border-radius: 24px;
        box-shadow: var(--shadow-lg);
        border: 1px solid var(--border-color);
        overflow: hidden;
        transform: scale(0.9) translateY(20px);
        transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        display: flex;
        flex-direction: column;
    }

    .modal-overlay.active .modal-content {
        transform: scale(1) translateY(0);
    }

    .modal-header {
        padding: 1.5rem 2rem;
        background: var(--bg-card);
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .modal-header h2 {
        font-size: 1.5rem;
        letter-spacing: -0.025em;
    }

    .modal-close {
        background: var(--bg-body);
        color: var(--text-muted);
        border: none;
        width: 36px;
        height: 36px;
        border-radius: 12px;
        font-size: 1.5rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }

    .modal-close:hover {
        background: #fee2e2;
        color: #ef4444;
        transform: rotate(90deg);
    }

    .modal-body {
        padding: 2rem;
        overflow-y: auto;
        flex-grow: 1;
        scrollbar-width: thin;
    }

    /* Review Items */
    .reviews-list {
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }

    .review-item {
        padding: 1.25rem;
        background: var(--bg-body);
        border-radius: 18px;
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
    }

    .review-item:hover {
        background: #fff;
        border-color: #fbbf24;
        transform: translateX(5px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
    }

    .review-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.75rem;
    }

    .reviewer-info {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .reviewer-avatar {
        width: 32px;
        height: 32px;
        background: #e0e7ff;
        color: #4338ca;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.8rem;
    }

    .rating-stars {
        color: #fbbf24;
        display: flex;
        gap: 2px;
    }

    .empty-reviews {
        text-align: center;
        padding: 4rem 2rem;
    }

    .empty-reviews i {
        font-size: 4rem;
        background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin-bottom: 1.5rem;
    }

    .empty-reviews p {
        color: #94a3b8;
        font-weight: 500;
    }
</style>

<script>
    function openReviewsModal() {
        const modal = document.getElementById('reviewsModal');
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeReviewsModal() {
        const modal = document.getElementById('reviewsModal');
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }

    window.addEventListener('click', function(e) {
        const modal = document.getElementById('reviewsModal');
        if (e.target === modal) closeReviewsModal();
    });
</script>

</body>
</html>
