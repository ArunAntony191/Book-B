<?php 
require_once '../includes/db_helper.php';
require_once '../paths.php';
include '../includes/dashboard_header.php'; 

// Ensure only admin can see this
if ($user['role'] !== 'admin') {
    header("Location: dashboard_user.php");
    exit();
}

// Get platform statistics
try {
    $pdo = getDBConnection();
    
    // Total users
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $totalUsers = $stmt->fetch()['total'];
    
    // Total books
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM books");
    $totalBooks = $stmt->fetch()['total'];
    
    // Total listings
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM listings");
    $totalListings = $stmt->fetch()['total'];
    
    // Active transactions
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM transactions WHERE status IN ('active', 'approved', 'returned')");
    $activeTransactions = $stmt->fetch()['total'];
    
    // Total credits in circulation
    $stmt = $pdo->query("SELECT SUM(credits) as total FROM users");
    $totalCredits = $stmt->fetch()['total'] ?? 0;
    
    // Recent joins (last 7 days)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $recentJoins = $stmt->fetch()['total'];
    
    // Low trust users (< 30)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE trust_score < 30 AND role != 'admin'");
    $lowTrustUsers = $stmt->fetch()['total'];
    
    // Overdue transactions
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM transactions WHERE status IN ('active', 'approved') AND due_date < CURDATE()");
    $overdueTransactions = $stmt->fetch()['total'];
    
    // Recent users
    $stmt = $pdo->query("SELECT id, firstname, lastname, email, role, credits, trust_score, created_at FROM users ORDER BY created_at DESC LIMIT 10");
    $recentUsers = $stmt->fetchAll();
    
    // User role distribution
    $stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
    $roleDistribution = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    
} catch (Exception $e) {
    error_log("Admin dashboard error: " . $e->getMessage());
    $totalUsers = $totalBooks = $totalListings = $activeTransactions = 0;
    $totalCredits = $recentJoins = $lowTrustUsers = $overdueTransactions = 0;
    $recentUsers = [];
    $roleDistribution = [];
    $pendingSupport = 0;
    $recentSupport = [];
}
?>

<div class="dashboard-wrapper">
    <?php include '../includes/dashboard_sidebar.php'; ?>

    <main class="main-content">
        <div class="section-header">
            <div>
                <h1>System Administration 🛡️</h1>
                <p>Monitor platform growth, manage users, and oversee community operations.</p>
            </div>
            <div style="display: flex; gap: 0.75rem;">
                <button class="btn btn-outline" onclick="window.location.href='admin_users.php'">
                    <i class='bx bx-user-plus'></i> Manage Users
                </button>
                <button class="btn btn-primary" onclick="window.location.href='admin_reports.php'">
                    <i class='bx bx-flag'></i> Reports
                </button>
            </div>
        </div>

        <!-- Platform Stats -->
        <div class="widgets-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
            <div class="widget-card" style="background: linear-gradient(135deg, #3b82f615 0%, #3b82f605 100%); border: 2px solid #3b82f6; cursor: pointer;" onclick="window.location.href='admin_users.php'">
                <div class="widget-title" style="justify-content: center;">
                    <span><i class='bx bx-user'></i> Total Users</span>
                </div>
                <div style="font-size: 2.8rem; font-weight: 900; color: #3b82f6; text-align: center; margin: 1rem 0;">
                    <?php echo number_format($totalUsers); ?>
                </div>
                <div style="text-align: center; color: var(--success); font-weight: 600; font-size: 0.85rem;">
                    +<?php echo $recentJoins; ?> this week
                </div>
            </div>

            <div class="widget-card" style="background: linear-gradient(135deg, #10b98115 0%, #10b98105 100%); border: 2px solid #10b981; cursor: pointer;" onclick="window.location.href='admin_listings.php'">
                <div class="widget-title" style="justify-content: center;">
                    <span><i class='bx bx-book'></i> Total Books</span>
                </div>
                <div style="font-size: 2.8rem; font-weight: 900; color: #10b981; text-align: center; margin: 1rem 0;">
                    <?php echo number_format($totalBooks); ?>
                </div>
                <div style="text-align: center; color: var(--text-muted); font-size: 0.85rem;">
                    <?php echo number_format($totalListings); ?> active listings
                </div>
            </div>

            <div class="widget-card" style="background: linear-gradient(135deg, #f59e0b15 0%, #f59e0b05 100%); border: 2px solid #f59e0b; cursor: pointer;" onclick="window.location.href='active_deals.php'">
                <div class="widget-title" style="justify-content: center;">
                    <span><i class='bx bx-transfer'></i> Active Deals</span>
                </div>
                <div style="font-size: 2.8rem; font-weight: 900; color: #f59e0b; text-align: center; margin: 1rem 0;">
                    <?php echo number_format($activeTransactions); ?>
                </div>
                <div style="text-align: center; color: var(--text-muted); font-size: 0.85rem;">
                    Currently active
                </div>
            </div>

            <div class="widget-card gradient-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; cursor: pointer;" onclick="window.location.href='admin_credits.php'">
                <div class="widget-title" style="justify-content: center; color: rgba(255,255,255,0.9);">
                    <span><i class='bx bx-wallet'></i> Credits</span>
                </div>
                <div style="font-size: 2.8rem; font-weight: 900; text-align: center; margin: 1rem 0;">
                    <?php echo number_format($totalCredits); ?>
                </div>
                <div style="text-align: center; opacity: 0.9; font-size: 0.85rem;">
                    In circulation
                </div>
            </div>

            <?php if ($lowTrustUsers > 0): ?>
            <div class="widget-card" style="background: linear-gradient(135deg, #ef444415 0%, #ef444405 100%); border: 2px solid #ef4444; cursor: pointer;" onclick="window.location.href='admin_users.php?filter=low_trust'">
                <div class="widget-title" style="justify-content: center;">
                    <span><i class='bx bx-error-circle'></i> Low Trust</span>
                </div>
                <div style="font-size: 2.8rem; font-weight: 900; color: #ef4444; text-align: center; margin: 1rem 0;">
                    <?php echo $lowTrustUsers; ?>
                </div>
                <div style="text-align: center; color: var(--text-muted); font-size: 0.85rem;">
                    Users need attention
                </div>
            </div>
            <?php endif; ?>

            <?php if ($overdueTransactions > 0): ?>
            <div class="widget-card" style="background: linear-gradient(135deg, #dc262615 0%, #dc262605 100%); border: 2px solid #dc2626; cursor: pointer;" onclick="window.location.href='overdue_transactions.php'">
                <div class="widget-title" style="justify-content: center;">
                    <span><i class='bx bx-time-five'></i> Overdue</span>
                </div>
                <div style="font-size: 2.8rem; font-weight: 900; color: #dc2626; text-align: center; margin: 1rem 0;">
                    <?php echo $overdueTransactions; ?>
                </div>
                <div style="text-align: center; color: var(--text-muted); font-size: 0.85rem;">
                    Late returns
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- User Role Distribution -->
        <div style="background: var(--section-bg); padding: 2rem; border-radius: var(--radius-lg); margin-bottom: 2rem; border: 1px solid var(--border-color);">
            <h3 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem; color: var(--text-main);">
                <i class='bx bx-pie-chart-alt-2' style="color: var(--primary);"></i> User Distribution
            </h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1.5rem;">
                <?php 
                $roleDisplay = [
                    'user' => 'Users',
                    'library' => 'Libraries',
                    'bookstore' => 'Bookstores',
                    'delivery_agent' => 'Delivery Agents'
                ];
                
                foreach ($roleDistribution as $role => $count): 
                    if ($role === 'admin') continue;
                    
                    $roleColors = [
                        'user' => ['color' => '#3b82f6', 'icon' => 'bx-user'],
                        'library' => ['color' => '#10b981', 'icon' => 'bx-library'],
                        'bookstore' => ['color' => '#f59e0b', 'icon' => 'bx-store'],
                        'delivery_agent' => ['color' => '#8b5cf6', 'icon' => 'bx-truck']
                    ];
                    $info = $roleColors[$role] ?? ['color' => '#6b7280', 'icon' => 'bx-user'];
                    $displayName = $roleDisplay[$role] ?? ucfirst($role) . 's';
                ?>
                <div style="text-align: center; padding: 1rem; background: var(--bg-card); border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                    <i class='bx <?php echo $info['icon']; ?>' style="font-size: 2rem; color: <?php echo $info['color']; ?>; margin-bottom: 0.5rem;"></i>
                    <div style="font-size: 1.8rem; font-weight: 800; color: <?php echo $info['color']; ?>;"><?php echo $count; ?></div>
                    <div style="font-size: 0.85rem; color: var(--text-muted);"><?php echo $displayName; ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div style="background: var(--bg-card); padding: 2rem; border-radius: var(--radius-lg); margin-bottom: 2rem; border: 1px solid var(--border-color);">
            <h3 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem; color: var(--text-main);">
                <i class='bx bx-zap' style="color: #f59e0b;"></i> Quick Actions
            </h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
                <button class="btn btn-outline" onclick="window.location.href='admin_users.php'" style="justify-content: flex-start; padding: 1rem;">
                    <i class='bx bx-user-circle'></i> Manage All Users
                </button>
                <button class="btn btn-outline" onclick="window.location.href='admin_credits.php'" style="justify-content: flex-start; padding: 1rem;">
                    <i class='bx bx-wallet'></i> Credit Management
                </button>
                <button class="btn btn-outline" onclick="window.location.href='admin_penalties.php'" style="justify-content: flex-start; padding: 1rem;">
                    <i class='bx bx-error-circle'></i> Review Penalties
                </button>
                <button class="btn btn-outline" onclick="window.location.href='admin_transactions.php'" style="justify-content: flex-start; padding: 1rem;">
                    <i class='bx bx-transfer-alt'></i> All Transactions
                </button>
            </div>
        </div>

        <!-- Recent Users -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; color: var(--text-main);">
                <i class='bx bx-user-plus' style="color: var(--primary);"></i>
                Recent User Registrations
            </h2>
            <a href="admin_users.php" class="btn btn-outline" style="font-weight: 600;">
                View All <i class='bx bx-right-arrow-alt'></i>
            </a>
        </div>

        <?php if (count($recentUsers) > 0): ?>
        <div style="background: var(--bg-card); border-radius: var(--radius-lg); border: 1px solid var(--border-color); overflow: hidden; box-shadow: var(--shadow-md);">
            <table style="width: 100%; border-collapse: collapse;">
                <thead style="background: var(--section-bg); border-bottom: 1px solid var(--border-color);">
                    <tr>
                        <th style="padding: 1rem; text-align: left; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">User</th>
                        <th style="padding: 1rem; text-align: left; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Role</th>
                        <th style="padding: 1rem; text-align: center; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Credits</th>
                        <th style="padding: 1rem; text-align: center; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Trust</th>
                        <th style="padding: 1rem; text-align: right; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Joined</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentUsers as $u): 
                        $trustRating = getTrustScoreRating($u['trust_score']);
                    ?>
                    <tr style="border-bottom: 1px solid var(--border-color); transition: background 0.2s;" onmouseover="this.style.background='var(--section-bg)'" onmouseout="this.style.background='transparent'">
                        <td style="padding: 1.25rem;">
                            <div style="font-weight: 700; color: var(--text-main);"><?php echo htmlspecialchars($u['firstname'] . ' ' . $u['lastname']); ?></div>
                            <div style="font-size: 0.85rem; color: var(--text-muted);"><?php echo htmlspecialchars($u['email']); ?></div>
                        </td>
                        <td style="padding: 1.25rem;">
                            <span style="padding: 0.3rem 0.8rem; background: var(--section-bg); color: var(--text-main); border-radius: 12px; font-size: 0.8rem; font-weight: 600; text-transform: capitalize;">
                                <?php echo $u['role']; ?>
                            </span>
                        </td>
                        <td style="padding: 1.25rem; text-align: center; font-weight: 600; color: var(--text-main);">
                            <?php echo $u['credits']; ?>
                        </td>
                        <td style="padding: 1.25rem; text-align: center;">
                            <span style="padding: 0.3rem 0.8rem; background: <?php echo $trustRating['color']; ?>15; color: <?php echo $trustRating['color']; ?>; border-radius: 12px; font-size: 0.8rem; font-weight: 600;">
                                <?php echo $u['trust_score']; ?>
                            </span>
                        </td>
                        <td style="padding: 1.25rem; text-align: right; font-size: 0.85rem; color: var(--text-muted);">
                            <?php echo date('M d, Y', strtotime($u['created_at'])); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div style="background: var(--bg-card); border-radius: var(--radius-lg); border: 1px solid var(--border-color); padding: 3rem; text-align: center;">
            <i class='bx bx-user-plus' style="font-size: 4rem; color: var(--text-muted); opacity: 0.3;"></i>
            <p style="color: var(--text-muted); margin-top: 1rem;">No recent user activity</p>
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
