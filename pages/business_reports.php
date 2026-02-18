<?php
require_once '../includes/db_helper.php';
require_once '../paths.php';
include '../includes/dashboard_header.php';

$user_role = $_SESSION['role'] ?? 'user';

// Ensure only library or bookstore (or admin) can access
if (!$userId || (!in_array($user_role, ['library', 'bookstore', 'admin']))) {
    header("Location: login.php");
    exit();
}

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

$report = getBusinessReportStats($userId, $startDate, $endDate);
?>
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
    <div class="dashboard-wrapper">
        <?php include '../includes/dashboard_sidebar.php'; ?>
        
        <main class="main-content">
            <div class="report-header">
                <div>
                    <h1><i class='bx bx-line-chart'></i> Business Analytics</h1>
                    <p>Track your sales, stock, and community impact.</p>
                </div>
                <div style="display: flex; gap: 0.75rem;">
                    <button onclick="exportToExcel()" class="print-btn" style="background: #10b981;">
                        <i class='bx bxs-file-export'></i> Export Excel
                    </button>
                    <button onclick="window.print()" class="print-btn">
                        <i class='bx bx-printer'></i> Print PDF
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
                    <div class="stat-label">Total Listings</div>
                    <div class="stat-value"><?php echo $report['total_listings']; ?></div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">Current Books</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Out of Stock</div>
                    <div class="stat-value" style="color: #ef4444;"><?php echo $report['out_of_stock']; ?></div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">Needs Refill</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Completed Deals</div>
                    <div class="stat-value" style="color: #059669;"><?php echo $report['total_completed']; ?></div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">Successful Orders</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label"><?php echo $user_role === 'bookstore' ? 'Revenue' : 'Tokens Earned'; ?></div>
                    <div class="stat-value" style="color: var(--primary);">
                        <?php 
                            if ($user_role === 'bookstore') {
                                echo "₹" . number_format($report['total_revenue_money'], 2);
                            } else {
                                echo $report['total_revenue_tokens'] . " CR";
                            }
                        ?>
                    </div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo $report['active_deals']; ?> Active Deals</div>
                </div>
            </div>

            <!-- Charts Grid -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
                <div style="background: white; padding: 1.5rem; border-radius: var(--radius-lg); border: 1px solid var(--border-color);">
                    <h3 style="margin-bottom: 1.5rem; font-size: 1.1rem; color: var(--text-main);"><i class='bx bx-stats'></i> Activity Volume</h3>
                    <div style="height: 300px; width: 100%;">
                        <canvas id="activityChart"></canvas>
                    </div>
                </div>

                <div style="background: white; padding: 1.5rem; border-radius: var(--radius-lg); border: 1px solid var(--border-color);">
                    <h3 style="margin-bottom: 1.5rem; font-size: 1.1rem; color: var(--text-main);"><i class='bx bx-pie-chart-alt'></i> Listing Split</h3>
                    <div style="height: 300px; width: 100%;">
                        <canvas id="typeChart"></canvas>
                    </div>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3 style="margin: 0;"><i class='bx bx-history'></i> Recent Business Activity</h3>
            </div>
            
            <?php if (empty($report['activity'])): ?>
                <div style="text-align: center; padding: 4rem; background: white; border-radius: var(--radius-lg); border: 1px dashed var(--border-color);">
                    <p style="color: var(--text-muted);">No transactions found for the selected period.</p>
                </div>
            <?php else: ?>
                <table class="report-table" id="activityTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Order ID</th>
                            <th>Book Title</th>
                            <th>Customer</th>
                            <th>Type</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($report['activity'] as $row): ?>
                        <tr>
                            <td style="font-weight: 500; font-size: 0.85rem;">
                                <?php echo date('M d, Y', strtotime($row['created_at'])); ?>
                            </td>
                            <td style="font-family: monospace; font-weight: 600;">#ORD-<?php echo $row['id']; ?></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.8rem;">
                                    <?php 
                                        $cover = $row['cover_image'];
                                        $cover = $cover ?: '../assets/images/book-placeholder.jpg';
                                    ?>
                                    <img src="<?php echo htmlspecialchars($cover, ENT_QUOTES, 'UTF-8', false); ?>" style="width: 30px; height: 40px; object-fit: cover; border-radius: 4px;" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1543004218-ee141104975a?w=400';">
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($row['title']); ?></div>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($row['borrower_name'] . ' ' . $row['borrower_lastname']); ?></div>
                            </td>
                            <td>
                                <span style="padding: 0.3rem 0.6rem; border-radius: 20px; font-size: 0.75rem; font-weight: 700; background: #f1f5f9; color: var(--text-muted);">
                                    <?php echo strtoupper($row['listing_type']); ?>
                                </span>
                            </td>
                            <td style="font-weight: 700; color: var(--primary);">
                                <?php 
                                    if ($row['listing_type'] === 'sell') {
                                        echo "₹" . number_format($row['price'], 2);
                                    } else {
                                        echo $row['credit_cost'] . " CR";
                                    }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

        </main>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/xlsx/dist/xlsx.full.min.js"></script>
    <script>
        // 1. Activity Chart (Daily Volume)
        <?php
            $chartData = [];
            foreach ($report['activity'] as $act) {
                $date = date('Y-m-d', strtotime($act['created_at']));
                if (!isset($chartData[$date])) $chartData[$date] = 0;
                $chartData[$date]++;
            }
            ksort($chartData);
            $labels = array_keys($chartData);
            $values = array_values($chartData);
        ?>
        
        const activityCtx = document.getElementById('activityChart').getContext('2d');
        new Chart(activityCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    label: 'Transactions',
                    data: <?php echo json_encode($values); ?>,
                    borderColor: '#4f46e5',
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        // 2. Type Chart (Sell vs Borrow)
        <?php
            $typeData = ['sell' => 0, 'borrow' => 0];
            foreach ($report['activity'] as $act) {
                if (isset($typeData[$act['listing_type']])) $typeData[$act['listing_type']]++;
            }
        ?>
        const typeCtx = document.getElementById('typeChart').getContext('2d');
        new Chart(typeCtx, {
            type: 'doughnut',
            data: {
                labels: ['Sell', 'Borrow'],
                datasets: [{
                    data: [<?php echo $typeData['sell']; ?>, <?php echo $typeData['borrow']; ?>],
                    backgroundColor: ['#10b981', '#3b82f6']
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        // 3. Export to Excel
        function exportToExcel() {
            const table = document.getElementById('activityTable');
            if (!table) return;
            
            const ws_data = [['Date', 'Order ID', 'Book Title', 'Customer', 'Type', 'Total']];
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const cols = row.querySelectorAll('td');
                ws_data.push([
                    cols[0].innerText.trim(),
                    cols[1].innerText.trim(),
                    cols[2].innerText.trim(),
                    cols[3].innerText.trim(),
                    cols[4].innerText.trim(),
                    cols[5].innerText.trim()
                ]);
            });

            const ws = XLSX.utils.aoa_to_sheet(ws_data);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, "Business_Report");
            XLSX.writeFile(wb, "Business_Report_<?php echo date('Y-m-d'); ?>.xlsx");
        }
    </script>
</body>
</html>
