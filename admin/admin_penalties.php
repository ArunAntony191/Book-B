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
    
    // Get all penalties
    $stmt = $pdo->query("
        SELECT p.*, u.firstname, u.lastname, u.email 
        FROM penalties p
        JOIN users u ON p.user_id = u.id
        ORDER BY p.created_at DESC
        LIMIT 50
    ");
    $penalties = $stmt->fetchAll();
    
} catch (Exception $e) {
    $penalties = [];
}
?>
<div class="dashboard-wrapper">
        <?php include '../includes/dashboard_sidebar.php'; ?>
        <main class="main-content">
            <div class="section-header">
                <div>
                    <h1><i class='bx bx-error-circle'></i> Platform Penalties</h1>
                    <p>Review automatic and manual penalties applied to users</p>
                </div>
            </div>

            <div style="background: white; border-radius: var(--radius-lg); border: 1px solid var(--border-color); margin-top: 2rem;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8fafc; text-align: left;">
                            <th style="padding: 1rem;">User</th>
                            <th style="padding: 1rem;">Type</th>
                            <th style="padding: 1rem; text-align: center;">Penalty</th>
                            <th style="padding: 1rem;">Reason</th>
                            <th style="padding: 1rem; text-align: right;">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($penalties as $p): 
                            $typeColor = '#64748b'; // default
                            if ($p['penalty_type'] === 'late_return') $typeColor = '#f59e0b';
                            if ($p['penalty_type'] === 'report_ref') $typeColor = '#ef4444';
                            if ($p['penalty_type'] === 'manual_deduction') $typeColor = '#3b82f6';
                        ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 1rem;">
                                <strong><?php echo htmlspecialchars($p['firstname'] . ' ' . $p['lastname']); ?></strong><br>
                                <small style="color: var(--text-muted);"><?php echo htmlspecialchars($p['email']); ?></small>
                            </td>
                            <td style="padding: 1rem;">
                                <span style="padding: 0.2rem 0.6rem; background: <?php echo $typeColor; ?>15; color: <?php echo $typeColor; ?>; border-radius: 6px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">
                                    <?php echo str_replace('_', ' ', $p['penalty_type']); ?>
                                </span>
                            </td>
                            <td style="padding: 1rem; text-align: center; color: #ef4444; font-weight: 700;">
                                -<?php echo $p['amount']; ?> Credits
                            </td>
                            <td style="padding: 1rem; color: var(--text-main); font-size: 0.9rem;">
                                <?php echo htmlspecialchars($p['reason']); ?>
                            </td>
                            <td style="padding: 1rem; text-align: right; color: var(--text-muted); font-size: 0.85rem;">
                                <?php echo date('M d, Y', strtotime($p['created_at'])); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($penalties)): ?>
                        <tr>
                            <td colspan="5" style="padding: 3rem; text-align: center; color: var(--text-muted);">No penalties recorded yet.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
