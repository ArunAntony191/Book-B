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
markNotificationsAsReadByType($userId, ['delivery_assigned', 'delivery_cancelled', 'delivery_pending_confirmation', 'delivery_update', 'receipt_confirmed', 'borrower_confirmed']);

$deliveries = getUserDeliveries($userId);

// Centrally defined return statuses
$returnStatuses = ['return_requested', 'return_approved', 'returning', 'returned', 'return_delivery_assigned', 'return_pending_confirmation'];

// Categorization
$incoming = []; $outgoing = []; $returns = []; $exchanges = []; $pickups = []; $cancelled = []; $all_deliveries = [];

foreach ($deliveries as $d) {
    $all_deliveries[] = $d;
    $isActuallyReturning = in_array($d['status'], $returnStatuses);

    if ($d['status'] === 'cancelled') {
        $cancelled[] = $d;
        continue;
    }
    if ($d['transaction_type'] === 'exchange') {
        $exchanges[] = $d;
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
            return $agentId ? 'Agent Assigned' : 'Finding Agent';
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
    <link rel="stylesheet" href="../assets/css/style.css">
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
            background: #f8fafc;
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
        .page-header h1 { font-size: 1.75rem; font-weight: 950; color: #0f172a; margin-bottom: 0.5rem; letter-spacing: -0.5px; }
        .page-header p { color: #64748b; font-weight: 500; }

        /* Tabs */
        .tabs-header {
            display: flex; gap: 0.35rem; margin-bottom: 3rem;
            background: #f1f5f9; padding: 0.5rem; border-radius: 20px;
            width: 100%; overflow-x: auto; 
            border: 1px solid #e2e8f0;
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
            padding: 0.6rem 0.85rem; font-weight: 700; color: #64748b;
            cursor: pointer; transition: all 0.2s; border-radius: 16px;
            background: none; border: none; font-size: 0.75rem;
            white-space: nowrap; flex: none; text-align: center;
        }
        .tab-btn span { opacity: 0.6; font-size: 0.7rem; margin-left: 3px; }
        .tab-btn.active { background: white; color: var(--primary); box-shadow: 0 4px 12px rgba(0,0,0,0.06); }

        /* Card Layout */
        .delivery-card {
            background: white; border-radius: 24px; border: 1px solid #eef2f6;
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
        .status-badge.cancelled { background: #f1f5f9; color: #475569; border-color: #cbd5e1; text-decoration: line-through; }
        .status-badge.returning { background: var(--danger-soft); color: var(--danger-dark); border-color: #ffe4e6; }
        .status-badge.returned { background: var(--slate-soft); color: var(--slate-dark); border-color: #e2e8f0; }

        .price-tag {
            background: #fffbeb; color: #92400e; border: 1px solid #fef3c7;
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
        .book-title { font-size: 1.35rem; font-weight: 900; color: #0f172a; line-height: 1.2; margin-bottom: 1rem; }

        .info-grid { display: grid; grid-template-columns: 1fr; gap: 0.75rem; }
        .info-item { display: flex; align-items: flex-start; gap: 0.75rem; font-size: 0.9rem; color: #475569; }
        .info-item i { color: var(--primary); font-size: 1.1rem; margin-top: 2px; }
        .info-label { font-size: 0.7rem; font-weight: 800; color: #64748b; text-transform: uppercase; display: block; margin-bottom: 2px; }

        /* Progress Steps */
        .tracking-steps {
            display: flex; justify-content: space-between; position: relative;
            margin: 3.5rem auto 1rem auto; width: 100%; max-width: 500px;
        }
        .tracking-steps::before {
            content: ''; position: absolute; top: 12px; left: 0;
            width: 100%; height: 2px; background: #f1f5f9; z-index: 1;
        }
        .step { position: relative; z-index: 2; display: flex; flex-direction: column; align-items: center; gap: 0.5rem; flex: 1; }
        .step-dot { width: 24px; height: 24px; background: white; border: 2.5px solid #f1f5f9; border-radius: 50%; transition: all 0.3s; }
        .step-label { font-size: 0.6rem; font-weight: 800; color: #cbd5e1; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .step.completed .step-dot { background: var(--primary); border-color: var(--primary); }
        .step.completed .step-label { color: var(--primary); }
        .step.active .step-dot { background: white; border-color: var(--primary); border-width: 6px; }
        .step.active .step-label { color: #0f172a; }

        .verification-box {
            background: #f8fafc; border-radius: 16px; padding: 1.25rem;
            margin: 2.5rem auto 0 auto; border: 1px solid #f1f5f9;
            max-width: 600px;
        }
        .v-title { font-size: 0.65rem; font-weight: 900; color: #94a3b8; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1.25rem; }
        .v-badges { display: flex; gap: 1rem; }
        .v-badge {
            flex: 1; display: flex; align-items: center; gap: 0.6rem; background: white;
            padding: 0.75rem; border-radius: 12px; font-size: 0.75rem; font-weight: 700;
            color: #94a3b8; border: 1px solid #eef2f6;
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
                    <button class="tab-btn" onclick="switchTab('exchanges', this)">Exchanges <span>(<?= count($exchanges) ?>)</span></button>
                    <button class="tab-btn" onclick="switchTab('pickups', this)">Pickups <span>(<?= count($pickups) ?>)</span></button>
                    <button class="tab-btn" onclick="switchTab('cancelled', this)">Cancelled <span>(<?= count($cancelled) ?>)</span></button>
                </div>

                <div id="all-list" class="tab-content"><?php foreach($all_deliveries as $d) renderDeliveryCard($d); ?></div>
                <div id="incoming-list" class="tab-content" style="display:none"><?php foreach($incoming as $d) renderDeliveryCard($d); ?></div>
                <div id="outgoing-list" class="tab-content" style="display:none"><?php foreach($outgoing as $d) renderDeliveryCard($d); ?></div>
                <div id="returns-list" class="tab-content" style="display:none"><?php foreach($returns as $d) renderDeliveryCard($d, 'return'); ?></div>
                <div id="exchanges-list" class="tab-content" style="display:none"><?php foreach($exchanges as $d) renderDeliveryCard($d); ?></div>
                <div id="pickups-list" class="tab-content" style="display:none"><?php foreach($pickups as $d) renderDeliveryCard($d); ?></div>
                <div id="cancelled-list" class="tab-content" style="display:none"><?php foreach($cancelled as $d) renderDeliveryCard($d); ?></div>

                <?php if(empty($all_deliveries)): ?>
                    <div class="empty-state"><i class='bx bx-package'></i><h3>No deliveries track yet</h3><p>Your active book movements will appear here.</p></div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Modals (Simplified for Redo) -->
    <div id="feedback-modal" class="modal-overlay"><div class="modal-card"><h2>Rate Your Experience</h2><p id="rating-target"></p><button onclick="closeModal('feedback-modal')">Close</button></div></div>

    <?php
    function renderDeliveryCard($d, $forceLeg = null) {
        global $userId, $returnStatuses;
        $isBorrower = ($d['borrower_id'] == $userId);
        $isLender = ($d['lender_id'] == $userId);
        $isReturn = ($forceLeg === 'return') || ($forceLeg === null && in_array($d['status'], $returnStatuses));
        $isPickup = ($d['delivery_method'] === 'pickup');

        // Status Label Refinement
        $status = $d['status'];
        $badgeClass = $isReturn ? 'returning' : $status;
        if ($status === 'returned') $badgeClass = 'returned';

        $label = getStatusLabel($status, $isReturn ? ($d['return_agent_id'] ?? null) : ($d['delivery_agent_id'] ?? null), $d['delivery_method'] ?? 'delivery');
        
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
        ?>
        <div class="delivery-card">
            <?php if ($d['transaction_type'] === 'purchase'): 
                $itemTotal = $d['price'] * $d['quantity'];
                $deliveryFee = ($d['delivery_method'] === 'delivery') ? 50 : 0;
                $discount = (int)($d['credit_discount'] ?? 0);
                $finalTotal = $itemTotal + $deliveryFee - $discount;
            ?>
                <div class="price-tag"><i class='bx bx-check-shield'></i> Total ₹<?= number_format($finalTotal, 2) ?></div>
                <div class="price-tag" style="background:#f1f5f9; color:#475569; border-color:#e2e8f0; margin-left: 0.5rem;">
                    <i class='bx bx-layer'></i> Qty: <?= $d['quantity'] ?>
                </div>
            <?php else: 
                $tokenBase = (int)($d['credit_cost'] ?? 10);
                $deliveryFee = ($d['delivery_method'] === 'delivery') ? 10 : 0;
                $finalTokens = $tokenBase + $deliveryFee;
            ?>
                <div class="price-tag" style="background:#eff6ff; color:#1e40af; border-color:#dbeafe;">
                    <i class='bx bxs-coin-stack'></i> Total Tokens: <?= $finalTokens ?>
                </div>
                <?php if ($d['quantity'] > 1): ?>
                <div class="price-tag" style="background:#f1f5f9; color:#475569; border-color:#e2e8f0; margin-left: 0.5rem;">
                    <i class='bx bx-layer'></i> Qty: <?= $d['quantity'] ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="status-badge <?= $badgeClass ?>"><?= $label ?></div>

            <div class="delivery-main">
                <img src="<?= htmlspecialchars($d['cover_image']) ?>" class="book-img" 
                     onerror="this.src='https://images.unsplash.com/photo-1543004218-ee141104975a?w=400';">
                
                <div class="delivery-info">
                    <span class="order-id">#ORD-<?= $d['id'] ?> • <?= date('M d', strtotime($d['created_at'])) ?></span>
                    <h2 class="book-title"><?= htmlspecialchars($d['title']) ?></h2>

                    <div class="info-grid">
                        <div class="info-item">
                            <i class='bx bx-map-pin'></i>
                            <div>
                                <span class="info-label"><?= $isReturn ? ($isBorrower ? 'Return to (Lender)' : 'Your Collection Address') : ($isBorrower ? 'Your Delivery Address' : 'Recipient Address') ?></span>
                                <?= htmlspecialchars($isReturn || $isPickup ? $d['pickup_location'] : $d['order_address']) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

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

            <div class="verification-box">
                <div class="v-title"><i class='bx bx-shield-check'></i> <?= $isPickup ? '2-PARTY' : '3-PARTY' ?> VERIFICATION</div>
                <div class="v-badges">
                    <?php if($isReturn): ?>
                        <div class="v-badge <?= !empty($d['return_borrower_confirm_at'])?'confirmed':'' ?>"><i class='bx bxs-check-circle'></i> Borrowers</div>
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

            <?php if ($status === 'delivered' && $isBorrower && empty($d['borrower_confirm_at'])): ?>
                <!-- Already have Confirm Receipt button below -->
            <?php elseif ($status === 'delivered' && $isBorrower && !empty($d['borrower_confirm_at']) && $d['transaction_type'] === 'borrow'): ?>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; width: 100%; margin-top: 0.5rem;">
                    <button class="btn-action btn-outline" onclick="handleVerify('request_return_delivery', <?= $d['id'] ?>)">
                        <i class='bx bx-undo'></i> Return Book
                    </button>
                    <button class="btn-action btn-outline" onclick="openExtendModal(<?= $d['id'] ?>, '<?= $d['due_date'] ?>', <?= $d['lender_id'] ?>)">
                        <i class='bx bx-calendar-plus'></i> Extend Date
                    </button>
                </div>
            <?php endif; ?>

            <?php if($status !== 'cancelled'): ?>
                <?php if($needHandover): ?>
                    <button class="btn-action btn-primary" onclick="handleVerify('confirm_handover', <?= $d['id'] ?>)">
                        <i class='bx bx-check-circle'></i> Confirm Handover<?= $isPickup ? '' : ' to Agent' ?>
                    </button>
                <?php endif; ?>

                <?php if($needReceipt): ?>
                    <button class="btn-action btn-primary" onclick="handleVerify('confirm_receipt', <?= $d['id'] ?>)">
                        <i class='bx bx-package'></i> Confirm Receipt
                    </button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }
    ?>

    <!-- Extension Modal -->
    <div id="extension-modal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h2 style="font-weight: 800; font-size: 1.25rem;">Extend Return Date</h2>
                <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.25rem;">Request a later return date from the owner.</p>
            </div>
            <div class="modal-body">
                <input type="hidden" id="ext-tx-id">
                <div class="form-group">
                    <label class="form-label">New Due Date</label>
                    <input type="date" id="ext-date" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Reason for Extension</label>
                    <textarea id="ext-reason" class="form-control" rows="3" placeholder="Explain why you need more time..."></textarea>
                </div>
                <div style="background: #fffbeb; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid #fef3c7; color: #92400e; font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class='bx bx-info-circle'></i> This extension will cost 5 credits upon approval.
                </div>
            </div>
            <div class="modal-footer">
                <a id="ext-chat-btn" href="#" class="btn-action btn-outline" style="margin-right: auto; border: none; background: #f1f5f9; color: var(--text-main);">
                    <i class='bx bx-message-square-dots'></i> Chat
                </a>
                <button onclick="closeExtendModal()" class="btn-action btn-outline" style="border: none;">Cancel</button>
                <button onclick="submitExtension()" class="btn-action btn-primary">Send Request</button>
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
                'confirm_receipt': 'confirm you received the book?',
                'request_return_delivery': 'initiate the return process for this book?'
            };
            const label = labels[action] || 'confirm this action?';
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
            } catch(e) {
                alert('Error sending request');
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
