<?php
require_once '../includes/db_helper.php';
require_once '../paths.php';
include '../includes/dashboard_header.php';

if ($user['role'] !== 'delivery_agent' && $user['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if ($user['role'] === 'delivery_agent') {
    header("Location: agent_history.php");
    exit();
}

$pdo = getDBConnection();

// Fetch all completed legs for this agent using UNION ALL to separate mission types
$stmt = $pdo->prepare("
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
$stmt->execute([$userId, $userId]);
$history = $stmt->fetchAll();

$stats = getUserStatsEnhanced($userId);
// Calculate Lifetime Earnings based on history count for consistency
$lifetime_earnings = count($history) * 50.00;

// Helper function to build location string
function buildLocation($addr, $city, $dist) {
    if (!empty($addr)) return $addr;
    if (!empty($city) && !empty($dist)) return "$city, $dist";
    if (!empty($city)) return $city;
    return "Location not provided";
}
?>
<style>
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
        .history-meta { display: flex; gap: 1rem; color: var(--text-muted); font-size: 0.85rem; }
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
</style>
    <div class="dashboard-wrapper">
        <?php include '../includes/dashboard_sidebar.php'; ?>
        
        <main class="main-content">
            <div class="section-header">
                <div>
                    <h1><i class='bx bx-history'></i> Delivery History</h1>
                    <p>Track all your completed delivery missions and earnings.</p>
                </div>
                <div style="display: flex; gap: 1rem; align-items: center;">
                    <button onclick="exportHistoryExcel()" class="btn" style="background: #10b981; color: white; display: flex; align-items: center; gap: 0.5rem; padding: 0.8rem 1.2rem;">
                        <i class='bx bxs-file-export'></i> Export History
                    </button>
                    <div style="background: white; padding: 0.8rem 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); text-align: center;">
                        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Lifetime Earnings</div>
                        <div style="font-size: 1.4rem; font-weight: 800; color: #059669;">₹<?php echo number_format($lifetime_earnings, 2); ?></div>
                    </div>
                </div>
            </div>

            <?php if (empty($history)): ?>
                <div style="text-align: center; padding: 5rem; background: white; border-radius: var(--radius-lg); border: 1px dashed var(--border-color); margin-top: 2rem;">
                    <i class='bx bx-package' style="font-size: 4rem; color: var(--text-muted); opacity: 0.3;"></i>
                    <h2 style="margin-top: 1rem; color: var(--text-muted);">No completed deliveries yet</h2>
                    <p style="color: var(--text-muted);">Start accepting jobs to see your history here!</p>
                    <a href="delivery_jobs.php" class="btn btn-primary" style="margin-top: 1.5rem;">Find Jobs</a>
                </div>
            <?php else: ?>
                <div style="margin-top: 2rem;" id="history-list">
                    <?php foreach ($history as $h): ?>
                        <?php 
                            // Robust Address Resolution Logic
                            $borrowerLoc = buildLocation($h['borrower_address'], $h['borrower_city'], $h['borrower_district']);
                            $lenderLoc = buildLocation($h['lender_address'], $h['lender_city'], $h['lender_district']);
                            $listingLoc = buildLocation($h['listing_loc'], $h['listing_city'], $h['listing_dist']);
                            
                            // Determine Order Address (Borrower Side)
                            // Priority: 1. Specific Order Addr, 2. Borrower Profile Addr, 3. Borrower City
                            $toPoint = !empty($h['order_address']) ? $h['order_address'] : $borrowerLoc;

                            // Determine Listing Address (Lender Side)
                            // Priority: 1. Listing specific loc, 2. Listing City, 3. Lender Profile Addr, 4. Lender City
                            $fromPoint = !empty($h['listing_loc']) ? $h['listing_loc'] : 
                                         ($listingLoc !== 'Location not provided' ? $listingLoc : $lenderLoc);

                            if ($h['job_type_label'] === 'Delivery Mission') {
                                $fromAddr = $fromPoint;
                                $toAddr = $toPoint;
                            } else {
                                // Return Mission: From Borrower -> To Lender
                                $fromAddr = $toPoint; // Borrower is source
                                $toAddr = $fromPoint; // Lender is dist
                            }
                        ?>
                        <div class="history-card" 
                             data-id="<?php echo $h['id']; ?>" 
                             data-title="<?php echo htmlspecialchars($h['title']); ?>"
                             data-type="<?php echo $h['job_type_label']; ?>"
                             data-from="<?php echo htmlspecialchars($fromAddr); ?>"
                             data-to="<?php echo htmlspecialchars($toAddr); ?>"
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
                                <h3 style="margin-top: 0.2rem;"><?php echo htmlspecialchars($h['title']); ?></h3>
                                
                                <div class="address-box">
                                    <div class="addr-point">
                                        <i class='bx bx-radio-circle' style="color: #2563eb;"></i>
                                        <span>From: <strong><?php echo htmlspecialchars($h['job_type_label'] === 'Delivery Mission' ? $h['lender_fname'] : $h['borrower_fname']); ?></strong> • <?php echo htmlspecialchars($fromAddr); ?></span>
                                    </div>
                                    <div class="addr-point">
                                        <i class='bx bx-map' style="color: #e11d48;"></i>
                                        <span>To: <strong><?php echo htmlspecialchars($h['job_type_label'] === 'Delivery Mission' ? $h['borrower_fname'] : $h['lender_fname']); ?></strong> • <?php echo htmlspecialchars($toAddr); ?></span>
                                    </div>
                                </div>

                                <div style="margin-top: 0.8rem; font-size: 0.8rem; color: var(--text-muted);">
                                    <i class='bx bx-calendar-check'></i> Finished: <?php echo date('M d, Y • h:i A', strtotime($h['completion_time'])); ?>
                                </div>
                            </div>
                            <div class="history-status">
                                <div class="earned-badge">
                                    <i class='bx bx-plus-circle'></i> ₹50 Earned
                                </div>
                                <div style="margin-top: 0.5rem; color: #2563eb; font-weight: 600; font-size: 0.8rem; opacity: 0.8;">
                                    <i class='bx bxs-check-shield'></i> Verified
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Excel Export Script -->
    <script src="https://unpkg.com/xlsx/dist/xlsx.full.min.js"></script>
    <script>
        function exportHistoryExcel() {
            const container = document.getElementById('history-list');
            if (!container) { showToast('No history data to export.', 'warning', 3500); return; }

            const cards = container.querySelectorAll('.history-card');
            if (cards.length === 0) { showToast('No history data to export.', 'warning', 3500); return; }

            const data = [];
            // Headers
            data.push(['Order ID', 'Mission Type', 'Book Title', 'From Location', 'To Location', 'Completion Date', 'Earnings (₹)']);

            cards.forEach(card => {
                const row = [
                    '#ORD-' + card.dataset.id,
                    card.dataset.type,
                    card.dataset.title,
                    card.dataset.from,
                    card.dataset.to,
                    card.dataset.date,
                    card.dataset.earnings
                ];
                data.push(row);
            });

            // Create Workbook
            const wb = XLSX.utils.book_new();
            const ws = XLSX.utils.aoa_to_sheet(data);
            
            // Auto-width for columns
            const wscols = [
                {wch: 15}, {wch: 20}, {wch: 30}, {wch: 30}, {wch: 30}, {wch: 25}, {wch: 15}
            ];
            ws['!cols'] = wscols;

            XLSX.utils.book_append_sheet(wb, ws, "Delivery History");
            XLSX.writeFile(wb, "My_Delivery_History_<?php echo date('Y-m-d'); ?>.xlsx");
        }
    </script>
</body>
</html>
