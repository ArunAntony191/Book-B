<?php 
require_once '../includes/db_helper.php';
require_once '../paths.php';
include '../includes/dashboard_header.php'; 

// Ensure only library users can see this
if ($user['role'] !== 'library' && $user['role'] !== 'admin') {
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
                <h1 style="font-size: 2rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.5rem;">Library Management 🏛️<br><small style="font-size: 1rem; color: var(--text-muted); font-weight: 500;">Welcome, <strong><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></strong></small></h1>
                <p style="color: var(--text-muted); margin-top: -0.5rem;">Manage your collection, track borrowings, and serve your community.</p>
            </div>
            <a href="add_listing.php" class="btn btn-primary" style="white-space: nowrap;">
                <i class='bx bx-plus-circle'></i> Add Books
            </a>
        </div>

        <!-- Symmetrical Widgets Grid -->
        <style>
            .library-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 1.5rem;
                margin-bottom: 2rem;
            }
            @media (max-width: 1100px) {
                .library-grid { grid-template-columns: repeat(2, 1fr); }
            }
            @media (max-width: 600px) {
                .library-grid { grid-template-columns: 1fr; }
            }
        </style>
        <div class="library-grid">
            <!-- Total Inventory -->
            <div class="widget-card" onclick="window.location.href='listings.php'" style="background: linear-gradient(135deg, #3b82f615 0%, #3b82f605 100%); border: 2px solid #3b82f6; cursor: pointer;">
                <div class="widget-title" style="justify-content: center;">
                    <span><i class='bx bx-library'></i> Total Inventory</span>
                </div>
                <div style="font-size: 2.8rem; font-weight: 900; color: #3b82f6; text-align: center; margin: 1rem 0;">
                    <?php echo number_format($storeStats['total_inventory'] ?? 0); ?>
                </div>
                <div style="text-align: center; color: var(--text-muted); font-size: 0.8rem;">
                    <?php echo $storeStats['unique_titles'] ?? 0; ?> unique titles
                </div>
            </div>

            <!-- Currently Lent -->
            <div class="widget-card" onclick="window.location.href='business_reports.php'" style="background: linear-gradient(135deg, #10b98115 0%, #10b98105 100%); border: 2px solid #10b981; cursor: pointer;">
                <div class="widget-title" style="justify-content: center;">
                    <span><i class='bx bx-export'></i> Currently Lent</span>
                </div>
                <div style="font-size: 2.8rem; font-weight: 900; color: #10b981; text-align: center; margin: 1rem 0;">
                    <?php echo $storeStats['currently_lent'] ?? 0; ?>
                </div>
                <div style="text-align: center; color: var(--text-muted); font-size: 0.8rem;">Books with members</div>
            </div>

            <!-- Available Stock -->
            <div class="widget-card" onclick="window.location.href='listings.php'" style="background: linear-gradient(135deg, #3b82f615 0%, #3b82f605 100%); border: 2px solid #3b82f6; cursor: pointer;">
                <div class="widget-title" style="justify-content: center;">
                    <span><i class='bx bx-check-circle'></i> Available</span>
                </div>
                <div style="font-size: 2.8rem; font-weight: 900; color: #3b82f6; text-align: center; margin: 1rem 0;">
                    <?php echo ($storeStats['total_inventory'] ?? 0) - ($storeStats['currently_lent'] ?? 0); ?>
                </div>
                <div style="text-align: center; color: var(--text-muted); font-size: 0.8rem;">Ready to lend</div>
            </div>

            <!-- Credits Earned -->
            <div class="widget-card gradient-card" onclick="window.location.href='credit_history.php'" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; cursor: pointer;">
                <div class="widget-title" style="justify-content: center; color: rgba(255,255,255,0.9);">
                    <span><i class='bx bx-wallet'></i> Credits Earned</span>
                </div>
                <div style="font-size: 2.8rem; font-weight: 900; text-align: center; margin: 1rem 0;">
                    <?php echo $stats['credits'] ?? 0; ?>
                </div>
                <div style="text-align: center; opacity: 0.9; font-size: 0.8rem;">From lending services</div>
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

            <!-- Library Rating -->
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
            <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; color: var(--text-main);">
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
                    <i class='bx bx-list-ul'></i> View All Borrows
                </button>
                <button class="btn btn-outline" onclick="window.location.href='manage_announcements.php'" style="justify-content: center; padding: 0.75rem;">
                    <i class='bx bxs-megaphone'></i> Manage Announcements
                </button>
                <button class="btn btn-primary" onclick="window.location.href='deals.php?filter=requested'" style="justify-content: center; padding: 0.75rem;">
                    <i class='bx bx-time-five'></i> Pending Requests (<?php echo $stats['pending_requests'] ?? 0; ?>)
                </button>
            </div>
        </div>

        <!-- Recent Activity -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; color: var(--text-main);">
                <i class='bx bx-history' style="color: var(--primary);"></i>
                Recent Activity
            </h2>
            <a href="deals.php" class="btn btn-outline" style="font-weight: 600;">
                View All <i class='bx bx-right-arrow-alt'></i>
            </a>
        </div>
        
        <?php
        // Get recent deals for library
        $recentDeals = getUserDeals($userId);
        $recentDeals = array_slice($recentDeals, 0, 5); // Show only 5 most recent
        ?>

        <?php if (count($recentDeals) > 0): ?>
        <div style="background: var(--bg-card); border-radius: var(--radius-lg); border: 1px solid var(--border-color); overflow: hidden; box-shadow: var(--shadow-sm);">
            <table style="width: 100%; border-collapse: collapse;">
                <thead style="background: var(--bg-body); border-bottom: 1px solid var(--border-color);">
                    <tr>
                        <th style="padding: 1rem; text-align: left; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Book</th>
                        <th style="padding: 1rem; text-align: left; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Borrower</th>
                        <th style="padding: 1rem; text-align: left; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Status</th>
                        <th style="padding: 1rem; text-align: left; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Date</th>
                        <th style="padding: 1rem; text-align: right; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentDeals as $deal): 
                        $statusColors = [
                            'requested' => ['bg' => 'var(--alert-warning-bg, #fef3c7)', 'text' => 'var(--warning)', 'label' => 'Pending'],
                            'approved' => ['bg' => 'var(--alert-info-bg, #dbeafe)', 'text' => 'var(--info)', 'label' => 'Approved'],
                            'active' => ['bg' => 'var(--alert-success-bg, #d1fae5)', 'text' => 'var(--success)', 'label' => 'Active'],
                            'returned' => ['bg' => 'var(--alert-success-bg, #e0e7ff)', 'text' => 'var(--primary)', 'label' => 'Returned'],
                            'cancelled' => ['bg' => 'var(--alert-danger-bg, #fee2e2)', 'text' => 'var(--danger)', 'label' => 'Cancelled']
                        ];
                        $statusInfo = $statusColors[$deal['status']] ?? ['bg' => 'var(--bg-body)', 'text' => 'var(--text-muted)', 'label' => ucfirst($deal['status'])];
                    ?>
                    <tr style="border-bottom: 1px solid var(--border-color); transition: background 0.2s;" onmouseover="this.style.background='var(--bg-body)'" onmouseout="this.style.background='transparent'">
                        <td style="padding: 1.25rem;">
                            <div style="font-weight: 700; color: var(--text-main);"><?php echo htmlspecialchars($deal['title']); ?></div>
                            <div style="font-size: 0.8rem; color: var(--text-muted);"><?php echo htmlspecialchars($deal['author']); ?></div>
                        </td>
                        <td style="padding: 1.25rem;"><?php echo htmlspecialchars($deal['borrower_name']); ?></td>
                        <td style="padding: 1.25rem;">
                            <span style="color: <?php echo $statusInfo['text']; ?>; background: <?php echo $statusInfo['bg']; ?>; padding: 4px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: 600;">
                                <?php echo $statusInfo['label']; ?>
                            </span>
                        </td>
                        <td style="padding: 1.25rem; font-size: 0.85rem; color: var(--text-muted);">
                            <?php echo date('M d, Y', strtotime($deal['created_at'])); ?>
                        </td>
                        <td style="padding: 1.25rem; text-align: right;">
                            <button class="btn btn-outline btn-sm" onclick="window.location.href='deals.php'">View</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div style="background: var(--bg-card); border-radius: var(--radius-lg); border: 1px solid var(--border-color); padding: 3rem; text-align: center;">
            <i class='bx bx-book-open' style="font-size: 4rem; color: var(--text-muted); opacity: 0.5;"></i>
            <p style="color: var(--text-muted); margin-top: 1rem; font-size: 1.1rem;">No borrowing activity yet</p>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.5rem;">Start by adding books to your inventory</p>
        </div>
        <?php endif; ?>
    </main>
</div>

<!-- Reviews Modal -->
<div id="reviewsModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h2 style="font-weight: 800; display: flex; align-items: center; gap: 0.5rem; color: var(--text-main);">
                <i class='bx bxs-star' style="color: #fbbf24;"></i> Library Reviews
            </h2>
            <button class="modal-close" onclick="closeReviewsModal()">&times;</button>
        </div>
        <div class="modal-body">
            <?php if (empty($userReviews)): ?>
                <div style="text-align: center; padding: 3rem; color: var(--text-muted);">
                    <i class='bx bx-message-rounded-dots' style="font-size: 3rem; opacity: 0.3;"></i>
                    <p>No member reviews yet.</p>
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

<script>
    function openReviewsModal() {
        document.getElementById('reviewsModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeReviewsModal() {
        document.getElementById('reviewsModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    // Close on overlay click
        window.addEventListener('click', function(e) {
            const modal = document.getElementById('reviewsModal');
            if (e.target === modal) closeReviewsModal();
        });
    </script>

<style>
/* Modal Styles */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.5);
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
}

.review-item:last-child {
    border-bottom: none;
}

</style>

</body>
</html>

