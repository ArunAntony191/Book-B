<?php
require_once '../includes/db_helper.php';
require_once '../paths.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in or not admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../pages/login.php");
    exit();
}

try {
    $pdo = getDBConnection();
    
    // Get active transactions
    $status = $_GET['status'] ?? 'all';
    $baseQuery = "
        SELECT t.*, b.title, l.firstname as lender_name, b_u.firstname as borrower_name, 
               u_agent.firstname as agent_name
        FROM transactions t
        JOIN listings list ON t.listing_id = list.id
        JOIN books b ON list.book_id = b.id
        JOIN users l ON t.lender_id = l.id
        JOIN users b_u ON t.borrower_id = b_u.id
        LEFT JOIN users u_agent ON t.delivery_agent_id = u_agent.id
        WHERE t.status IN ('active', 'approved', 'returned', 'returning', 'out_for_delivery')
    ";
    
    if ($status !== 'all') {
        $baseQuery .= " AND t.status = " . $pdo->quote($status);
    }
    
    $baseQuery .= " ORDER BY t.created_at DESC";
    
    // Handle CSV Export
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        $stmt = $pdo->query($baseQuery);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $filename = "active_deals_export_" . date('Y-m-d') . ".csv";
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        if (ob_get_length()) ob_clean();
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        fputcsv($output, ['ID', 'Book Title', 'Lender', 'Borrower', 'Agent', 'Status', 'Date']);
        
        foreach ($data as $row) {
            fputcsv($output, [
                $row['id'],
                $row['title'],
                $row['lender_name'],
                $row['borrower_name'],
                $row['agent_name'] ?? 'Self/None',
                $row['status'],
                date('d/m/Y H:i', strtotime($row['created_at']))
            ]);
        }
        fclose($output);
        exit();
    }

    // Normal Page View
    include '../includes/dashboard_header.php';
    
    $query = $baseQuery . " LIMIT 50";
    $stmt = $pdo->query($query);
    $deals = $stmt->fetchAll();
    
} catch (Exception $e) {
    $deals = [];
    if (!isset($pdo)) {
        include '../includes/dashboard_header.php';
    }
}
?>

<div class="dashboard-wrapper">
    <?php include '../includes/dashboard_sidebar.php'; ?>
    <main class="main-content">
        <div class="section-header">
            <div>
                <h1><i class='bx bx-transfer'></i> Active Deals</h1>
                <p>Overview of all ongoing book transactions</p>
            </div>
            <div class="header-actions no-print" style="display: flex; gap: 1rem;">
                <button onclick="window.print()" class="btn btn-outline" style="display: flex; align-items: center; gap: 0.5rem;">
                    <i class='bx bx-printer'></i> Print
                </button>
                <a href="?export=csv" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.5rem; background: #10b981; border-color: #10b981;">
                    <i class='bx bx-file'></i> Export
                </a>
            </div>
        </div>

        <style>
            @media print {
                .sidebar, .navbar, .no-print, .header-actions {
                    display: none !important;
                }
                .main-content {
                    margin-left: 0 !important;
                    padding: 0 !important;
                    width: 100% !important;
                }
                .dashboard-wrapper {
                    display: block !important;
                }
                table {
                    width: 100% !important;
                    border: 1px solid #eee !important;
                }
            }
        </style>

        <div style="background: white; border-radius: var(--radius-lg); border: 1px solid var(--border-color); margin-top: 2rem;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8fafc; text-align: left;">
                        <th style="padding: 1rem;">Book</th>
                        <th style="padding: 1rem;">Lender</th>
                        <th style="padding: 1rem;">Borrower</th>
                        <th style="padding: 1rem;">Agent</th>
                        <th style="padding: 1rem;">Status</th>
                        <th style="padding: 1rem; text-align: right;">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($deals as $t): ?>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 1rem;"><strong><?php echo htmlspecialchars($t['title']); ?></strong></td>
                        <td style="padding: 1rem;"><?php echo htmlspecialchars($t['lender_name']); ?></td>
                        <td style="padding: 1rem;"><?php echo htmlspecialchars($t['borrower_name']); ?></td>
                        <td style="padding: 1rem;">
                            <?php echo $t['agent_name'] ? htmlspecialchars($t['agent_name']) : '<span style="color: var(--text-muted); font-size: 0.8rem;">Self/None</span>'; ?>
                        </td>
                        <td style="padding: 1rem;"><span class="badge" style="text-transform: capitalize;"><?php echo $t['status']; ?></span></td>
                        <td style="padding: 1rem; text-align: right; color: var(--text-muted);"><?php echo date('M d, Y', strtotime($t['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($deals)): ?>
                    <tr>
                        <td colspan="6" style="padding: 3rem; text-align: center; color: var(--text-muted);">No active deals found.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
