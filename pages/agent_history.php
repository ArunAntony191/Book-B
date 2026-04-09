<?php
require_once '../includes/db_helper.php';
require_once '../paths.php';
include '../includes/dashboard_header.php';

if ($user['role'] !== 'delivery_agent' && $user['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];

// --- Data Fetching: Missions ---
$stmtMissions = $pdo->prepare("
    (SELECT t.id, t.created_at, b.title, b.cover_image, 
            u_lender.firstname as lender_fname, u_lender.lastname as lender_lname,
            u_borrower.firstname as borrower_fname, u_borrower.lastname as borrower_lname,
            u_borrower.address as borrower_address, u_borrower.city as borrower_city, u_borrower.district as borrower_district,
            u_lender.address as lender_address, u_lender.city as lender_city, u_lender.district as lender_district,
            l.location as listing_loc, l.city as listing_city, l.district as listing_dist,
            'Delivery Mission' as job_type_label,
            t.agent_confirm_delivery_at as completion_time,
            t.order_address
     FROM transactions t
     JOIN listings l ON t.listing_id = l.id
     JOIN books b ON l.book_id = b.id
     JOIN users u_lender ON t.lender_id = u_lender.id
     JOIN users u_borrower ON t.borrower_id = u_borrower.id
     WHERE t.delivery_agent_id = ? AND t.agent_confirm_delivery_at IS NOT NULL)

    UNION ALL

    (SELECT t.id, t.created_at, b.title, b.cover_image, 
            u_lender.firstname as lender_fname, u_lender.lastname as lender_lname,
            u_borrower.firstname as borrower_fname, u_borrower.lastname as borrower_lname,
            u_borrower.address as borrower_address, u_borrower.city as borrower_city, u_borrower.district as borrower_district,
            u_lender.address as lender_address, u_lender.city as lender_city, u_lender.district as lender_district,
            l.location as listing_loc, l.city as listing_city, l.district as listing_dist,
            'Return Mission' as job_type_label,
            t.return_agent_confirm_at as completion_time,
            t.order_address
     FROM transactions t
     JOIN listings l ON t.listing_id = l.id
     JOIN books b ON l.book_id = b.id
     JOIN users u_lender ON t.lender_id = u_lender.id
     JOIN users u_borrower ON t.borrower_id = u_borrower.id
     WHERE t.return_agent_id = ? AND t.return_agent_confirm_at IS NOT NULL)

    ORDER BY completion_time DESC
");
$stmtMissions->execute([$userId, $userId]);
$missionHistory = $stmtMissions->fetchAll();

// --- Data Fetching: Credits ---
$creditHistory = getCreditHistory($userId, 50);
$currentCredits = getUserCredits($userId);
$lifetime_earnings = count($missionHistory) * 50.00;

// Helper function to build location string
function buildLocation($addr, $city, $dist) {
    if (!empty($addr)) return $addr;
    if (!empty($city) && !empty($dist)) return "$city, $dist";
    if (!empty($city)) return $city;
    return "Location not provided";
}
?>

<style>
    .history-tabs {
        display: flex;
        gap: 1rem;
        margin-bottom: 2rem;
        border-bottom: 1px solid var(--border-color);
        padding-bottom: 0.5rem;
    }
    .tab-btn {
        padding: 0.8rem 1.5rem;
        border: none;
        background: none;
        color: var(--text-muted);
        font-weight: 700;
        cursor: pointer;
        border-radius: var(--radius-md);
        transition: all 0.3s;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .tab-btn.active {
        background: var(--primary-soft);
        color: var(--primary);
    }
    .tab-content { display: none; animation: fadeIn 0.3s ease; }
    .tab-content.active { display: block; }

    /* Mission Card Styles */
    .history-card {
        background: white;
        border-radius: var(--radius-lg);
        border: 1px solid var(--border-color);
        padding: 1.5rem;
        margin-bottom: 1rem;
        display: grid;
        grid-template-columns: 80px 1fr auto;
        gap: 1.5rem;
        align-items: center;
        transition: transform 0.2s;
    }
    .history-card:hover { transform: translateX(5px); border-color: var(--primary); }
    .book-img { width: 80px; height: 110px; object-fit: cover; border-radius: 8px; box-shadow: var(--shadow-sm); }
    .history-details h3 { margin: 0 0 0.5rem 0; font-size: 1.1rem; color: var(--text-main); }
    .history-status { text-align: right; }
    .earned-badge { 
        background: #d1fae5; color: #059669; padding: 0.4rem 0.8rem; 
        border-radius: 20px; font-weight: 700; font-size: 0.8rem;
        display: inline-flex; align-items: center; gap: 0.3rem;
    }
    .mission-tag {
        font-size: 0.7rem; padding: 2px 8px; border-radius: 4px; font-weight: 700;
        text-transform: uppercase; margin-bottom: 0.5rem; display: inline-block;
    }
    .tag-forward { background: #eff6ff; color: #2563eb; }
    .tag-return { background: #fff1f2; color: #e11d48; }
    .address-box { font-size: 0.85rem; color: var(--text-muted); margin-top: 0.5rem; display: flex; flex-direction: column; gap: 0.2rem; }
    .addr-point { display: flex; align-items: center; gap: 0.5rem; }

    /* Credit Table Styles */
    .credit-table { width: 100%; border-collapse: collapse; }
    .credit-table th { padding: 1rem; text-align: left; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; border-bottom: 1px solid var(--border-color); background: #f8fafc; }
    .credit-table td { padding: 1.25rem; border-bottom: 1px solid var(--border-color); }
    .credit-row:hover { background: #f8fafc; }

    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>

<div class="dashboard-wrapper">
    <?php include '../includes/dashboard_sidebar.php'; ?>
    
    <main class="main-content">
        <div class="section-header" style="flex-wrap: wrap; gap: 1.5rem;">
            <div>
                <h1><i class='bx bx-wallet'></i> Wallet & Performance</h1>
                <p>Track your earnings, credits, and mission history in one place.</p>
            </div>
            <div style="display: flex; gap: 1rem; align-items: stretch;">
                <!-- Cash Earnings -->
                <div style="background: #10b981; color: white; padding: 1rem 2rem; border-radius: var(--radius-lg); text-align: center; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);">
                    <div style="font-size: 0.75rem; opacity: 0.9; text-transform: uppercase; font-weight: 700;">Lifetime Cash</div>
                    <div style="font-size: 1.8rem; font-weight: 900;">₹<?php echo number_format($lifetime_earnings, 0); ?></div>
                    <div style="font-size: 0.7rem; opacity: 0.8;">(₹50 per Mission)</div>
                </div>
                <!-- Credit Balance -->
                <div style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; padding: 1rem 2rem; border-radius: var(--radius-lg); text-align: center; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);">
                    <div style="font-size: 0.75rem; opacity: 0.9; text-transform: uppercase; font-weight: 700;">Token Balance</div>
                    <div style="font-size: 1.8rem; font-weight: 900;"><?php echo $currentCredits; ?></div>
                    <div style="font-size: 0.7rem; opacity: 0.8;">Bonus credits</div>
                </div>
            </div>
        </div>

        <div class="history-tabs">
            <button class="tab-btn active" onclick="switchTab('missions')">
                <i class='bx bx-rocket'></i> Delivery Missions
            </button>
            <button class="tab-btn" onclick="switchTab('credits')">
                <i class='bx bx-coin-stack'></i> Token Ledger
            </button>
        </div>

        <!-- Missions Tab -->
        <div id="missions-tab" class="tab-content active">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h2 style="font-size: 1.1rem; font-weight: 700;">Completed Missions (<?php echo count($missionHistory); ?>)</h2>
                <button onclick="exportHistoryExcel()" class="btn btn-sm" style="background: #10b981; color: white; display: flex; align-items: center; gap: 0.4rem;">
                    <i class='bx bxs-file-export'></i> Export Excel
                </button>
            </div>

            <?php if (empty($missionHistory)): ?>
                <div style="text-align: center; padding: 4rem; background: white; border-radius: var(--radius-lg); border: 1px dashed var(--border-color);">
                    <i class='bx bx-package' style="font-size: 3rem; color: var(--text-muted); opacity: 0.3;"></i>
                    <p style="margin-top: 1rem; color: var(--text-muted);">No missions completed yet. Time to hit the road!</p>
                </div>
            <?php else: ?>
                <div id="history-list">
                    <?php foreach ($missionHistory as $h): ?>
                        <?php 
                            $fromLoc = buildLocation($h['job_type_label'] === 'Delivery Mission' ? $h['lender_address'] : $h['borrower_address'], 
                                                   $h['job_type_label'] === 'Delivery Mission' ? $h['lender_city'] : $h['borrower_city'],
                                                   $h['job_type_label'] === 'Delivery Mission' ? $h['lender_district'] : $h['borrower_district']);
                            $toLoc = buildLocation($h['job_type_label'] === 'Delivery Mission' ? ($h['order_address'] ?: $h['borrower_address']) : $h['lender_address'],
                                                $h['job_type_label'] === 'Delivery Mission' ? $h['borrower_city'] : $h['lender_city'],
                                                $h['job_type_label'] === 'Delivery Mission' ? $h['borrower_district'] : $h['lender_district']);
                        ?>
                        <div class="history-card" 
                             data-id="<?php echo $h['id']; ?>" 
                             data-title="<?php echo htmlspecialchars($h['title']); ?>"
                             data-type="<?php echo $h['job_type_label']; ?>"
                             data-from="<?php echo htmlspecialchars($fromLoc); ?>"
                             data-to="<?php echo htmlspecialchars($toLoc); ?>"
                             data-date="<?php echo date('Y-m-d H:i:s', strtotime($h['completion_time'])); ?>"
                             data-earnings="50">
                            
                            <img src="<?php echo htmlspecialchars(html_entity_decode($h['cover_image']), ENT_QUOTES, 'UTF-8'); ?>" 
                                 onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1543004218-ee141104975a?w=400';" 
                                 class="book-img">
                            <div class="history-details">
                                <span class="mission-tag <?php echo $h['job_type_label'] === 'Delivery Mission' ? 'tag-forward' : 'tag-return'; ?>">
                                    <?php echo $h['job_type_label']; ?>
                                </span>
                                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">Order #ORD-<?php echo $h['id']; ?></div>
                                <h3><?php echo htmlspecialchars($h['title']); ?></h3>
                                
                                <div class="address-box">
                                    <div class="addr-point">
                                        <i class='bx bx-radio-circle' style="color: #2563eb;"></i>
                                        <span>From: <?php echo htmlspecialchars($fromLoc); ?></span>
                                    </div>
                                    <div class="addr-point">
                                        <i class='bx bx-map' style="color: #e11d48;"></i>
                                        <span>To: <?php echo htmlspecialchars($toLoc); ?></span>
                                    </div>
                                </div>
                                <div style="margin-top: 0.5rem; font-size: 0.8rem; color: var(--text-muted);">
                                    <i class='bx bx-calendar-check'></i> <?php echo date('M d, Y • h:i A', strtotime($h['completion_time'])); ?>
                                </div>
                            </div>
                            <div class="history-status">
                                <div class="earned-badge">₹50</div>
                                <div style="margin-top: 0.4rem; color: #10b981; font-weight: 700; font-size: 0.75rem;">
                                    <i class='bx bxs-check-shield'></i> Verified
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Credits Tab -->
        <div id="credits-tab" class="tab-content">
            <h2 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1.5rem;">Credits Ledger</h2>

            <?php if (empty($creditHistory)): ?>
                <div style="text-align: center; padding: 4rem; background: white; border-radius: var(--radius-lg); border: 1px dashed var(--border-color);">
                    <i class='bx bx-wallet' style="font-size: 3rem; color: var(--text-muted); opacity: 0.3;"></i>
                    <p style="margin-top: 1rem; color: var(--text-muted);">No token transactions yet.</p>
                </div>
            <?php else: ?>
                <div style="background: white; border-radius: var(--radius-lg); border: 1px solid var(--border-color); overflow: hidden;">
                    <table class="credit-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Description</th>
                                <th style="text-align: center;">Amount</th>
                                <th style="text-align: right;">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($creditHistory as $tx): 
                                $colors = ['earn' => '#10b981', 'spend' => '#3b82f6', 'penalty' => '#ef4444', 'bonus' => '#8b5cf6'];
                                $color = $colors[$tx['type']] ?? '#6b7280';
                            ?>
                                <tr class="credit-row">
                                    <td>
                                        <span style="font-weight: 700; text-transform: uppercase; font-size: 0.7rem; color: <?php echo $color; ?>; background: <?php echo $color; ?>15; padding: 3px 8px; border-radius: 4px;">
                                            <?php echo $tx['type']; ?>
                                        </span>
                                    </td>
                                    <td style="font-size: 0.9rem; color: var(--text-main);"><?php echo htmlspecialchars($tx['description']); ?></td>
                                    <td style="text-align: center; font-weight: 800; color: <?php echo $tx['amount'] >= 0 ? '#10b981' : '#ef4444'; ?>;">
                                        <?php echo ($tx['amount'] >= 0 ? '+' : '') . $tx['amount']; ?>
                                    </td>
                                    <td style="text-align: right; font-size: 0.8rem; color: var(--text-muted);">
                                        <?php echo date('M d, Y', strtotime($tx['created_at'])); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script src="https://unpkg.com/xlsx/dist/xlsx.full.min.js"></script>
<script>
    function switchTab(tab) {
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        
        document.getElementById(tab + '-tab').classList.add('active');
        event.currentTarget.classList.add('active');
    }

    function exportHistoryExcel() {
        const container = document.getElementById('history-list');
        if (!container) { showToast('No history data to export.', 'warning', 3500); return; }
        const cards = container.querySelectorAll('.history-card');
        const data = [['Order ID', 'Mission Type', 'Book Title', 'From Location', 'To Location', 'Completion Date', 'Earnings (₹)']];
        cards.forEach(card => {
            data.push(['#ORD-' + card.dataset.id, card.dataset.type, card.dataset.title, card.dataset.from, card.dataset.to, card.dataset.date, card.dataset.earnings]);
        });
        const wb = XLSX.utils.book_new();
        const ws = XLSX.utils.aoa_to_sheet(data);
        XLSX.utils.book_append_sheet(wb, ws, "Delivery History");
        XLSX.writeFile(wb, "My_Performance_<?php echo date('Y-m-d'); ?>.xlsx");
    }
</script>
</body>
</html>
