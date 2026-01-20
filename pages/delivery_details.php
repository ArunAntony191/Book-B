<?php
require_once '../includes/db_helper.php';
require_once '../paths.php';
include '../includes/dashboard_header.php';

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
    header("Location: login.php");
    exit();
}

$id = $_GET['id'] ?? 0;
if (!$id) {
    echo "<div class='main-content'><div class='container'><h1>Invalid ID</h1><p>No transaction ID provided.</p></div></div>";
    exit;
}

// Fetch transaction details
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT t.*, 
               b.title, b.cover_image, b.author,
               l.credit_cost as listing_credits,
               u_lender.firstname as lender_fname, u_lender.lastname as lender_lname, u_lender.phone as lender_phone,
               u_borrower.firstname as borrower_fname, u_borrower.lastname as borrower_lname, u_borrower.phone as borrower_phone,
               u_agent.firstname as agent_fname, u_agent.lastname as agent_lname, u_agent.phone as agent_phone,
               u_ret_agent.firstname as ret_agent_fname, u_ret_agent.lastname as ret_agent_lname, u_ret_agent.phone as ret_agent_phone,
               lender_loc.service_start_lat as pickup_lat, lender_loc.service_start_lng as pickup_lng
        FROM transactions t
        JOIN listings l ON t.listing_id = l.id
        JOIN books b ON l.book_id = b.id
        JOIN users u_lender ON t.lender_id = u_lender.id
        JOIN users u_borrower ON t.borrower_id = u_borrower.id
        LEFT JOIN users u_agent ON t.delivery_agent_id = u_agent.id
        LEFT JOIN users u_ret_agent ON t.return_agent_id = u_ret_agent.id
        LEFT JOIN users lender_loc ON t.lender_id = lender_loc.id
        WHERE t.id = ? AND (t.lender_id = ? OR t.borrower_id = ?)
    ");
    $stmt->execute([$id, $userId, $userId]);
    $d = $stmt->fetch();

    if (!$d) {
        echo "<div class='main-content'><div class='container'><h1>Transaction Not Found</h1><p>You don't have permission to view this transaction or it doesn't exist.</p></div></div>";
        exit;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    exit;
}

// Mark this notification as read if it exists
markSpecificNotificationAsRead($userId, $id, ['borrow_request', 'sell_request', 'exchange_request', 'delivery_assigned', 'delivery_update']);

$isLender = ($d['lender_id'] == $userId);
$isBorrower = ($d['borrower_id'] == $userId);

function getStatusLabel($status, $agentId) {
    switch ($status) {
        case 'requested': return 'Waiting for Owner';
        case 'approved': return $agentId ? 'Agent Assigned' : 'Finding Agent';
        case 'active': return 'In Transit';
        case 'delivered': return 'Delivered & Verified';
        case 'returning': return 'Return in Progress';
        case 'returned': return 'Returned to Owner';
        default: return ucfirst($status);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction Details | BOOK-B</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        .details-container { max-width: 900px; margin: 0 auto; padding: 2rem; }
        .glass-card { background: white; border-radius: 24px; padding: 2.5rem; border: 1px solid var(--border-color); box-shadow: var(--shadow-lg); }
        .status-badge { padding: 0.6rem 1.2rem; border-radius: 12px; font-weight: 800; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; }
        .status-badge.requested { background: #eff6ff; color: #3b82f6; }
        .status-badge.approved { background: #f0fdf4; color: #16a34a; }
        .status-badge.active { background: #fff7ed; color: #ea580c; }
        .status-badge.delivered { background: #f0fdf4; color: #16a34a; }
        
        .book-details-grid { display: grid; grid-template-columns: 180px 1fr; gap: 2.5rem; margin-top: 2rem; }
        .book-cover { width: 100%; border-radius: 16px; box-shadow: var(--shadow-md); }
        .info-section { margin-top: 3rem; }
        .section-title { font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem; }
        
        .tracking-steps { display: flex; justify-content: space-between; position: relative; margin: 4rem 0 2rem 0; }
        .tracking-steps::before { content: ''; position: absolute; top: 13px; left: 0; width: 100%; height: 4px; background: #f1f5f9; z-index: 1; }
        .step { position: relative; z-index: 2; display: flex; flex-direction: column; align-items: center; gap: 0.75rem; flex: 1; }
        .step-dot { width: 26px; height: 26px; background: white; border: 4px solid #f1f5f9; border-radius: 50%; }
        .step-label { font-size: 0.7rem; font-weight: 900; color: #cbd5e1; text-transform: uppercase; }
        .step.completed .step-dot { background: var(--primary); border-color: var(--primary); }
        .step.completed .step-label { color: var(--primary); }
        .step.active .step-dot { border-color: var(--primary); border-width: 6px; }
        .step.active .step-label { color: var(--text-main); }

        #map { height: 300px; border-radius: 20px; border: 1px solid var(--border-color); margin-top: 1.5rem; }
        .verification-card { background: #f8fafc; border-radius: 16px; padding: 1.5rem; border: 1px solid #e2e8f0; margin-top: 2rem; }
        .v-badge { display: flex; align-items: center; gap: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 0.5rem; }
        .v-badge.verified { color: #10b981; }
        .actions-bar { display: flex; gap: 1rem; margin-top: 2rem; }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include '../includes/dashboard_sidebar.php'; ?>
        
        <main class="main-content">
            <div class="details-container">
                <div class="glass-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <div>
                            <span class="status-badge <?php echo $d['status']; ?>">
                                <?php echo getStatusLabel($d['status'], $d['delivery_agent_id']); ?>
                            </span>
                            <h1 style="font-size: 1.8rem; font-weight: 900; margin-top: 1rem;">Transaction Details #<?php echo $d['id']; ?></h1>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-weight: 800; color: var(--primary); font-size: 1.2rem;">
                                <i class='bx bxs-coin-stack'></i> <?php echo $d['listing_credits']; ?> Credits
                            </div>
                            <div style="font-size: 0.85rem; color: var(--text-muted);"><?php echo date('M d, Y', strtotime($d['created_at'])); ?></div>
                        </div>
                    </div>

                    <div class="book-details-grid">
                        <img src="<?php echo htmlspecialchars($d['cover_image'] ?: 'https://images.unsplash.com/photo-1543004218-ee141104975a?w=400'); ?>" class="book-cover">
                        <div>
                            <h2 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($d['title']); ?></h2>
                            <p style="color: var(--text-muted); font-size: 1rem;">by <?php echo htmlspecialchars($d['author']); ?></p>
                            
                            <div style="margin-top: 2rem; display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                                <div>
                                    <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #94a3b8; margin-bottom: 0.5rem;">Lender (Owner)</div>
                                    <div style="font-weight: 700; color: var(--text-main);"><?php echo $d['lender_fname'] . ' ' . $d['lender_lname']; ?></div>
                                    <div style="color: var(--text-muted); font-size: 0.9rem;"><?php echo $d['lender_phone']; ?></div>
                                </div>
                                <div>
                                    <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #94a3b8; margin-bottom: 0.5rem;">Borrower (Receiver)</div>
                                    <div style="font-weight: 700; color: var(--text-main);"><?php echo $d['borrower_fname'] . ' ' . $d['borrower_lname']; ?></div>
                                    <div style="color: var(--text-muted); font-size: 0.9rem;"><?php echo $d['borrower_phone']; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($d['delivery_method'] === 'delivery'): ?>
                    <div class="info-section">
                        <h3 class="section-title"><i class='bx bx-rocket'></i> Delivery Progress</h3>
                        <?php 
                        $isReturnPhase = in_array($d['status'], ['returning', 'returned']);
                        $steps = $isReturnPhase ? ['delivered', 'returning', 'returned'] : ['requested', 'approved', 'active', 'delivered'];
                        $currentIndex = array_search($d['status'], $steps);
                        if($currentIndex === false) $currentIndex = 0;
                        ?>
                        <div class="tracking-steps">
                            <?php foreach($steps as $i => $step): ?>
                                <div class="step <?php echo $i < $currentIndex ? 'completed' : ($i == $currentIndex ? 'active' : ''); ?>">
                                    <div class="step-dot"></div>
                                    <span class="step-label"><?php echo ucfirst($step); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="verification-card">
                            <div style="font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: #94a3b8; margin-bottom: 1rem;">3-Party Verification</div>
                            <?php if (!$isReturnPhase): ?>
                                <div class="v-badge <?php echo !empty($d['lender_confirm_at']) ? 'verified' : ''; ?>">
                                    <i class='bx <?php echo !empty($d['lender_confirm_at']) ? 'bxs-check-circle' : 'bx-circle'; ?>'></i> Sender Confirmation
                                </div>
                                <div class="v-badge <?php echo !empty($d['agent_confirm_delivery_at']) ? 'verified' : ''; ?>">
                                    <i class='bx <?php echo !empty($d['agent_confirm_delivery_at']) ? 'bxs-check-circle' : 'bx-circle'; ?>'></i> Agent Confirmation
                                </div>
                                <div class="v-badge <?php echo !empty($d['borrower_confirm_at']) ? 'verified' : ''; ?>">
                                    <i class='bx <?php echo !empty($d['borrower_confirm_at']) ? 'bxs-check-circle' : 'bx-circle'; ?>'></i> Receiver Confirmation
                                </div>
                            <?php else: ?>
                                <div class="v-badge <?php echo !empty($d['return_borrower_confirm_at']) ? 'verified' : ''; ?>">
                                    <i class='bx <?php echo !empty($d['return_borrower_confirm_at']) ? 'bxs-check-circle' : 'bx-circle'; ?>'></i> Sender (Return) Confirmation
                                </div>
                                <div class="v-badge <?php echo !empty($d['return_agent_confirm_at']) ? 'verified' : ''; ?>">
                                    <i class='bx <?php echo !empty($d['return_agent_confirm_at']) ? 'bxs-check-circle' : 'bx-circle'; ?>'></i> Agent Confirmation
                                </div>
                                <div class="v-badge <?php echo !empty($d['return_lender_confirm_at']) ? 'verified' : ''; ?>">
                                    <i class='bx <?php echo !empty($d['return_lender_confirm_at']) ? 'bxs-check-circle' : 'bx-circle'; ?>'></i> Receiver (Return) Confirmation
                                </div>
                            <?php endif; ?>
                        </div>

                        <div id="map"></div>
                    </div>
                    <?php endif; ?>

                    <div class="actions-bar">
                        <?php if ($d['status'] === 'requested' && $isLender): ?>
                            <button onclick="handleAction('accept_request')" class="btn btn-primary">Accept Request</button>
                            <button onclick="handleAction('decline_request')" class="btn btn-outline" style="color: #ef4444; border-color: #ef4444;">Decline</button>
                        <?php elseif ($isBorrower && ($d['status'] === 'active' || $d['status'] === 'delivered') && empty($d['borrower_confirm_at'])): ?>
                            <button onclick="handleAction('confirm_receipt')" class="btn btn-primary">Confirm Receipt</button>
                        <?php elseif ($isLender && ($d['status'] === 'active' || $d['status'] === 'delivered') && empty($d['lender_confirm_at'])): ?>
                            <button onclick="handleAction('confirm_handover')" class="btn btn-primary">Confirm Handover to Agent</button>
                        <?php endif; ?>
                        <a href="chat/index.php?user=<?php echo $isLender ? $d['borrower_id'] : $d['lender_id']; ?>" class="btn btn-outline">
                            <i class='bx bx-message-square-dots'></i> Chat with <?php echo $isLender ? 'Borrower' : 'Owner'; ?>
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const d = <?php echo json_encode($d); ?>;
        if (d.pickup_lat && d.order_lat) {
            const map = L.map('map').setView([d.pickup_lat, d.pickup_lng], 13);
            L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png').addTo(map);
            
            L.marker([d.pickup_lat, d.pickup_lng]).addTo(map).bindPopup('Pickup Location');
            L.marker([d.order_lat, d.order_lng]).addTo(map).bindPopup('Delivery Location');
            
            const line = L.polyline([[d.pickup_lat, d.pickup_lng], [d.order_lat, d.order_lng]], {color: 'var(--primary)', dashArray: '5, 10'}).addTo(map);
            map.fitBounds(line.getBounds().pad(0.2));
        }

        async function handleAction(action) {
            if (!confirm('Confirm this action?')) return;
            
            const formData = new FormData();
            formData.append('action', action);
            formData.append('transaction_id', d.id);

            try {
                const response = await fetch('../actions/request_action.php', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) {
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (err) {
                alert('Connection failed');
            }
        }
    </script>
</body>
</html>
