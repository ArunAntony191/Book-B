<?php
require_once '../includes/db_helper.php';
require_once '../paths.php';
include '../includes/dashboard_header.php';

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
    header("Location: login.php");
    exit();
}

// Mark notifications as read
// markNotificationsAsReadByType($userId, ['delivery_assigned', 'delivery_cancelled', 'delivery_pending_confirmation', 'delivery_update', 'receive_confirmed', 'borrower_confirmed']);

$deliveries = getUserDeliveries($userId);

// Centrally defined return statuses
$returnStatuses = ['return_requested', 'return_approved', 'returning', 'returned', 'return_delivery_assigned', 'return_pending_confirmation'];

// Categorization
$incoming = []; $outgoing = []; $returns = []; $pickups = []; $cancelled = []; $all_deliveries = [];

foreach ($deliveries as $d) {
    $all_deliveries[] = $d;
    $isActuallyReturning = in_array($d['status'], $returnStatuses);

    if ($d['status'] === 'cancelled') {
        $cancelled[] = $d;
        continue;
    }
    if ($isActuallyReturning && $d['transaction_type'] !== 'purchase') {
        $returns[] = $d;
        continue;
    }
    if ($d['delivery_method'] === 'pickup') {
        $pickups[] = $d;
        continue;
    }
    if ($d['borrower_id'] == $userId) {
        $incoming[] = $d;
    } else {
        $outgoing[] = $d;
    }
}

function getStatusLabel($status, $agentId, $deliveryMethod = 'delivery') {
    switch ($status) {
        case 'requested': return 'Waiting for Owner';
        case 'approved': 
            if ($deliveryMethod === 'pickup') return 'Awaiting Pickup';
            return $agentId ? 'Agent Accepted' : 'Finding Agent';
        case 'active': 
            if ($deliveryMethod === 'pickup') return 'Ready for Pickup';
            return 'In Transit';
        case 'delivered': return 'Delivered & Verified';
        case 'cancelled': return 'Cancelled';
        case 'returning': return 'Return in Progress';
        case 'returned': return 'Returned & Verified';
        default: return ucfirst(str_replace('_', ' ', $status));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Deliveries | BOOK-B</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=1.2">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        :root {
            --primary-soft: #eef2ff;
            --primary-dark: #3730a3;
            --success-soft: #f0fdf4;
            --success-dark: #166534;
            --warning-soft: #fff7ed;
            --warning-dark: #9a3412;
            --danger-soft: #fff1f2;
            --danger-dark: #9f1239;
            --slate-soft: #f8fafc;
            --slate-dark: #334155;
        }

        .main-content {
            display: block;
            background: var(--bg-body);
            min-height: 100vh;
            padding-bottom: 5rem;
        }

        .tracking-wrapper {
            width: 100%;
            max-width: 900px;
            margin: 0 auto;
            padding: 2.5rem 1.5rem;
        }

        @media (min-width: 1200px) {
            .tracking-wrapper { padding: 2.5rem 6rem; }
        }

        .page-header { margin-bottom: 2.5rem; }
        .page-header h1 { font-size: 1.75rem; font-weight: 950; color: var(--text-main); margin-bottom: 0.5rem; letter-spacing: -0.5px; }
        .page-header p { color: var(--text-muted); font-weight: 500; }

        /* Tabs */
        .tabs-header {
            display: flex; gap: 0.35rem; margin-bottom: 3rem;
            background: var(--bg-body); padding: 0.5rem; border-radius: 20px;
            width: 100%; overflow-x: auto; 
            border: 1px solid var(--border-color);
            -ms-overflow-style: auto; /* Show scrollbar on IE/Edge */
        }
        .tabs-header::-webkit-scrollbar { 
            height: 4px;
            display: block; 
        }
        .tabs-header::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }
        .tab-btn {
            padding: 0.6rem 0.85rem; font-weight: 700; color: var(--text-muted);
            cursor: pointer; transition: all 0.2s; border-radius: 16px;
            background: none; border: none; font-size: 0.75rem;
            white-space: nowrap; flex: none; text-align: center;
        }
        .tab-btn span { opacity: 0.6; font-size: 0.7rem; margin-left: 3px; }
        .tab-btn.active { background: var(--bg-card); color: var(--primary); box-shadow: 0 4px 12px rgba(0,0,0,0.06); }

        /* Card Layout */
        .delivery-card {
            background: var(--bg-card); border-radius: 24px; border: 1px solid var(--border-color);
            padding: 1.5rem; margin-bottom: 2rem; position: relative;
            box-shadow: 0 2px 20px rgba(0,0,0,0.02); overflow: hidden;
        }

        .status-badge {
            position: absolute; top: 1.5rem; right: 1.5rem;
            padding: 0.5rem 0.75rem; border-radius: 10px; font-size: 0.65rem;
            font-weight: 800; text-transform: uppercase; letter-spacing: 0.8px;
            border: 1px solid transparent;
        }
        .status-badge.requested { background: var(--primary-soft); color: var(--primary-dark); border-color: #dbeafe; }
        .status-badge.approved { background: var(--success-soft); color: var(--success-dark); border-color: #dcfce7; }
        .status-badge.active { background: var(--warning-soft); color: var(--warning-dark); border-color: #ffedd5; }
        .status-badge.delivered { background: var(--success-soft); color: var(--success-dark); border-color: #dcfce7; }
        .status-badge.cancelled { background: var(--bg-body); color: var(--text-muted); border-color: var(--border-color); text-decoration: line-through; }

        /* Premium Extension Modal Styles */
        .modal-overlay {
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            display: none; 
            align-items: center; 
            justify-content: center; 
            z-index: 9999;
        }
        .modal-card {
            background: var(--bg-card); 
            border-radius: 20px; 
            width: 90%;
            max-width: 480px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25); 
            overflow: hidden;
            border: 1px solid var(--border-color);
            animation: modalFadeIn 0.3s ease-out;
        }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-20px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .ext-header { text-align: center; padding: 2rem 1.5rem 0.5rem; }
        .ext-icon-wrapper {
            width: 64px; height: 64px; background: rgba(79, 70, 229, 0.1);
            color: var(--primary); border-radius: 20px; display: flex;
            align-items: center; justify-content: center; font-size: 2rem;
            margin: 0 auto 1.25rem;
        }
        .ext-info-box {
            background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3);
            padding: 1rem; border-radius: 12px; display: flex;
            align-items: flex-start; gap: 0.75rem; margin-top: 1.25rem;
        }
        .ext-info-icon { color: #d97706; font-size: 1.25rem; }
        .ext-info-content { flex: 1; }
        .ext-info-title { font-weight: 700; color: #92400e; font-size: 0.9rem; margin-bottom: 0.2rem; }
        .ext-info-text { color: #a16207; font-size: 0.8rem; line-height: 1.4; }
        .ext-modal-footer {
            padding: 1.5rem; background: var(--bg-body);
            display: flex; flex-direction: column; gap: 0.75rem;
        }
        .ext-btn-primary {
            background: var(--primary); color: white; border: none;
            padding: 0.875rem; border-radius: 12px; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            gap: 0.5rem; transition: all 0.3s; cursor: pointer;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
        }
        .ext-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(79, 70, 229, 0.3); }
        .ext-btn-outline {
            background: var(--bg-card); color: var(--text-main);
            border: 1px solid var(--border-color); padding: 0.875rem;
            border-radius: 12px; font-weight: 600;
            display: flex; align-items: center; justify-content: center;
            gap: 0.5rem; transition: all 0.3s; cursor: pointer;
        }
        .ext-btn-outline:hover { background: var(--bg-body); }
        
        .form-group { margin-bottom: 1.25rem; }
        .form-label { display: block; font-weight: 700; margin-bottom: 0.5rem; color: var(--text-main); font-size: 0.85rem; }
        .form-control {
            width: 100%; border: 2px solid var(--border-color); border-radius: 12px;
            padding: 0.8rem; font-family: inherit; outline: none; transition: border-color 0.3s;
            background: var(--bg-body); color: var(--text-main);
        }
        .form-control:focus { border-color: var(--primary); }
        .status-badge.returning { background: var(--danger-soft); color: var(--danger-dark); border-color: #ffe4e6; }
        .status-badge.returned { background: var(--slate-soft); color: var(--slate-dark); border-color: #e2e8f0; }

        .price-tag {
            background: rgba(245, 158, 11, 0.1); color: #92400e; border: 1px solid rgba(245, 158, 11, 0.3);
            padding: 0.4rem 0.75rem; border-radius: 8px; font-size: 0.7rem;
            font-weight: 700; display: inline-flex; align-items: center; gap: 0.4rem;
            margin-bottom: 1rem;
        }

        .delivery-main { 
            display: grid; grid-template-columns: 120px 1fr; gap: 1.75rem; 
            margin-bottom: 2rem; align-items: start;
        }
        .book-img { width: 100%; aspect-ratio: 2/3; object-fit: cover; border-radius: 14px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); }
        
        .order-id { font-size: 0.65rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; display: block; margin-bottom: 0.4rem; }
        .book-title { font-size: 1.35rem; font-weight: 900; color: var(--text-main); line-height: 1.2; margin-bottom: 1rem; }

        .info-grid { display: grid; grid-template-columns: 1fr; gap: 0.75rem; }
        .info-item { display: flex; align-items: flex-start; gap: 0.75rem; font-size: 0.9rem; color: var(--text-body); }
        .info-item i { color: var(--primary); font-size: 1.1rem; margin-top: 2px; }
        .info-label { font-size: 0.7rem; font-weight: 800; color: #64748b; text-transform: uppercase; display: block; margin-bottom: 2px; }

        /* Progress Steps */
        .tracking-steps {
            display: flex; justify-content: space-between; position: relative;
            margin: 3.5rem auto 1rem auto; width: 100%; max-width: 500px;
        }
        .tracking-steps::before {
            content: ''; position: absolute; top: 12px; left: 0;
            width: 100%; height: 2px; background: var(--border-color); z-index: 1;
        }
        .step { position: relative; z-index: 2; display: flex; flex-direction: column; align-items: center; gap: 0.5rem; flex: 1; }
        .step-dot { width: 24px; height: 24px; background: var(--bg-card); border: 2.5px solid var(--border-color); border-radius: 50%; transition: all 0.3s; }
        .step-label { font-size: 0.6rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        
        .step.completed .step-dot { background: var(--primary); border-color: var(--primary); }
        .step.completed .step-label { color: var(--primary); }
        .step.active .step-dot { background: var(--bg-card); border-color: var(--primary); border-width: 6px; }
        .step.active .step-label { color: var(--text-main); }

        .verification-box {
            background: var(--bg-body); border-radius: 16px; padding: 1.25rem;
            margin: 2.5rem auto 0 auto; border: 1px solid var(--border-color);
            max-width: 600px;
        }
        .v-title { font-size: 0.65rem; font-weight: 900; color: #94a3b8; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1.25rem; }
        .v-badges { display: flex; gap: 1rem; }
        .v-badge {
            flex: 1; display: flex; align-items: center; gap: 0.6rem; background: var(--bg-card);
            padding: 0.75rem; border-radius: 12px; font-size: 0.75rem; font-weight: 700;
            color: var(--text-muted); border: 1px solid var(--border-color);
        }
        .v-badge.confirmed { color: var(--success-dark); border-color: #bbf7d0; background: #f0fdf4; }

        .btn-action {
            width: 100%; padding: 1rem; border-radius: 14px; border: none;
            font-weight: 800; font-size: 0.9rem; cursor: pointer; transition: all 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 0.6rem;
            margin-top: 1.5rem;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(99, 102, 241, 0.25); }

        .empty-state { text-align: center; padding: 5rem 2rem; color: #94a3b8; }
        .empty-state i { font-size: 4rem; margin-bottom: 1rem; opacity: 0.3; }

        /* Modals */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.5); display: none; align-items: center; justify-content: center;
            z-index: 1000; backdrop-filter: blur(4px);
        }
        .modal-card {
            background: white; border-radius: 24px; padding: 2rem; width: 90%; max-width: 420px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include '../includes/dashboard_sidebar.php'; ?>
        
        <main class="main-content">
            <div class="tracking-wrapper">
                <div class="page-header">
                    <h1>Delivery Tracking</h1>
                    <p>Manage and monitor your book movements</p>
                </div>

                <div class="tabs-header">
                    <button class="tab-btn active" onclick="switchTab('all', this)">All <span>(<?= count($all_deliveries) ?>)</span></button>
                    <button class="tab-btn" onclick="switchTab('incoming', this)">Incoming <span>(<?= count($incoming) ?>)</span></button>
                    <button class="tab-btn" onclick="switchTab('outgoing', this)">Outgoing <span>(<?= count($outgoing) ?>)</span></button>
                    <button class="tab-btn" onclick="switchTab('returns', this)">Returns <span>(<?= count($returns) ?>)</span></button>
                    <button class="tab-btn" onclick="switchTab('pickups', this)">Pickups <span>(<?= count($pickups) ?>)</span></button>
                    <button class="tab-btn" onclick="switchTab('cancelled', this)">Cancelled <span>(<?= count($cancelled) ?>)</span></button>
                </div>

                <div id="all-list" class="tab-content"><?php foreach($all_deliveries as $d) renderDeliveryCard($d); ?></div>
                <div id="incoming-list" class="tab-content" style="display:none"><?php foreach($incoming as $d) renderDeliveryCard($d); ?></div>
                <div id="outgoing-list" class="tab-content" style="display:none"><?php foreach($outgoing as $d) renderDeliveryCard($d); ?></div>
                <div id="returns-list" class="tab-content" style="display:none"><?php foreach($returns as $d) renderDeliveryCard($d, 'return'); ?></div>
                <div id="pickups-list" class="tab-content" style="display:none"><?php foreach($pickups as $d) renderDeliveryCard($d); ?></div>
                <div id="cancelled-list" class="tab-content" style="display:none"><?php foreach($cancelled as $d) renderDeliveryCard($d); ?></div>

                <?php if(empty($all_deliveries)): ?>
                    <div class="empty-state"><i class='bx bx-package'></i><h3>No deliveries track yet</h3><p>Your active book movements will appear here.</p></div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Rate Agent Modal -->
    <div id="feedback-modal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header" style="margin-bottom: 1.5rem;">
                <h2 style="font-weight: 800; font-size: 1.25rem;">Rate Delivery Agent</h2>
                <p id="rating-target-name" style="color: #64748b; font-size: 0.9rem; margin-top: 0.25rem;"></p>
            </div>
            <div class="modal-body">
                <div style="text-align: center; margin-bottom: 2rem;">
                    <div id="feedback-rating" style="font-size: 2.5rem; color: #fbbf24; cursor: pointer; display: flex; justify-content: center; gap: 0.5rem;">
                        <i class='bx bx-star' data-value="1"></i>
                        <i class='bx bx-star' data-value="2"></i>
                        <i class='bx bx-star' data-value="3"></i>
                        <i class='bx bx-star' data-value="4"></i>
                        <i class='bx bx-star' data-value="5"></i>
                    </div>
                    <p style="font-size: 0.8rem; color: #94a3b8; font-weight: 700; margin-top: 0.5rem; text-transform: uppercase;">Tap to rate</p>
                </div>
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label">Optional Comment</label>
                    <textarea id="feedback-comment" class="form-control" rows="3" placeholder="How was the delivery person?" style="width: 100%; padding: 0.75rem; border-radius: 12px; border: 1px solid var(--border-color);"></textarea>
                </div>
            </div>
            <div class="modal-footer" style="display: flex; gap: 0.75rem;">
                <button onclick="closeFeedbackModal()" class="btn-action btn-outline" style="background: var(--bg-body); border: none; flex: 1; margin-top: 0;">Cancel</button>
                <button onclick="submitFeedback()" class="btn-action btn-primary" style="flex: 1; margin-top: 0;">Submit Review</button>
            </div>
        </div>
    </div>

    <!-- Report Agent Modal -->
    <div id="agent-report-modal" class="modal-overlay">
        <div class="modal-card" style="max-width: 480px;">
            <div class="modal-header" style="margin-bottom: 1.5rem; text-align: center;">
                <div style="width: 60px; height: 60px; background: #fee2e2; color: #ef4444; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.75rem; margin: 0 auto 1rem;">
                    <i class='bx bx-flag'></i>
                </div>
                <h2 style="font-weight: 800; font-size: 1.25rem;">Report Delivery Agent</h2>
                <p id="report-agent-name" style="color: #64748b; font-size: 0.9rem; margin-top: 0.25rem;"></p>
            </div>
            <div class="modal-body">
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label">Reason for Report</label>
                    <select id="report-reason" class="form-control" style="width: 100%; padding: 0.75rem; border-radius: 12px; border: 1px solid var(--border-color); appearance: none; background: var(--bg-body) url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2364748B%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22/%3E%3C/svg%3E') no-repeat right 0.75rem center; background-size: 0.65rem auto;">
                        <option value="">Select a reason...</option>
                        <option value="behavior">Unprofessional Behavior</option>
                        <option value="late">Extremely Late Delivery</option>
                        <option value="damaged">Items Damaged in Transit</option>
                        <option value="misconduct">Safety or Misconduct Concern</option>
                        <option value="other">Other Issue</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label">Describe the Incident</label>
                    <textarea id="report-description" class="form-control" rows="4" placeholder="Please provide details about what happened..." style="width: 100%; padding: 0.75rem; border-radius: 12px; border: 1px solid var(--border-color);"></textarea>
                </div>
                <div style="background: #fff7ed; border: 1px solid #ffedd5; padding: 1rem; border-radius: 12px; color: #9a3412; font-size: 0.8rem; line-height: 1.4; margin-bottom: 1.5rem;">
                    <i class='bx bx-info-circle'></i> <strong>Note:</strong> False reports may result in penalties to your own account. Our admin team will investigate this report.
                </div>
            </div>
            <div class="modal-footer" style="display: flex; gap: 0.75rem;">
                <button onclick="closeAgentReportModal()" class="btn-action btn-outline" style="background: var(--bg-body); border: none; flex: 1; margin-top: 0;">Cancel</button>
                <button onclick="submitAgentReport()" class="btn-action" style="background: #ef4444; color: white; border: none; flex: 1; margin-top: 0; font-weight: 800;">Submit Report</button>
            </div>
        </div>
    </div>

    <?php
    function renderDeliveryCard($d, $forceLeg = null) {
        global $userId, $returnStatuses;
        $isBorrower = ($d['borrower_id'] == $userId);
        $isLender = ($d['lender_id'] == $userId);
        $isReturn = ($forceLeg === 'return') || ($forceLeg === null && in_array($d['status'], $returnStatuses));
        $isPickup = ($d['delivery_method'] === 'pickup');

        // Identify Agent
        $agentId = $isReturn ? ($d['return_agent_id'] ?? null) : ($d['delivery_agent_id'] ?? null);
        $agentName = $isReturn ? ($d['ret_agent_name'] ?? 'Agent') : ($d['agent_name'] ?? 'Agent');

        // Status Label Refinement
        $status = $d['status'];
        $badgeClass = $isReturn ? 'returning' : $status;
        if ($status === 'returned') $badgeClass = 'returned';

        $label = getStatusLabel($status, $agentId, $d['delivery_method'] ?? 'delivery');
        
        // Don't show "Verified" unless relevant parties confirmed
        if ($status === 'delivered') {
            $allConfirmedForward = !empty($d['lender_confirm_at']) && !empty($d['borrower_confirm_at']);
            if (!$allConfirmedForward) $label = 'Out for Delivery';
        } elseif ($status === 'returned') {
            $allConfirmedReturn = !empty($d['return_borrower_confirm_at']) && !empty($d['return_lender_confirm_at']);
            if (!$allConfirmedReturn) $label = 'Returned (Pending Verification)';
        }

        // Stepper Index
        $stepIdx = 0;
        if($isReturn) {
            if(!empty($d['return_agent_id'])) $stepIdx = 1;
            if(!empty($d['return_picked_up_at'])) $stepIdx = 2;
            if($d['status'] === 'returned') $stepIdx = 3;
        } else {
            $steps = ['requested', 'approved', 'assigned', 'active', 'delivered'];
            $s = $d['status']; if($s === 'assigned') $s = 'approved';
            $stepIdx = array_search($s, $steps);
            if($stepIdx === false) $stepIdx = 0;
        }

        // Verification Flags
        if ($isReturn) {
            $needHandover = $isBorrower && empty($d['return_borrower_confirm_at']);
            $needReceipt = $isLender && empty($d['return_lender_confirm_at']);
        } else {
            $needHandover = $isLender && empty($d['lender_confirm_at']);
            $needReceipt = $isBorrower && empty($d['borrower_confirm_at']);
        }

        // Show "Rate Agent" button?
        // Rules: Completed leg, person rating is the recipient of the leg, agent exists, and not already reviewed.
        $showRateAgent = false;
        if ($agentId && empty($d['is_reviewed'])) {
            if (!$isReturn && $status === 'delivered' && $isBorrower && !empty($d['borrower_confirm_at'])) {
                $showRateAgent = true;
            } elseif ($isReturn && $status === 'returned' && $isLender && !empty($d['return_lender_confirm_at'])) {
                $showRateAgent = true;
            }
        }
        ?>
        <div class="delivery-card">
            <?php if ($d['transaction_type'] === 'purchase'): 
                $itemTotal = $d['price'] * $d['quantity'];
                $deliveryFee = ($d['delivery_method'] === 'delivery') ? 50 : 0;
                $discount = (int)($d['credit_discount'] ?? 0);
                $finalTotal = $itemTotal + $deliveryFee - $discount;
            ?>
                <div class="price-tag"><i class='bx bx-check-shield'></i> Total ₹<?= number_format($finalTotal, 2) ?></div>
                <div class="price-tag" style="background:var(--bg-body); color:var(--text-muted); border-color:var(--border-color); margin-left: 0.5rem;">
                    <i class='bx bx-layer'></i> Qty: <?= $d['quantity'] ?>
                </div>
            <?php else: 
                $tokenBase = (int)($d['credit_cost'] ?? 10);
                $deliveryFeeForward = ($d['delivery_method'] === 'delivery') ? 10 : 0;
                $returnFeeCredits = ($d['return_delivery_method'] === 'delivery') ? (int)($d['return_delivery_credits'] ?? 10) : 0;
                
                // Only show return fees to the borrower (the returner)
                $finalTokens = $tokenBase + $deliveryFeeForward + ($isBorrower ? $returnFeeCredits : 0);
                $returnFeeINR = ($isBorrower && $d['return_delivery_method'] === 'delivery') ? (int)($d['return_delivery_price'] ?? 50) : 0;
            ?>
                <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                    <div class="price-tag" style="background:rgba(59, 130, 246, 0.1); color:#1e40af; border-color:rgba(59, 130, 246, 0.2);">
                        <i class='bx bxs-coin-stack'></i> Total Credits: <?= $finalTokens ?>
                    </div>
                    <?php if ($returnFeeINR > 0): ?>
                        <div class="price-tag" style="background:#fff7ed; color:#c2410c; border-color:#ffedd5;">
                            <i class='bx bx-money'></i> Return Fee: ₹<?= $returnFeeINR ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($d['quantity'] > 1): ?>
                    <div class="price-tag" style="background:var(--bg-body); color:var(--text-muted); border-color:var(--border-color);">
                        <i class='bx bx-layer'></i> Qty: <?= $d['quantity'] ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div style="font-size: 0.6rem; color: #94a3b8; margin: -0.5rem 0 1rem 0.25rem;">
                    (Base: <?= $tokenBase ?> 
                    <?php if($deliveryFeeForward > 0): ?> + Deliv: <?= $deliveryFeeForward ?><?php endif; ?>
                    <?php if($returnFeeCredits > 0 && $isBorrower): ?> + Return: <?= $returnFeeCredits ?><?php endif; ?>)
                </div>
            <?php endif; ?>

            <div class="status-badge <?= $badgeClass ?>"><?= $label ?></div>

            <div class="delivery-main">
                <img src="<?= htmlspecialchars(html_entity_decode($d['cover_image']), ENT_QUOTES, 'UTF-8') ?>" class="book-img" 
                     onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1543004218-ee141104975a?w=400';">
                
                <div class="delivery-info">
                    <span class="order-id">#ORD-<?= $d['id'] ?> • <?= date('M d', strtotime($d['created_at'])) ?></span>
                    <h2 class="book-title"><?= htmlspecialchars($d['title']) ?></h2>

                    <div class="info-grid">
                            <div class="info-item">
                                <i class='bx bx-map-pin'></i>
                                <div>
                                    <span class="info-label"><?php 
                                        if ($isPickup) {
                                            echo $isBorrower ? 'Pickup Location (Lender Address)' : 'Your Pickup Address';
                                        } else {
                                            echo $isReturn ? ($isBorrower ? 'Return to (Lender)' : 'Your Collection Address') : ($isBorrower ? 'Your Delivery Address' : 'Recipient Address');
                                        }
                                    ?></span>
                                    <?= htmlspecialchars($isReturn || $isPickup ? $d['pickup_location'] : $d['order_address']) ?>
                                </div>
                            </div>
                            <?php if ($agentId): 
                                $aPhone = $isReturn ? ($d['ret_agent_phone'] ?? '') : ($d['agent_phone'] ?? '');
                                $aRating = $isReturn ? ($d['ret_agent_rating'] ?? 0) : ($d['agent_rating'] ?? 0);
                            ?>
                            <div class="info-item" style="border-top: 1px dashed #e2e8f0; padding-top: 0.75rem; margin-top: 0.25rem; display: flex; justify-content: space-between; align-items: center;">
                                <div style="display: flex; gap: 0.75rem; align-items: flex-start;">
                                    <i class='bx bx-user-circle' style="color: var(--primary); font-size: 1.1rem; margin-top: 2px;"></i>
                                    <div>
                                        <span class="info-label">Delivery Partner</span>
                                        <div style="font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 0.4rem;">
                                            <?= htmlspecialchars($agentName) ?>
                                        </div>
                                        <div style="color: var(--text-muted); font-size: 0.85rem; margin-top: 2px;">
                                            <i class='bx bx-phone' style="font-size: 0.9rem;"></i> <?= htmlspecialchars($aPhone) ?>
                                        </div>
                                    </div>
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 0.4rem;">
                                    <a href="user_profile.php?id=<?= $agentId ?>" style="color: var(--primary); font-size: 0.65rem; font-weight: 800; text-decoration: none; padding: 0.4rem 0.75rem; background: var(--primary-soft); border-radius: 10px; text-transform: uppercase; letter-spacing: 0.5px; text-align: center;">Show Rating</a>
                                    <?php 
                                        $hasHandedOver = $isReturn ? !empty($d['return_borrower_confirm_at']) : !empty($d['lender_confirm_at']);
                                        if ($hasHandedOver): 
                                    ?>
                                    <button onclick="openAgentReportModal(<?= $agentId ?>, '<?= addslashes($agentName) ?>')" style="color: #ef4444; font-size: 0.65rem; font-weight: 800; border: none; background: #fee2e2; padding: 0.4rem 0.75rem; border-radius: 10px; text-transform: uppercase; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.3rem;">
                                        <i class='bx bx-flag'></i> Report
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                </div>
            </div>

            <?php if (!$isPickup): ?>
            <div class="tracking-steps">
                <?php if($isReturn): ?>
                    <div class="step <?= $stepIdx >= 0 ? 'completed' : '' ?>"><div class="step-dot"></div><span class="step-label">Return Request</span></div>
                    <div class="step <?= $stepIdx >= 1 ? 'completed' : ($stepIdx==0?'active':'') ?>"><div class="step-dot"></div><span class="step-label">Agent</span></div>
                    <div class="step <?= $stepIdx >= 2 ? 'completed' : ($stepIdx==1?'active':'') ?>"><div class="step-dot"></div><span class="step-label">Transit</span></div>
                    <div class="step <?= $stepIdx >= 3 ? 'completed' : ($stepIdx==2?'active':'') ?>"><div class="step-dot"></div><span class="step-label">Returned</span></div>
                <?php else: ?>
                    <div class="step <?= $stepIdx >= 0 ? 'completed' : '' ?>"><div class="step-dot"></div><span class="step-label">Request</span></div>
                    <div class="step <?= $stepIdx >= 1 ? 'completed' : '' ?>"><div class="step-dot"></div><span class="step-label">Approved</span></div>
                    <div class="step <?= $stepIdx >= 3 ? 'completed' : ($stepIdx==2?'active':'') ?>"><div class="step-dot"></div><span class="step-label">Transit</span></div>
                    <div class="step <?= $stepIdx >= 4 ? 'completed' : ($stepIdx==3?'active':'') ?>"><div class="step-dot"></div><span class="step-label">Delivered</span></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="verification-box">
                <div class="v-title"><i class='bx bx-shield-check'></i> <?= $isPickup ? '2-PARTY' : '3-PARTY' ?> VERIFICATION</div>
                <div class="v-badges">
                    <?php if($isReturn): ?>
                        <div class="v-badge <?= !empty($d['return_borrower_confirm_at'])?'confirmed':'' ?>"><i class='bx bxs-check-circle'></i> Borrower</div>
                        <?php if(!$isPickup): ?>
                            <div class="v-badge <?= !empty($d['return_agent_confirm_at'])?'confirmed':'' ?>"><i class='bx bxs-check-circle'></i> Agent</div>
                        <?php endif; ?>
                        <div class="v-badge <?= !empty($d['return_lender_confirm_at'])?'confirmed':'' ?>"><i class='bx bxs-check-circle'></i> Owner</div>
                    <?php else: ?>
                        <div class="v-badge <?= !empty($d['lender_confirm_at'])?'confirmed':'' ?>"><i class='bx bxs-check-circle'></i> Sender</div>
                        <?php if(!$isPickup): ?>
                            <div class="v-badge <?= !empty($d['agent_confirm_delivery_at'])?'confirmed':'' ?>"><i class='bx bxs-check-circle'></i> Agent</div>
                        <?php endif; ?>
                        <div class="v-badge <?= !empty($d['borrower_confirm_at'])?'confirmed':'' ?>"><i class='bx bxs-check-circle'></i> Receiver</div>
                    <?php endif; ?>
                </div>
            </div>

            <div style="margin-top: 1.5rem; display: flex; flex-direction: column; gap: 0.75rem;">
                <?php if (($status === 'delivered' || ($isPickup && in_array($status, ['approved', 'active']))) && $isBorrower && empty($d['borrower_confirm_at'])): ?>
                    <button class="btn-action btn-primary" onclick="handleVerify('confirm_receive', <?= $d['id'] ?>)">
                        <i class='bx bx-package'></i> Confirm Receive
                    </button>
                <?php endif; ?>

                <?php if ($status === 'delivered' && $isBorrower && !empty($d['borrower_confirm_at']) && $d['transaction_type'] === 'borrow'): ?>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; width: 100%;">
                        <button class="btn-action btn-outline" style="background:var(--bg-body); border:none; margin-top:0;" onclick="handleVerify('request_return_delivery', <?= $d['id'] ?>)">
                            <i class='bx bx-undo'></i> Return Book
                        </button>
                        <button class="btn-action btn-outline" style="background:var(--bg-body); border:none; margin-top:0;" onclick="openExtendModal(<?= $d['id'] ?>, '<?= $d['due_date'] ?>', <?= $d['lender_id'] ?>)">
                            <i class='bx bx-calendar-plus'></i> Extend Date
                        </button>
                    </div>
                <?php endif; ?>

                <?php if($status !== 'cancelled'): ?>
                    <?php if($needHandover && $status !== 'requested' && ($isPickup || !empty($agentId))): ?>
                        <button class="btn-action btn-primary" onclick="handleVerify('confirm_handover', <?= $d['id'] ?>)">
                            <i class='bx bx-check-circle'></i> Confirm Handover<?= $isPickup ? '' : ' to Agent' ?>
                        </button>
                    <?php endif; ?>

                    <?php if($isReturn && $status !== 'returned' && $needReceipt): ?>
                         <button class="btn-action btn-primary" onclick="handleVerify('confirm_receive', <?= $d['id'] ?>)">
                            <i class='bx bx-check-circle'></i> Confirm Receive (Return)
                        </button>
                    <?php endif; ?>

                    <?php if($status === 'requested' && empty($agentId)): ?>
                        <?php if($isBorrower): ?>
                            <button class="btn-action btn-outline" style="border-color: #fee2e2; color: #dc2626; background: #fffafb;" onclick="handleVerify('cancel_order', <?= $d['id'] ?>)">
                                <i class='bx bx-x-circle'></i> Cancel Request
                            </button>
                        <?php else: ?>
                            <button class="btn-action btn-outline" style="border-color: #fee2e2; color: #dc2626; background: #fffafb;" onclick="handleVerify('cancel_order', <?= $d['id'] ?>)">
                                <i class='bx bx-block'></i> Decline Request
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if($showRateAgent): ?>
                    <button class="btn-action" style="background: #fbbf24; color: #78350f; border: none; margin-top: 0;" 
                            onclick="openFeedbackModal(<?= $d['id'] ?>, <?= $agentId ?>, '<?= addslashes($agentName) ?>')">
                        <i class='bx bx-star'></i> Rate Agent
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    ?>

    <!-- Extension Modal -->
    <div id="extension-modal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header ext-header">
                <div class="ext-icon-wrapper">
                    <i class='bx bx-calendar-plus'></i>
                </div>
                <h2 style="font-weight: 950; font-size: 1.4rem; color: var(--text-main); margin: 0;">Extend Return Date</h2>
                <p style="color: #64748b; font-size: 0.9rem; margin-top: 0.4rem;">Request a later return date from the owner.</p>
            </div>
            <div class="modal-body" style="padding: 1rem 1.75rem 1.75rem;">
                <input type="hidden" id="ext-tx-id">
                <div class="form-group">
                    <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem;">
                        <i class='bx bx-calendar-event' style="color: var(--primary);"></i>
                        New Due Date
                    </label>
                    <input type="date" id="ext-date" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem;">
                        <i class='bx bx-comment-detail' style="color: var(--primary);"></i>
                        Reason for Extension
                    </label>
                    <textarea id="ext-reason" class="form-control" rows="3" placeholder="Explain why you need more time..."></textarea>
                </div>
                <div class="ext-info-box">
                    <div class="ext-info-icon"><i class='bx bxs-info-circle'></i></div>
                    <div class="ext-info-content">
                        <p class="ext-info-title">Cost: 5 Credits</p>
                        <p class="ext-info-text">This will be deducted once the owner approves your request.</p>
                    </div>
                </div>
            </div>
            <div class="ext-modal-footer">
                <button onclick="submitExtension()" class="ext-btn-primary">
                    <i class='bx bx-paper-plane'></i> Send Request
                </button>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                    <a id="ext-chat-btn" href="#" class="ext-btn-outline" style="text-decoration: none;">
                        <i class='bx bx-message-square-dots'></i> Chat
                    </a>
                    <button onclick="closeExtendModal()" class="ext-btn-outline" style="color: #ef4444; border-color: #fee2e2;">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tab, btn) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
            document.getElementById(tab + '-list').style.display = 'block';
        }

        async function handleVerify(action, txId) {
            const labels = {
                'confirm_handover': 'confirm you handed over the book?',
                'confirm_receive': 'confirm you received the book?',
                'request_return_delivery': 'initiate the return process for this book?',
                'cancel_order': 'cancel this request/order? This will abort the transaction.'
            };
            let label = labels[action] || 'confirm this action?';
            if (action === 'request_return_delivery') {
                label = 'initiate the return process? This will deduct 10 credits and incur a 50 INR delivery fee (payable to agent). Credits will be refunded upon on-time return.';
            }
            if(!confirm('Are you sure you want to ' + label)) return;

            const formData = new FormData();
            formData.append('action', action);
            formData.append('transaction_id', txId);

            try {
                const r = await fetch('../actions/request_action.php', { method: 'POST', body: formData });
                const data = await r.json();
                if(data.success) location.reload();
                else alert(data.message || 'Action failed');
            } catch(e) {
                alert('Connection error');
            }
        }

        /* Feedback Functions */
        let currentFeedbackTx = 0;
        let currentFeedbackReviewee = 0;
        let currentRatingValue = 0;

        function openFeedbackModal(txId, revieweeId, name) {
            currentFeedbackTx = txId;
            currentFeedbackReviewee = revieweeId;
            document.getElementById('rating-target-name').textContent = `Rating: ${name}`;
            document.getElementById('feedback-modal').style.display = 'flex';
            resetRating();
        }

        function closeFeedbackModal() {
            document.getElementById('feedback-modal').style.display = 'none';
        }

        function resetRating() {
            currentRatingValue = 0;
            document.querySelectorAll('#feedback-rating i').forEach(star => {
                star.className = 'bx bx-star';
            });
            document.getElementById('feedback-comment').value = '';
        }

        document.querySelectorAll('#feedback-rating i').forEach(star => {
            star.addEventListener('mouseover', function() {
                const val = this.dataset.value;
                highlightStars(val);
            });
            star.addEventListener('mouseout', function() {
                highlightStars(currentRatingValue);
            });
            star.addEventListener('click', function() {
                currentRatingValue = this.dataset.value;
                highlightStars(currentRatingValue);
            });
        });

        function highlightStars(val) {
            document.querySelectorAll('#feedback-rating i').forEach(star => {
                if (star.dataset.value <= val) {
                    star.className = 'bx bxs-star';
                } else {
                    star.className = 'bx bx-star';
                }
            });
        }

        async function submitFeedback() {
            if (currentRatingValue === 0) {
                showToast('Please select a rating', 'warning');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'submit_feedback');
            formData.append('transaction_id', currentFeedbackTx);
            formData.append('reviewee_id', currentFeedbackReviewee);
            formData.append('rating', currentRatingValue);
            formData.append('comment', document.getElementById('feedback-comment').value);

            try {
                const response = await fetch('../actions/feedback_action.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (result.success) {
                    showToast(result.message, 'success');
                    closeFeedbackModal();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(result.message || 'Failed to submit feedback', 'error');
                }
            } catch (error) {
                showToast('An error occurred. Please try again.', 'error');
            }
        }

        function openExtendModal(txId, currentDueDate, ownerId) {
            document.getElementById('ext-tx-id').value = txId;
            document.getElementById('ext-date').value = currentDueDate;
            document.getElementById('ext-reason').value = '';
            document.getElementById('ext-chat-btn').href = '../chat/index.php?user=' + ownerId;
            document.getElementById('extension-modal').style.display = 'flex';
        }

        function closeExtendModal() {
            document.getElementById('extension-modal').style.display = 'none';
        }

        async function submitExtension() {
            const txId = document.getElementById('ext-tx-id').value;
            const newDate = document.getElementById('ext-date').value;
            const reason = document.getElementById('ext-reason').value;

            if (!newDate) {
                alert('Please select a new due date');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'request_extension');
            formData.append('transaction_id', txId);
            formData.append('new_date', newDate);
            formData.append('reason', reason);

            try {
                const r = await fetch('../actions/request_action.php', { method: 'POST', body: formData });
                const data = await r.json();
                if (data.success) {
                    alert('Extension request sent to owner!');
                    closeExtendModal();
                } else {
                    alert(data.message || 'Failed to send request');
                }
            } catch (e) {
                alert('Error sending request');
            }
        }

        /* Agent Report Functions */
        let currentReportedAgentId = 0;

        function openAgentReportModal(agentId, name) {
            currentReportedAgentId = agentId;
            document.getElementById('report-agent-name').textContent = `Reporting: ${name}`;
            document.getElementById('report-reason').value = '';
            document.getElementById('report-description').value = '';
            document.getElementById('agent-report-modal').style.display = 'flex';
        }

        function closeAgentReportModal() {
            document.getElementById('agent-report-modal').style.display = 'none';
        }

        async function submitAgentReport() {
            const reason = document.getElementById('report-reason').value;
            const description = document.getElementById('report-description').value;

            if (!reason) {
                alert('Please select a reason for reporting');
                return;
            }
            if (!description.trim()) {
                alert('Please provide a description');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'submit_report');
            formData.append('reported_id', currentReportedAgentId);
            formData.append('reason', reason);
            formData.append('description', description);
            formData.append('type', 'user'); // Agents are users

            try {
                const response = await fetch('../actions/request_action.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (result.success) {
                    alert('Report submitted successfully. Our team will investigate.');
                    closeAgentReportModal();
                } else {
                    alert(result.message || 'Failed to submit report');
                }
            } catch (error) {
                alert('An error occurred. Please try again.');
            }
        }
        // Handle URL tab parameter
        window.addEventListener('load', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab');
            if (tab) {
                const btn = Array.from(document.querySelectorAll('.tab-btn')).find(b => b.innerText.toLowerCase().includes(tab.toLowerCase()));
                if (btn) btn.click();
            }
        });
    </script>
</body>
</html>
