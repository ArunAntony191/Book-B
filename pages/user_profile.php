<?php
require_once '../includes/db_helper.php';
require_once '../paths.php';
include '../includes/dashboard_header.php';

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

// Calculate Mission Count for Agents
$totalMissions = 0;
if ($user['role'] === 'delivery_agent') {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM transactions WHERE delivery_agent_id = ? AND agent_confirm_delivery_at IS NOT NULL) +
            (SELECT COUNT(*) FROM transactions WHERE return_agent_id = ? AND return_agent_confirm_at IS NOT NULL)
    ");
    $stmt->execute([$viewUserId, $viewUserId]);
    $totalMissions = (int)$stmt->fetchColumn();
}
?>
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
            overflow: hidden;
        }
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
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
        .badge-elite {
            background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
            color: #451a03;
            border: 1px solid #d97706;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            box-shadow: 0 2px 4px rgba(245, 158, 11, 0.3);
            animation: glow 2s infinite alternate;
        }
        @keyframes glow {
            from { box-shadow: 0 0 5px rgba(245, 158, 11, 0.3); }
            to { box-shadow: 0 0 15px rgba(245, 158, 11, 0.6); }
        }
</style>
    <div class="dashboard-wrapper">
        <?php include '../includes/dashboard_sidebar.php'; ?>
        
        <main class="main-content">
            <div class="profile-container">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?php if (!empty($user['profile_picture'])): ?>
                            <img src="<?php echo APP_URL . '/' . $user['profile_picture']; ?>" alt="<?php echo htmlspecialchars($user['firstname']); ?>">
                        <?php else: ?>
                            <?php echo strtoupper(substr($user['firstname'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="profile-info">
                        <h1><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></h1>
                        <span class="badge badge-primary"><?php echo ucfirst($user['role']); ?></span>
                        <?php if ($user['role'] === 'delivery_agent' && $user['credits'] >= MAX_TOKEN_LIMIT): ?>
                            <span class="badge badge-elite"><i class='bx bxs-crown'></i> Elite Agent</span>
                        <?php endif; ?>
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
                        <span class="stat-value"><?php echo $user['role'] === 'delivery_agent' ? $totalMissions : ($user['total_lends'] + $user['total_borrows']); ?></span>
                        <span class="stat-label"><?php echo $user['role'] === 'delivery_agent' ? 'Missions Completed' : 'Total Deals'; ?></span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-value"><?php echo $user['reputation_score']; ?></span>
                        <span class="stat-label">Reputation</span>
                    </div>

                    <?php if (($user['unpaid_fines'] ?? 0) > 0): ?>
                    <div class="stat-card" style="border-color: #fee2e2; background: #fff1f2;">
                        <span class="stat-value" style="color: #ef4444;">₹<?php echo number_format($user['unpaid_fines'], 2); ?></span>
                        <span class="stat-label" style="color: #991b1b;">Pending Dues</span>
                        
                        <?php 
                        $currentRole = $_SESSION['role'] ?? 'user';
                        if (in_array($currentRole, ['admin', 'library', 'bookstore']) && $viewUserId != $userId): ?>
                            <button id="btn-mark-paid" onclick="markPaidOffline(<?php echo $viewUserId; ?>, <?php echo $user['unpaid_fines']; ?>, this)" class="btn btn-sm" style="margin-top: 1rem; background: #10b981; color: white; border: none; width: 100%;">
                                <i class='bx bx-check-double'></i> Mark Paid (Offline)
                            </button>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <script>
                async function markPaidOffline(targetUserId, amount, btn) {
                    const confirmed = await Popup.confirm(
                        'Mark Dues as Paid',
                        `Are you sure you want to mark ₹${amount} as paid in cash? This will clear all dues for this user.`,
                        { confirmText: 'Yes, Mark Paid' }
                    );
                    if (!confirmed) return;

                    try {
                        if (btn) {
                            btn.disabled = true;
                            btn.innerHTML = "<i class='bx bx-loader-alt bx-spin'></i> Processing...";
                        }

                        const formData = new FormData();
                        formData.append('action', 'clear_fines_offline');
                        formData.append('target_user_id', targetUserId);

                        const response = await fetch('../actions/request_action.php', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();

                        if (result.success) {
                            showToast('✅ Dues cleared successfully!', 'success', 3000);
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showToast(result.message || 'Failed to clear dues.', 'error', 5000);
                            if (btn) {
                                btn.disabled = false;
                                btn.innerHTML = "<i class='bx bx-check-double'></i> Mark Paid (Offline)";
                            }
                        }
                    } catch (err) {
                        showToast('Network error. Please try again.', 'error', 4000);
                        if (btn) {
                            btn.disabled = false;
                            btn.innerHTML = "<i class='bx bx-check-double'></i> Mark Paid (Offline)";
                        }
                    }
                }
                </script>

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
