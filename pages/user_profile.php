<?php
require_once '../includes/db_helper.php';
require_once '../paths.php';
session_start();

$viewUserId = $_GET['id'] ?? 0;
if (!$viewUserId) {
    header("Location: explore.php");
    exit();
}

$user = getUserById($viewUserId);
if (!$user) {
    header("Location: explore.php");
    exit();
}

$reviews = getUserReviews($viewUserId, 20);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?> - Profile | BOOK-B</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        .profile-container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }
        .profile-header {
            background: white;
            padding: 3rem;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 3rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
        }
        .profile-avatar {
            width: 120px;
            height: 120px;
            background: var(--primary-light);
            color: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3.5rem;
            font-weight: 800;
            border: 4px solid white;
            box-shadow: var(--shadow-md);
        }
        .profile-info h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            color: var(--text-main);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        .stat-card {
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: var(--radius-md);
            text-align: center;
            border: 1px solid var(--border-color);
        }
        .stat-value {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
            display: block;
        }
        .stat-label {
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
        }
        .reviews-section {
            background: white;
            padding: 2.5rem;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }
        .review-card {
            padding: 1.5rem 0;
            border-bottom: 1px solid var(--border-color);
        }
        .review-card:last-child { border-bottom: none; }
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }
        .reviewer-name {
            font-weight: 700;
            color: var(--text-main);
        }
        .review-date {
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        .rating-display {
            color: #f59e0b;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        .review-comment {
            color: var(--text-muted);
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include '../includes/dashboard_sidebar.php'; ?>
        
        <main class="main-content">
            <div class="profile-container">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($user['firstname'], 0, 1)); ?>
                    </div>
                    <div class="profile-info">
                        <h1><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></h1>
                        <span class="badge badge-primary"><?php echo ucfirst($user['role']); ?></span>
                        <p style="margin-top: 1rem; color: var(--text-muted);">
                           Member since <?php echo date('M Y', strtotime($user['created_at'])); ?>
                        </p>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <span class="stat-value"><?php echo number_format($user['average_rating'], 1); ?> <i class='bx bxs-star'></i></span>
                        <span class="stat-label">Avg Rating (<?php echo $user['total_ratings']; ?>)</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-value"><?php echo $user['trust_score']; ?>%</span>
                        <span class="stat-label">Trust Score</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-value"><?php echo $user['total_lends'] + $user['total_borrows']; ?></span>
                        <span class="stat-label">Total Deals</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-value"><?php echo $user['reputation_score']; ?></span>
                        <span class="stat-label">Reputation</span>
                    </div>
                </div>

                <div class="reviews-section" style="margin-top: 2rem;">
                    <h2 style="font-weight: 800; margin-bottom: 2rem;">User Feedback</h2>
                    <?php if (empty($reviews)): ?>
                        <div style="text-align: center; padding: 3rem; color: var(--text-muted);">
                            <i class='bx bx-message-rounded-dots' style="font-size: 3rem; opacity: 0.3;"></i>
                            <p>No reviews yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($reviews as $r): ?>
                            <div class="review-card">
                                <div class="review-header">
                                    <span class="reviewer-name"><?php echo htmlspecialchars($r['firstname'] . ' ' . $r['lastname']); ?></span>
                                    <span class="review-date"><?php echo date('M d, Y', strtotime($r['created_at'])); ?></span>
                                </div>
                                <div class="rating-display">
                                    <?php for($i=1; $i<=5; $i++): ?>
                                        <i class='bx <?php echo $i <= $r['rating'] ? 'bxs-star' : 'bx-star'; ?>'></i>
                                    <?php endfor; ?>
                                </div>
                                <?php if ($r['comment']): ?>
                                    <p class="review-comment"><?php echo nl2br(htmlspecialchars($r['comment'])); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
