<?php
require_once '../includes/db_helper.php';
require_once '../paths.php';
include '../includes/dashboard_header.php';

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
    header("Location: login.php");
    exit();
}

// Mark delivery notifications as read when visiting this page
markNotificationsAsReadByType($userId, ['delivery_assigned', 'delivery_cancelled', 'delivery_pending_confirmation', 'delivery_update', 'receipt_confirmed', 'borrower_confirmed']);

$deliveries = getUserDeliveries($userId);

// Categorize deliveries
$incoming = [];
$outgoing = [];
$returns = [];
$exchanges = [];

foreach ($deliveries as $d) {
    // If it's an exchange transaction, it goes to Exchanges tab
    if ($d['transaction_type'] === 'exchange') {
        $exchanges[] = $d;
        continue;
    }

    // If it's in a return phase, it goes to Returns tab
    $isActuallyReturning = (in_array($d['status'], ['returning', 'returned']));
    if ($isActuallyReturning) {
        $returns[] = $d;
        // Do NOT continue here; let it also appear in Incoming/Outgoing as a completed forward leg
    }

    // Forward phase categorization (or historically forward)
    if ($d['borrower_id'] == $userId) {
        $incoming[] = $d;
    } else {
        $outgoing[] = $d;
    }
}

function getStatusLabel($status, $agentId) {
    switch ($status) {
        case 'requested': return 'Waiting for Owner';
        case 'approved': return $agentId ? 'Agent Assigned' : 'Finding Agent';
        case 'active': return 'In Transit';
        case 'delivered': return 'Delivered & Verified';
        case 'returning': return 'Return in Progress';
        case 'returned': return 'Returned to Owner';
        default: return ucfirst($status);
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
            --glass-bg: rgba(255, 255, 255, 0.9);
            --glass-border: rgba(255, 255, 255, 0.4);
            --success-logistics: #10b981;
        }

        .tracking-wrapper { max-width: 1000px; margin: 0 auto; padding: 0 1.5rem; }
        .page-header { margin-bottom: 2.5rem; text-align: left; }
        
        .confirmation-box .actions {
            display: flex; gap: 1rem; margin-top: 1.5rem; justify-content: flex-end;
        }

        /* Feedback Modal */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            display: none; align-items: center; justify-content: center; z-index: 1000;
            backdrop-filter: blur(4px);
        }
        .modal-card {
            background: white; border-radius: var(--radius-lg); width: 450px;
            box-shadow: var(--shadow-xl); overflow: hidden;
        }
        .modal-header { padding: 1.5rem; border-bottom: 1px solid var(--border-color); }
        .modal-body { padding: 1.5rem; }
        .modal-footer { padding: 1.25rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 1rem; }
        
        .rating-stars {
            display: flex; gap: 0.5rem; font-size: 2rem; color: #cbd5e1; cursor: pointer;
            margin-bottom: 1.5rem;
        }
        .rating-stars i.bxs-star { color: #f59e0b; }
        .review-textarea {
            width: 100%; border: 1px solid var(--border-color); border-radius: var(--radius-md);
            padding: 1rem; font-family: inherit; resize: none; outline: none;
        }
        .review-textarea:focus { border-color: var(--primary); }
        
        .tabs-header {
            display: flex; gap: 1rem; margin-bottom: 2.5rem;
            background: rgba(241, 245, 249, 0.8); backdrop-filter: blur(8px);
            padding: 0.6rem; border-radius: 18px; width: fit-content;
            border: 1px solid var(--glass-border);
        }
        .tab-btn {
            padding: 0.7rem 1.8rem; font-weight: 800; color: #64748b;
            cursor: pointer; transition: all 0.3s; border-radius: 14px;
            background: none; border: none; font-size: 0.9rem;
        }
        .tab-btn.active { background: white; color: var(--primary); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }

        .delivery-card {
            background: var(--glass-bg); backdrop-filter: blur(12px);
            border-radius: 24px; border: 1px solid var(--glass-border);
            padding: 2rem; margin-bottom: 2rem; transition: all 0.3s;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03); position: relative;
            overflow: hidden;
        }
        .delivery-card:hover { transform: translateY(-5px); box-shadow: 0 15px 40px rgba(0,0,0,0.08); }

        .status-badge {
            position: absolute; top: 1.5rem; right: 1.5rem;
            padding: 0.6rem 1.2rem; border-radius: 14px; font-size: 0.75rem;
            font-weight: 800; text-transform: uppercase; letter-spacing: 0.8px;
        }
        .status-badge.requested { background: #eff6ff; color: #3b82f6; }
        .status-badge.approved { background: #f0fdf4; color: #16a34a; }
        .status-badge.active { background: #fff7ed; color: #ea580c; }
        .status-badge.delivered { background: #f0fdf4; color: #16a34a; }
        .status-badge.returning { background: #fff1f2; color: #e11d48; }
        .status-badge.returned { background: #f8fafc; color: #64748b; }

        .credit-info-tag {
            background: #f8fafc; border: 1px solid #e2e8f0;
            padding: 0.5rem 1rem; border-radius: 12px;
            display: inline-flex; align-items: center; gap: 0.5rem;
            font-size: 0.8rem; font-weight: 700; color: #64748b;
            margin-bottom: 1.5rem;
        }

        .delivery-main { display: grid; grid-template-columns: 140px 1fr; gap: 2.5rem; margin-bottom: 2.5rem; }
        .book-aside { width: 100%; position: relative; }
        .book-img { 
            width: 100%; aspect-ratio: 2/3; object-fit: cover; border-radius: 16px; 
            box-shadow: 0 8px 25px rgba(0,0,0,0.1); 
        }
        
        .delivery-body { flex: 1; }
        .order-meta { font-size: 0.75rem; color: #94a3b8; margin-bottom: 0.5rem; display: block; font-weight: 800; letter-spacing: 0.5px; }
        .book-title { font-size: 1.6rem; font-weight: 900; margin-bottom: 0.75rem; color: #1e293b; line-height: 1.1; }
        
        .location-info { 
            display: grid; grid-template-columns: 1fr; gap: 0.8rem; 
            margin-top: 1.5rem; background: white; padding: 1.25rem; 
            border-radius: 18px; border: 1px solid #f1f5f9;
        }
        .loc-item { display: flex; align-items: flex-start; gap: 0.75rem; font-size: 0.95rem; color: #475569; }
        .loc-item i { margin-top: 4px; color: var(--primary); font-size: 1.2rem; }
        .landmark-tag { font-size: 0.75rem; color: var(--primary); font-weight: 800; margin-top: 4px; background: #eef2ff; padding: 2px 10px; border-radius: 6px; display: inline-block; }

        /* Progress Steps */
        .tracking-steps { display: flex; justify-content: space-between; position: relative; margin: 3.5rem 0 1.5rem 0; width: 100%; }
        .tracking-steps::before {
            content: ''; position: absolute; top: 12px; left: 0; 
            width: 100%; height: 3px; background: #f1f5f9; z-index: 1;
        }
        .step { position: relative; z-index: 2; display: flex; flex-direction: column; align-items: center; gap: 0.75rem; flex: 1; }
        .step-dot { 
            width: 26px; height: 26px; background: white; border: 3px solid #f1f5f9; 
            border-radius: 50%; transition: all 0.4s; 
        }
        .step-label { font-size: 0.65rem; font-weight: 900; color: #cbd5e1; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .step.completed .step-dot { background: var(--primary); border-color: var(--primary); box-shadow: 0 0 0 5px rgba(99, 102, 241, 0.15); }
        .step.completed .step-label { color: var(--primary); }
        .step.active .step-dot { background: white; border-color: var(--primary); border-width: 6px; box-shadow: 0 0 0 8px rgba(99, 102, 241, 0.05); }
        .step.active .step-label { color: #1e293b; }

        .confirmation-box {
            background: rgba(248, 250, 252, 0.6); border-radius: 20px; padding: 1.5rem;
            margin-top: 2rem; border: 1px solid #f1f5f9;
        }
        .confirm-title { font-size: 0.75rem; font-weight: 900; color: #64748b; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.6rem; letter-spacing: 0.5px; }
        
        .confirm-badges { display: flex; gap: 1.25rem; margin-bottom: 1.5rem; }
        .c-badge { 
            flex: 1; display: flex; align-items: center; gap: 0.75rem; 
            background: white; padding: 1rem; border-radius: 14px; border: 1px solid #eef2f6;
            font-size: 0.85rem; font-weight: 700; color: #94a3b8; box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        }
        .c-badge.verified { border-color: var(--success-logistics); color: var(--success-logistics); background: #f0fdf4; }
        .c-badge i { font-size: 1.3rem; }

        .btn-confirm {
            display: flex; align-items: center; justify-content: center; gap: 0.6rem;
            width: 100%; padding: 1.1rem; border-radius: 16px; border: none;
            font-weight: 800; cursor: pointer; transition: all 0.3s;
            font-size: 1rem;
        }
        .btn-confirm.primary { background: var(--primary); color: white; }
        .btn-confirm.primary:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(99, 102, 241, 0.3); }
        
        .mini-map {
            height: 250px; width: 100%; border-radius: 20px;
            margin-top: 1.5rem; border: 4px solid white; box-shadow: var(--shadow-md); z-index: 1;
        }
        .empty-state { text-align: center; padding: 5rem 2rem; color: #94a3b8; }
        .empty-state i { font-size: 5rem; margin-bottom: 1.5rem; opacity: 0.2; }
        
        /* Agent Profile Button */
        .agent-profile-btn {
            background: white;
            border: 1px solid #e2e8f0;
            color: var(--primary);
            padding: 0.4rem 1rem;
            border-radius: 8px;
            font-weight: 700;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }
        .agent-profile-btn:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.25);
        }
        .agent-profile-btn i {
            transition: transform 0.3s ease;
        }
        .agent-profile-btn:hover i {
            transform: scale(1.1);
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
                    <p style="color: #64748b; font-weight: 500;">Secure 3-party verified shipments</p>
                </div>

                <div class="tabs-header">
                    <button class="tab-btn active" onclick="switchTab('incoming', this)">
                        Incoming <span style="font-size: 0.75rem; opacity: 0.6; font-weight: 600;">(<?php echo count($incoming); ?>)</span>
                    </button>
                    <button class="tab-btn" onclick="switchTab('outgoing', this)">
                        Outgoing <span style="font-size: 0.75rem; opacity: 0.6; font-weight: 600;">(<?php echo count($outgoing); ?>)</span>
                    </button>
                    <button class="tab-btn" onclick="switchTab('returns', this)">
                        Return <span style="font-size: 0.75rem; opacity: 0.6; font-weight: 600;">(<?php echo count($returns); ?>)</span>
                    </button>
                    <button class="tab-btn" onclick="switchTab('exchanges', this)">
                        Exchange <span style="font-size: 0.75rem; opacity: 0.6; font-weight: 600;">(<?php echo count($exchanges); ?>)</span>
                    </button>
                </div>

                <div id="incoming-list">
                    <?php if (empty($incoming)): ?>
                        <div class="empty-state">
                            <i class='bx bx-package'></i>
                            <h3>No incoming deliveries</h3>
                            <p>Books you borrow or purchase will appear here.</p>
                        </div>
                    <?php endif; ?>
                    <?php foreach ($incoming as $d): ?>
                        <?php renderEnhancedDeliveryCard($d, 'forward'); ?>
                    <?php endforeach; ?>
                </div>

                <div id="outgoing-list" style="display: none;">
                    <?php if (empty($outgoing)): ?>
                        <div class="empty-state">
                            <i class='bx bx-transfer-alt'></i>
                            <h3>No outgoing deliveries</h3>
                            <p>Books you lend or sell will appear here.</p>
                        </div>
                    <?php endif; ?>
                    <?php foreach ($outgoing as $d): ?>
                        <?php renderEnhancedDeliveryCard($d, 'forward'); ?>
                    <?php endforeach; ?>
                </div>

                <div id="returns-list" style="display: none;">
                    <?php if (empty($returns)): ?>
                        <div class="empty-state">
                            <i class='bx bx-undo'></i>
                            <h3>No items in return phase</h3>
                            <p>Books being returned to owners will appear here.</p>
                        </div>
                    <?php endif; ?>
                    <?php foreach ($returns as $d): ?>
                        <?php renderEnhancedDeliveryCard($d, 'return'); ?>
                    <?php endforeach; ?>
                </div>

                <div id="exchanges-list" style="display: none;">
                    <?php if (empty($exchanges)): ?>
                        <div class="empty-state">
                            <i class='bx bx-sync'></i>
                            <h3>No exchange deliveries</h3>
                            <p>Active book exchanges will appear here.</p>
                        </div>
                    <?php endif; ?>
                    <?php foreach ($exchanges as $d): ?>
                        <?php renderEnhancedDeliveryCard($d); ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Feedback Modal -->
    <div id="feedback-modal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h2 style="font-weight: 800; font-size: 1.25rem;">Rate Your Experience</h2>
                <p id="rating-target-name" style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.25rem;">with Delivery Agent</p>
            </div>
            <div class="modal-body">
                <div class="rating-stars" id="feedback-rating">
                    <i class='bx bx-star' data-value="1"></i>
                    <i class='bx bx-star' data-value="2"></i>
                    <i class='bx bx-star' data-value="3"></i>
                    <i class='bx bx-star' data-value="4"></i>
                    <i class='bx bx-star' data-value="5"></i>
                </div>
                <textarea id="feedback-comment" class="review-textarea" rows="4" placeholder="Share your experience (optional)..."></textarea>
            </div>
            <div class="modal-footer">
                <button onclick="closeFeedbackModal()" class="btn btn-outline">Cancel</button>
                <button onclick="submitFeedback()" class="btn btn-primary">Submit Review</button>
            </div>
        </div>
    </div>

    <!-- Restock Modal -->
    <div id="restock-modal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h2 style="font-weight: 800; font-size: 1.25rem;">Confirm Book Return Receipt</h2>
                <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.25rem;">Would you like to add this book back to your inventory?</p>
            </div>
            <div class="modal-body">
                <div style="background: #f8fafc; padding: 1.25rem; border-radius: 12px; margin-bottom: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer;">
                        <input type="checkbox" id="restock-checkbox" checked style="width: 18px; height: 18px; cursor: pointer;">
                        <span style="font-weight: 600; color: var(--text-main);">Yes, add this book back to my stock</span>
                    </label>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.5rem; margin-left: 2rem;">This will increase your available quantity by 1</p>
                </div>
            </div>
            <div class="modal-footer">
                <button onclick="closeRestockModal()" class="btn btn-outline">Cancel</button>
                <button onclick="confirmReceiptWithRestock()" class="btn btn-primary">Confirm Receipt</button>
            </div>
        </div>
    </div>

    <!-- Extend Date Modal -->
    <div id="extend-modal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h2 style="font-weight: 800; font-size: 1.25rem;">Request Extension</h2>
                <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.25rem;">Choose a new return date for this book.</p>
            </div>
            <div class="modal-body">
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; font-weight: 700; margin-bottom: 0.5rem; font-size: 0.9rem;">Current Due Date:</label>
                    <div id="current-due-display" style="background: #f8fafc; padding: 0.75rem; border-radius: 10px; color: #64748b;">-</div>
                </div>
                <div>
                    <label style="display: block; font-weight: 700; margin-bottom: 0.5rem; font-size: 0.9rem;">Select New Due Date:</label>
                    <input type="date" id="new-due-date" class="form-control" style="width: 100%; padding: 0.75rem; border-radius: 10px; border: 1px solid var(--border-color);">
                </div>
                <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 1rem;">
                    <i class='bx bx-info-circle'></i> Requests are sent to the book owner for approval.
                </p>
            </div>
            <div class="modal-footer">
                <button onclick="closeExtendModal()" class="btn btn-outline">Cancel</button>
                <button onclick="submitExtension()" class="btn btn-primary">Send Request</button>
            </div>
        </div>
    </div>


    <?php
    function renderEnhancedDeliveryCard($d, $forceLeg = null) {
        $userId = $_SESSION['user_id'];
        $isBorrower = ($d['borrower_id'] == $userId);
        
        // Progress Logic
        $isReturnPhase = ($forceLeg === 'return') || ($forceLeg === null && in_array($d['status'], ['returning', 'returned']));
        
        if (!$isReturnPhase) {
            $steps = ['requested', 'approved', 'active', 'delivered'];
            // If the actual status is returning/returned but we are forcing forward leg, show as Delivered
            $displayStatus = in_array($d['status'], ['returning', 'returned']) ? 'delivered' : $d['status'];
            $currentIndex = array_search($displayStatus, $steps);
            if ($currentIndex === false) $currentIndex = 0;
        } else {
            // Return journey: dot 0: Initiated, 1: Assigned, 2: In Transit, 3: Returned
            $currentIndex = 0;
            if (!empty($d['return_agent_id'])) $currentIndex = 1;
            if (!empty($d['return_picked_up_at'])) $currentIndex = 2;
            if ($d['status'] === 'returned') $currentIndex = 3;
        }
        
        // Custom labels for the badge
        $effectiveStatus = (!$isReturnPhase && in_array($d['status'], ['returning', 'returned'])) ? 'delivered' : $d['status'];
        $badgeLabel = getStatusLabel($effectiveStatus, $isReturnPhase ? $d['return_agent_id'] : $d['delivery_agent_id']);
        if ($effectiveStatus === 'active' && $d['agent_confirm_delivery_at']) {
            $badgeLabel = "Awaiting Your Confirmation";
        }
        if ($d['status'] === 'returning' && $d['return_agent_confirm_at']) {
            $badgeLabel = "Awaiting Return Confirmation";
        }
        ?>
        <div class="delivery-card">
            <?php if ($isBorrower): ?>
                <div class="credit-info-tag">
                    <i class='bx bxs-coin-stack' style="color: #f59e0b;"></i>
                    10 Credits Delivery Fee Paid
                </div>
            <?php elseif ($d['transaction_type'] === 'purchase'): ?>
                <div class="credit-info-tag">
                    <i class='bx bxs-coin-stack' style="color: #10b981;"></i>
                    Reward: <?php echo $d['credit_cost'] ?? 10; ?> Credits upon delivery
                </div>
            <?php endif; ?>

            <span class="status-badge <?php echo $effectiveStatus; ?>">
                <?php echo $badgeLabel; ?>
            </span>

            <div class="delivery-main">
                <div class="book-aside">
                    <img src="<?php echo htmlspecialchars($d['cover_image'] ?: '../assets/images/book-placeholder.jpg'); ?>" class="book-img">
                </div>
                
                <div class="delivery-body">
                    <span class="order-meta">#ORD-<?php echo $d['id']; ?> • <?php echo date('M d, Y', strtotime($d['created_at'])); ?></span>
                    <h2 class="book-title"><?php echo htmlspecialchars($d['title']); ?></h2>
                    
                    <div class="location-info">
                        <div class="loc-item">
                            <i class='bx <?php echo $isReturnPhase ? 'bx-undo' : 'bx-map-pin'; ?>'></i>
                            <div>
                                <strong><?php echo $isReturnPhase ? 'Returning to Owner (Destination):' : 'Destination:'; ?></strong><br>
                                <?php echo $isReturnPhase ? htmlspecialchars($d['pickup_location']) : htmlspecialchars($d['order_address']); ?>
                                <?php 
                                    $landmark = $isReturnPhase ? $d['pickup_landmark'] : $d['order_landmark'];
                                    if ($landmark): ?>
                                    <br><span class="landmark-tag">Near <?php echo htmlspecialchars($landmark); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="tracking-steps">
                        <?php if (!$isReturnPhase): ?>
                            <div class="step <?php echo $currentIndex >= 0 ? ($currentIndex > 0 ? 'completed' : 'active') : ''; ?>">
                                <div class="step-dot"></div>
                                <span class="step-label">Requested</span>
                            </div>
                            <div class="step <?php echo $currentIndex >= 1 ? ($currentIndex > 1 ? 'completed' : 'active') : ''; ?>">
                                <div class="step-dot"></div>
                                <span class="step-label">Assigned</span>
                            </div>
                            <div class="step <?php echo $currentIndex >= 2 ? ($currentIndex > 2 ? 'completed' : 'active') : ''; ?>">
                                <div class="step-dot"></div>
                                <span class="step-label">In Transit</span>
                            </div>
                            <div class="step <?php echo $currentIndex >= 3 ? 'completed' : ''; ?>">
                                <div class="step-dot"></div>
                                <span class="step-label">Delivered</span>
                            </div>
                        <?php else: ?>
                            <!-- Return Trip Stepper -->
                            <div class="step <?php echo $currentIndex >= 0 ? ($currentIndex > 0 ? 'completed' : 'active') : ''; ?>">
                                <div class="step-dot"></div>
                                <span class="step-label">Return Initiated</span>
                            </div>
                            <div class="step <?php echo $currentIndex >= 1 ? ($currentIndex > 1 ? 'completed' : 'active') : ''; ?>">
                                <div class="step-dot"></div>
                                <span class="step-label">Agent Assigned</span>
                            </div>
                            <div class="step <?php echo $currentIndex >= 2 ? ($currentIndex > 2 ? 'completed' : 'active') : ''; ?>">
                                <div class="step-dot"></div>
                                <span class="step-label">In Transit</span>
                            </div>
                            <div class="step <?php echo $currentIndex >= 3 ? 'completed' : ''; ?>">
                                <div class="step-dot"></div>
                                <span class="step-label">Returned</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($d['pickup_lat'] && $d['order_lat']): ?>
                <div id="map-<?php echo $forceLeg; ?>-<?php echo $d['id']; ?>" 
                     class="mini-map"
                     data-lat1="<?php echo $d['pickup_lat']; ?>"
                     data-lng1="<?php echo $d['pickup_lng']; ?>"
                     data-lat2="<?php echo $d['order_lat']; ?>"
                     data-lng2="<?php echo $d['order_lng']; ?>"
                     data-is-return="<?php echo $isReturnPhase ? '1' : '0'; ?>">
                </div>
            <?php endif; ?>

            <div class="confirmation-box">
                <div class="confirm-title">
                    <i class='bx bx-shield-quarter'></i> 3-PARTY VERIFICATION STATUS
                </div>
                
                <div class="confirm-badges">
                    <?php if (!$isReturnPhase): ?>
                        <div class="c-badge <?php echo !empty($d['lender_confirm_at']) ? 'verified' : ''; ?>">
                            <i class='bx <?php echo !empty($d['lender_confirm_at']) ? 'bxs-check-circle' : 'bx-circle'; ?>'></i>
                            <span><?php echo $isBorrower ? 'Sender (Lender)' : 'Sender (You)'; ?></span>
                        </div>
                        <div class="c-badge <?php echo !empty($d['agent_confirm_delivery_at']) ? 'verified' : ''; ?>">
                            <i class='bx <?php echo !empty($d['agent_confirm_delivery_at']) ? 'bxs-check-circle' : 'bx-circle'; ?>'></i>
                            <span>Agent</span>
                        </div>
                        <div class="c-badge <?php echo !empty($d['borrower_confirm_at']) ? 'verified' : ''; ?>">
                            <i class='bx <?php echo !empty($d['borrower_confirm_at']) ? 'bxs-check-circle' : 'bx-circle'; ?>'></i>
                            <span><?php echo $isBorrower ? 'Receiver (You)' : 'Receiver (Borrower)'; ?></span>
                        </div>
                    <?php else: ?>
                        <div class="c-badge <?php echo !empty($d['return_borrower_confirm_at']) ? 'verified' : ''; ?>">
                            <i class='bx <?php echo !empty($d['return_borrower_confirm_at']) ? 'bxs-check-circle' : 'bx-circle'; ?>'></i>
                            <span><?php echo $isBorrower ? 'Sender (You)' : 'Sender (Borrower)'; ?></span>
                        </div>
                        <div class="c-badge <?php echo !empty($d['return_agent_confirm_at']) ? 'verified' : ''; ?>">
                            <i class='bx <?php echo !empty($d['return_agent_confirm_at']) ? 'bxs-check-circle' : 'bx-circle'; ?>'></i>
                            <span>Agent</span>
                        </div>
                        <div class="c-badge <?php echo !empty($d['return_lender_confirm_at']) ? 'verified' : ''; ?>">
                            <i class='bx <?php echo !empty($d['return_lender_confirm_at']) ? 'bxs-check-circle' : 'bx-circle'; ?>'></i>
                            <span><?php echo $isBorrower ? 'Receiver (Owner)' : 'Receiver (You)'; ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="actions">
                    <?php if ($d['status'] === 'requested' && $d['lender_id'] == $userId): ?>
                        <div style="display: flex; gap: 1rem;">
                            <button onclick="confirmAction(<?php echo $d['id']; ?>, 'accept_request')" class="btn-confirm primary">
                                <i class='bx bx-check'></i> Accept Request
                            </button>
                            <button onclick="confirmAction(<?php echo $d['id']; ?>, 'decline_request')" class="btn-confirm" style="border: 1px solid #e2e8f0;">
                                Decline
                            </button>
                        </div>

                    <?php elseif ($isBorrower && in_array($d['status'], ['approved', 'assigned', 'active', 'delivered']) && !empty($d['delivery_agent_id']) && empty($d['borrower_confirm_at'])): ?>
                        <button onclick="confirmAction(<?php echo $d['id']; ?>, 'confirm_receipt')" class="btn-confirm primary">
                            <i class='bx bx-check-shield'></i> I Received My Book
                        </button>
                    
                    <?php elseif ($isBorrower && $d['status'] === 'delivered' && ($d['transaction_type'] === 'borrow' || $d['transaction_type'] === 'exchange')): ?>
                        <div style="display: flex; flex-direction: column; gap: 0.75rem; width: 100%;">
                            <?php $btnLabel = ($d['transaction_type'] === 'exchange') ? 'Send Exchange Book via Agent' : 'Return Book via Agent'; ?>
                            <button onclick="confirmAction(<?php echo $d['id']; ?>, 'request_return_delivery')" class="btn-confirm primary" style="background: #e11d48; box-shadow: 0 4px 12px rgba(225, 29, 72, 0.2);">
                                <i class='bx bx-undo'></i> <?php echo $btnLabel; ?> (10 Credits)
                            </button>
                            
                            <?php if ($d['transaction_type'] === 'borrow'): ?>
                                <?php if (empty($d['pending_due_date'])): ?>
                                    <button onclick="openExtendModal(<?php echo $d['id']; ?>, '<?php echo $d['due_date']; ?>')" class="btn-confirm" style="background: white; color: var(--text-main); border: 1px solid var(--border-color);">
                                        <i class='bx bx-calendar-plus'></i> Extend Return Date
                                    </button>
                                <?php else: ?>
                                    <div style="background: #f1f5f9; padding: 0.75rem; border-radius: 12px; text-align: center; color: #475569; font-size: 0.85rem; font-weight: 700;">
                                        <i class='bx bx-time'></i> Extension Pending: <?php echo date('M d, Y', strtotime($d['pending_due_date'])); ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>


                    <?php elseif ($isBorrower && in_array($d['status'], ['delivered', 'returning', 'returned']) && !empty($d['return_agent_id']) && empty($d['return_borrower_confirm_at'])): ?>
                        <button onclick="confirmAction(<?php echo $d['id']; ?>, 'confirm_handover')" class="btn-confirm primary">
                            <i class='bx bx-hand'></i> Handover to Return Agent
                        </button>

                    <?php elseif (!$isBorrower && in_array($d['status'], ['approved', 'assigned', 'active', 'delivered']) && !empty($d['delivery_agent_id']) && empty($d['lender_confirm_at'])): ?>
                        <button onclick="confirmAction(<?php echo $d['id']; ?>, 'confirm_handover')" class="btn-confirm primary">
                            <i class='bx bx-hand'></i> Confirm Handover to Agent
                        </button>

                    <?php elseif (!$isBorrower && in_array($d['status'], ['returning', 'returned']) && !empty($d['return_agent_id']) && empty($d['return_lender_confirm_at']) && empty($d['is_restocked'])): ?>
                        <button onclick="showRestockModal(<?php echo $d['id']; ?>)" class="btn-confirm primary">
                            <i class='bx bx-check-shield'></i> I Received My Returned Book
                        </button>

                    <?php elseif ($d['status'] === 'returned' && !empty($d['return_lender_confirm_at'])): ?>
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 1.5rem;">
                            <div style="flex: 1; text-align: center; color: #10b981; font-weight: 700; font-size: 0.9rem;">
                                <i class='bx bxs-badge-check'></i> <?php echo ($d['transaction_type'] === 'exchange') ? 'EXCHANGE COMPLETED' : 'BOOK RETURNED TO OWNER'; ?>
                            </div>

                    <?php elseif ($d['status'] === 'delivered' && !empty($d['borrower_confirm_at'])): ?>
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 1.5rem;">
                            <div style="flex: 1; text-align: center; color: #10b981; font-weight: 700; font-size: 0.9rem;">
                                <i class='bx bxs-badge-check'></i> TRANSACTION COMPLETED SUCCESSFULLY
                            </div>
                    <?php endif; ?>
                    
                    <?php 
                    $currentAgentId = $isReturnPhase ? $d['return_agent_id'] : $d['delivery_agent_id'];
                    if ($currentAgentId): ?>
                        <div style="background: #f8fafc; padding: 1.25rem; border-radius: 14px; border: 1px solid #e2e8f0; margin-top: <?php echo (($d['status'] === 'returned' && !empty($d['return_lender_confirm_at'])) || ($d['status'] === 'delivered' && !empty($d['borrower_confirm_at']))) ? '0' : '1.5rem'; ?>;">
                            <div style="display: flex; align-items: flex-start; justify-content: space-between; font-size: 0.85rem;">
                                <div style="display: flex; align-items: center; gap: 0.75rem; flex: 1;">
                                    <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 800; font-size: 1.1rem;">
                                        <?php echo strtoupper(substr($isReturnPhase ? ($d['ret_agent_name'] ?? 'A') : ($d['agent_name'] ?? 'A'), 0, 1)); ?>
                                    </div>
                                    <div>
                                        <strong style="display: block; font-size: 0.95rem;"><?php echo htmlspecialchars($isReturnPhase ? ($d['ret_agent_name'] ?? 'Agent') : ($d['agent_name'] ?? 'Agent')); ?></strong>
                                        <span style="color: #64748b; font-size: 0.8rem;"><?php echo $isReturnPhase ? 'Return Agent' : 'Delivery Agent'; ?></span>
                                    </div>
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 0.5rem; align-items: flex-end;">
                                    <?php $phone = $isReturnPhase ? ($d['ret_agent_phone'] ?? '') : ($d['agent_phone'] ?? ''); ?>
                                    <?php if ($phone): ?>
                                        <a href="tel:<?php echo $phone; ?>" style="color: var(--primary); font-weight: 700; text-decoration: none; display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem;">
                                            <i class='bx bx-phone'></i> <?php echo htmlspecialchars($phone); ?>
                                        </a>
                                    <?php endif; ?>
                                    <a href="user_profile.php?id=<?php echo $currentAgentId; ?>" class="agent-profile-btn">
                                        <i class='bx bx-user-circle'></i> View Profile & Ratings
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php if (($d['status'] === 'returned' && !empty($d['return_lender_confirm_at'])) || ($d['status'] === 'delivered' && !empty($d['borrower_confirm_at']))): ?>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php 
                    // Feedback Button Logic
                    $showRateBtn = false;
                    $revieweeId = 0;
                    $revieweeName = '';
                    
                    if ($d['status'] === 'delivered' && !empty($d['borrower_confirm_at'])) {
                        $showRateBtn = true;
                        $revieweeId = $d['delivery_agent_id'];
                        $revieweeName = 'Delivery Agent';
                    } elseif ($d['status'] === 'returned' && !empty($d['return_lender_confirm_at'])) {
                        $showRateBtn = true;
                        $revieweeId = $d['return_agent_id'];
                        $revieweeName = 'Return Agent';
                    }

                    if ($showRateBtn && $revieweeId): ?>
                        <button onclick="openFeedbackModal(<?php echo $d['id']; ?>, <?php echo $revieweeId; ?>, '<?php echo $revieweeName; ?>')" class="btn-confirm" style="background: #f8fafc; color: var(--text-main); border: 1px solid var(--border-color);">
                            <i class='bx bx-star'></i> Rate Agent
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    ?>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>

        function initMaps() {
            document.querySelectorAll('.mini-map').forEach(container => {
                const lat1 = parseFloat(container.dataset.lat1);
                const lng1 = parseFloat(container.dataset.lng1);
                const lat2 = parseFloat(container.dataset.lat2);
                const lng2 = parseFloat(container.dataset.lng2);
                const isReturn = container.dataset.isReturn === '1';

                const startPoint = isReturn ? [lat2, lng2] : [lat1, lng1];
                const endPoint = isReturn ? [lat1, lng1] : [lat2, lng2];

                const map = L.map(container, {
                    zoomControl: false,
                    attributionControl: false
                }).setView(startPoint, 13);

                L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png').addTo(map);

                const iconBase = 'display:flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:50%; border:3px solid white; box-shadow:0 2px 10px rgba(0,0,0,0.2); font-size:16px;';
                
                // Origin (Package Location)
                L.marker(startPoint, {
                    icon: L.divIcon({
                        className: 'custom-div-icon',
                        html: `<div style="${iconBase} background:#3b82f6; color:white;"><i class='bx bx-package'></i></div>`,
                        iconSize: [28, 28],
                        iconAnchor: [14, 14]
                    })
                }).addTo(map);

                // Home/Destination
                L.marker(endPoint, {
                    icon: L.divIcon({
                        className: 'custom-div-icon',
                        html: `<div style="${iconBase} background:#10b981; color:white;"><i class='bx bx-home'></i></div>`,
                        iconSize: [28, 28],
                        iconAnchor: [14, 14]
                    })
                }).addTo(map);

                const line = L.polyline([startPoint, endPoint], {
                    color: isReturn ? '#e11d48' : '#6366f1',
                    weight: 3,
                    opacity: 0.6,
                    dashArray: '8, 12'
                }).addTo(map);

                map.fitBounds(line.getBounds().pad(0.4));
            });
        }

        document.addEventListener('DOMContentLoaded', initMaps);

        function switchTab(tab, el) {
            document.querySelectorAll('.tab-btn').forEach(t => t.classList.remove('active'));
            el.classList.add('active');
            
            document.getElementById('incoming-list').style.display = tab === 'incoming' ? 'block' : 'none';
            document.getElementById('outgoing-list').style.display = tab === 'outgoing' ? 'block' : 'none';
            document.getElementById('returns-list').style.display = tab === 'returns' ? 'block' : 'none';
            document.getElementById('exchanges-list').style.display = tab === 'exchanges' ? 'block' : 'none';
            
            setTimeout(() => {
                window.dispatchEvent(new Event('resize')); 
            }, 100);
        }

        function showMsg(text, type) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `status-msg ${type}`;
            messageDiv.innerHTML = `<i class='bx ${type === 'success' ? 'bx-check-circle' : 'bx-error-circle'}'></i> ${text}`;
            document.body.appendChild(messageDiv);
            setTimeout(() => messageDiv.remove(), 3000);
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
                alert('Please select a rating');
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
                    showMsg(result.message, 'success');
                    closeFeedbackModal();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showMsg(result.message, 'error');
                }
            } catch (err) {
                showMsg('Network error. Please try again.', 'error');
            }
        }

        /* Restock Modal Functions */
        let currentRestockTxId = 0;

        function showRestockModal(txId) {
            currentRestockTxId = txId;
            document.getElementById('restock-modal').style.display = 'flex';
        }

        function closeRestockModal() {
            document.getElementById('restock-modal').style.display = 'none';
            currentRestockTxId = 0;
        }

        function confirmReceiptWithRestock() {
            const restock = document.getElementById('restock-checkbox').checked ? 1 : 0;
            
            const formData = new FormData();
            formData.append('action', 'confirm_receipt');
            formData.append('transaction_id', currentRestockTxId);
            formData.append('restock', restock);

            fetch('../actions/request_action.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        closeRestockModal();
                        showMsg(data.message, 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showMsg(data.message, 'error');
                    }
                })
                .catch(err => {
                    showMsg('Network error. Please try again.', 'error');
                });
        }

        /* Extension Functions */
        let currentExtendTxId = 0;

        function openExtendModal(txId, currentDate) {
            currentExtendTxId = txId;
            document.getElementById('current-due-display').textContent = new Date(currentDate).toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' });
            
            // Set min date to tomorrow
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            document.getElementById('new-due-date').min = tomorrow.toISOString().split('T')[0];
            
            document.getElementById('extend-modal').style.display = 'flex';
        }

        function closeExtendModal() {
            document.getElementById('extend-modal').style.display = 'none';
            currentExtendTxId = 0;
        }

        async function submitExtension() {
            const newDate = document.getElementById('new-due-date').value;
            if (!newDate) {
                alert('Please select a new date');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'request_extension');
            formData.append('transaction_id', currentExtendTxId);
            formData.append('new_date', newDate);

            try {
                const response = await fetch('../actions/request_action.php', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) {
                    showMsg(result.message, 'success');
                    closeExtendModal();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showMsg(result.message, 'error');
                }
            } catch (err) {
                showMsg('Network error. Please try again.', 'error');
            }
        }


        function confirmAction(txId, action) {
            const msgs = {
                'confirm_receipt': 'Confirming receipt will verify the delivery and build trust. Proceed?',
                'confirm_handover': 'Confirm you have handed over the book to the agent?',
                'accept_request': 'Accept this delivery request?',
                'request_return_delivery': 'Request a return delivery for this book? This will cost 10 credits.'
            };
            
            if (!confirm(msgs[action] || 'Confirm this action?')) return;
            
            const formData = new FormData();
            formData.append('action', action);
            formData.append('transaction_id', txId);

            fetch('../actions/request_action.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
        }
    </script>
</body>
</html>
