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
    
    // Get all transactions filters
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
        WHERE 1=1
    ";
    
    if ($status !== 'all') {
        $baseQuery .= " AND t.status = " . $pdo->quote($status);
    }
    
    $baseQuery .= " ORDER BY t.created_at DESC";
    
    // Handle CSV Export - MUST BE BEFORE ANY HTML OUTPUT
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        $stmt = $pdo->query($baseQuery);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $filename = "transactions_export_" . date('Y-m-d') . ".csv";
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        // Clean output buffer if any
        if (ob_get_length()) ob_clean();
        
        // Add UTF-8 BOM for Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Header
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

    // Continue with normal page loading
    include '../includes/dashboard_header.php';
    
    // Status distribution for the chart
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM transactions GROUP BY status");
    $statusCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $query = $baseQuery . " LIMIT 50";
    $stmt = $pdo->query($query);
    $allTransactions = $stmt->fetchAll();
    
} catch (Exception $e) {
    $allTransactions = [];
    $statusCounts = [];
    if (!isset($pdo)) {
        include '../includes/dashboard_header.php';
    }
}
?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="dashboard-wrapper">
        <?php include '../includes/dashboard_sidebar.php'; ?>
        <main class="main-content">
            <div class="section-header">
                <div>
                    <h1><i class='bx bx-transfer-alt'></i> All Transactions</h1>
                    <p>Monitor all book borrows and sales on the platform</p>
                </div>
                <div class="header-actions no-print" style="display: flex; gap: 1rem;">
                    <button onclick="window.print()" class="btn btn-outline" style="display: flex; align-items: center; gap: 0.5rem;">
                        <i class='bx bx-printer'></i> Print Report
                    </button>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.5rem; background: #10b981; border-color: #10b981;">
                        <i class='bx bx-file'></i> Export to Excel
                    </a>
                </div>
            </div>

            <!-- Stats Charts -->
            <div class="no-print" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                <!-- Bar Chart -->
                <div style="background: white; padding: 1.5rem; border-radius: var(--radius-lg); border: 1px solid var(--border-color);">
                    <h3 style="font-size: 1rem; font-weight: 700; margin-bottom: 1rem; color: var(--text-main);">
                        <i class='bx bx-bar-chart-alt-2'></i> Status Distribution
                    </h3>
                    <div style="height: 250px; width: 100%;">
                        <canvas id="statusBarChart"></canvas>
                    </div>
                </div>

                <!-- Pie Chart -->
                <div style="background: white; padding: 1.5rem; border-radius: var(--radius-lg); border: 1px solid var(--border-color);">
                    <h3 style="font-size: 1rem; font-weight: 700; margin-bottom: 1rem; color: var(--text-main);">
                        <i class='bx bx-pie-chart-alt'></i> Proportion Overview
                    </h3>
                    <div style="height: 250px; width: 100%;">
                        <canvas id="statusPieChart"></canvas>
                    </div>
                </div>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const statusData = <?php echo json_encode($statusCounts); ?>;
                    
                    const labels = statusData.map(d => d.status.charAt(0).toUpperCase() + d.status.slice(1));
                    const counts = statusData.map(d => d.count);
                    
                    const statusColors = {
                        'Pending': '#f59e0b',
                        'Approved': '#3b82f6',
                        'Active': '#10b981',
                        'Returning': '#8b5cf6',
                        'Returned': '#059669',
                        'Cancelled': '#ef4444',
                        'Delivered': '#0d9488',
                        'Out_for_delivery': '#6366f1'
                    };

                    const backgroundColors = labels.map(label => statusColors[label] || '#6b7280');

                    // Bar Chart
                    const barCtx = document.getElementById('statusBarChart').getContext('2d');
                    new Chart(barCtx, {
                        type: 'bar',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Transactions',
                                data: counts,
                                backgroundColor: backgroundColors,
                                borderRadius: 6,
                                barThickness: 30
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: { color: '#f1f5f9' },
                                    ticks: { 
                                        stepSize: 10,
                                        precision: 0
                                    }
                                },
                                x: {
                                    grid: { display: false }
                                }
                            }
                        }
                    });

                    // Pie Chart
                    const pieCtx = document.getElementById('statusPieChart').getContext('2d');
                    new Chart(pieCtx, {
                        type: 'pie',
                        data: {
                            labels: labels,
                            datasets: [{
                                data: counts,
                                backgroundColor: backgroundColors,
                                borderWeight: 0
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'right',
                                    labels: {
                                        usePointStyle: true,
                                        padding: 15,
                                        font: { 
                                            size: 13,
                                            weight: 'bold',
                                            family: "'Inter', sans-serif"
                                        },
                                        boxWidth: 10
                                    }
                                }
                            }
                        }
                    });
                });
            </script>

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
                        <?php foreach($allTransactions as $t): ?>
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
                    </tbody>
                </table>
            </div>
        </main>
    </div>
