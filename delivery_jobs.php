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

$aLatStart = $agent['service_start_lat'] ?? null;
$aLngStart = $agent['service_start_lng'] ?? null;
$aLatEnd = $agent['service_end_lat'] ?? null;
$aLngEnd = $agent['service_end_lng'] ?? null;

// Fetch ALL pending deliveries
// We want items that are 'approved' (ready for pickup) but have NO agent assigned
$stmt = $pdo->prepare("
    SELECT t.*, b.title, b.cover_image,
           u_borrower.firstname as borrower_fname, u_borrower.lastname as borrower_lname,
           u_lender.firstname as lender_fname, u_lender.lastname as lender_lname,
           l.location as pickup_location, l.latitude as pickup_lat, l.longitude as pickup_lng
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
$route_set = ($aLatStart && $aLatEnd);

foreach ($all_jobs as $job) {
    if (!$route_set) {
        $available_jobs[] = $job; // Show all if no route
    } else {
        // Filter by proximity
        $distStart = calculateDistance($job['pickup_lat'], $job['pickup_lng'], $aLatStart, $aLngStart);
        $distEnd = calculateDistance($job['pickup_lat'], $job['pickup_lng'], $aLatEnd, $aLngEnd);
        
        // Show if pickup is within 25km of either start or end of service route
        // This is a simplified logic; in real app we'd check distance to the line segment
        if ($distStart < 25 || $distEnd < 25) {
            $available_jobs[] = $job;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find Jobs | BOOK-B</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        .job-card {
            background: white; border: 1px solid var(--border-color);
            border-radius: var(--radius-lg); padding: 1.5rem;
            margin-bottom: 1rem;
            display: flex; flex-direction: column; gap: 1rem;
        }
        .job-header {
            display: flex; justify-content: space-between; align-items: start;
        }
        .route-path {
            display: flex; align-items: center; gap: 1rem;
            margin: 1rem 0; color: var(--text-muted); font-size: 0.9rem;
        }
        .badge-dist {
            background: #e0f2fe; color: #0284c7; padding: 0.2rem 0.5rem;
            border-radius: 4px; font-size: 0.75rem; font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/dashboard_sidebar.php'; ?>
        
        <main class="main-content">
            <div style="margin-bottom: 2rem;">
                <h1>Find Jobs</h1>
                <p style="color: var(--text-muted);">Available delivery requests in your area</p>
            </div>

            <?php if (!$route_set): ?>
                <div style="background: #fff7ed; padding: 1rem; border-radius: var(--radius-md); border: 1px solid #ffedd5; margin-bottom: 2rem; display: flex; align-items: center; gap: 1rem;">
                    <i class='bx bx-map-pin' style="font-size: 1.5rem; color: #c2410c;"></i>
                    <div style="flex-grow:1">
                        <strong>Filters are open!</strong>
                        <div style="font-size: 0.85rem; color: #9a3412;">You're seeing ALL jobs because you haven't set a route yet.</div>
                    </div>
                    <a href="agent_route.php" class="btn btn-sm btn-outline">Set Route</a>
                </div>
            <?php endif; ?>

            <?php if (empty($available_jobs)): ?>
                <div style="text-align: center; padding: 4rem; background: white; border-radius: var(--radius-lg); border: 1px dashed var(--border-color);">
                    <i class='bx bx-search-alt' style="font-size: 3rem; color: var(--text-muted); margin-bottom: 1rem;"></i>
                    <h3>No jobs found</h3>
                    <p style="color: var(--text-muted);">There are no pending delivery requests in you area right now.</p>
                </div>
            <?php else: ?>
                <div style="display: grid; gap: 1.5rem; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));">
                    <?php foreach ($available_jobs as $job): ?>
                        <div class="job-card">
                            <div class="job-header">
                                <span style="font-weight: 700; font-size: 1.1rem;">Pickup Request</span>
                                <span style="font-size: 0.8rem; background: #f1f5f9; padding: 0.2rem 0.6rem; border-radius: 20px;">
                                    <?php echo date('M d, H:i', strtotime($job['created_at'])); ?>
                                </span>
                            </div>
                            
                            <div style="display: flex; gap: 1rem; align-items: start;">
                                <img src="<?php echo htmlspecialchars($job['cover_image'] ?: 'assets/images/book-placeholder.jpg'); ?>" style="width: 50px; height: 75px; object-fit: cover; border-radius: 4px;">
                                <div>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($job['title']); ?></div>
                                    <div style="font-size: 0.9rem; color: var(--text-muted);">From: <?php echo htmlspecialchars($job['lender_fname']); ?></div>
                                </div>
                            </div>

                            <div style="background: #f8fafc; padding: 1rem; border-radius: var(--radius-md);">
                                <div style="margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class='bx bx-store-alt'></i> 
                                    <strong><?php echo htmlspecialchars($job['pickup_location']); ?></strong>
                                </div>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <i class='bx bx-home'></i> 
                                    <span>To: <?php echo htmlspecialchars($job['order_address']); ?></span>
                                </div>
                            </div>

                            <button onclick="claimJob(<?php echo $job['id']; ?>)" class="btn btn-primary w-full" style="justify-content: center; margin-top: auto;">
                                Accept Job
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </main>
    </div>

    <script>
        function claimJob(id) {
            if(!confirm('Accept this delivery job?')) return;
            
            const formData = new FormData();
            formData.append('action', 'claim_job'); // We need to implement this in request_action.php
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
