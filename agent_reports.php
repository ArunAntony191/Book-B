<?php
require_once 'includes/db_helper.php';
require_once 'paths.php';
session_start();

$userId = $_SESSION['user_id'] ?? 0;
$user_role = $_SESSION['role'] ?? 'user';

if (!$userId || ($user_role !== 'delivery_agent' && $user_role !== 'admin')) {
    header("Location: login.php");
    exit();
}

$startDate = $_GET['start_date'] ?? date('Y-m-01'); // Default to start of current month
$endDate = $_GET['end_date'] ?? date('Y-m-d');

$report = getAgentReportStats($userId, $startDate, $endDate);
$agent = getUserById($userId);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Earnings Report | BOOK-B Agent</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        .report-header {
            display: flex; justify-content: space-between; align-items: flex-end;
            margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color);
        }
        .filter-form {
            background: white; padding: 1.5rem; border-radius: var(--radius-lg);
            border: 1px solid var(--border-color); display: flex; gap: 1rem; align-items: flex-end;
            margin-bottom: 2rem;
        }
        .form-group { display: flex; flex-direction: column; gap: 0.5rem; }
        .form-group label { font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; }
        .form-control { padding: 0.6rem 1rem; border: 1px solid var(--border-color); border-radius: 8px; font-family: inherit; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem; }
        .stat-card {
            background: white; padding: 1.5rem; border-radius: var(--radius-lg);
            border: 1px solid var(--border-color); text-align: center;
        }
        .stat-value { font-size: 2rem; font-weight: 800; margin: 0.5rem 0; color: var(--text-main); }
        .stat-label { font-size: 0.85rem; color: var(--text-muted); font-weight: 600; }
        
        .report-table { width: 100%; border-collapse: collapse; background: white; border-radius: var(--radius-lg); overflow: hidden; border: 1px solid var(--border-color); }
        .report-table th { background: #f8fafc; padding: 1rem; text-align: left; font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; border-bottom: 1px solid var(--border-color); }
        .report-table td { padding: 1.2rem 1rem; border-bottom: 1px solid var(--border-color); font-size: 0.95rem; }
        
        .print-btn { background: #64748b; color: white; display: flex; align-items: center; gap: 0.5rem; border: none; padding: 0.6rem 1.2rem; border-radius: 8px; cursor: pointer; font-weight: 600; }
        
        @media print {
            .sidebar, .filter-form, .print-btn, .dashboard-sidebar { display: none !important; }
            .main-content { margin-left: 0 !important; width: 100% !important; padding: 0 !important; }
            .report-card { border: none !important; box-shadow: none !important; }
            body { background: white !important; }
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/dashboard_sidebar.php'; ?>
        
        <main class="main-content">
            <div class="report-header">
                <div>
                    <h1><i class='bx bx-file'></i> Performance Report</h1>
                    <p>Detailed analysis of your logistics activities and earnings.</p>
                </div>
                <div style="display: flex; gap: 0.75rem;">
                    <button onclick="exportToExcel()" class="print-btn" style="background: #10b981;">
                        <i class='bx bxs-file-export'></i> Export Excel
                    </button>
                    <button onclick="window.print()" class="print-btn">
                        <i class='bx bx-printer'></i> Print Report
                    </button>
                </div>
            </div>

            <form class="filter-form" method="GET">
                <div class="form-group">
                    <label>From Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo $startDate; ?>">
                </div>
                <div class="form-group">
                    <label>To Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?php echo $endDate; ?>">
                </div>
                <button type="submit" class="btn btn-primary" style="height: fit-content; padding: 0.6rem 1.5rem;">
                    Apply Filter
                </button>
            </form>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Earnings</div>
                    <div class="stat-value" style="color: #059669;"><?php echo $report['total_earnings']; ?> CR</div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">Credits Collected</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Completed Tasks</div>
                    <div class="stat-value"><?php echo $report['total_completed']; ?></div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">Successful Deliveries</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Success Rate</div>
                    <div class="stat-value" style="color: <?php echo $report['success_rate'] >= 90 ? '#059669' : ($report['success_rate'] >= 70 ? '#2563eb' : '#ef4444'); ?>;">
                        <?php echo number_format($report['success_rate'], 1); ?>%
                    </div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo $report['total_abandoned']; ?> Abandoned</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Average Rating</div>
                    <div class="stat-value" style="color: #fbbf24;">
                        <?php echo $report['avg_rating'] > 0 ? number_format($report['avg_rating'], 1) : '—'; ?>
                    </div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">Customer Feedback</div>
                </div>
            </div>

            <!-- Charts Grid -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
                <!-- Earnings Graph -->
                <div style="background: white; padding: 1.5rem; border-radius: var(--radius-lg); border: 1px solid var(--border-color);">
                    <h3 style="margin-bottom: 1.5rem; font-size: 1.1rem; color: var(--text-main);"><i class='bx bx-bar-chart-alt-2'></i> Earnings Overview</h3>
                    <div style="height: 300px; width: 100%;">
                        <canvas id="earningsChart"></canvas>
                    </div>
                </div>

                <!-- NEW: Delivery Count Graph -->
                <div style="background: white; padding: 1.5rem; border-radius: var(--radius-lg); border: 1px solid var(--border-color);">
                    <h3 style="margin-bottom: 1.5rem; font-size: 1.1rem; color: var(--text-main);"><i class='bx bx-package'></i> Mission Activity</h3>
                    <div style="height: 300px; width: 100%;">
                        <canvas id="missionChart"></canvas>
                    </div>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3 style="margin: 0;"><i class='bx bx-list-check'></i> Delivery Activity Log</h3>
            </div>
            
            <?php if (empty($report['activity'])): ?>
                <div style="text-align: center; padding: 4rem; background: white; border-radius: var(--radius-lg); border: 1px dashed var(--border-color);">
                    <p style="color: var(--text-muted);">No activity found for the selected period.</p>
                </div>
            <?php else: ?>
                <table class="report-table" id="activityTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Order ID</th>
                            <th>Book Title</th>
                            <th>Participants</th>
                            <th>Status</th>
                            <th>Earnings</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($report['activity'] as $row): ?>
                        <tr>
                            <td style="font-weight: 500; font-size: 0.85rem;">
                                <?php echo date('M d, Y', strtotime($row['mission_timestamp'])); ?><br>
                                <small style="color: var(--text-muted);"><?php echo date('h:i A', strtotime($row['mission_timestamp'])); ?></small>
                            </td>
                            <td style="font-family: monospace; font-weight: 600;">#ORD-<?php echo $row['id']; ?></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.8rem;">
                                    <img src="<?php echo htmlspecialchars($row['cover_image'] ?: 'assets/images/book-placeholder.jpg'); ?>" style="width: 30px; height: 40px; object-fit: cover; border-radius: 4px;">
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($row['title']); ?></div>
                                </div>
                            </td>
                            <td>
                                <div style="font-size: 0.8rem; color: var(--text-muted);">
                                    <?php if ($row['mission_type'] === 'Forward'): ?>
                                        From: <span style="color: var(--text-main); font-weight: 600;"><?php echo htmlspecialchars($row['lender_name']); ?></span><br>
                                        To: <span style="color: var(--text-main); font-weight: 600;"><?php echo htmlspecialchars($row['borrower_name']); ?></span>
                                    <?php else: ?>
                                        From: <span style="color: var(--text-main); font-weight: 600;"><?php echo htmlspecialchars($row['borrower_name']); ?></span><br>
                                        To: <span style="color: var(--text-main); font-weight: 600;"><?php echo htmlspecialchars($row['lender_name']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span style="background: <?php echo $row['mission_type'] === 'Forward' ? '#d1fae5' : '#eff6ff'; ?>; 
                                             color: <?php echo $row['mission_type'] === 'Forward' ? '#059669' : '#2563eb'; ?>; 
                                             padding: 0.3rem 0.6rem; border-radius: 20px; font-size: 0.75rem; font-weight: 700;">
                                    <?php echo $row['mission_type'] === 'Forward' ? 'DELIVERED' : 'RETURNED'; ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-weight: 700; color: #059669;">+10 CR</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

        </main>
    </div>

    <!-- Scripts for Chart and Excel -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/xlsx/dist/xlsx.full.min.js"></script>
    <script>
        // 1. Prepare Data for Chart
        <?php
            // Aggregate earnings and mission counts by date
            $chartData = [];
            foreach ($report['activity'] as $act) {
                // Determine date
                $date = date('Y-m-d', strtotime($act['mission_timestamp']));
                
                if (!isset($chartData[$date])) {
                    $chartData[$date] = ['earnings' => 0, 'forward' => 0, 'return' => 0];
                }
                
                $chartData[$date]['earnings'] += 10;
                
                if ($act['mission_type'] === 'Forward') {
                    $chartData[$date]['forward']++;
                } else {
                    $chartData[$date]['return']++;
                }
            }
            ksort($chartData); // Sort by date
            
            $labels = array_keys($chartData);
            $earningsValues = array_column($chartData, 'earnings');
            $forwardValues = array_column($chartData, 'forward');
            $returnValues = array_column($chartData, 'return');
        ?>
        
        // --- Earnings Chart ---
        const ctx = document.getElementById('earningsChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    label: 'Daily Earnings (CR)',
                    data: <?php echo json_encode($earningsValues); ?>,
                    borderColor: '#4f46e5',
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#4f46e5',
                    pointBorderWidth: 2,
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: function(value) { return value + ' CR'; } }
                    }
                }
            }
        });

        // --- NEW: Mission Activity Chart ---
        const ctxMission = document.getElementById('missionChart').getContext('2d');
        new Chart(ctxMission, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [
                    {
                        label: 'Forward Missions',
                        data: <?php echo json_encode($forwardValues); ?>,
                        backgroundColor: '#10b981', // Green
                        borderRadius: 4,
                    },
                    {
                        label: 'Return Missions',
                        data: <?php echo json_encode($returnValues); ?>,
                        backgroundColor: '#3b82f6', // Blue
                        borderRadius: 4,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
                scales: {
                    x: { stacked: true },
                    y: { 
                        stacked: true, 
                        beginAtZero: true,
                        ticks: { stepSize: 1 } // Iterate by whole numbers
                    }
                }
            }
        });

        // 2. Export to Excel Function
        function exportToExcel() {
            const table = document.getElementById('activityTable');
            if (!table) { alert('No data to export!'); return; }
            
            // Create a clean data array from the table, handling nested HTML logic
            const ws_data = [];
            
            // Headers
            ws_data.push(['Date', 'Order ID', 'Book Title', 'Participants', 'Status', 'Earnings']);
            
            // Rows
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const cols = row.querySelectorAll('td');
                // Extract clean text
                const date = cols[0].innerText.split('\n')[0]; // Date only
                const orderId = cols[1].innerText;
                const title = cols[2].innerText.trim();
                const participants = cols[3].innerText.replace(/\n/g, ' ').replace(/\s+/g, ' ').trim(); // Flatten text
                const status = cols[4].innerText.trim();
                const earnings = cols[5].innerText.trim();
                
                ws_data.push([date, orderId, title, participants, status, earnings]);
            });

            // Make Sheet
            const ws = XLSX.utils.aoa_to_sheet(ws_data);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, "Activity_Log");

            // Save File
            XLSX.writeFile(wb, "Delivery_Activity_Report_<?php echo date('Y-m-d'); ?>.xlsx");
        }
    </script>
</body>
</html>
