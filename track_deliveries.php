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

function getStatusProgress($status, $agentId) {
    switch ($status) {
        case 'requested': return 10;
        case 'approved': return $agentId ? 40 : 25;
        case 'active': return 75;
        case 'delivered': return 100;
        default: return 0;
    }
}

function getStatusLabel($status, $agentId) {
    switch ($status) {
        case 'requested': return 'Waiting for Owner';
        case 'approved': return $agentId ? 'Agent Assigned' : 'Finding Agent';
        case 'active': return 'Out for Delivery';
        case 'delivered': return 'Delivered';
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
        .tracking-wrapper { max-width: 1000px; margin: 0 auto; }
        .page-header { margin-bottom: 2rem; }
        
        .tabs-header {
            display: flex; gap: 2rem; margin-bottom: 2rem;
            border-bottom: 1px solid var(--border-color);
        }
        .tab-btn {
            padding: 1rem 0; font-weight: 700; color: var(--text-muted);
            cursor: pointer; transition: all 0.3s; position: relative;
            background: none; border: none; font-size: 1rem;
        }
        .tab-btn.active { color: var(--primary); }
        .tab-btn.active::after {
            content: ''; position: absolute; bottom: -1px; left: 0;
            width: 100%; height: 3px; background: var(--primary); border-radius: 3px;
        }

        .delivery-card {
            background: white; border-radius: var(--radius-lg); border: 1px solid var(--border-color);
            padding: 1.5rem; margin-bottom: 1.5rem; transition: all 0.3s;
        }
        .delivery-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }

        .delivery-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #f1f5f9;
        }
        .order-id { font-family: monospace; font-weight: 700; color: var(--text-muted); }

        .delivery-content { display: grid; grid-template-columns: 80px 1fr 200px; gap: 1.5rem; align-items: start; }
        .book-img { width: 80px; height: 110px; object-fit: cover; border-radius: 8px; box-shadow: var(--shadow-sm); }
        
        .tracking-info { flex: 1; }
        .book-title { font-size: 1.1rem; font-weight: 800; margin-bottom: 0.5rem; }
        
        /* Progress Bar */
        .progress-container { margin: 1.5rem 0; position: relative; }
        .progress-bg { height: 8px; background: #f1f5f9; border-radius: 10px; overflow: hidden; }
        .progress-fill { 
            height: 100%; background: var(--primary); width: 0%; 
            transition: width 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275); 
        }
        .progress-labels { display: flex; justify-content: space-between; margin-top: 0.8rem; font-size: 0.75rem; color: var(--text-muted); font-weight: 600; }
        .progress-step.active { color: var(--primary); }

        .agent-info {
            background: #f8fafc; padding: 1rem; border-radius: var(--radius-md);
            border: 1px solid #e2e8f0; font-size: 0.85rem;
        }
        .agent-title { font-weight: 700; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.4rem; color: var(--primary); }
        .agent-name { font-weight: 700; display: block; margin-bottom: 0.3rem; }
        .contact-btn {
            display: inline-flex; align-items: center; gap: 0.4rem;
            color: var(--primary); font-weight: 700; text-decoration: none; margin-top: 0.5rem;
        }

        .empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-muted); }

        .mini-map {
            height: 150px; width: 100%; border-radius: 12px;
            margin-top: 1rem; border: 1px solid var(--border-color);
            z-index: 1;
        }
        .marker-pin { display: flex; align-items: center; justify-content: center; background: white; border-radius: 50%; border: 2px solid var(--primary); box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/dashboard_sidebar.php'; ?>

        <main class="main-content">
            <div class="tracking-wrapper">
                <div class="page-header">
                    <h1>Delivery Tracking</h1>
                    <p>Track your book shipments in real-time</p>
                </div>

                <div class="tabs-header">
                    <button class="tab-btn active" onclick="switchTab('borrowing', this)">
                        Incoming Books <span style="font-size: 0.8rem; opacity: 0.6;">(<?php echo count($borrowing); ?>)</span>
                    </button>
                    <button class="tab-btn" onclick="switchTab('lending', this)">
                        Outgoing Books <span style="font-size: 0.8rem; opacity: 0.6;">(<?php echo count($lending); ?>)</span>
                    </button>
                </div>

                <div id="borrowing-list">
                    <?php if (empty($borrowing)): ?>
                        <div class="empty-state">
                            <i class='bx bx-package' style="font-size: 4rem; opacity: 0.2;"></i>
                            <p>No incoming deliveries at the moment.</p>
                        </div>
                    <?php endif; ?>
                    <?php foreach ($borrowing as $d): ?>
                        <?php renderDeliveryCard($d); ?>
                    <?php endforeach; ?>
                </div>

                <div id="lending-list" style="display: none;">
                    <?php if (empty($lending)): ?>
                        <div class="empty-state">
                            <i class='bx bx-transfer-alt' style="font-size: 4rem; opacity: 0.2;"></i>
                            <p>No outgoing deliveries at the moment.</p>
                        </div>
                    <?php endif; ?>
                    <?php foreach ($lending as $d): ?>
                        <?php renderDeliveryCard($d); ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>

    <?php
    function renderDeliveryCard($d) {
        $progress = getStatusProgress($d['status'], $d['delivery_agent_id']);
        $statusLabel = getStatusLabel($d['status'], $d['delivery_agent_id']);
        ?>
        <div class="delivery-card">
            <div class="delivery-header">
                <div>
                    <span class="order-id">#TRK-<?php echo $d['id']; ?></span>
                    <span style="font-size: 0.8rem; color: var(--text-muted); margin-left: 1rem;">
                        Ordered on <?php echo date('M d, Y', strtotime($d['created_at'])); ?>
                    </span>
                </div>
                <div style="font-weight: 800; color: var(--primary); font-size: 0.9rem;">
                    <?php echo $statusLabel; ?>
                </div>
            </div>
            
            <div class="delivery-content">
                <img src="<?php echo htmlspecialchars($d['cover_image'] ?: 'assets/images/book-placeholder.jpg'); ?>" class="book-img">
                
                <div class="tracking-info">
                    <div class="book-title"><?php echo htmlspecialchars($d['title']); ?></div>
                    <div style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 0.25rem;">
                        <i class='bx bx-map'></i> <?php echo $d['borrower_id'] == $_SESSION['user_id'] ? 'Coming to: ' . htmlspecialchars($d['order_address']) : 'Picking up from: ' . htmlspecialchars($d['pickup_location']); ?>
                    </div>
                    <?php if ($d['borrower_id'] == $_SESSION['user_id'] && $d['order_landmark']): ?>
                        <div style="font-size: 0.8rem; color: var(--primary); font-weight: 600; margin-left: 1.5rem;">
                            Reference Point: <?php echo htmlspecialchars($d['order_landmark']); ?>
                        </div>
                    <?php elseif ($d['lender_id'] == $_SESSION['user_id'] && $d['pickup_landmark']): ?>
                        <div style="font-size: 0.8rem; color: var(--primary); font-weight: 600; margin-left: 1.5rem;">
                            Reference Point: <?php echo htmlspecialchars($d['pickup_landmark']); ?>
                        </div>
                    <?php endif; ?>

                    <div class="progress-container">
                        <div class="progress-bg">
                            <div class="progress-fill" style="width: <?php echo $progress; ?>%;"></div>
                        </div>
                        <div class="progress-labels">
                            <span class="progress-step <?php echo $progress >= 10 ? 'active' : ''; ?>">Requested</span>
                            <span class="progress-step <?php echo $progress >= 40 ? 'active' : ''; ?>">Assigned</span>
                            <span class="progress-step <?php echo $progress >= 75 ? 'active' : ''; ?>">In Transit</span>
                            <span class="progress-step <?php echo $progress >= 100 ? 'active' : ''; ?>">Delivered</span>
                        </div>
                    </div>

                    <?php if ($d['pickup_lat'] && $d['order_lat']): ?>
                        <div id="map-<?php echo $d['id']; ?>" class="mini-map"></div>
                    <?php endif; ?>
                </div>

                <div class="agent-details">
                <div class="agent-details">
                    <?php if ($d['status'] === 'requested'): ?>
                        <div class="agent-info" style="border: 1px solid #e0f2fe; background: #f0f9ff;">
                            <div class="agent-title" style="color: #0284c7;">
                                <i class='bx bx-time-five'></i> Request Pending
                            </div>
                            
                            <?php if ($d['lender_id'] == $_SESSION['user_id']): ?>
                                <p style="margin:0 0 0.8rem 0; font-size: 0.8rem;">Do you want to accept this request?</p>
                                <div style="display: flex; gap: 0.5rem;">
                                    <button onclick="confirmAction(<?php echo $d['id']; ?>, 'accept_request')" class="btn-sm" style="background:var(--primary); color:white; border:none; border-radius:4px; padding:0.4rem 0.8rem; flex:1; cursor:pointer;">
                                        Accept
                                    </button>
                                    <button onclick="confirmAction(<?php echo $d['id']; ?>, 'decline_request')" class="btn-sm" style="background:white; color:#ef4444; border:1px solid #ef4444; border-radius:4px; padding:0.4rem 0.8rem; flex:1; cursor:pointer;">
                                        Decline
                                    </button>
                                </div>
                            <?php else: ?>
                                <p style="margin:0; font-size: 0.75rem;">Waiting for the owner to accept your request.</p>
                            <?php endif; ?>
                        </div>

                    <?php elseif ($d['delivery_agent_id']): ?>
                        <div class="agent-info">
                            <div class="agent-title"><i class='bx bxs-user-check'></i> Delivery Hero</div>
                            <span class="agent-name"><?php echo htmlspecialchars($d['agent_name']); ?></span>
                            <div style="font-size: 0.75rem; color: var(--text-muted);">Assigned to your delivery</div>
                            <a href="tel:<?php echo $d['agent_phone']; ?>" class="contact-btn">
                                <i class='bx bx-phone'></i> Call Agent
                            </a>
                        </div>

                    <?php else: ?>
                        <!-- Status is 'approved' but no agent yet -->
                        <div class="agent-info" style="border-style: dashed; background: transparent; opacity: 0.7;">
                            <div class="agent-title" style="color: var(--text-muted);"><i class='bx bx-search'></i> Finding Agent</div>
                            <p style="margin:0; font-size: 0.75rem;">Nearby agents are being notified of the request.</p>
                        </div>
                    <?php endif; ?>

                    <!-- User Confirmation UI -->
                    <?php if ($d['delivery_agent_id']): // Only if agent is involved ?>
                        <div style="margin-top: 1rem;">
                            <!-- Lender Confirmation -->
                            <?php if ($d['lender_id'] == $_SESSION['user_id']): ?>
                                <?php if ($d['lender_confirm_at']): ?>
                                    <div style="color: #16a34a; font-size: 0.8rem; font-weight: 700;">
                                        <i class='bx bx-check-double'></i> Handover Verified
                                    </div>
                                <?php elseif (in_array($d['status'], ['approved', 'active'])): // Agent assigned or picked up ?>
                                    <button onclick="confirmAction(<?php echo $d['id']; ?>, 'confirm_handover')" class="btn-sm" style="background:var(--primary); color:white; border:none; border-radius:4px; padding:0.4rem 0.8rem; width:100%; cursor:pointer;">
                                        Confirm Handover
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>

                            <!-- Borrower Confirmation -->
                            <?php if ($d['borrower_id'] == $_SESSION['user_id']): ?>
                                <?php if ($d['borrower_confirm_at']): ?>
                                    <div style="color: #16a34a; font-size: 0.8rem; font-weight: 700;">
                                        <i class='bx bx-check-double'></i> Receipt Confirmed
                                    </div>
                                <?php elseif (in_array($d['status'], ['approved', 'active', 'delivered'])): // Agent assigned/in-transit/delivered ?>
                                    <button onclick="confirmAction(<?php echo $d['id']; ?>, 'confirm_receipt')" class="btn-sm" style="background: #10b981; color:white; border:none; border-radius:4px; padding:0.4rem 0.8rem; width:100%; cursor:pointer;">
                                        <i class='bx bx-package'></i> I Received
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
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

                const iconBase = 'display:flex; align-items:center; justify-content:center; width:24px; height:24px; border-radius:50%; border:2px solid white; box-shadow:0 2px 10px rgba(0,0,0,0.2); font-size:14px;';
                
                // Pickup Marker
                L.marker([d.pickup_lat, d.pickup_lng], {
                    icon: L.divIcon({
                        className: 'custom-div-icon',
                        html: `<div style="${iconBase} background:#3b82f6; color:white;"><i class='bx bx-package'></i></div>`,
                        iconSize: [24, 24],
                        iconAnchor: [12, 12]
                    })
                }).addTo(map).bindPopup("Pickup: " + d.pickup_location);

                // Dropoff Marker
                L.marker([d.order_lat, d.order_lng], {
                    icon: L.divIcon({
                        className: 'custom-div-icon',
                        html: `<div style="${iconBase} background:#10b981; color:white;"><i class='bx bx-home'></i></div>`,
                        iconSize: [24, 24],
                        iconAnchor: [12, 12]
                    })
                }).addTo(map).bindPopup("Dropoff: " + d.order_address);

                // Route Line
                const line = L.polyline([[d.pickup_lat, d.pickup_lng], [d.order_lat, d.order_lng]], {
                    color: '#6366f1',
                    weight: 3,
                    opacity: 0.6,
                    dashArray: '5, 10'
                }).addTo(map);

                map.fitBounds(line.getBounds().pad(0.3));
            });
        }

        document.addEventListener('DOMContentLoaded', initMaps);

        function switchTab(tab, el) {
            document.querySelectorAll('.tab-btn').forEach(t => t.classList.remove('active'));
            el.classList.add('active');
            
            document.getElementById('borrowing-list').style.display = tab === 'borrowing' ? 'block' : 'none';
            document.getElementById('lending-list').style.display = tab === 'lending' ? 'block' : 'none';
            
            // Fix for Leaflet maps in hidden tabs
            setTimeout(() => {
                deliveriesData.forEach(d => {
                    const mapId = `map-${d.id}`;
                    const container = document.getElementById(mapId);
                    if (container && container.offsetParent !== null) {
                        // Invalidate size if map instance is stored, or just rely on CSS
                    }
                });
                window.dispatchEvent(new Event('resize')); 
            }, 100);
        }

        function confirmAction(txId, action) {
            if (!confirm('Confirm this action? This helps build trust.')) return;
            
            const formData = new FormData();
            formData.append('action', action);
            formData.append('transaction_id', txId);

            fetch('request_action.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
        }
    </script>
</body>
</html>
