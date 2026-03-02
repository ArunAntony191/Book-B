<?php
require_once '../includes/db_helper.php';
require_once '../paths.php';
include '../includes/dashboard_header.php';

if ($user['role'] !== 'delivery_agent' && $user['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];

// Fetch active tasks assigned to this agent
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
$my_deliveries = $stmt->fetchAll();

?>
<style>
    :root {
        --primary-logistics: #2563eb;
        --success-logistics: #16a34a;
        --bg-card: #ffffff;
    }
    .job-card {
        background: white; border-radius: var(--radius-lg); border: 1px solid var(--border-color);
        margin-bottom: 1.5rem; overflow: hidden; box-shadow: var(--shadow-sm);
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .job-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
    .job-header {
        padding: 1rem 1.25rem; background: #f8fafc; border-bottom: 1px solid var(--border-color);
        display: flex; justify-content: space-between; align-items: center;
    }
    .job-id { font-family: monospace; font-weight: 700; color: var(--text-muted); font-size: 0.9rem; }
    .job-status { font-size: 0.75rem; padding: 0.3rem 0.8rem; border-radius: 50px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }
    .status-approved { background: #fef3c7; color: #b45309; }
    .status-active { background: #dbf4ff; color: #0070f3; }
    .status-returning { background: #fee2e2; color: #dc2626; }

    .job-body { padding: 1.25rem 1.5rem; }
    .route-visual {
        position: relative; padding-left: 2rem; margin: 0.25rem 0 1.5rem 0;
        border-left: 2px dashed #e2e8f0; margin-left: 0.7rem;
    }
    .route-point { position: relative; margin-bottom: 1.25rem; }
    .route-point:last-child { margin-bottom: 0; }
    .point-icon {
        position: absolute; left: -2.6rem; top: 0; width: 30px; height: 30px;
        background: white; border: 2px solid #cbd5e1; border-radius: 50%;
        display: flex; align-items: center; justify-content: center; color: var(--text-muted);
        font-size: 1.2rem; z-index: 2;
    }
    .point-icon.active { border-color: var(--primary-logistics); color: var(--primary-logistics); background: #eff6ff; }
    
    .action-bar {
        padding: 1rem 1.25rem; border-top: 1px solid var(--border-color);
        display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;
        background: #fafafa;
    }
    
    .btn-action {
        width: 100%; padding: 0.75rem; border-radius: 10px; font-weight: 700;
        display: flex; align-items: center; justify-content: center; gap: 0.5rem;
        cursor: pointer; border: none; transition: all 0.2s; font-size: 0.9rem;
    }
    .btn-nav { background: #ffffff; color: var(--text-main); border: 1.5px solid var(--border-color); }
    .btn-nav:hover { background: #f1f5f9; border-color: #cbd5e1; }
    .btn-primary-action { background: var(--primary); color: white; box-shadow: 0 4px 10px rgba(79, 70, 229, 0.2); }
    .btn-primary-action:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(79, 70, 229, 0.3); }
    .btn-danger-outline { background: white; color: #ef4444; border: 1.5px solid #fee2e2; }
    .btn-danger-outline:hover { background: #fef2f2; border-color: #ef4444; }

    .book-preview {
        background: #f8fafc; padding: 1.25rem; border-radius: 16px; border: 1px solid #eef2f6;
        display: flex; align-items: center; gap: 1.25rem; margin-top: 1.5rem;
    }
    .book-cover-mini { width: 50px; height: 75px; object-fit: cover; border-radius: 6px; box-shadow: var(--shadow-sm); }
</style>

<div class="dashboard-wrapper">
    <?php include '../includes/dashboard_sidebar.php'; ?>
    
    <main class="main-content">
        <div class="section-header">
            <div>
                <h1>Current Tasks</h1>
                <p>Manage your active delivery and return missions. Speed and accuracy matter!</p>
            </div>
            <?php if (!empty($my_deliveries)): ?>
                <div class="badge-status" style="background: var(--primary-soft); color: var(--primary); padding: 0.5rem 1rem; border-radius: 10px; font-weight: 800;">
                    <?php echo count($my_deliveries); ?> Active Job(s)
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (empty($my_deliveries)): ?>
            <div style="text-align: center; padding: 4rem 2rem; background: var(--bg-card); border-radius: var(--radius-lg); border: 1px dashed var(--border-color); color: var(--text-muted);">
                <i class='bx bx-coffee' style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.2;"></i>
                <h2 style="color: var(--text-main); margin-bottom: 0.5rem;">All Clear!</h2>
                <p>You have no active jobs assigned to you at the moment.</p>
                <a href="delivery_jobs.php" class="btn btn-primary" style="margin-top: 1.5rem;">
                    <i class='bx bx-radar'></i> Find Available Jobs
                </a>
            </div>
        <?php else: ?>
            <div style="max-width: 750px; margin: 0 auto;">
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
                                <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.2rem; text-transform: uppercase; font-weight: 700;">PICKUP FROM (<?php echo $job['job_type'] === 'forward' ? 'Owner' : 'Borrower'; ?>)</div>
                                <div style="font-weight: 800; font-size: 1rem; color: var(--text-main);"><?php echo $job['job_type'] === 'forward' ? htmlspecialchars($job['lender_fname']) : htmlspecialchars($job['borrower_fname']); ?></div>
                                <div style="font-size: 0.85rem; margin-top: 0.2rem; color: var(--text-body);"><?php echo $job['job_type'] === 'forward' ? htmlspecialchars($job['pickup_location']) : htmlspecialchars($job['order_address']); ?></div>
                                <?php if ($job['job_type'] === 'forward' && $job['pickup_landmark']): ?>
                                    <div style="font-size: 0.8rem; color: var(--primary); font-weight: 700; margin-top: 0.2rem; background: var(--primary-soft); display: inline-block; padding: 1px 6px; border-radius: 4px;">Landmark: <?php echo htmlspecialchars($job['pickup_landmark']); ?></div>
                                <?php elseif ($job['job_type'] === 'return' && $job['order_landmark']): ?>
                                    <div style="font-size: 0.8rem; color: var(--primary); font-weight: 700; margin-top: 0.2rem; background: var(--primary-soft); display: inline-block; padding: 1px 6px; border-radius: 4px;">Landmark: <?php echo htmlspecialchars($job['order_landmark']); ?></div>
                                <?php endif; ?>
                                <div style="margin-top: 0.5rem;">
                                    <a href="tel:<?php echo $job['job_type'] === 'forward' ? $job['lender_phone'] : $job['borrower_phone']; ?>" style="font-size: 0.85rem; color: var(--primary); font-weight: 800; display: inline-flex; align-items: center; gap: 0.3rem; text-decoration: none; padding: 0.3rem 0.6rem; background: #eff6ff; border-radius: 6px;">
                                        <i class='bx bx-phone'></i> <?php echo $job['job_type'] === 'forward' ? htmlspecialchars($job['lender_phone']) : htmlspecialchars($job['borrower_phone']); ?>
                                    </a>
                                </div>
                            </div>
                            <!-- Dropoff -->
                            <div class="route-point" style="margin-top: 1.5rem;">
                                <div class="point-icon active"><i class='bx bx-home'></i></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.2rem; text-transform: uppercase; font-weight: 700;">DELIVER TO (<?php echo $job['job_type'] === 'forward' ? 'Borrower' : 'Owner'; ?>)</div>
                                <div style="font-weight: 800; font-size: 1rem; color: var(--text-main);"><?php echo $job['job_type'] === 'forward' ? htmlspecialchars($job['borrower_fname']) : htmlspecialchars($job['lender_fname']); ?></div>
                                <div style="font-size: 0.85rem; margin-top: 0.2rem; color: var(--text-body);"><?php echo $job['job_type'] === 'forward' ? htmlspecialchars($job['order_address']) : htmlspecialchars($job['pickup_location']); ?></div>
                                <?php if ($job['job_type'] === 'forward' && $job['order_landmark']): ?>
                                    <div style="font-size: 0.8rem; color: var(--primary); font-weight: 700; margin-top: 0.2rem; background: var(--primary-soft); display: inline-block; padding: 1px 6px; border-radius: 4px;">Landmark: <?php echo htmlspecialchars($job['order_landmark']); ?></div>
                                <?php elseif ($job['job_type'] === 'return' && $job['pickup_landmark']): ?>
                                    <div style="font-size: 0.8rem; color: var(--primary); font-weight: 700; margin-top: 0.2rem; background: var(--primary-soft); display: inline-block; padding: 1px 6px; border-radius: 4px;">Landmark: <?php echo htmlspecialchars($job['pickup_landmark']); ?></div>
                                <?php endif; ?>
                                <div style="margin-top: 0.5rem;">
                                    <a href="tel:<?php echo $job['job_type'] === 'forward' ? $job['borrower_phone'] : $job['lender_phone']; ?>" style="font-size: 0.85rem; color: var(--primary); font-weight: 800; display: inline-flex; align-items: center; gap: 0.3rem; text-decoration: none; padding: 0.3rem 0.6rem; background: #eff6ff; border-radius: 6px;">
                                        <i class='bx bx-phone'></i> <?php echo $job['job_type'] === 'forward' ? htmlspecialchars($job['borrower_phone']) : htmlspecialchars($job['lender_phone']); ?>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="book-preview">
                            <img src="<?php echo htmlspecialchars(html_entity_decode($job['cover_image'] ?: 'https://images.unsplash.com/photo-1543004218-ee141104975a?w=400'), ENT_QUOTES, 'UTF-8'); ?>" class="book-cover-mini">
                            <div style="flex-grow: 1;">
                                <div style="font-weight: 700; color: var(--text-main); font-size: 0.9rem;"><?php echo htmlspecialchars($job['title']); ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.1rem;">
                                    Standard Delivery • <?php echo ($job['payment_method'] === 'cod') ? '<span style="color:#d97706; font-weight:700;">Cash on Delivery</span>' : 'Online Payment'; ?>
                                </div>
                            </div>
                            <?php if ($job['payment_method'] === 'cod' && $job['job_type'] === 'forward'): ?>
                                <div style="text-align: right; background: #fffbeb; border: 1px solid #fef3c7; padding: 0.4rem 0.8rem; border-radius: 10px;">
                                    <div style="font-size: 0.65rem; color: #92400e; font-weight: 700; text-transform: uppercase;">Collect Cash</div>
                                    <div style="font-size: 1rem; font-weight: 900; color: #b45309;">₹<?php echo number_format($job['price'] ?? 0, 0); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="action-bar">
                        <?php 
                            $navDest = ($job['job_type'] === 'forward') 
                                ? (($job['status'] === 'approved' || $job['status'] === 'assigned') ? ($job['pickup_lat'].','.$job['pickup_lng']) : $job['order_address'])
                                : (($job['status'] === 'delivered' || $job['status'] === 'returning_pending') ? $job['order_address'] : ($job['pickup_lat'].','.$job['pickup_lng']));
                        ?>
                        <a href="https://www.google.com/maps/dir/?api=1&origin=Current+Location&destination=<?php echo urlencode($navDest); ?>" target="_blank" class="btn-action btn-nav">
                            <i class='bx bxs-navigation'></i> Navigate
                        </a>
                        
                        <div style="display: grid; grid-template-columns: 1fr; width: 100%;">
                            <?php if ($job['job_type'] === 'forward'): ?>
                                <?php if (!$job['picked_up_at']): ?>
                                    <button onclick="updateStatus(<?php echo $job['id']; ?>, 'active')" class="btn-action btn-primary-action">
                                        <i class='bx bx-box'></i> Confirm Pickup
                                    </button>
                                <?php elseif (!$job['agent_confirm_delivery_at']): ?>
                                    <button onclick="updateStatus(<?php echo $job['id']; ?>, 'delivered')" class="btn-action btn-primary-action" style="background: var(--success-logistics);">
                                        <i class='bx bx-check-circle'></i> Complete Delivery
                                    </button>
                                <?php else: ?>
                                    <div style="display: flex; align-items: center; justify-content: center; color: var(--success-logistics); font-weight: 700; gap: 0.5rem; background: #f0fdf4; border-radius: 12px; height: 100%;">
                                        <i class='bx bxs-check-shield'></i> Waiting for Verification
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if (!$job['return_picked_up_at']): ?>
                                    <button onclick="updateStatus(<?php echo $job['id']; ?>, 'returning_active')" class="btn-action btn-primary-action">
                                        <i class='bx bx-box'></i> Confirm Return Pickup
                                    </button>
                                <?php elseif (!$job['return_agent_confirm_at']): ?>
                                    <button onclick="updateStatus(<?php echo $job['id']; ?>, 'return_delivered')" class="btn-action btn-primary-action" style="background: var(--success-logistics);">
                                        <i class='bx bx-check-circle'></i> Complete Return
                                    </button>
                                <?php else: ?>
                                    <div style="display: flex; align-items: center; justify-content: center; color: var(--success-logistics); font-weight: 700; gap: 0.5rem; background: #f0fdf4; border-radius: 12px; height: 100%;">
                                        <i class='bx bxs-check-shield'></i> Waiting for Verification
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (in_array($job['status'], ['approved', 'assigned', 'active', 'returning'])): ?>
                    <div style="padding: 0.75rem 1.25rem; border-top: 1px solid #f1f5f9; text-align: right; background: #fff;">
                        <button onclick="cancelJob(<?php echo $job['id']; ?>)" class="btn-action btn-danger-outline" style="width: auto; padding: 0.4rem 1rem; font-size: 0.8rem; border-radius: 8px;">
                            <i class='bx bx-x-circle'></i> Cancel Mission (5 CR Penalty)
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<script>
    const statusLabels = {
        'active': 'Confirm you have picked up the book from the sender?',
        'delivered': 'Confirm you have successfully delivered the book to the recipient?',
        'returning_active': 'Confirm you have picked up the book from the borrower for return?',
        'return_delivered': 'Confirm you have returned the book to the owner?'
    };

    async function updateStatus(txId, status) {
        const msg = statusLabels[status] || 'Confirm this status update?';
        const confirmed = await Popup.confirm('Update Mission Status', msg, { confirmText: 'Yes, Confirm' });
        if (!confirmed) return;
        
        const formData = new FormData();
        formData.append('action', 'update_delivery_status');
        formData.append('transaction_id', txId);
        formData.append('status', status);

        fetch('../actions/request_action.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('✅ Mission status updated!', 'success', 2500);
                setTimeout(() => location.reload(), 1200);
            } else {
                showToast('Error: ' + data.message, 'error', 5000);
            }
        })
        .catch(err => showToast('Connection error.', 'error', 4000));
    }

    async function cancelJob(txId) {
        const confirmed = await Popup.confirm(
            'Cancel Mission',
            'Are you sure you want to cancel this mission? A 5-credit penalty will be applied and your trust score will drop.',
            { confirmText: 'Yes, Cancel Mission', confirmStyle: 'danger' }
        );
        if (!confirmed) return;
        
        const formData = new FormData();
        formData.append('action', 'cancel_job');
        formData.append('transaction_id', txId);

        fetch('../actions/request_action.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('❌ Mission cancelled. 5 credits deducted.', 'warning', 4000);
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
