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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Penalty Log | Admin</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>
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
                        <?php foreach($penalties as $p): ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 1rem;">
                                <strong><?php echo htmlspecialchars($p['firstname'] . ' ' . $p['lastname']); ?></strong>
                            </td>
                            <td style="padding: 1rem; text-transform: capitalize;"><?php echo $p['penalty_type']; ?></td>
                            <td style="padding: 1rem; text-align: center; color: #ef4444; font-weight: 700;">
                                -<?php echo $p['amount']; ?> Credits
                            </td>
                            <td style="padding: 1rem; color: var(--text-muted);"><?php echo htmlspecialchars($p['reason']); ?></td>
                            <td style="padding: 1rem; text-align: right; color: var(--text-muted);"><?php echo date('M d, Y', strtotime($p['created_at'])); ?></td>
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
</body>
</html>
