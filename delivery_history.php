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

// Fetch all completed legs for this agent
$stmt = $pdo->prepare("
    SELECT t.*, b.title, b.cover_image,
           u_borrower.firstname as borrower_fname, u_borrower.lastname as borrower_lname,
           u_lender.firstname as lender_fname, u_lender.lastname as lender_lname,
           l.location as listing_loc, l.city as listing_city, l.district as listing_dist,
           CASE 
             WHEN t.delivery_agent_id = ? THEN 'Forward Mission'
             WHEN t.return_agent_id = ? THEN 'Return Mission'
           END as job_type_label,
           CASE 
             WHEN t.delivery_agent_id = ? THEN t.agent_confirm_delivery_at
             WHEN t.return_agent_id = ? THEN t.return_agent_confirm_at
           END as completion_time
    FROM transactions t
    JOIN listings l ON t.listing_id = l.id
    JOIN books b ON l.book_id = b.id
    JOIN users u_borrower ON t.borrower_id = u_borrower.id
    JOIN users u_lender ON t.lender_id = u_lender.id
    WHERE (t.delivery_agent_id = ? AND t.agent_confirm_delivery_at IS NOT NULL)
       OR (t.return_agent_id = ? AND t.return_agent_confirm_at IS NOT NULL)
    ORDER BY completion_time DESC
");
$stmt->execute([$userId, $userId, $userId, $userId, $userId, $userId]);
$history = $stmt->fetchAll();

$stats = getUserStatsEnhanced($userId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery History | BOOK-B</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
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
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/dashboard_sidebar.php'; ?>
        
        <main class="main-content">
            <div class="section-header">
                <div>
                    <h1><i class='bx bx-history'></i> Delivery History</h1>
                    <p>Track all your completed delivery missions and earnings.</p>
                </div>
                <div style="background: white; padding: 1rem 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); text-align: center;">
                    <div style="font-size: 0.8rem; color: var(--text-muted);">Lifetime Earnings</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #059669;"><?php echo (count($history) * 10); ?> CR</div>
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
                <div style="margin-top: 2rem;">
                    <?php foreach ($history as $h): ?>
                        <div class="history-card">
                            <img src="<?php echo htmlspecialchars($h['cover_image'] ?: 'assets/images/book-placeholder.jpg'); ?>" class="book-img">
                            <div class="history-details">
                                <span class="mission-tag <?php echo $h['job_type_label'] === 'Forward Mission' ? 'tag-forward' : 'tag-return'; ?>">
                                    <?php echo $h['job_type_label']; ?>
                                </span>
                                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">Order #ORD-<?php echo $h['id']; ?></div>
                                <h3 style="margin-top: 0.2rem;"><?php echo htmlspecialchars($h['title']); ?></h3>
                                
                                <div class="address-box">
                                    <div class="addr-point">
                                        <i class='bx bx-radio-circle' style="color: #2563eb;"></i>
                                        <span>From: <?php echo $h['job_type_label'] === 'Forward Mission' ? htmlspecialchars($h['listing_loc']) : htmlspecialchars($h['order_address']); ?></span>
                                    </div>
                                    <div class="addr-point">
                                        <i class='bx bx-map' style="color: #e11d48;"></i>
                                        <span>To: <?php echo $h['job_type_label'] === 'Forward Mission' ? htmlspecialchars($h['order_address']) : htmlspecialchars($h['listing_loc']); ?></span>
                                    </div>
                                </div>

                                <div style="margin-top: 0.8rem; font-size: 0.8rem; color: var(--text-muted);">
                                    <i class='bx bx-calendar-check'></i> Finished: <?php echo date('M d, Y • h:i A', strtotime($h['completion_time'])); ?>
                                </div>
                            </div>
                            <div class="history-status">
                                <div class="earned-badge">
                                    <i class='bx bx-plus-circle'></i> 10 Credits
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
</body>
</html>
