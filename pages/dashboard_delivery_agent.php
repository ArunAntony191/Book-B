<?php
require_once '../includes/db_helper.php';
require_once '../paths.php';
include '../includes/dashboard_header.php';

if ($user['role'] !== 'delivery_agent' && $user['role'] !== 'admin') {
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
    SELECT t.*, b.title, b.cover_image, l.price,
           u_borrower.firstname as borrower_fname, u_borrower.lastname as borrower_lname, u_borrower.phone as borrower_phone,
           u_lender.firstname as lender_fname, u_lender.lastname as lender_lname, u_lender.phone as lender_phone,
           l.location as pickup_location, l.landmark as pickup_landmark, l.latitude as pickup_lat, l.longitude as pickup_lng,
           l.district as pickup_district, l.city as pickup_city, l.pincode as pickup_pincode, t.order_landmark,
           CASE 
             WHEN t.return_agent_id = ? THEN 'return'
             WHEN t.delivery_agent_id = ? THEN 'forward'
             ELSE 'forward'
           END as job_type
    FROM transactions t
    JOIN listings l ON t.listing_id = l.id
    JOIN books b ON l.book_id = b.id
    JOIN users u_borrower ON t.borrower_id = u_borrower.id
    JOIN users u_lender ON t.lender_id = u_lender.id
    WHERE (t.delivery_method = 'delivery' AND t.delivery_agent_id = ? AND t.agent_confirm_delivery_at IS NULL AND t.status IN ('approved', 'assigned', 'active'))
       OR (t.return_delivery_method = 'delivery' AND t.return_agent_id = ? AND t.return_agent_confirm_at IS NULL AND t.status = 'returning')
    ORDER BY CASE 
        WHEN t.status IN ('active', 'returning') THEN 1 
        ELSE 2 
    END, t.created_at DESC
");
$stmt->execute([$userId, $userId, $userId, $userId]);
$all_deliveries = $stmt->fetchAll();

// Filter based on route (simplified for now, can be strict)
$my_deliveries = [];
$available_deliveries = [];
$location_set = ($aLatBase && $aLngBase);

foreach ($all_deliveries as $d) {
    if ($d['delivery_agent_id'] == $userId || $d['return_agent_id'] == $userId) {
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
$active_transit_count = count(array_filter($my_deliveries, function($d) {
    if ($d['job_type'] === 'return') {
        return $d['status'] === 'returning' && !empty($d['return_picked_up_at']);
    }
    return $d['status'] === 'active';
}));
$pending_pickup_count = count(array_filter($my_deliveries, function($d) {
    if ($d['job_type'] === 'return') {
        return $d['status'] === 'returning' && empty($d['return_picked_up_at']);
    }
    return in_array($d['status'], ['approved', 'assigned']);
}));

// Delivered Today / Total
// Delivered Today / Total
// Sum both forward deliveries and return missions using confirmation timestamps for consistency with report
$stmt = $pdo->prepare("
    SELECT 
        (SELECT COUNT(*) FROM transactions WHERE delivery_agent_id = ? AND agent_confirm_delivery_at IS NOT NULL) +
        (SELECT COUNT(*) FROM transactions WHERE return_agent_id = ? AND return_agent_confirm_at IS NOT NULL)
");
$stmt->execute([$userId, $userId]);
$total_delivered = (int)$stmt->fetchColumn();

// Calculate Earnings consistency with report (total_completed * 50)
$calculated_earnings = $total_delivered * 50.00;

// Fetch latest reviews for the modal
$userReviews = getUserReviews($userId, 5); 
?>
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
        .btn-danger-outline { background: white; color: #ef4444; border: 1.5px solid #fee2e2; }
        .btn-danger-outline:hover { background: #fef2f2; border-color: #ef4444; }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background: var(--bg-card);
            width: 90%;
            max-width: 500px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
            transform: translateY(20px);
            transition: all 0.3s ease;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            color: var(--text-body);
        }

        .modal-overlay.active .modal-content {
            transform: translateY(0);
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-muted);
            cursor: pointer;
            line-height: 1;
        }

        .modal-body {
            padding: 1.5rem;
            overflow-y: auto;
        }

        .reviews-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .review-item {
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            text-align: left;
        }

        .review-item:last-child {
            border-bottom: none;
        }
</style>
    <div class="dashboard-wrapper">
        <?php include '../includes/dashboard_sidebar.php'; ?>
        
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
                    <a href="credit_history.php" style="display: block; text-align: center; margin-top: 1rem; color: white; text-decoration: underline; font-size: 0.85rem;">
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

                <!-- Wallet Balance -->
                <div class="widget-card" style="background: linear-gradient(135deg, #10b98115 0%, #10b98105 100%); border: 2px solid #10b981; cursor: pointer;" onclick="location.href='agent_reports.php'">
                    <div class="widget-title">
                        <span><i class='bx bx-wallet'></i> Wallet Balance</span>
                    </div>
                    <div style="margin: 1rem 0; text-align: center;">
                        <div style="font-size: 2.5rem; font-weight: 900; color: #10b981;">
                            ₹<?php echo number_format($calculated_earnings, 2); ?>
                        </div>
                        <div style="color: #10b981; font-size: 0.9rem; font-weight: 700; margin-top: 0.5rem; text-transform: uppercase;">
                            Real Money
                        </div>
                    </div>
                    <div style="text-align: center; color: var(--text-muted); font-size: 0.8rem;">
                        Withdrawable Earnings
                    </div>
                </div>

                <!-- Your Rating -->
                <div class="widget-card" style="background: linear-gradient(135deg, #fbbf2415 0%, #fbbf2405 100%); border: 2px solid #fbbf24; cursor: pointer;" onclick="openReviewsModal()">
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
                <div class="widget-card" style="cursor: pointer; transition: all 0.3s;" onclick="location.href='current_jobs.php'">
                    <div class="widget-title">
                        <span><i class='bx bx-rocket'></i> In Transit</span>
                    </div>
                    <div style="font-size: 2.5rem; font-weight: 800; color: var(--primary-logistics); margin: 1rem 0;">
                        <?php echo $active_transit_count; ?>
                    </div>
                    <div style="color: var(--text-muted); font-size: 0.8rem;">Active deliveries</div>
                </div>

                <!-- Total Delivered -->
                <div class="widget-card" style="cursor: pointer; transition: all 0.3s;" onclick="location.href='delivery_history.php'">
                    <div class="widget-title">
                        <span><i class='bx bx-check-circle'></i> Completed</span>
                    </div>
                    <div style="font-size: 2.5rem; font-weight: 800; color: var(--success-logistics); margin: 1rem 0;">
                        <?php echo $total_delivered; ?>
                    </div>
                    <div style="color: var(--text-muted); font-size: 0.8rem;">Lifetime deliveries</div>
                </div>

                <!-- Pending Pickup -->
                <div class="widget-card" style="cursor: pointer; transition: all 0.3s;" onclick="location.href='current_jobs.php'">
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
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <div class="section-title" style="margin-bottom: 0;"><i class='bx bx-list-ul'></i> My Current Jobs</div>
                <?php if (!empty($my_deliveries)): ?>
                    <a href="current_jobs.php" class="btn btn-sm btn-outline">Manage All Tasks</a>
                <?php endif; ?>
            </div>
            
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
                            <span class="job-id">#ORD-<?php echo $job['id']; ?> <?php echo $job['job_type'] === 'return' ? '(Return Mission)' : ''; ?></span>
                            <?php 
                                $statusLabel = 'Pending';
                                if ($job['job_type'] === 'forward') {
                                    $statusLabel = (in_array($job['status'], ['approved', 'assigned'])) ? 'Pickup Pending' : 'In Transit';
                                } else {
                                    $statusLabel = ($job['status'] === 'delivered') ? 'Pickup Pending' : 'Return Transit';
                                }
                            ?>
                            <span class="job-status status-<?php echo $job['status']; ?>"><?php echo $statusLabel; ?></span>
                        </div>
                        <div class="job-body">
                            <div class="route-visual">
                                <!-- Pickup -->
                                <div class="route-point">
                                    <div class="point-icon active"><i class='bx bx-store-alt'></i></div>
                                    <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.2rem;">PICKUP FROM (<?php echo $job['job_type'] === 'forward' ? 'Owner' : 'Borrower'; ?>)</div>
                                    <div style="font-weight: 600;"><?php echo $job['job_type'] === 'forward' ? htmlspecialchars($job['lender_fname']) : htmlspecialchars($job['borrower_fname']); ?></div>
                                    <div style="font-size: 0.9rem; margin-top: 0.2rem;"><?php echo $job['job_type'] === 'forward' ? htmlspecialchars($job['pickup_location']) : htmlspecialchars($job['order_address']); ?></div>
                                    <?php if ($job['job_type'] === 'forward' && $job['pickup_landmark']): ?>
                                        <div style="font-size: 0.8rem; color: var(--primary); font-weight: 600;">Reference Point: <?php echo htmlspecialchars($job['pickup_landmark']); ?></div>
                                    <?php elseif ($job['job_type'] === 'return' && $job['order_landmark']): ?>
                                        <div style="font-size: 0.8rem; color: var(--primary); font-weight: 600;">Reference Point: <?php echo htmlspecialchars($job['order_landmark']); ?></div>
                                    <?php endif; ?>
                                    <a href="tel:<?php echo $job['job_type'] === 'forward' ? $job['lender_phone'] : $job['borrower_phone']; ?>" style="font-size: 0.85rem; color: var(--primary); font-weight: 700; display: inline-flex; align-items: center; gap: 0.3rem; margin-top: 0.5rem; text-decoration: none;">
                                        <i class='bx bx-phone'></i> <?php echo $job['job_type'] === 'forward' ? htmlspecialchars($job['lender_phone']) : htmlspecialchars($job['borrower_phone']); ?>
                                    </a>
                                </div>
                                <!-- Dropoff -->
                                <div class="route-point" style="margin-top: 2rem;">
                                    <div class="point-icon active"><i class='bx bx-home'></i></div>
                                    <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.2rem;">DELIVER TO (<?php echo $job['job_type'] === 'forward' ? 'Borrower' : 'Owner'; ?>)</div>
                                    <div style="font-weight: 600;"><?php echo $job['job_type'] === 'forward' ? htmlspecialchars($job['borrower_fname']) : htmlspecialchars($job['lender_fname']); ?></div>
                                    <div style="font-size: 0.9rem; margin-top: 0.2rem;"><?php echo $job['job_type'] === 'forward' ? htmlspecialchars($job['order_address']) : htmlspecialchars($job['pickup_location']); ?></div>
                                    <?php if ($job['job_type'] === 'forward' && $job['order_landmark']): ?>
                                        <div style="font-size: 0.8rem; color: var(--primary); font-weight: 600;">Reference Point: <?php echo htmlspecialchars($job['order_landmark']); ?></div>
                                    <?php elseif ($job['job_type'] === 'return' && $job['pickup_landmark']): ?>
                                        <div style="font-size: 0.8rem; color: var(--primary); font-weight: 600;">Reference Point: <?php echo htmlspecialchars($job['pickup_landmark']); ?></div>
                                    <?php endif; ?>
                                    <a href="tel:<?php echo $job['job_type'] === 'forward' ? $job['borrower_phone'] : $job['lender_phone']; ?>" style="font-size: 0.85rem; color: var(--primary); font-weight: 700; display: inline-flex; align-items: center; gap: 0.3rem; margin-top: 0.5rem; text-decoration: none;">
                                        <i class='bx bx-phone'></i> <?php echo $job['job_type'] === 'forward' ? htmlspecialchars($job['borrower_phone']) : htmlspecialchars($job['lender_phone']); ?>
                                    </a>
                                </div>
                            </div>
                            <div style="background: #f8fafc; padding: 1rem; border-radius: var(--radius-md); display: flex; align-items: check; gap: 1rem;">
                                <?php 
                                    $cover = $job['cover_image'];
                                    $fallback = 'https://images.unsplash.com/photo-1543004218-ee141104975a?w=400';
                                    $cover = $cover ?: $fallback;
                                ?>
                                <img src="<?php echo htmlspecialchars(html_entity_decode($cover), ENT_QUOTES, 'UTF-8'); ?>" style="width: 40px; height: 60px; object-fit: cover; border-radius: 4px;" onerror="this.onerror=null; this.src='<?php echo $fallback; ?>';">
                                    <div style="font-weight: 600; font-size: 0.9rem;"><?php echo htmlspecialchars($job['title']); ?></div>
                                    <div style="font-size: 0.8rem; color: var(--text-muted);">
                                        Standard Delivery • <?php echo ($job['payment_method'] === 'cod') ? 'Cash on Delivery' : 'Online Payment'; ?>
                                    </div>
                                    <?php if ($job['payment_method'] === 'cod' && $job['job_type'] === 'forward'): ?>
                                        <div style="margin-top: 0.5rem; background: #fffbeb; border: 1px solid #fef3c7; padding: 0.6rem; border-radius: 8px; display: flex; align-items: flex-start; gap: 0.5rem;">
                                            <i class='bx bx-money' style="color: #d97706; font-size: 1.1rem; margin-top: 2px;"></i>
                                            <div style="font-size: 0.8rem; color: #92400e;">
                                                <strong>CASH TO COLLECT: ₹<?php echo $job['price'] ?? 0; ?></strong><br>
                                                Collect this from the borrower during delivery.
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="action-bar">
                            <?php 
                                $navDest = ($job['job_type'] === 'forward') 
                                    ? (($job['status'] === 'approved') ? ($job['pickup_lat'].','.$job['pickup_lng']) : $job['order_address'])
                                    : (($job['status'] === 'delivered') ? $job['order_address'] : ($job['pickup_lat'].','.$job['pickup_lng']));
                            ?>
                            <a href="https://www.google.com/maps/dir/?api=1&origin=Current+Location&destination=<?php echo urlencode($navDest); ?>" target="_blank" class="btn-action btn-nav">
                                <i class='bx bxs-navigation'></i> Navigate
                            </a>
                            
                            <?php if ($job['job_type'] === 'forward'): ?>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; width: 100%;">
                                    <?php if (!$job['picked_up_at']): ?>
                                        <button onclick="updateStatus(<?php echo $job['id']; ?>, 'active')" class="btn-action btn-primary-action">
                                            <i class='bx bx-box'></i> Pickup
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if (!$job['agent_confirm_delivery_at']): ?>
                                        <button onclick="updateStatus(<?php echo $job['id']; ?>, 'delivered')" class="btn-action btn-primary-action" style="background: var(--success-logistics); <?php echo $job['picked_up_at'] ? 'grid-column: span 2;' : ''; ?>">
                                            <i class='bx bx-check-circle'></i> Deliver
                                        </button>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($job['agent_confirm_delivery_at'] && !$job['borrower_confirm_at']): ?>
                                    <div style="text-align: center; color: var(--success-logistics); font-weight: 700; width: 100%; margin-top: 0.5rem;">
                                        <i class='bx bxs-check-shield'></i> Waiting for Receiver
                                    </div>
                                <?php endif; ?>
                            <?php elseif ($job['job_type'] === 'return' && $job['return_agent_id'] == $userId): ?>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; width: 100%;">
                                    <?php if (!$job['return_picked_up_at']): ?>
                                        <button onclick="updateStatus(<?php echo $job['id']; ?>, 'returning_active')" class="btn-action btn-primary-action">
                                            <i class='bx bx-box'></i> Pickup
                                        </button>
                                    <?php endif; ?>

                                    <?php if (!$job['return_agent_confirm_at']): ?>
                                        <button onclick="updateStatus(<?php echo $job['id']; ?>, 'return_delivered')" class="btn-action btn-primary-action" style="background: var(--success-logistics); <?php echo $job['return_picked_up_at'] ? 'grid-column: span 2;' : ''; ?>">
                                            <i class='bx bx-check-circle'></i> Deliver
                                        </button>
                                    <?php endif; ?>
                                </div>

                                <?php if ($job['return_agent_confirm_at'] && !$job['return_lender_confirm_at']): ?>
                                    <div style="text-align: center; color: var(--success-logistics); font-weight: 700; width: 100%; margin-top: 0.5rem;">
                                        <i class='bx bxs-check-shield'></i> Waiting for Owner
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <?php if ($job['status'] !== 'delivered'): ?>
                        <div style="padding: 0.5rem 1rem; border-top: 1px solid #f1f5f9; text-align: right;">
                            <button onclick="cancelJob(<?php echo $job['id']; ?>)" class="btn-action btn-danger-outline" style="width: auto; padding: 0.4rem 1rem; font-size: 0.8rem;">
                                <i class='bx bx-x'></i> Cancel Job (5 CR Penalty)
                            </button>
                        </div>
                        <?php endif; ?>
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
                                <div style="background: #e0f2fe; color: #0284c7; padding: 4px 8px; border-radius: 4px; font-size: 0.7rem; font-weight:700; text-align: right;">
                                    EARN ₹50<br>+ 10 CR
                                </div>
                            </div>
                            <button class="btn btn-sm btn-primary w-full" onclick="location.href='delivery_jobs.php'">View & Claim</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </main>
    </div>

    <!-- Reviews Modal -->
    <div id="reviewsModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2 style="font-weight: 800; display: flex; align-items: center; gap: 0.5rem; color: var(--text-main);">
                    <i class='bx bxs-star' style="color: #fbbf24;"></i> Your Reviews
                </h2>
                <button class="modal-close" onclick="closeReviewsModal()">&times;</button>
            </div>
            <div class="modal-body">
                <?php if (empty($userReviews)): ?>
                    <div style="text-align: center; padding: 3rem; color: var(--text-muted);">
                        <i class='bx bx-message-rounded-dots' style="font-size: 3rem; opacity: 0.3;"></i>
                        <p>No reviews received yet.</p>
                    </div>
                <?php else: ?>
                    <div class="reviews-list">
                        <?php foreach ($userReviews as $r): ?>
                            <div class="review-item">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                                    <span style="font-weight: 700; color: var(--text-main);">
                                        <?php echo htmlspecialchars($r['firstname'] . ' ' . $r['lastname']); ?>
                                    </span>
                                    <span style="font-size: 0.75rem; color: var(--text-muted);">
                                        <?php echo date('M d, Y', strtotime($r['created_at'])); ?>
                                    </span>
                                </div>
                                <div style="color: #fbbf24; font-size: 0.85rem; margin-bottom: 0.5rem;">
                                    <?php for($i=1; $i<=5; $i++): ?>
                                        <i class='bx <?php echo $i <= $r['rating'] ? "bxs-star" : "bx-star"; ?>'></i>
                                    <?php endfor; ?>
                                </div>
                                <?php if ($r['comment']): ?>
                                    <p style="font-size: 0.9rem; color: var(--text-body); line-height: 1.5;">
                                        <?php echo nl2br(htmlspecialchars($r['comment'])); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div style="margin-top: 2rem; text-align: center;">
                    <a href="user_profile.php?id=<?php echo $userId; ?>#reviews" class="btn btn-outline btn-sm">View All Reviews on Profile</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openReviewsModal() {
            document.getElementById('reviewsModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeReviewsModal() {
            document.getElementById('reviewsModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        // Close on overlay click
        document.getElementById('reviewsModal').addEventListener('click', function(e) {
            if (e.target === this) closeReviewsModal();
        });
        function toggleStatus() {
            const toggle = document.querySelector('.status-toggle');
            const isNowActive = !toggle.classList.contains('status-active');
            
            // Optimistic UI update
            toggle.classList.toggle('status-active');
            document.getElementById('status-text').innerText = isNowActive ? 'On Duty' : 'Off Duty';

            const formData = new FormData();
            formData.append('action', 'update_availability');
            formData.append('status', isNowActive ? 1 : 0);

            fetch('../actions/request_action.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (!data.success) { 
                        showToast('Failed to update status. Please retry.', 'error', 4000);
                        location.reload(); 
                    }
                });
        }

        async function updateStatus(txId, status) {
            const labelMap = {
                'active': 'Confirm you have picked up the book?',
                'delivered': 'Confirm you have delivered the book to the recipient?',
                'returning_active': 'Confirm you have picked up the book for return?',
                'return_delivered': 'Confirm you have delivered the book back to the owner?'
            };
            const confirmed = await Popup.confirm('Update Status', labelMap[status] || 'Confirm this action?', { confirmText: 'Yes, Confirm' });
            if (!confirmed) return;

            const formData = new FormData();
            formData.append('action', 'update_delivery_status');
            formData.append('transaction_id', txId);
            formData.append('status', status);

            fetch('../actions/request_action.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('✅ Status updated!', 'success', 2500);
                    setTimeout(() => location.reload(), 1200);
                } else {
                    showToast('Error: ' + data.message, 'error', 5000);
                }
            })
            .catch(err => showToast('Connection error.', 'error', 4000));
        }
        async function cancelJob(txId) {
            const confirmed = await Popup.confirm(
                'Cancel Job',
                'Are you sure you want to cancel this job? A 5-credit penalty will be applied to your account.',
                { confirmText: 'Yes, Cancel Job', confirmStyle: 'danger' }
            );
            if (!confirmed) return;

            const formData = new FormData();
            formData.append('action', 'cancel_job');
            formData.append('transaction_id', txId);

            fetch('../actions/request_action.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('❌ Job cancelled. 5 credits have been deducted.', 'warning', 4000);
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showToast('Error: ' + data.message, 'error', 5000);
                }
            })
            .catch(err => showToast('Connection error.', 'error', 4000));
        }
    </script>
</body>
</html>
