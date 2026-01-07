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

$pdo = getDBConnection();
$agent = getUserById($userId);

if (!$agent) {
    header("Location: login.php");
    exit();
}

$aLatStart = $agent['service_start_lat'] ?? null;
$aLngStart = $agent['service_start_lng'] ?? null;
$aLatEnd = $agent['service_end_lat'] ?? null;
$aLngEnd = $agent['service_end_lng'] ?? null;
$isOnline = $agent['is_accepting_deliveries'] ?? 0;

// Fetch active tasks
$stmt = $pdo->prepare("
    SELECT t.*, b.title, b.cover_image,
           u_borrower.firstname as borrower_fname, u_borrower.lastname as borrower_lname, u_borrower.phone as borrower_phone,
           u_lender.firstname as lender_fname, u_lender.lastname as lender_lname, u_lender.phone as lender_phone,
           l.location as pickup_location, l.latitude as pickup_lat, l.longitude as pickup_lng
    FROM transactions t
    JOIN listings l ON t.listing_id = l.id
    JOIN books b ON l.book_id = b.id
    JOIN users u_borrower ON t.borrower_id = u_borrower.id
    JOIN users u_lender ON t.lender_id = u_lender.id
    WHERE t.delivery_method = 'delivery' 
    AND (
        (t.status = 'approved' AND t.delivery_agent_id IS NULL) -- Available for pickup
        OR 
        (t.status IN ('active', 'approved') AND t.delivery_agent_id = ?) -- Assigned to me
    )
    ORDER BY CASE WHEN t.status = 'active' THEN 1 ELSE 2 END, t.created_at DESC
");
$stmt->execute([$userId]);
$all_deliveries = $stmt->fetchAll();

// Filter based on route (simplified for now, can be strict)
$my_deliveries = [];
$available_deliveries = [];

foreach ($all_deliveries as $d) {
    if ($d['delivery_agent_id'] == $userId) {
        $my_deliveries[] = $d;
    } else {
        // Filter available ones by route proximity
        if (!$aLatStart || !$aLatEnd) {
             $available_deliveries[] = $d; // Show all if no route set
        } else {
            // Distance check
            $distToStart = calculateDistance($d['pickup_lat'], $d['pickup_lng'], $aLatStart, $aLngStart);
            $distToEnd = calculateDistance($d['pickup_lat'], $d['pickup_lng'], $aLatEnd, $aLngEnd);
            if ($distToStart < 20 || $distToEnd < 20) {
                $available_deliveries[] = $d;
            }
        }
    }
}

$active_count = count(array_filter($my_deliveries, fn($d) => $d['status'] === 'active'));
$completed_today = 0; // Placeholder for now

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Hub | BOOK-B</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        :root {
            --primary-logistics: #2563eb;
            --success-logistics: #16a34a;
            --bg-card: #ffffff;
        }
        .dashboard-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;
        }
        .status-toggle {
            display: flex; align-items: center; gap: 0.5rem;
            background: white; padding: 0.5rem 1rem; border-radius: 50px;
            box-shadow: var(--shadow-sm); cursor: pointer; border: 1px solid var(--border-color);
        }
        .status-indicator {
            width: 10px; height: 10px; border-radius: 50%;
            background: #ccc; transition: all 0.3s;
        }
        .status-active .status-indicator { background: var(--success-logistics); box-shadow: 0 0 8px var(--success-logistics); }
        .stats-row {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 1rem; margin-bottom: 2rem;
        }
        .stat-card {
            background: var(--bg-card); padding: 1.25rem; border-radius: var(--radius-lg);
            border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);
        }
        .stat-num { font-size: 1.8rem; font-weight: 800; color: var(--text-main); line-height: 1.2; }
        .stat-label { font-size: 0.85rem; color: var(--text-muted); }
        
        .section-title { font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        
        .job-card {
            background: white; border-radius: var(--radius-lg); border: 1px solid var(--border-color);
            margin-bottom: 1rem; overflow: hidden; box-shadow: var(--shadow-sm);
        }
        .job-header {
            padding: 1rem; background: #f8fafc; border-bottom: 1px solid var(--border-color);
            display: flex; justify-content: space-between; align-items: center;
        }
        .job-id { font-family: monospace; font-weight: 600; color: var(--text-muted); }
        .job-status { font-size: 0.75rem; padding: 0.25rem 0.75rem; border-radius: 20px; font-weight: 700; text-transform: uppercase; }
        .status-approved { background: #fef3c7; color: #b45309; }
        .status-active { background: #dbf4ff; color: #0070f3; }

        .job-body { padding: 1.5rem; }
        .route-visual {
            position: relative; padding-left: 2rem; margin: 0.5rem 0 1.5rem 0;
            border-left: 2px dashed #e2e8f0; margin-left: 0.6rem;
        }
        .route-point { position: relative; margin-bottom: 1.5rem; pl: 1rem; }
        .route-point:last-child { margin-bottom: 0; }
        .point-icon {
            position: absolute; left: -2.7rem; top: 0; width: 30px; height: 30px;
            background: white; border: 2px solid #cbd5e1; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; color: var(--text-muted);
            font-size: 1.2rem;
        }
        .point-icon.active { border-color: var(--primary-logistics); color: var(--primary-logistics); }
        
        .action-bar {
            padding: 1rem; border-top: 1px solid var(--border-color);
            display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;
        }
        
        .btn-action {
            width: 100%; padding: 0.8rem; border-radius: var(--radius-md); font-weight: 600;
            display: flex; align-items: center; justify-content: center; gap: 0.5rem;
            cursor: pointer; border: none; transition: all 0.2s;
        }
        .btn-nav { background: #f1f5f9; color: var(--text-main); }
        .btn-primary-action { background: var(--primary); color: white; }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/dashboard_sidebar.php'; ?>
        
        <main class="main-content">
            <div class="dashboard-header">
                <div>
                    <h1 style="margin:0; font-size: 1.8rem;">Agent Hub</h1>
                    <p style="margin:0; color: var(--text-muted);">Welcome back, <?php echo htmlspecialchars($agent['firstname']); ?></p>
                </div>
                
                <div class="status-toggle <?php echo $isOnline ? 'status-active' : ''; ?>" onclick="toggleStatus()">
                    <div class="status-indicator"></div>
                    <span id="status-text" style="font-weight: 600; font-size: 0.9rem;"><?php echo $isOnline ? 'On Duty' : 'Off Duty'; ?></span>
                </div>
            </div>

            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-num"><?php echo count($my_deliveries); ?></div>
                    <div class="stat-label">Active Jobs</div>
                </div>
                <div class="stat-card">
                    <div class="stat-num"><?php echo $completed_today; ?></div>
                    <div class="stat-label">Delivered Today</div>
                </div>
                <div class="stat-card">
                    <div class="stat-num">
                         <span style="color: #fbbf24;">4.9</span>
                    </div>
                    <div class="stat-label">Rating</div>
                </div>
            </div>

            <?php if (!$aLatStart): ?>
                <div style="background: #fff7ed; padding: 1rem; border-radius: var(--radius-md); border: 1px solid #ffedd5; margin-bottom: 2rem; display: flex; align-items: center; gap: 1rem;">
                    <i class='bx bx-map-pin' style="font-size: 1.5rem; color: #c2410c;"></i>
                    <div style="flex-grow:1">
                        <strong>Route not set!</strong>
                        <div style="font-size: 0.85rem; color: #9a3412;">Set your service route to get nearby job alerts.</div>
                    </div>
                    <a href="profile.php" class="btn btn-sm btn-outline">Set Route</a>
                </div>
            <?php endif; ?>

            <!-- Active Jobs Section -->
            <div class="section-title"><i class='bx bx-rocket'></i> My Active Jobs</div>
            
            <?php if (empty($my_deliveries)): ?>
                <div style="text-align: center; padding: 3rem; background: var(--bg-card); border-radius: var(--radius-lg); border: 1px dashed var(--border-color); color: var(--text-muted); margin-bottom: 2rem;">
                    <i class='bx bx-coffee' style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                    <p>No active jobs right now.</p>
                </div>
            <?php else: ?>
                <?php foreach ($my_deliveries as $job): ?>
                    <div class="job-card">
                        <div class="job-header">
                            <span class="job-id">#ORD-<?php echo $job['id']; ?></span>
                            <span class="job-status status-<?php echo $job['status']; ?>"><?php echo $job['status'] == 'approved' ? 'Pickup Pending' : 'In Transit'; ?></span>
                        </div>
                        <div class="job-body">
                            <div class="route-visual">
                                <!-- Pickup -->
                                <div class="route-point">
                                    <div class="point-icon <?php echo $job['status'] == 'approved' ? 'active' : ''; ?>"><i class='bx bx-store-alt'></i></div>
                                    <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.2rem;">PICKUP FROM</div>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($job['lender_fname']); ?></div>
                                    <div style="font-size: 0.9rem; margin-top: 0.2rem;"><?php echo htmlspecialchars($job['pickup_location']); ?></div>
                                    <a href="tel:<?php echo $job['lender_phone']; ?>" style="font-size: 0.8rem; color: var(--primary); display: inline-flex; align-items: center; gap: 0.3rem; margin-top: 0.5rem;">
                                        <i class='bx bx-phone'></i> Call Sender
                                    </a>
                                </div>
                                <!-- Dropoff -->
                                <div class="route-point" style="margin-top: 2rem;">
                                    <div class="point-icon <?php echo $job['status'] == 'active' ? 'active' : ''; ?>"><i class='bx bx-home'></i></div>
                                    <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.2rem;">DELIVER TO</div>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($job['borrower_fname']); ?></div>
                                    <div style="font-size: 0.9rem; margin-top: 0.2rem;"><?php echo htmlspecialchars($job['order_address'] ?: 'No address provided'); ?></div>
                                    <a href="tel:<?php echo $job['borrower_phone']; ?>" style="font-size: 0.8rem; color: var(--primary); display: inline-flex; align-items: center; gap: 0.3rem; margin-top: 0.5rem;">
                                        <i class='bx bx-phone'></i> Call Receiver
                                    </a>
                                </div>
                            </div>
                            <div style="background: #f8fafc; padding: 1rem; border-radius: var(--radius-md); display: flex; align-items: check; gap: 1rem;">
                                <img src="<?php echo htmlspecialchars($job['cover_image'] ?: 'assets/images/book-placeholder.jpg'); ?>" style="width: 40px; height: 60px; object-fit: cover; border-radius: 4px;">
                                <div>
                                    <div style="font-weight: 600; font-size: 0.9rem;"><?php echo htmlspecialchars($job['title']); ?></div>
                                    <div style="font-size: 0.8rem; color: var(--text-muted);">Standard Delivery • Cash on Delivery</div>
                                </div>
                            </div>
                        </div>
                        <div class="action-bar">
                            <a href="https://www.google.com/maps/dir/?api=1&origin=Current+Location&destination=<?php echo urlencode($job['status'] == 'approved' ? ($job['pickup_lat'].','.$job['pickup_lng']) : $job['order_address']); ?>" target="_blank" class="btn-action btn-nav">
                                <i class='bx bxs-navigation'></i> Navigate
                            </a>
                            <?php if ($job['status'] == 'approved'): ?>
                                <button onclick="updateStatus(<?php echo $job['id']; ?>, 'active')" class="btn-action btn-primary-action">
                                    <i class='bx bx-box'></i> Confirm Pickup
                                </button>
                            <?php else: ?>
                                <button onclick="updateStatus(<?php echo $job['id']; ?>, 'delivered')" class="btn-action btn-primary-action" style="background: var(--success-logistics);">
                                    <i class='bx bx-check-circle'></i> Complete Order
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Available Jobs -->
            <div class="section-title" style="margin-top: 2.5rem;"><i class='bx bx-radar'></i> Available Near You</div>
            
            <?php if (empty($available_deliveries)): ?>
                <div style="color: var(--text-muted); font-size: 0.9rem;">No new requests in your area.</div>
            <?php else: ?>
                <div style="display: grid; gap: 1rem;">
                    <?php foreach($available_deliveries as $av): ?>
                        <div style="background: white; padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <div style="font-weight: 700; margin-bottom: 0.3rem;">Pickup in <?php echo substr($av['pickup_location'], 0, 20); ?>...</div>
                                <div style="font-size: 0.85rem; color: var(--text-muted);">Dropping off at <?php echo substr($av['order_address'], 0, 20); ?>...</div>
                            </div>
                            <!-- To be implemented: Claim Logic -->
                            <button class="btn btn-sm btn-primary" onclick="alert('Claim feature coming next!')">Claim</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </main>
    </div>

    <script>
        function toggleStatus() {
            const toggle = document.querySelector('.status-toggle');
            const isNowActive = !toggle.classList.contains('status-active');
            
            // Optimistic UI update
            toggle.classList.toggle('status-active');
            document.getElementById('status-text').innerText = isNowActive ? 'On Duty' : 'Off Duty';

            const formData = new FormData();
            formData.append('action', 'update_availability');
            formData.append('status', isNowActive ? 1 : 0);

            fetch('request_action.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if(!data.success) { 
                        alert('Failed to update status');
                        location.reload(); 
                    }
                });
        }

        function updateStatus(txId, status) {
            if (!confirm('Confirm current action?')) return;
            const formData = new FormData();
            formData.append('action', 'update_delivery_status');
            formData.append('transaction_id', txId);
            formData.append('status', status);

            fetch('request_action.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => console.error('Fetch error:', err));
        }
    </script>
</body>
</html>
