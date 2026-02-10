<?php
require_once '../includes/db_helper.php';
require_once '../paths.php';
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

$aLatBase = $agent['service_start_lat'] ?? null;
$aLngBase = $agent['service_start_lng'] ?? null;
$aDistrict = $agent['district'] ?? null;
$aCity = $agent['city'] ?? null;
$aPincode = $agent['pincode'] ?? null;

// Fetch ALL pending deliveries (Standard & Returns)
$stmt = $pdo->prepare("
    SELECT t.*, b.title, b.cover_image,
           u_borrower.firstname as borrower_fname, u_borrower.lastname as borrower_lname,
           u_borrower.service_start_lat as borrower_lat, u_borrower.service_start_lng as borrower_lng,
           u_borrower.address as borrower_addr, u_borrower.city as borrower_city, u_borrower.district as borrower_dist,
           u_lender.firstname as lender_fname, u_lender.lastname as lender_lname,
           l.location as listing_loc, l.latitude as listing_lat, l.longitude as listing_lng,
           l.district as listing_dist, l.city as listing_city, l.landmark as listing_landmark,
           CASE 
             WHEN t.status = 'approved' AND t.delivery_agent_id IS NULL THEN 'forward'
             ELSE 'return'
           END as job_type
    FROM transactions t
    JOIN listings l ON t.listing_id = l.id
    JOIN books b ON l.book_id = b.id
    JOIN users u_borrower ON t.borrower_id = u_borrower.id
    JOIN users u_lender ON t.lender_id = u_lender.id
    WHERE (t.delivery_method = 'delivery' AND t.status = 'approved' AND t.delivery_agent_id IS NULL)
       OR (t.return_delivery_method = 'delivery' AND t.return_agent_id IS NULL AND t.status = 'returning')
    ORDER BY t.created_at DESC
");
$stmt->execute();
$all_raw_jobs = $stmt->fetchAll();

$available_jobs = [];
$location_set = ($aLatBase && $aLngBase);

foreach ($all_raw_jobs as $job) {
    // Normalize Pickup/Dropoff based on Job Type
    if ($job['job_type'] === 'forward') {
        $job['p_lat'] = $job['listing_lat'];
        $job['p_lng'] = $job['listing_lng'];
        $job['p_addr'] = $job['listing_loc'];
        $job['pickup_landmark'] = $job['listing_landmark'] ?? '';
        $job['d_lat'] = $job['order_lat'];
        $job['d_lng'] = $job['order_lng'];
        $job['d_addr'] = $job['order_address'];
        $job['order_landmark'] = $job['order_landmark'] ?? ''; 
        $job['p_city'] = $job['listing_city'];
        $job['p_dist'] = $job['listing_dist'];
    } else {
        // Return Leg: Pickup from Borrower (order_address or profile), Dropoff at Lender (listing_loc)
        $job['p_lat'] = $job['order_lat'] ?: $job['borrower_lat'];
        $job['p_lng'] = $job['order_lng'] ?: $job['borrower_lng'];
        $job['p_addr'] = $job['order_address'] ?: $job['borrower_addr'];
        $job['pickup_landmark'] = $job['order_landmark'] ?? ''; 
        
        $job['d_lat'] = $job['listing_lat'];
        $job['d_lng'] = $job['listing_lng'];
        $job['d_addr'] = $job['listing_loc'];
        $job['order_landmark'] = $job['listing_landmark'] ?? ''; // Destination landmark for return is listing landmark
        
        $job['p_city'] = $job['borrower_city'];
        $job['p_dist'] = $job['borrower_dist'];
    }

    if (!$location_set || $area_filter === 'all') {
        $available_jobs[] = $job;
    } else {
        $distance = getDistanceKM($job['p_lat'], $job['p_lng'], $aLatBase, $aLngBase);
        
        $match = false;
        if ($area_filter === 'city' && $aCity && $job['p_city'] === $aCity) {
            $match = true;
        } elseif ($area_filter === 'district' && $aDistrict && $job['p_dist'] === $aDistrict) {
            $match = true;
        } elseif ($area_filter === 'near_me' && ($distance < 25 || ($aDistrict && $job['p_dist'] === $aDistrict))) {
            $match = true;
        }

        if ($match) {
            $job['relevance_dist'] = $distance;
            $job['in_district'] = ($aDistrict && $job['p_dist'] === $aDistrict);
            $available_jobs[] = $job;
        }
    }
}

// Sort by relevance (District first, then distance) - only if we have jobs
if (!empty($available_jobs) && is_array($available_jobs)) {
    usort($available_jobs, function($a, $b) {
        $aInDistrict = $a['in_district'] ?? false;
        $bInDistrict = $b['in_district'] ?? false;
        
        if ($aInDistrict && !$bInDistrict) return -1;
        if (!$aInDistrict && $bInDistrict) return 1;
        
        $aDist = $a['relevance_dist'] ?? 0;
        $bDist = $b['relevance_dist'] ?? 0;
        
        return $aDist <=> $bDist;
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find Jobs | BOOK-B</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        :root {
            --glass-bg: rgba(255, 255, 255, 0.9);
            --glass-border: rgba(255, 255, 255, 0.4);
            --success-logistics: #10b981;
        }

        .jobs-container { 
            display: grid; 
            grid-template-columns: 1fr 450px; 
            gap: 2rem; 
            height: calc(100vh - 180px); 
        }
        
        @media (max-width: 1024px) {
            .jobs-container { grid-template-columns: 1fr; height: auto; }
            #radar-map { height: 400px; margin-top: 1rem; }
        }

        .jobs-list-side { 
            overflow-y: auto; 
            padding-right: 0.5rem;
            scrollbar-width: thin;
        }
        
        #radar-map { 
            height: 100%; 
            border-radius: 24px; 
            border: 4px solid white; 
            box-shadow: var(--shadow-lg); 
            z-index: 1;
        }
        
        .job-card {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        .job-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.1);
            border-color: var(--primary-light);
        }

        .job-card.highlighted {
            border-color: var(--primary);
            background: linear-gradient(to right, #ffffff, #f0f7ff);
        }

        .earnings-tag {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 12px;
            font-weight: 800;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            box-shadow: 0 4px 10px rgba(2, 132, 199, 0.3);
        }

        .job-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.25rem;
        }

        .book-snapshot {
            display: flex;
            gap: 1.25rem;
            background: white;
            padding: 1rem;
            border-radius: 16px;
            border: 1px solid #f1f5f9;
        }

        .route-info {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.75rem;
            margin-top: 1.25rem;
            padding-left: 0.5rem;
            position: relative;
        }

        .route-info::before {
            content: '';
            position: absolute;
            left: 14px;
            top: 15px;
            bottom: 15px;
            width: 2px;
            background: repeating-linear-gradient(to bottom, #cbd5e1 0, #cbd5e1 4px, transparent 4px, transparent 8px);
        }

        .route-point {
            display: flex;
            gap: 1rem;
            align-items: flex-start;
            position: relative;
            z-index: 2;
        }

        .point-icon {
            width: 20px;
            height: 20px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .point-pickup { border: 2px solid #3b82f6; color: #3b82f6; }
        .point-drop { border: 2px solid var(--success-logistics); color: var(--success-logistics); }

        .btn-claim {
            background: var(--primary);
            color: white;
            width: 100%;
            padding: 1rem;
            border-radius: 14px;
            font-weight: 700;
            margin-top: 1.5rem;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            transition: all 0.3s;
        }

        .btn-claim:hover {
            background: #4338ca;
            transform: scale(1.02);
            box-shadow: 0 10px 20px rgba(79, 70, 229, 0.3);
        }

        .filter-bar {
            display: flex;
            gap: 0.8rem;
            margin-bottom: 2rem;
            background: rgba(241, 245, 249, 0.8);
            backdrop-filter: blur(8px);
            padding: 0.6rem;
            border-radius: 18px;
            border: 1px solid var(--glass-border);
            width: fit-content;
        }

        .filter-btn {
            padding: 0.6rem 1.2rem;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #64748b;
            background: transparent;
        }

        .filter-btn.active {
            background: white;
            color: var(--primary);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include '../includes/dashboard_sidebar.php'; ?>
        
        <main class="main-content">
            <div style="margin-bottom: 1.5rem;">
                <h1>Find Jobs</h1>
                <p style="color: var(--text-muted);">Available delivery requests in your area</p>
            </div>

            <div class="jobs-container">
                <div class="jobs-list-side">
                    <?php if (!$location_set): ?>
                <div style="background: #fff7ed; padding: 1rem; border-radius: var(--radius-md); border: 1px solid #ffedd5; margin-bottom: 2rem; display: flex; align-items: center; gap: 1rem;">
                    <i class='bx bx-map-pin' style="font-size: 1.5rem; color: #c2410c;"></i>
                    <div style="flex-grow:1">
                        <strong>Filters are limited!</strong>
                        <div style="font-size: 0.85rem; color: #9a3412;">Complete your <strong>profile address</strong> to unlock district and city-level filtering.</div>
                    </div>
                    <a href="profile.php" class="btn btn-sm btn-outline">Set Address</a>
                </div>
            <?php else: ?>
                <!-- Efficiency Filters -->
                <div class="filter-bar">
                    <a href="?filter=near_me" class="filter-btn <?php echo $area_filter === 'near_me' ? 'active' : ''; ?>">
                        <i class='bx bx-navigation'></i> Near Me
                    </a>
                    <a href="?filter=city" class="filter-btn <?php echo $area_filter === 'city' ? 'active' : ''; ?>">
                        <i class='bx bx-building'></i> <?php echo htmlspecialchars($aCity ?: 'City'); ?>
                    </a>
                    <a href="?filter=district" class="filter-btn <?php echo $area_filter === 'district' ? 'active' : ''; ?>">
                        <i class='bx bx-map-alt'></i> <?php echo htmlspecialchars($aDistrict ?: 'District'); ?>
                    </a>
                    <a href="?filter=all" class="filter-btn <?php echo $area_filter === 'all' ? 'active' : ''; ?>">
                        <i class='bx bx-globe'></i> All
                    </a>
                </div>
            <?php endif; ?>
            <?php if (empty($available_jobs)): ?>
                <div style="text-align: center; padding: 4rem; background: white; border-radius: var(--radius-lg); border: 1px dashed var(--border-color);">
                    <i class='bx bx-search-alt' style="font-size: 3rem; color: var(--text-muted); margin-bottom: 1rem;"></i>
                    <h3>No jobs found</h3>
                    <p style="color: var(--text-muted);">There are no pending delivery requests in you area right now.</p>
                </div>
            <?php else: ?>
                <div style="display: grid; gap: 1.5rem; grid-template-columns: 1fr;">
                    <?php foreach ($available_jobs as $job): ?>
                        <div class="job-card <?php echo $job['job_type'] === 'return' ? 'return-job' : ''; ?>" id="job-<?php echo $job['id']; ?>">
                            <div class="job-meta">
                                <div class="earnings-tag" style="<?php echo $job['job_type'] === 'return' ? 'background: linear-gradient(135deg, #e11d48, #be123c);' : ''; ?>">
                                    <i class='bx bxs-coin-stack'></i>
                                    10 CREDITS
                                </div>
                                <div style="display: flex; flex-direction: column; align-items: flex-end;">
                                    <?php if($job['job_type'] === 'return'): ?>
                                        <span class="status-badge returning" style="position: static; padding: 0.3rem 0.8rem; margin-bottom: 5px;">Return Mission</span>
                                    <?php endif; ?>
                                    <span style="font-size: 0.75rem; color: #94a3b8; font-weight: 700;">
                                        ORDER #<?php echo $job['id']; ?> • <?php echo date('H:i', strtotime($job['created_at'])); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="book-snapshot">
                                <?php 
                                    $cover = $job['cover_image'];
                                    $fallback = 'https://images.unsplash.com/photo-1543004218-ee141104975a?w=400';
                                    $cover = $cover ?: $fallback;
                                ?>
                                <img src="<?php echo htmlspecialchars($cover, ENT_QUOTES, 'UTF-8', false); ?>" style="width: 50px; height: 75px; object-fit: cover; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);" onerror="this.onerror=null; this.src='<?php echo $fallback; ?>';">
                                <div style="flex: 1;">
                                    <div style="font-weight: 850; font-size: 1.1rem; color: #1e293b; line-height: 1.2; margin-bottom: 4px;"><?php echo htmlspecialchars($job['title']); ?></div>
                                    <div style="font-size: 0.85rem; color: #64748b; font-weight: 600;">
                                        <?php if($job['job_type'] === 'forward'): ?>
                                            Owner: <?php echo htmlspecialchars($job['lender_fname']); ?>
                                        <?php else: ?>
                                            Returning From: <?php echo htmlspecialchars($job['borrower_fname']); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="route-info">
                                <div class="route-point">
                                    <div class="point-icon point-pickup">
                                        <div style="width: 8px; height: 8px; background: currentColor; border-radius: 50%;"></div>
                                    </div>
                                    <div>
                                        <div style="font-size: 0.7rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">Pickup From</div>
                                        <div style="font-size: 0.9rem; font-weight: 700; color: #334155;"><?php echo htmlspecialchars($job['p_addr']); ?></div>
                                        <?php if($job['job_type'] === 'forward' && $job['pickup_landmark']): ?>
                                            <div style="font-size: 0.75rem; color: #3b82f6; font-weight: 700;">Near <?php echo htmlspecialchars($job['pickup_landmark']); ?></div>
                                        <?php elseif($job['job_type'] === 'return' && $job['order_landmark']): ?>
                                            <div style="font-size: 0.75rem; color: #3b82f6; font-weight: 700;">Near <?php echo htmlspecialchars($job['order_landmark']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="route-point">
                                    <div class="point-icon point-drop">
                                        <div style="width: 8px; height: 8px; background: currentColor; border-radius: 50%;"></div>
                                    </div>
                                    <div>
                                        <div style="font-size: 0.7rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">Deliver To</div>
                                        <div style="font-size: 0.9rem; font-weight: 700; color: #334155;"><?php echo htmlspecialchars($job['d_addr']); ?></div>
                                        <?php if($job['job_type'] === 'forward' && $job['order_landmark']): ?>
                                            <div style="font-size: 0.75rem; color: var(--success-logistics); font-weight: 700;">Near <?php echo htmlspecialchars($job['order_landmark']); ?></div>
                                        <?php elseif($job['job_type'] === 'return' && isset($job['pickup_landmark'])): ?>
                                             <div style="font-size: 0.75rem; color: var(--success-logistics); font-weight: 700;">Near <?php echo htmlspecialchars($job['pickup_landmark']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <button onclick="claimJob(<?php echo $job['id']; ?>)" class="btn-claim">
                                <i class='bx bx-check-double'></i> Accept Assignment
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

                </div> <!-- End of jobs-list-side -->
                
                <div id="radar-map"></div>
            </div> <!-- End of jobs-container -->
        </main>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const jobsData = <?php echo json_encode($available_jobs); ?>;
        const agentLoc = <?php echo json_encode(['lat' => $aLatBase, 'lng' => $aLngBase]); ?>;

        const map = L.map('radar-map').setView([agentLoc.lat || 9.9312, agentLoc.lng || 76.2673], 12);
        L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png').addTo(map);

        if (agentLoc.lat) {
            L.marker([agentLoc.lat, agentLoc.lng], {
                icon: L.divIcon({
                    className: 'home-marker',
                    html: '<div style="background:var(--primary); width:15px; height:15px; border:3px solid white; border-radius:50%; box-shadow:0 0 10px var(--primary);"></div>',
                    iconSize: [20, 20]
                })
            }).addTo(map).bindPopup("Your Home Base");
        }

        const markers = L.featureGroup();
        jobsData.forEach(job => {
            if (job.p_lat && job.p_lng) {
                const markerColor = job.job_type === 'return' ? '#e11d48' : '#3b82f6';
                const marker = L.marker([job.p_lat, job.p_lng])
                    .bindPopup(`
                        <div style="padding:5px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                <strong style="font-size:1rem;">${job.title}</strong>
                                <span style="background:${markerColor}; color:white; font-size:10px; padding:2px 6px; border-radius:4px; font-weight:bold;">${job.job_type.toUpperCase()}</span>
                            </div>
                            <div style="font-size:0.8rem;color:#667;"><strong>Pickup:</strong> ${job.p_addr}</div>
                            <div style="font-size:0.8rem;color:#667;margin-top:5px;"><strong>Drop:</strong> ${job.d_addr}</div>
                            <button onclick="claimJob(${job.id})" class="btn btn-primary btn-sm w-full" style="margin-top:10px; background:${markerColor}; border:none;">Accept assignment</button>
                        </div>
                    `);
                
                marker.on('click', () => {
                   document.querySelectorAll('.job-card').forEach(c => c.classList.remove('highlighted'));
                   const card = document.getElementById('job-' + job.id);
                   if (card) {
                       card.classList.add('highlighted');
                       card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                   }
                });
                markers.addLayer(marker);
            }
        });
        markers.addTo(map);

        if (jobsData.length > 0) {
            map.fitBounds(markers.getBounds().pad(0.2));
        }

        function claimJob(id) {
            if(!confirm('Accept this delivery job?')) return;
            
            // Find both button types (card button and map popup button)
            const buttons = document.querySelectorAll(`button[onclick="claimJob(${id})"]`);
            
            // Disable all buttons for this job to prevent double-clicks
            buttons.forEach(btn => {
                btn.disabled = true;
                btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Processing...';
                btn.style.opacity = '0.7';
                btn.style.cursor = 'not-allowed';
            });

            const formData = new FormData();
            formData.append('action', 'claim_job');
            formData.append('transaction_id', id);

            fetch('../actions/request_action.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if(data.success) {
                        // Keep disabled, redirecting...
                        buttons.forEach(btn => {
                            btn.innerHTML = '<i class="bx bx-check"></i> Accepted!';
                            btn.className = 'btn-claim success'; 
                            btn.style.background = '#10b981';
                        });
                        setTimeout(() => {
                            window.location.href = 'dashboard_delivery_agent.php';
                        }, 500);
                    } else {
                        alert('Error: ' + data.message);
                        // Re-enable on error
                        buttons.forEach(btn => {
                            btn.disabled = false;
                            btn.innerHTML = '<i class="bx bx-check-double"></i> Accept Assignment';
                            btn.style.opacity = '1';
                            btn.style.cursor = 'pointer';
                        });
                    }
                })
                .catch(err => {
                    alert('Network error occurred.');
                    buttons.forEach(btn => {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="bx bx-check-double"></i> Accept Assignment';
                        btn.style.opacity = '1';
                        btn.style.cursor = 'pointer';
                    });
                });
        }
    </script>
</body>
</html>
