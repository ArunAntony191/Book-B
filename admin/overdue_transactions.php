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
    
    // Get overdue transactions
    $baseQuery = "
        SELECT t.*, b.title, l.firstname as lender_name, b_u.firstname as borrower_name, 
               u_agent.firstname as agent_name,
               DATEDIFF(CURDATE(), t.due_date) as calculated_days_late
        FROM transactions t
        JOIN listings list ON t.listing_id = list.id
        JOIN books b ON list.book_id = b.id
        JOIN users l ON t.lender_id = l.id
        JOIN users b_u ON t.borrower_id = b_u.id
        LEFT JOIN users u_agent ON t.delivery_agent_id = u_agent.id
        WHERE t.status IN ('active', 'approved', 'delivered') 
        AND t.due_date < CURDATE()
    ";
    
    $baseQuery .= " ORDER BY calculated_days_late DESC";
    
    // Handle CSV Export
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        $stmt = $pdo->query($baseQuery);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $filename = "overdue_transactions_" . date('Y-m-d') . ".csv";
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        if (ob_get_length()) ob_clean();
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        fputcsv($output, ['ID', 'Book Title', 'Lender', 'Borrower', 'Due Date', 'Days Late', 'Status']);
        
        foreach ($data as $row) {
            fputcsv($output, [
                $row['id'],
                $row['title'],
                $row['lender_name'],
                $row['borrower_name'],
                $row['due_date'],
                $row['days_overdue'],
                $row['status']
            ]);
        }
        fclose($output);
        exit();
    }

    // Normal Page View
    include '../includes/dashboard_header.php';
    
    $stmt = $pdo->query($baseQuery);
    $overdue = $stmt->fetchAll();
    
} catch (Exception $e) {
    $overdue = [];
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
                <h1><i class='bx bx-time-five'></i> Overdue Transactions</h1>
                <p>Tracking books that haven't been returned on time</p>
            </div>
            <div class="header-actions no-print" style="display: flex; gap: 1rem;">
                <button onclick="window.print()" class="btn btn-outline" style="display: flex; align-items: center; gap: 0.5rem;">
                    <i class='bx bx-printer'></i> Print
                </button>
                <a href="?export=csv" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.5rem; background: #dc2626; border-color: #dc2626;">
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
            .days-badge {
                background: #fef2f2;
                color: #dc2626;
                padding: 4px 8px;
                border-radius: 6px;
                font-weight: 700;
                font-size: 0.8rem;
                display: inline-flex;
                align-items: center;
                gap: 0.25rem;
                border: 1px solid #fee2e2;
            }
        </style>

        <div style="background: white; border-radius: var(--radius-lg); border: 1px solid var(--border-color); margin-top: 2rem;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8fafc; text-align: left;">
                        <th style="padding: 1rem;">Book</th>
                        <th style="padding: 1rem;">Borrower / Lender</th>
                        <th style="padding: 1rem; text-align: center;">Due Date</th>
                        <th style="padding: 1rem; text-align: center;">Days Late</th>
                        <th style="padding: 1rem;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($overdue as $t): ?>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 1rem;">
                            <strong><?php echo htmlspecialchars($t['title']); ?></strong>
                        </td>
                        <td style="padding: 1rem;">
                            <div style="font-weight: 600;"><?php echo htmlspecialchars($t['borrower_name']); ?> (B)</div>
                            <div style="color: var(--text-muted); font-size: 0.85rem;"><?php echo htmlspecialchars($t['lender_name']); ?> (L)</div>
                        </td>
                        <td style="padding: 1rem; text-align: center; color: #dc2626; font-weight: 600;">
                            <?php echo date('M d, Y', strtotime($t['due_date'])); ?>
                        </td>
                        <td style="padding: 1rem; text-align: center;">
                            <span class="days-badge">
                                <i class='bx bx-trending-up'></i> <?php echo max(0, (int)($t['calculated_days_late'] ?? 0)); ?> days
                            </span>
                        </td>
                        <td style="padding: 1rem;"><span class="badge" style="text-transform: capitalize;"><?php echo $t['status']; ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($overdue)): ?>
                    <tr>
                        <td colspan="5" style="padding: 3rem; text-align: center; color: var(--text-muted);">No overdue transactions. Great job!</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
