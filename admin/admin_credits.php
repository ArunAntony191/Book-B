<?php
require_once '../includes/db_helper.php';
require_once '../paths.php';
include '../includes/dashboard_header.php';

if ($user['role'] !== 'admin') {
    header("Location: dashboard_user.php");
    exit();
}

try {
    $pdo = getDBConnection();
    
    // Get total credits in circulation
    $stmt = $pdo->query("SELECT SUM(credits) as total FROM users");
    $totalCredits = $stmt->fetch()['total'] ?? 0;
    
    // Get recent transactions
    $stmt = $pdo->query("
        SELECT ct.*, u.firstname, u.lastname, u.email 
        FROM credit_transactions ct
        JOIN users u ON ct.user_id = u.id
        ORDER BY ct.created_at DESC
        LIMIT 20
    ");
    $transactions = $stmt->fetchAll();
    
} catch (Exception $e) {
    $transactions = [];
    $totalCredits = 0;
}
?>
<div class="dashboard-wrapper">
        <?php include '../includes/dashboard_sidebar.php'; ?>
        <main class="main-content">
            <div class="section-header">
                <div>
                    <h1><i class='bx bx-wallet'></i> Credit Management</h1>
                    <p>Monitor and manage platform economy</p>
                </div>
                <div class="widget-card" style="padding: 1rem 2rem; border: 2px solid var(--primary);">
                    <div style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">CIRCULATION</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: var(--primary);"><?php echo number_format($totalCredits); ?> Credits</div>
                </div>
            </div>

            <div style="background: white; border-radius: var(--radius-lg); border: 1px solid var(--border-color); margin-top: 2rem;">
                <div style="padding: 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="font-size: 1.1rem; font-weight: 700;">Recent Credit Activity</h3>
                </div>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8fafc; text-align: left;">
                            <th style="padding: 1rem;">User</th>
                            <th style="padding: 1rem;">Type</th>
                            <th style="padding: 1rem; text-align: center;">Amount</th>
                            <th style="padding: 1rem;">Description</th>
                            <th style="padding: 1rem; text-align: right;">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($transactions as $t): ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 1rem;">
                                <strong><?php echo htmlspecialchars($t['firstname'] . ' ' . $t['lastname']); ?></strong><br>
                                <small><?php echo htmlspecialchars($t['email']); ?></small>
                            </td>
                            <td style="padding: 1rem;"><span style="text-transform: capitalize;"><?php echo str_replace('_', ' ', $t['type']); ?></span></td>
                            <td style="padding: 1rem; text-align: center; color: <?php echo $t['amount'] > 0 ? '#10b981' : '#ef4444'; ?>; font-weight: 700;">
                                <?php echo ($t['amount'] > 0 ? '+' : '') . $t['amount']; ?>
                            </td>
                            <td style="padding: 1rem; color: var(--text-muted);"><?php echo htmlspecialchars($t['description']); ?></td>
                            <td style="padding: 1rem; text-align: right; color: var(--text-muted);"><?php echo date('M d, H:i', strtotime($t['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
