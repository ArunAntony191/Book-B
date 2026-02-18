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
    
    // Get all transactions
    $status = $_GET['status'] ?? 'all';
    $query = "
        SELECT t.*, b.title, l.firstname as lender_name, b_u.firstname as borrower_name 
        FROM transactions t
        JOIN listings list ON t.listing_id = list.id
        JOIN books b ON list.book_id = b.id
        JOIN users l ON t.lender_id = l.id
        JOIN users b_u ON t.borrower_id = b_u.id
        WHERE 1=1
    ";
    
    if ($status !== 'all') {
        $query .= " AND t.status = " . $pdo->quote($status);
    }
    
    $query .= " ORDER BY t.created_at DESC LIMIT 50";
    $stmt = $pdo->query($query);
    $allTransactions = $stmt->fetchAll();
    
} catch (Exception $e) {
    $allTransactions = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Transaction History | Admin</title>
    <link rel="stylesheet" href="assets/css/style.css?v=1.2">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include '../includes/dashboard_sidebar.php'; ?>
        <main class="main-content">
            <div class="section-header">
                <div>
                    <h1><i class='bx bx-transfer-alt'></i> All Transactions</h1>
                    <p>Monitor all book borrows and sales on the platform</p>
                </div>
            </div>

            <div style="background: white; border-radius: var(--radius-lg); border: 1px solid var(--border-color); margin-top: 2rem;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8fafc; text-align: left;">
                            <th style="padding: 1rem;">Book</th>
                            <th style="padding: 1rem;">Lender</th>
                            <th style="padding: 1rem;">Borrower</th>
                            <th style="padding: 1rem;">Status</th>
                            <th style="padding: 1rem; text-align: right;">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($allTransactions as $t): ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 1rem;"><strong><?php echo htmlspecialchars($t['title']); ?></strong></td>
                            <td style="padding: 1rem;"><?php echo htmlspecialchars($t['lender_name']); ?></td>
                            <td style="padding: 1rem;"><?php echo htmlspecialchars($t['borrower_name']); ?></td>
                            <td style="padding: 1rem;"><span class="badge" style="text-transform: capitalize;"><?php echo $t['status']; ?></span></td>
                            <td style="padding: 1rem; text-align: right; color: var(--text-muted);"><?php echo date('M d, Y', strtotime($t['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
