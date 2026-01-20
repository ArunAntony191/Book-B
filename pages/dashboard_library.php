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
?>

<div class="dashboard-wrapper">
    <?php include '../includes/dashboard_sidebar.php'; ?>

    <main class="main-content">
        <div class="section-header">
            <div>
                <h1>Library Management 🏛️</h1>
                <p>Manage your collection, track borrowings, and serve your community.</p>
            </div>
            <a href="add_listing.php" class="btn btn-primary">
                <i class='bx bx-plus-circle'></i> Add Books
            </a>
        </div>

        <!-- Enhanced Library Stats -->
        <div class="widgets-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
            <!-- Total Inventory -->
            <div class="widget-card" style="background: linear-gradient(135deg, #3b82f615 0%, #3b82f605 100%); border: 2px solid #3b82f6;">
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
            <div class="widget-card" style="background: linear-gradient(135deg, #10b98115 0%, #10b98105 100%); border: 2px solid #10b981;">
                <div class="widget-title" style="justify-content: center;">
                    <span><i class='bx bx-export'></i> Currently Lent</span>
                </div>
                <div style="font-size: 2.8rem; font-weight: 900; color: #10b981; text-align: center; margin: 1rem 0;">
                    <?php echo $storeStats['currently_lent'] ?? 0; ?>
                </div>
                <div style="text-align: center; color: var(--text-muted); font-size: 0.8rem;">Books with members</div>
            </div>

            <!-- Credits Earned -->
            <div class="widget-card gradient-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none;">
                <div class="widget-title" style="justify-content: center; color: rgba(255,255,255,0.9);">
                    <span><i class='bx bx-wallet'></i> Credits Earned</span>
                </div>
                <div style="font-size: 2.8rem; font-weight: 900; text-align: center; margin: 1rem 0;">
                    <?php echo $stats['credits'] ?? 0; ?>
                </div>
                <div style="text-align: center; opacity: 0.9; font-size: 0.8rem;">From lending services</div>
            </div>

            <!-- Low Stock Alert -->
            <div class="widget-card" style="background: linear-gradient(135deg, #f59e0b15 0%, #f59e0b05 100%); border: 2px solid #f59e0b;">
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
            <div class="widget-card" style="background: linear-gradient(135deg, <?php echo getTrustScoreRating($stats['trust_score'] ?? 50)['color']; ?>15 0%, <?php echo getTrustScoreRating($stats['trust_score'] ?? 50)['color']; ?>05 100%); border: 2px solid <?php echo getTrustScoreRating($stats['trust_score'] ?? 50)['color']; ?>;">
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
        </div>

        <!-- Quick Actions -->
        <div style="background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); padding: 1.5rem; border-radius: var(--radius-lg); margin-bottom: 2rem; border: 1px solid var(--border-color);">
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
                <button class="btn btn-outline" onclick="window.location.href='deals.php'" style="justify-content: center; padding: 0.75rem;">
                    <i class='bx bx-list-ul'></i> View All Borrows
                </button>
                <button class="btn btn-primary" onclick="window.location.href='deals.php?filter=requested'" style="justify-content: center; padding: 0.75rem;">
                    <i class='bx bx-time-five'></i> Pending Requests (<?php echo $stats['pending_requests'] ?? 0; ?>)
                </button>
            </div>
        </div>

        <!-- Recent Activity -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem;">
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
        <div style="background: white; border-radius: var(--radius-lg); border: 1px solid var(--border-color); overflow: hidden; box-shadow: var(--shadow-sm);">
            <table style="width: 100%; border-collapse: collapse;">
                <thead style="background: #f8fafc; border-bottom: 1px solid var(--border-color);">
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
                            'requested' => ['bg' => '#fef3c7', 'text' => '#f59e0b', 'label' => 'Pending'],
                            'approved' => ['bg' => '#dbeafe', 'text' => '#3b82f6', 'label' => 'Approved'],
                            'active' => ['bg' => '#d1fae5', 'text' => '#10b981', 'label' => 'Active'],
                            'returned' => ['bg' => '#e0e7ff', 'text' => '#6366f1', 'label' => 'Returned'],
                            'cancelled' => ['bg' => '#fee2e2', 'text' => '#ef4444', 'label' => 'Cancelled']
                        ];
                        $statusInfo = $statusColors[$deal['status']] ?? ['bg' => '#f3f4f6', 'text' => '#6b7280', 'label' => ucfirst($deal['status'])];
                    ?>
                    <tr style="border-bottom: 1px solid var(--border-color); transition: background 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='white'">
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
        <div style="background: white; border-radius: var(--radius-lg); border: 1px solid var(--border-color); padding: 3rem; text-align: center;">
            <i class='bx bx-book-open' style="font-size: 4rem; color: var(--text-muted); opacity: 0.5;"></i>
            <p style="color: var(--text-muted); margin-top: 1rem; font-size: 1.1rem;">No borrowing activity yet</p>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.5rem;">Start by adding books to your inventory</p>
        </div>
        <?php endif; ?>
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
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
}
</style>

</body>
</html>

