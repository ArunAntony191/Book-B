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

$aLatBase = $agent['service_start_lat'] ?? null;
$aLngBase = $agent['service_start_lng'] ?? null;
$aDistrict = $agent['district'] ?? null;
$aCity = $agent['city'] ?? null;
$aPincode = $agent['pincode'] ?? null;

// Fetch ALL pending deliveries
// We want items that are 'approved' (ready for pickup) but have NO agent assigned
$stmt = $pdo->prepare("
    SELECT t.*, b.title, b.cover_image,
           u_borrower.firstname as borrower_fname, u_borrower.lastname as borrower_lname,
           u_lender.firstname as lender_fname, u_lender.lastname as lender_lname,
           l.location as pickup_location, l.landmark as pickup_landmark, l.latitude as pickup_lat, l.longitude as pickup_lng,
           l.district as pickup_district, l.city as pickup_city, l.pincode as pickup_pincode, t.order_landmark
    FROM transactions t
    JOIN listings l ON t.listing_id = l.id
    JOIN books b ON l.book_id = b.id
    JOIN users u_borrower ON t.borrower_id = u_borrower.id
    JOIN users u_lender ON t.lender_id = u_lender.id
    WHERE t.delivery_method = 'delivery' 
    AND t.status = 'approved' 
    AND t.delivery_agent_id IS NULL
    ORDER BY t.created_at DESC
");
$stmt->execute();
$all_jobs = $stmt->fetchAll();

$available_jobs = [];
$location_set = ($aLatBase && $aLngBase);

foreach ($all_jobs as $job) {
    if (!$location_set || $area_filter === 'all') {
        $available_jobs[] = $job;
    } else {
        $distance = getDistanceKM($job['pickup_lat'], $job['pickup_lng'], $aLatBase, $aLngBase);
        
        $match = false;
        if ($area_filter === 'city' && $aCity && $job['pickup_city'] === $aCity) {
            $match = true;
        } elseif ($area_filter === 'district' && $aDistrict && $job['pickup_district'] === $aDistrict) {
            $match = true;
        } elseif ($area_filter === 'near_me' && ($distance < 25 || ($aDistrict && $job['pickup_district'] === $aDistrict))) {
            $match = true;
        }

        if ($match) {
            $job['relevance_dist'] = $distance;
            $job['in_district'] = ($aDistrict && $job['pickup_district'] === $aDistrict);
            $available_jobs[] = $job;
        }
    }
}

// Sort by relevance (District first, then distance)
usort($available_jobs, function($a, $b) {
    if ($a['in_district'] && !$b['in_district']) return -1;
    if (!$a['in_district'] && $b['in_district']) return 1;
    return $a['relevance_dist'] <=> $b['relevance_dist'];
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find Jobs | BOOK-B</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        .jobs-container { display: grid; grid-template-columns: 1fr 400px; gap: 2rem; height: calc(100vh - 180px); }
        .jobs-list-side { overflow-y: auto; padding-right: 0.5rem; }
        #radar-map { height: 100%; border-radius: var(--radius-lg); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); }
        
        .job-card {
            background: white; border: 1px solid var(--border-color);
            border-radius: var(--radius-lg); padding: 1.5rem;
            margin-bottom: 1rem; transition: all 0.3s;
        }
        .job-card.highlighted { border-color: var(--primary); box-shadow: 0 0 0 2px var(--primary); transform: translateX(5px); }
        .job-header { display: flex; justify-content: space-between; align-items: start; }
        .route-path { display: flex; align-items: center; gap: 1rem; margin: 1rem 0; color: var(--text-muted); font-size: 0.9rem; }
        .badge-dist { background: #e0f2fe; color: #0284c7; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/dashboard_sidebar.php'; ?>
        
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
                <div style="display: flex; gap: 0.8rem; margin-bottom: 2rem; background: #f8fafc; padding: 0.5rem; border-radius: 12px; border: 1px solid #e2e8f0; width: fit-content;">
                    <a href="?filter=near_me" class="btn btn-sm <?php echo $area_filter === 'near_me' ? 'btn-primary' : 'btn-outline'; ?>" style="border-radius: 8px;">
                        <i class='bx bx-navigation'></i> Near Me
                    </a>
                    <a href="?filter=city" class="btn btn-sm <?php echo $area_filter === 'city' ? 'btn-primary' : 'btn-outline'; ?>" style="border-radius: 8px;">
                        <i class='bx bx-building'></i> My City (<?php echo htmlspecialchars($aCity); ?>)
                    </a>
                    <a href="?filter=district" class="btn btn-sm <?php echo $area_filter === 'district' ? 'btn-primary' : 'btn-outline'; ?>" style="border-radius: 8px;">
                        <i class='bx bx-map-alt'></i> My District (<?php echo htmlspecialchars($aDistrict); ?>)
                    </a>
                    <a href="?filter=all" class="btn btn-sm <?php echo $area_filter === 'all' ? 'btn-primary' : 'btn-outline'; ?>" style="border-radius: 8px;">
                        <i class='bx bx-globe'></i> All Jobs
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
                        <div class="job-card" id="job-<?php echo $job['id']; ?>">
                            <div class="job-header">
                                <span style="font-weight: 700; font-size: 1.1rem;">Pickup Request</span>
                                <span style="font-size: 0.8rem; background: #f1f5f9; padding: 0.2rem 0.6rem; border-radius: 20px;">
                                    <?php echo date('M d, H:i', strtotime($job['created_at'])); ?>
                                </span>
                            </div>
                            
                            <div style="display: flex; gap: 1rem; align-items: start;">
                                <img src="<?php echo htmlspecialchars($job['cover_image'] ?: 'assets/img/book-placeholder.jpg'); ?>" style="width: 50px; height: 75px; object-fit: cover; border-radius: 4px;">
                                <div>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($job['title']); ?></div>
                                    <div style="font-size: 0.9rem; color: var(--text-muted);">From: <?php echo htmlspecialchars($job['lender_fname']); ?></div>
                                </div>
                            </div>

                            <div style="background: #f8fafc; padding: 1rem; border-radius: var(--radius-md); margin-top: 10px;">
                                <div style="margin-bottom: 0.5rem; display: flex; align-items: flex-start; gap: 0.5rem;">
                                    <i class='bx bx-store-alt' style="margin-top: 3px;"></i> 
                                    <div>
                                        <strong><?php echo htmlspecialchars($job['pickup_location']); ?></strong>
                                        <?php if($job['pickup_landmark']): ?>
                                            <div style="font-size: 0.8rem; color: var(--primary); font-weight: 600;">Reference Point: <?php echo htmlspecialchars($job['pickup_landmark']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div style="display: flex; align-items: flex-start; gap: 0.5rem;">
                                    <i class='bx bx-home' style="margin-top: 3px;"></i> 
                                    <div>
                                        <span>To: <?php echo htmlspecialchars($job['order_address']); ?></span>
                                        <?php if($job['order_landmark']): ?>
                                            <div style="font-size: 0.8rem; color: var(--primary); font-weight: 600;">Reference Point: <?php echo htmlspecialchars($job['order_landmark']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <button onclick="claimJob(<?php echo $job['id']; ?>)" class="btn btn-primary w-full" style="justify-content: center; margin-top: 15px;">
                                Accept Job
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
            if (job.pickup_lat && job.pickup_lng) {
                const marker = L.marker([job.pickup_lat, job.pickup_lng])
                    .bindPopup(`
                        <div style="padding:5px;">
                            <strong style="display:block;margin-bottom:5px;">${job.title}</strong>
                            <div style="font-size:0.8rem;color:#667;">From: ${job.pickup_location}${job.pickup_landmark ? '<br><span style="color:var(--primary);font-weight:600;">Reference Point: ' + job.pickup_landmark + '</span>' : ''}</div>
                            <div style="font-size:0.8rem;color:#667;margin-top:5px;">To: ${job.order_address}${job.order_landmark ? '<br><span style="color:var(--primary);font-weight:600;">Reference Point: ' + job.order_landmark + '</span>' : ''}</div>
                            <button onclick="claimJob(${job.id})" class="btn btn-primary btn-sm w-full" style="margin-top:10px;">Accept Now</button>
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
            
            const formData = new FormData();
            formData.append('action', 'claim_job');
            formData.append('transaction_id', id);

            fetch('request_action.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if(data.success) {
                        alert('Job Accepted!');
                        window.location.href = 'dashboard_delivery_agent.php';
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
        }
    </script>
</body>
</html>
