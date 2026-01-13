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
$area_filter = $_GET['filter'] ?? 'near_me'; // near_me, district, city, all

if (!$agent) {
    header("Location: login.php");
    exit();
}

$aLatBase = $agent['service_start_lat'] ?? null;
$aLngBase = $agent['service_start_lng'] ?? null;
$aDistrict = $agent['district'] ?? null;
$isOnline = $agent['is_accepting_deliveries'] ?? 0;

// Fetch active tasks and mapped jobs
$stmt = $pdo->prepare("
    SELECT t.*, b.title, b.cover_image,
           u_borrower.firstname as borrower_fname, u_borrower.lastname as borrower_lname, u_borrower.phone as borrower_phone,
           u_lender.firstname as lender_fname, u_lender.lastname as lender_lname, u_lender.phone as lender_phone,
           l.location as pickup_location, l.landmark as pickup_landmark, l.latitude as pickup_lat, l.longitude as pickup_lng,
           l.district as pickup_district, l.city as pickup_city, l.pincode as pickup_pincode, t.order_landmark
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
$location_set = ($aLatBase && $aLngBase);

foreach ($all_deliveries as $d) {
    if ($d['delivery_agent_id'] == $userId) {
        $my_deliveries[] = $d;
    } else {
        // Filter available ones by home base proximity
        if (!$location_set || $area_filter === 'all') {
             $available_deliveries[] = $d;
        } else {
            $distance = getDistanceKM($d['pickup_lat'], $d['pickup_lng'], $aLatBase, $aLngBase);
            
            $match = false;
            if ($area_filter === 'city' && $aCity && $d['pickup_city'] === $aCity) {
                $match = true;
            } elseif ($area_filter === 'district' && $aDistrict && $d['pickup_district'] === $aDistrict) {
                $match = true;
            } elseif ($area_filter === 'near_me' && ($distance < 25 || ($aDistrict && $d['pickup_district'] === $aDistrict))) {
                $match = true;
            }

            if ($match) {
                $d['relevance_dist'] = $distance;
                $d['in_district'] = ($aDistrict && $d['pickup_district'] === $aDistrict);
                $available_deliveries[] = $d;
            }
        }
    }
}

// Sort available by relevance
usort($available_deliveries, function($a, $b) {
    if ($a['in_district'] && !$b['in_district']) return -1;
    if (!$a['in_district'] && $b['in_district']) return 1;
    return $a['relevance_dist'] <=> $b['relevance_dist'];
});

// Stats
// 1. Get Base User Stats (Credits, Trust, Rating)
$stats = getUserStatsEnhanced($userId);
$trustRating = $stats['trust_rating'] ?? getTrustScoreRating(50);

// 2. Get Delivery Stats
// Active Jobs (Assigned to me)
$active_transit_count = count(array_filter($my_deliveries, fn($d) => $d['status'] === 'active'));
$pending_pickup_count = count(array_filter($my_deliveries, fn($d) => $d['status'] === 'approved'));

// Delivered Today / Total
$stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE delivery_agent_id = ? AND status = 'delivered'");
$stmt->execute([$userId]);
$total_delivered = $stmt->fetchColumn();

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
        
        /* Widget Styles copied/adapted from dashboard_user.php */
        .gradient-card {
            position: relative;
            overflow: hidden;
        }
        .gradient-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            pointer-events: none;
        }
        .widget-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            display: flex; 
            flex-direction: column; 
            align-items: center;
            height: 100%;
        }
        .widget-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .widget-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            width: 100%;
            justify-content: center;
        }
        
        .section-title { font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        
        /* Delivery Card Styles */
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
                    <p style="margin:0; color: var(--text-muted);">Welcome back, <?php echo htmlspecialchars($agent['firstname']); ?>! 👋</p>
                </div>
                
                <div style="display: flex; gap: 1rem;">
                    <button class="status-toggle <?php echo $isOnline ? 'status-active' : ''; ?>" onclick="toggleStatus()">
                        <div class="status-indicator"></div>
                        <span id="status-text" style="font-weight: 600; font-size: 0.9rem;"><?php echo $isOnline ? 'On Duty' : 'Off Duty'; ?></span>
                    </button>
                    
                    <a href="delivery_jobs.php" class="btn btn-primary">
                        <i class='bx bx-radar'></i> Find Jobs
                    </a>
                </div>
            </div>

            <!-- Widgets Grid -->
            <div class="widgets-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                
                <!-- Credit Balance -->
                <div class="widget-card gradient-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none;">
                    <div class="widget-title" style="color: rgba(255,255,255,0.9);">
                        <span><i class='bx bx-wallet'></i> Credit Balance</span>
                    </div>
                    <div style="font-size: 3rem; font-weight: 900; text-align: center; margin: 1rem 0;">
                        <?php echo $stats['credits'] ?? 0; ?>
                    </div>
                    <div style="text-align: center; opacity: 0.9; font-size: 0.85rem;">
                        Earnings & Balance
                    </div>
                    <a href="#" style="display: block; text-align: center; margin-top: 1rem; color: white; text-decoration: underline; font-size: 0.85rem;">
                        View History →
                    </a>
                </div>

                <!-- Trust Score -->
                <div class="widget-card" style="background: linear-gradient(135deg, <?php echo $trustRating['color']; ?>15 0%, <?php echo $trustRating['color']; ?>05 100%); border: 2px solid <?php echo $trustRating['color']; ?>;">
                    <div class="widget-title">
                        <span><i class='bx bx-shield-alt-2'></i> Trust Score</span>
                    </div>
                    <div style="text-align: center; margin: 1rem 0;">
                        <div style="font-size: 2.5rem; font-weight: 900; color: <?php echo $trustRating['color']; ?>;">
                            <?php echo $stats['trust_score'] ?? 50; ?>/100
                        </div>
                        <div style="margin-top: 0.5rem; padding: 0.4rem 1.2rem; background: <?php echo $trustRating['color']; ?>; color: white; border-radius: 20px; display: inline-block; font-weight: 700; font-size: 0.85rem;">
                            <?php echo $trustRating['label']; ?>
                        </div>
                    </div>
                    <div style="text-align: center; color: var(--text-muted); font-size: 0.8rem;">
                        Built on reliability
                    </div>
                </div>

                <!-- Your Rating -->
                <div class="widget-card" style="background: linear-gradient(135deg, #fbbf2415 0%, #fbbf2405 100%); border: 2px solid #fbbf24;">
                    <div class="widget-title">
                        <span><i class='bx bxs-star'></i> Your Rating</span>
                    </div>
                    <div style="margin: 1rem 0; text-align: center;">
                        <div style="font-size: 2.5rem; font-weight: 900; color: #fbbf24;">
                            <?php 
                            $avgRating = $stats['average_rating'] ?? 0;
                            echo $avgRating > 0 ? number_format($avgRating, 1) : '—'; 
                            ?>
                        </div>
                        <div style="color: #fbbf24; font-size: 1.2rem; margin-top: 0.5rem;">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <i class='bx <?php echo $i <= round($avgRating) ? "bxs-star" : "bx-star"; ?>'></i>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div style="color: var(--text-muted); font-size: 0.8rem;">
                        <?php echo $stats['total_ratings'] ?? 0; ?> reviews
                    </div>
                </div>

                <!-- Active Deliveries -->
                <div class="widget-card" style="cursor: pointer; transition: all 0.3s;" onclick="window.scrollTo({top: 500, behavior: 'smooth'})">
                    <div class="widget-title">
                        <span><i class='bx bx-rocket'></i> In Transit</span>
                    </div>
                    <div style="font-size: 2.5rem; font-weight: 800; color: var(--primary-logistics); margin: 1rem 0;">
                        <?php echo $active_transit_count; ?>
                    </div>
                    <div style="color: var(--text-muted); font-size: 0.8rem;">Active deliveries</div>
                </div>

                <!-- Total Delivered -->
                <div class="widget-card" style="cursor: pointer; transition: all 0.3s;">
                    <div class="widget-title">
                        <span><i class='bx bx-check-circle'></i> Completed</span>
                    </div>
                    <div style="font-size: 2.5rem; font-weight: 800; color: var(--success-logistics); margin: 1rem 0;">
                        <?php echo $total_delivered; ?>
                    </div>
                    <div style="color: var(--text-muted); font-size: 0.8rem;">Lifetime deliveries</div>
                </div>

                <!-- Pending Pickup -->
                <div class="widget-card" style="cursor: pointer; transition: all 0.3s;" onclick="window.scrollTo({top: 500, behavior: 'smooth'})">
                    <div class="widget-title">
                        <span><i class='bx bx-package'></i> To Pickup</span>
                    </div>
                    <div style="font-size: 2.5rem; font-weight: 800; color: #f59e0b; margin: 1rem 0;">
                        <?php echo $pending_pickup_count; ?>
                    </div>
                    <div style="color: var(--text-muted); font-size: 0.8rem;">Awaiting pickup</div>
                </div>

            </div>

            <?php if (!$aLatBase): ?>
                <div style="background: #fff7ed; padding: 1rem; border-radius: var(--radius-md); border: 1px solid #ffedd5; margin-bottom: 2rem; display: flex; align-items: center; gap: 1rem;">
                    <i class='bx bx-map-pin' style="font-size: 1.5rem; color: #c2410c;"></i>
                    <div style="flex-grow:1">
                        <strong>Address not set!</strong>
                        <div style="font-size: 0.85rem; color: #9a3412;">Complete your profile address to receive jobs matched to your area.</div>
                    </div>
                    <a href="profile.php" class="btn btn-sm btn-outline">Set Address</a>
                </div>
            <?php endif; ?>

            <!-- Active Jobs Section -->
            <div class="section-title"><i class='bx bx-list-ul'></i> My Current Jobs</div>
            
            <?php if (empty($my_deliveries)): ?>
                <div style="text-align: center; padding: 3rem; background: var(--bg-card); border-radius: var(--radius-lg); border: 1px dashed var(--border-color); color: var(--text-muted); margin-bottom: 2rem;">
                    <i class='bx bx-coffee' style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                    <p>No active jobs right now.</p>
                </div>
            <?php else: ?>
                <div style="display: grid; gap: 1rem;">
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
                                    <?php if ($job['pickup_landmark']): ?>
                                        <div style="font-size: 0.8rem; color: var(--primary); font-weight: 600;">Reference Point: <?php echo htmlspecialchars($job['pickup_landmark']); ?></div>
                                    <?php endif; ?>
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
                                    <?php if ($job['order_landmark']): ?>
                                        <div style="font-size: 0.8rem; color: var(--primary); font-weight: 600;">Reference Point: <?php echo htmlspecialchars($job['order_landmark']); ?></div>
                                    <?php endif; ?>
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
                            <?php elseif ($job['status'] == 'active'): ?>
                                <?php if (!$job['agent_confirm_delivery_at']): ?>
                                    <button onclick="updateStatus(<?php echo $job['id']; ?>, 'delivered')" class="btn-action btn-primary-action" style="background: var(--success-logistics);">
                                        <i class='bx bx-check-circle'></i> Mark Delivered
                                    </button>
                                <?php else: ?>
                                    <button disabled class="btn-action" style="background: #f1f5f9; color: #94a3b8; cursor: default;">
                                        <i class='bx bx-time'></i> Waiting for Receiver
                                    </button>
                                <?php endif; ?>
                            <?php else: ?>
                                <div style="text-align: center; color: var(--success-logistics); font-weight: 700; width: 100%; grid-column: span 2;">
                                    <i class='bx bxs-check-shield'></i> Delivery Verified
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Available Jobs -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 2.5rem; margin-bottom: 1rem;">
                <div class="section-title" style="margin-bottom: 0;"><i class='bx bx-radar'></i> Available Near You</div>
                <?php if ($location_set): ?>
                <div style="display: flex; gap: 0.5rem; background: #f1f5f9; padding: 0.3rem; border-radius: 8px;">
                    <a href="?filter=near_me" class="btn btn-xs <?php echo $area_filter === 'near_me' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 0.7rem; padding: 4px 8px;">Near Me</a>
                    <a href="?filter=city" class="btn btn-xs <?php echo $area_filter === 'city' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 0.7rem; padding: 4px 8px;">City</a>
                    <a href="?filter=district" class="btn btn-xs <?php echo $area_filter === 'district' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 0.7rem; padding: 4px 8px;">District</a>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if (empty($available_deliveries)): ?>
                <div style="color: var(--text-muted); font-size: 0.9rem;">No new requests in your area.</div>
            <?php else: ?>
                <div style="display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));">
                    <?php foreach($available_deliveries as $av): ?>
                        <div style="background: white; padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); display: flex; flex-direction: column; gap: 1rem;">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div>
                                    <div style="font-weight: 700; margin-bottom: 0.3rem;">Pickup in <?php echo substr($av['pickup_location'], 0, 20); ?>...</div>
                                    <div style="font-size: 0.85rem; color: var(--text-muted);">Dropping off at <?php echo substr($av['order_address'], 0, 20); ?>...</div>
                                </div>
                                <div style="background: #e0f2fe; color: #0284c7; padding: 4px 8px; border-radius: 4px; font-size: 0.7rem; font-weight:700;">
                                    EARN 10 CR
                                </div>
                            </div>
                            <button class="btn btn-sm btn-primary w-full" onclick="location.href='delivery_jobs.php'">View & Claim</button>
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
