<?php
require_once 'includes/db_helper.php';
require_once 'paths.php';
include 'includes/dashboard_header.php';

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
    header("Location: login.php");
    exit();
}

$deliveries = getUserDeliveries($userId);
$lending = array_filter($deliveries, fn($d) => $d['lender_id'] == $userId);
$borrowing = array_filter($deliveries, fn($d) => $d['borrower_id'] == $userId);

function getStatusLabel($status, $agentId) {
    switch ($status) {
        case 'requested': return 'Waiting for Owner';
        case 'approved': return $agentId ? 'Agent Assigned' : 'Finding Agent';
        case 'active': return 'In Transit';
        case 'delivered': return 'Delivered & Verified';
        default: return ucfirst($status);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Deliveries | BOOK-B</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        :root {
            --glass-bg: rgba(255, 255, 255, 0.9);
            --glass-border: rgba(255, 255, 255, 0.4);
            --success-logistics: #10b981;
        }

        .tracking-wrapper { max-width: 1000px; margin: 0 auto; padding: 0 1.5rem; }
        .page-header { margin-bottom: 2.5rem; text-align: left; }
        
        .tabs-header {
            display: flex; gap: 1rem; margin-bottom: 2.5rem;
            background: rgba(241, 245, 249, 0.8); backdrop-filter: blur(8px);
            padding: 0.6rem; border-radius: 18px; width: fit-content;
            border: 1px solid var(--glass-border);
        }
        .tab-btn {
            padding: 0.7rem 1.8rem; font-weight: 800; color: #64748b;
            cursor: pointer; transition: all 0.3s; border-radius: 14px;
            background: none; border: none; font-size: 0.9rem;
        }
        .tab-btn.active { background: white; color: var(--primary); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }

        .delivery-card {
            background: var(--glass-bg); backdrop-filter: blur(12px);
            border-radius: 24px; border: 1px solid var(--glass-border);
            padding: 2rem; margin-bottom: 2rem; transition: all 0.3s;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03); position: relative;
            overflow: hidden;
        }
        .delivery-card:hover { transform: translateY(-5px); box-shadow: 0 15px 40px rgba(0,0,0,0.08); }

        .status-badge {
            position: absolute; top: 1.5rem; right: 1.5rem;
            padding: 0.6rem 1.2rem; border-radius: 14px; font-size: 0.75rem;
            font-weight: 800; text-transform: uppercase; letter-spacing: 0.8px;
        }
        .status-badge.requested { background: #eff6ff; color: #3b82f6; }
        .status-badge.approved { background: #f0fdf4; color: #16a34a; }
        .status-badge.active { background: #fff7ed; color: #ea580c; }
        .status-badge.delivered { background: #f0fdf4; color: #16a34a; }

        .credit-info-tag {
            background: #f8fafc; border: 1px solid #e2e8f0;
            padding: 0.5rem 1rem; border-radius: 12px;
            display: inline-flex; align-items: center; gap: 0.5rem;
            font-size: 0.8rem; font-weight: 700; color: #64748b;
            margin-bottom: 1.5rem;
        }

        .delivery-main { display: grid; grid-template-columns: 140px 1fr; gap: 2.5rem; margin-bottom: 2.5rem; }
        .book-aside { width: 100%; position: relative; }
        .book-img { 
            width: 100%; aspect-ratio: 2/3; object-fit: cover; border-radius: 16px; 
            box-shadow: 0 8px 25px rgba(0,0,0,0.1); 
        }
        
        .delivery-body { flex: 1; }
        .order-meta { font-size: 0.75rem; color: #94a3b8; margin-bottom: 0.5rem; display: block; font-weight: 800; letter-spacing: 0.5px; }
        .book-title { font-size: 1.6rem; font-weight: 900; margin-bottom: 0.75rem; color: #1e293b; line-height: 1.1; }
        
        .location-info { 
            display: grid; grid-template-columns: 1fr; gap: 0.8rem; 
            margin-top: 1.5rem; background: white; padding: 1.25rem; 
            border-radius: 18px; border: 1px solid #f1f5f9;
        }
        .loc-item { display: flex; align-items: flex-start; gap: 0.75rem; font-size: 0.95rem; color: #475569; }
        .loc-item i { margin-top: 4px; color: var(--primary); font-size: 1.2rem; }
        .landmark-tag { font-size: 0.75rem; color: var(--primary); font-weight: 800; margin-top: 4px; background: #eef2ff; padding: 2px 10px; border-radius: 6px; display: inline-block; }

        /* Progress Steps */
        .tracking-steps { display: flex; justify-content: space-between; position: relative; margin: 3.5rem 0 1.5rem 0; width: 100%; }
        .tracking-steps::before {
            content: ''; position: absolute; top: 12px; left: 0; 
            width: 100%; height: 3px; background: #f1f5f9; z-index: 1;
        }
        .step { position: relative; z-index: 2; display: flex; flex-direction: column; align-items: center; gap: 0.75rem; flex: 1; }
        .step-dot { 
            width: 26px; height: 26px; background: white; border: 3px solid #f1f5f9; 
            border-radius: 50%; transition: all 0.4s; 
        }
        .step-label { font-size: 0.65rem; font-weight: 900; color: #cbd5e1; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .step.completed .step-dot { background: var(--primary); border-color: var(--primary); box-shadow: 0 0 0 5px rgba(99, 102, 241, 0.15); }
        .step.completed .step-label { color: var(--primary); }
        .step.active .step-dot { background: white; border-color: var(--primary); border-width: 6px; box-shadow: 0 0 0 8px rgba(99, 102, 241, 0.05); }
        .step.active .step-label { color: #1e293b; }

        .confirmation-box {
            background: rgba(248, 250, 252, 0.6); border-radius: 20px; padding: 1.5rem;
            margin-top: 2rem; border: 1px solid #f1f5f9;
        }
        .confirm-title { font-size: 0.75rem; font-weight: 900; color: #64748b; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.6rem; letter-spacing: 0.5px; }
        
        .confirm-badges { display: flex; gap: 1.25rem; margin-bottom: 1.5rem; }
        .c-badge { 
            flex: 1; display: flex; align-items: center; gap: 0.75rem; 
            background: white; padding: 1rem; border-radius: 14px; border: 1px solid #eef2f6;
            font-size: 0.85rem; font-weight: 700; color: #94a3b8; box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        }
        .c-badge.verified { border-color: var(--success-logistics); color: var(--success-logistics); background: #f0fdf4; }
        .c-badge i { font-size: 1.3rem; }

        .btn-confirm {
            display: flex; align-items: center; justify-content: center; gap: 0.6rem;
            width: 100%; padding: 1.1rem; border-radius: 16px; border: none;
            font-weight: 800; cursor: pointer; transition: all 0.3s;
            font-size: 1rem;
        }
        .btn-confirm.primary { background: var(--primary); color: white; }
        .btn-confirm.primary:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(99, 102, 241, 0.3); }
        
        .mini-map {
            height: 250px; width: 100%; border-radius: 20px;
            margin-top: 1.5rem; border: 4px solid white; box-shadow: var(--shadow-md); z-index: 1;
        }
        .empty-state { text-align: center; padding: 5rem 2rem; color: #94a3b8; }
        .empty-state i { font-size: 5rem; margin-bottom: 1.5rem; opacity: 0.2; }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/dashboard_sidebar.php'; ?>

        <main class="main-content">
            <div class="tracking-wrapper">
                <div class="page-header">
                    <h1>Delivery Tracking</h1>
                    <p style="color: #64748b; font-weight: 500;">Secure 3-party verified shipments</p>
                </div>

                <div class="tabs-header">
                    <button class="tab-btn active" onclick="switchTab('borrowing', this)">
                        Incoming <span style="font-size: 0.75rem; opacity: 0.6; font-weight: 600;">(<?php echo count($borrowing); ?>)</span>
                    </button>
                    <button class="tab-btn" onclick="switchTab('lending', this)">
                        Outgoing <span style="font-size: 0.75rem; opacity: 0.6; font-weight: 600;">(<?php echo count($lending); ?>)</span>
                    </button>
                </div>

                <div id="borrowing-list">
                    <?php if (empty($borrowing)): ?>
                        <div class="empty-state">
                            <i class='bx bx-package'></i>
                            <h3>No incoming deliveries</h3>
                            <p>Books you borrow will appear here for tracking.</p>
                        </div>
                    <?php endif; ?>
                    <?php foreach ($borrowing as $d): ?>
                        <?php renderEnhancedDeliveryCard($d); ?>
                    <?php endforeach; ?>
                </div>

                <div id="lending-list" style="display: none;">
                    <?php if (empty($lending)): ?>
                        <div class="empty-state">
                            <i class='bx bx-transfer-alt'></i>
                            <h3>No outgoing deliveries</h3>
                            <p>Books you lend via delivery agents will appear here.</p>
                        </div>
                    <?php endif; ?>
                    <?php foreach ($lending as $d): ?>
                        <?php renderEnhancedDeliveryCard($d); ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>

    <?php
    function renderEnhancedDeliveryCard($d) {
        $userId = $_SESSION['user_id'];
        $isBorrower = ($d['borrower_id'] == $userId);
        
        // Progress Logic
        $steps = ['requested', 'approved', 'active', 'delivered'];
        $currentIndex = array_search($d['status'], $steps);
        if ($currentIndex === false) $currentIndex = 0;
        
        // Custom labels for the badge
        $badgeLabel = getStatusLabel($d['status'], $d['delivery_agent_id']);
        if ($d['status'] === 'active' && $d['agent_confirm_delivery_at']) {
            $badgeLabel = "Awaiting Your Confirmation";
        }
        ?>
        <div class="delivery-card">
            <?php if ($isBorrower): ?>
                <div class="credit-info-tag">
                    <i class='bx bxs-coin-stack' style="color: #f59e0b;"></i>
                    10 Credits Delivery Fee Paid
                </div>
            <?php elseif ($d['transaction_type'] === 'purchase'): ?>
                <div class="credit-info-tag">
                    <i class='bx bxs-coin-stack' style="color: #10b981;"></i>
                    Reward: <?php echo $d['credit_cost'] ?? 10; ?> Credits upon delivery
                </div>
            <?php endif; ?>

            <span class="status-badge <?php echo $d['status']; ?>">
                <?php echo $badgeLabel; ?>
            </span>

            <div class="delivery-main">
                <div class="book-aside">
                    <img src="<?php echo htmlspecialchars($d['cover_image'] ?: 'assets/images/book-placeholder.jpg'); ?>" class="book-img">
                </div>
                
                <div class="delivery-body">
                    <span class="order-meta">#ORD-<?php echo $d['id']; ?> • <?php echo date('M d, Y', strtotime($d['created_at'])); ?></span>
                    <h2 class="book-title"><?php echo htmlspecialchars($d['title']); ?></h2>
                    
                    <div class="location-info">
                        <div class="loc-item">
                            <i class='bx bx-map-pin'></i>
                            <div>
                                <strong>Destination:</strong><br>
                                <?php echo htmlspecialchars($d['order_address']); ?>
                                <?php if ($d['order_landmark']): ?>
                                    <br><span class="landmark-tag">Near <?php echo htmlspecialchars($d['order_landmark']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="tracking-steps">
                        <div class="step <?php echo $currentIndex >= 0 ? ($currentIndex > 0 ? 'completed' : 'active') : ''; ?>">
                            <div class="step-dot"></div>
                            <span class="step-label">Requested</span>
                        </div>
                        <div class="step <?php echo $currentIndex >= 1 ? ($currentIndex > 1 ? 'completed' : 'active') : ''; ?>">
                            <div class="step-dot"></div>
                            <span class="step-label">Assigned</span>
                        </div>
                        <div class="step <?php echo $currentIndex >= 2 ? ($currentIndex > 2 ? 'completed' : 'active') : ''; ?>">
                            <div class="step-dot"></div>
                            <span class="step-label">In Transit</span>
                        </div>
                        <div class="step <?php echo $currentIndex >= 3 ? 'completed' : ''; ?>">
                            <div class="step-dot"></div>
                            <span class="step-label">Delivered</span>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($d['pickup_lat'] && $d['order_lat']): ?>
                <div id="map-<?php echo $d['id']; ?>" class="mini-map"></div>
            <?php endif; ?>

            <div class="confirmation-box">
                <div class="confirm-title">
                    <i class='bx bx-shield-quarter'></i> 3-PARTY VERIFICATION STATUS
                </div>
                
                <div class="confirm-badges">
                    <div class="c-badge <?php echo !empty($d['lender_confirm_at']) ? 'verified' : ''; ?>">
                        <i class='bx <?php echo !empty($d['lender_confirm_at']) ? 'bxs-check-circle' : 'bx-circle'; ?>'></i>
                        <span>Sender Confirmation</span>
                    </div>
                    <div class="c-badge <?php echo !empty($d['agent_confirm_delivery_at']) ? 'verified' : ''; ?>">
                        <i class='bx <?php echo !empty($d['agent_confirm_delivery_at']) ? 'bxs-check-circle' : 'bx-circle'; ?>'></i>
                        <span>Agent Confirmation</span>
                    </div>
                    <div class="c-badge <?php echo !empty($d['borrower_confirm_at']) ? 'verified' : ''; ?>">
                        <i class='bx <?php echo !empty($d['borrower_confirm_at']) ? 'bxs-check-circle' : 'bx-circle'; ?>'></i>
                        <span>Receiver Confirmation</span>
                    </div>
                </div>

                <div class="actions">
                    <?php if ($d['status'] === 'requested' && $d['lender_id'] == $userId): ?>
                        <div style="display: flex; gap: 1rem;">
                            <button onclick="confirmAction(<?php echo $d['id']; ?>, 'accept_request')" class="btn-confirm primary">
                                <i class='bx bx-check'></i> Accept Request
                            </button>
                            <button onclick="confirmAction(<?php echo $d['id']; ?>, 'decline_request')" class="btn-confirm" style="border: 1px solid #e2e8f0;">
                                Decline
                            </button>
                        </div>

                    <?php elseif ($isBorrower && $d['status'] === 'active' && empty($d['borrower_confirm_at'])): ?>
                        <button onclick="confirmAction(<?php echo $d['id']; ?>, 'confirm_receipt')" class="btn-confirm primary">
                            <i class='bx bx-check-shield'></i> I Received My Book
                        </button>
                    
                    <?php elseif (!$isBorrower && $d['status'] === 'active' && empty($d['lender_confirm_at'])): ?>
                        <button onclick="confirmAction(<?php echo $d['id']; ?>, 'confirm_handover')" class="btn-confirm primary">
                            <i class='bx bx-hand'></i> Confirm Handover to Agent
                        </button>

                    <?php elseif ($d['status'] === 'delivered'): ?>
                        <div style="text-align: center; color: #10b981; font-weight: 700; font-size: 0.9rem;">
                            <i class='bx bxs-badge-check'></i> TRANSACTION COMPLETED SUCCESSFULLY
                        </div>
                    
                    <?php elseif ($d['delivery_agent_id']): ?>
                        <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.85rem;">
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <div style="width: 35px; height: 35px; background: #eef2ff; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary);">
                                    <i class='bx bxs-user'></i>
                                </div>
                                <div>
                                    <strong style="display: block;"><?php echo htmlspecialchars($d['agent_name']); ?></strong>
                                    <span style="color: #64748b;">Delivery Agent Assigned</span>
                                </div>
                            </div>
                            <a href="tel:<?php echo $d['agent_phone']; ?>" style="color: var(--primary); font-weight: 700; text-decoration: none; display: flex; align-items: center; gap: 0.5rem;">
                                <i class='bx bx-phone'></i> <?php echo htmlspecialchars($d['agent_phone']); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    ?>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const deliveriesData = <?php echo json_encode($deliveries); ?>;

        function initMaps() {
            deliveriesData.forEach(d => {
                const mapId = `map-${d.id}`;
                const container = document.getElementById(mapId);
                if (!container) return;

                const map = L.map(mapId, {
                    zoomControl: false,
                    attributionControl: false
                }).setView([d.pickup_lat, d.pickup_lng], 13);

                L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png').addTo(map);

                const iconBase = 'display:flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:50%; border:3px solid white; box-shadow:0 2px 10px rgba(0,0,0,0.2); font-size:16px;';
                
                L.marker([d.pickup_lat, d.pickup_lng], {
                    icon: L.divIcon({
                        className: 'custom-div-icon',
                        html: `<div style="${iconBase} background:#3b82f6; color:white;"><i class='bx bx-package'></i></div>`,
                        iconSize: [28, 28],
                        iconAnchor: [14, 14]
                    })
                }).addTo(map);

                L.marker([d.order_lat, d.order_lng], {
                    icon: L.divIcon({
                        className: 'custom-div-icon',
                        html: `<div style="${iconBase} background:#10b981; color:white;"><i class='bx bx-home'></i></div>`,
                        iconSize: [28, 28],
                        iconAnchor: [14, 14]
                    })
                }).addTo(map);

                const line = L.polyline([[d.pickup_lat, d.pickup_lng], [d.order_lat, d.order_lng]], {
                    color: '#6366f1',
                    weight: 3,
                    opacity: 0.6,
                    dashArray: '8, 12'
                }).addTo(map);

                map.fitBounds(line.getBounds().pad(0.4));
            });
        }

        document.addEventListener('DOMContentLoaded', initMaps);

        function switchTab(tab, el) {
            document.querySelectorAll('.tab-btn').forEach(t => t.classList.remove('active'));
            el.classList.add('active');
            
            document.getElementById('borrowing-list').style.display = tab === 'borrowing' ? 'block' : 'none';
            document.getElementById('lending-list').style.display = tab === 'lending' ? 'block' : 'none';
            
            setTimeout(() => {
                window.dispatchEvent(new Event('resize')); 
            }, 100);
        }

        function confirmAction(txId, action) {
            const msgs = {
                'confirm_receipt': 'Confirming receipt will verify the delivery and build trust. Proceed?',
                'confirm_handover': 'Confirm you have handed over the book to the agent?',
                'accept_request': 'Accept this delivery request?'
            };
            
            if (!confirm(msgs[action] || 'Confirm this action?')) return;
            
            const formData = new FormData();
            formData.append('action', action);
            formData.append('transaction_id', txId);

            fetch('request_action.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
        }
    </script>
</body>
</html>
