<?php
require_once '../includes/db_helper.php';
require_once '../paths.php';
include '../includes/dashboard_header.php';

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
    header("Location: login.php");
    exit();
}

$id = $_GET['id'] ?? 0;
if (!$id) {
    echo "<div class='main-content'><div class='container'><h1>Invalid ID</h1><p>No transaction ID provided.</p></div></div>";
    exit;
}

// Fetch transaction details
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT t.*, 
               b.title, b.cover_image, b.author,
               l.credit_cost as listing_credits,
               u_lender.firstname as lender_fname, u_lender.lastname as lender_lname, u_lender.phone as lender_phone,
               u_borrower.firstname as borrower_fname, u_borrower.lastname as borrower_lname, u_borrower.phone as borrower_phone,
               u_agent.firstname as agent_fname, u_agent.lastname as agent_lname, u_agent.phone as agent_phone, u_agent.average_rating as agent_rating,
               u_ret_agent.firstname as ret_agent_fname, u_ret_agent.lastname as ret_agent_lname, u_ret_agent.phone as ret_agent_phone, u_ret_agent.average_rating as ret_agent_rating,
               lender_loc.service_start_lat as pickup_lat, lender_loc.service_start_lng as pickup_lng
        FROM transactions t
        JOIN listings l ON t.listing_id = l.id
        JOIN books b ON l.book_id = b.id
        JOIN users u_lender ON t.lender_id = u_lender.id
        JOIN users u_borrower ON t.borrower_id = u_borrower.id
        LEFT JOIN users u_agent ON t.delivery_agent_id = u_agent.id
        LEFT JOIN users u_ret_agent ON t.return_agent_id = u_ret_agent.id
        LEFT JOIN users lender_loc ON t.lender_id = lender_loc.id
        WHERE t.id = ? AND (t.lender_id = ? OR t.borrower_id = ?)
    ");
    $stmt->execute([$id, $userId, $userId]);
    $d = $stmt->fetch();

    if (!$d) {
        echo "<div class='main-content'><div class='container'><h1>Transaction Not Found</h1><p>You don't have permission to view this transaction or it doesn't exist.</p></div></div>";
        exit;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    exit;
}

// Mark this notification as read if it exists
markSpecificNotificationAsRead($userId, $id, ['borrow_request', 'sell_request', 'delivery_assigned', 'delivery_update']);

$isLender = ($d['lender_id'] == $userId);
$isBorrower = ($d['borrower_id'] == $userId);
$isReturnPhase = in_array($d['status'], ['returning', 'returned']);

function getStatusLabel($status, $agentId, $method) {
    if ($status === 'approved' && $method === 'pickup') return 'Ready for Collection';
    if ($status === 'approved' && $method === 'delivery') return $agentId ? 'Agent Accepted' : 'Finding Agent';
    
    switch ($status) {
        case 'requested': return 'Waiting for Owner';
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
    <title>Transaction Details | BOOK-B</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=1.2">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        .details-container { max-width: 900px; margin: 0 auto; padding: 2rem; }
        .glass-card { background: white; border-radius: 24px; padding: 2.5rem; border: 1px solid var(--border-color); box-shadow: var(--shadow-lg); }
        .status-badge { padding: 0.6rem 1.2rem; border-radius: 12px; font-weight: 800; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; }
        .status-badge.requested { background: #eff6ff; color: #3b82f6; }
        .status-badge.approved { background: #f0fdf4; color: #16a34a; }
        .status-badge.active { background: #fff7ed; color: #ea580c; }
        .status-badge.delivered { background: #f0fdf4; color: #16a34a; }
        
        .book-details-grid { display: grid; grid-template-columns: 180px 1fr; gap: 2.5rem; margin-top: 2rem; }
        .book-cover { width: 100%; border-radius: 16px; box-shadow: var(--shadow-md); }
        .info-section { margin-top: 3rem; }
        .section-title { font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem; }
        
        .tracking-steps { display: flex; justify-content: space-between; position: relative; margin: 4rem 0 2rem 0; }
        .tracking-steps::before { content: ''; position: absolute; top: 13px; left: 0; width: 100%; height: 4px; background: #f1f5f9; z-index: 1; }
        .step { position: relative; z-index: 2; display: flex; flex-direction: column; align-items: center; gap: 0.75rem; flex: 1; }
        .step-dot { width: 26px; height: 26px; background: white; border: 4px solid #f1f5f9; border-radius: 50%; }
        .step-label { font-size: 0.7rem; font-weight: 900; color: #cbd5e1; text-transform: uppercase; }
        .step.completed .step-dot { background: var(--primary); border-color: var(--primary); }
        .step.completed .step-label { color: var(--primary); }
        .step.active .step-dot { border-color: var(--primary); border-width: 6px; }
        .step.active .step-label { color: var(--text-main); }

        #map { height: 300px; border-radius: 20px; border: 1px solid var(--border-color); margin-top: 1.5rem; }
        .verification-card { background: #f8fafc; border-radius: 16px; padding: 1.5rem; border: 1px solid #e2e8f0; margin-top: 2rem; }
        .v-badge { display: flex; align-items: center; gap: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 0.5rem; }
        .v-badge.verified { color: #10b981; }
        .actions-bar { display: flex; gap: 1rem; margin-top: 2rem; }
        /* Extension Modal */
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
            background: white; 
            border-radius: var(--radius-lg); 
            width: 90%;
            max-width: 450px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25); 
            overflow: hidden;
            animation: modalFadeIn 0.3s ease-out;
        }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-20px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .modal-header { padding: 1.5rem; border-bottom: 1px solid var(--border-color); }
        .modal-body { padding: 1.5rem; }
        .modal-footer { padding: 1.25rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 1rem; }
        
        .form-group { margin-bottom: 1.5rem; }
        .form-label { display: block; font-weight: 700; margin-bottom: 0.5rem; color: var(--text-main); font-size: 0.9rem; }
        .form-control {
            width: 100%; border: 1px solid var(--border-color); border-radius: var(--radius-md);
            padding: 0.75rem; font-family: inherit; outline: none; transition: border-color 0.3s;
        }
        .form-control:focus { border-color: var(--primary); }

        /* Premium Extension Modal Styles */
        .ext-header { text-align: center; padding-bottom: 0.5rem; }
        .ext-icon-wrapper {
            width: 64px; height: 64px; background: rgba(79, 70, 229, 0.1);
            color: var(--primary); border-radius: 20px; display: flex;
            align-items: center; justify-content: center; font-size: 2rem;
            margin: 0 auto 1.25rem;
        }
        .ext-info-box {
            background: #fffbeb; border: 1px solid #fef3c7;
            padding: 1rem; border-radius: 12px; display: flex;
            align-items: flex-start; gap: 0.75rem; margin-top: 1.25rem;
        }
        .ext-info-icon { color: #d97706; font-size: 1.25rem; }
        .ext-info-content { flex: 1; }
        .ext-info-title { font-weight: 700; color: #92400e; font-size: 0.9rem; margin-bottom: 0.2rem; }
        .ext-info-text { color: #a16207; font-size: 0.8rem; line-height: 1.4; }
        .ext-modal-footer {
            padding: 1.5rem; background: #f8fafc;
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
            background: white; color: var(--text-main);
            border: 1px solid var(--border-color); padding: 0.875rem;
            border-radius: 12px; font-weight: 600;
            display: flex; align-items: center; justify-content: center;
            gap: 0.5rem; transition: all 0.3s; cursor: pointer;
        }
        .ext-btn-outline:hover { background: #f1f5f9; }

        .price-tag {
            background: #fffbeb; color: #92400e; border: 1px solid #fef3c7;
            padding: 0.4rem 0.75rem; border-radius: 8px; font-size: 0.75rem;
            font-weight: 700; display: inline-flex; align-items: center; gap: 0.4rem;
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include '../includes/dashboard_sidebar.php'; ?>
        
        <main class="main-content">
            <div class="details-container">
                <div class="glass-card">
                    <?php if ($d['status'] === 'cancelled'): ?>
                    <div style="background: #fee2e2; border: 1px solid #fecaca; color: #dc2626; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; font-weight: 700;">
                        <i class='bx bx-error-circle' style="font-size: 1.5rem;"></i>
                        This transaction has been cancelled. No further actions can be performed.
                    </div>
                    <?php endif; ?>
                    <div style="margin-bottom: 1.5rem;">
                        <?php if ($d['transaction_type'] === 'purchase'): 
                            $itemTotal = $d['book_price'] * $d['quantity'];
                            $deliveryFee = ($d['delivery_method'] === 'delivery') ? 50 : 0;
                            $discount = (int)($d['credit_discount'] ?? 0);
                            $finalTotal = $itemTotal + $deliveryFee - $discount;
                        ?>
                            <div style="display: flex; align-items: flex-start; flex-direction: column; gap: 0.25rem;">
                                <div class="price-tag"><i class='bx bx-check-shield'></i> Total ₹<?= number_format($finalTotal, 2) ?></div>
                                <div style="font-size: 0.7rem; color: var(--text-muted); padding-left: 0.25rem;">
                                    (Book: ₹<?= number_format($itemTotal, 2) ?> 
                                    <?php if($deliveryFee > 0): ?>+ Delivery: ₹<?= $deliveryFee ?><?php endif; ?>
                                    <?php if($discount > 0): ?>- Discount: ₹<?= $discount ?><?php endif; ?>)
                                </div>
                            </div>
                            <div class="price-tag" style="background:#f1f5f9; color:#475569; border-color:#e2e8f0; margin-left: 0.5rem;">
                                <i class='bx bx-layer'></i> Qty: <?= $d['quantity'] ?>
                            </div>
                        <?php else: 
                            $tokenBase = (int)($d['credit_cost'] ?? 10);
                            $deliveryFeeForward = ($d['delivery_method'] === 'delivery') ? 10 : 0;
                            $returnFeeCredits = ($d['return_delivery_method'] === 'delivery') ? (int)($d['return_delivery_credits'] ?? 10) : 0;
                            $finalTokens = $tokenBase + $deliveryFeeForward + $returnFeeCredits;
                            $returnFeeINR = ($d['return_delivery_method'] === 'delivery') ? (int)($d['return_delivery_price'] ?? 50) : 0;
                        ?>
                            <div style="display: flex; align-items: flex-start; flex-direction: column; gap: 0.25rem;">
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <div class="price-tag" style="background:#eff6ff; color:#1e40af; border-color:#dbeafe;">
                                        <i class='bx bxs-coin-stack'></i> Total Credits: <?= $finalTokens ?>
                                    </div>
                                    <?php if ($returnFeeINR > 0 && $isBorrower): ?>
                                        <div class="price-tag" style="background:#fff7ed; color:#c2410c; border-color:#ffedd5;">
                                            <i class='bx bx-money'></i> Return Fee: ₹<?= $returnFeeINR ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size: 0.7rem; color: var(--text-muted); padding-left: 0.25rem;">
                                    (Base: <?= $tokenBase ?> 
                                    <?php if($deliveryFeeForward > 0): ?>+ Delivery: <?= $deliveryFeeForward ?><?php endif; ?>
                                    <?php if($returnFeeCredits > 0 && $isBorrower): ?>+ Return Deliv: <?= $returnFeeCredits ?><?php endif; ?>)
                                </div>
                            </div>
                            <?php if ($d['quantity'] > 1): ?>
                            <div class="price-tag" style="background:#f1f5f9; color:#475569; border-color:#e2e8f0; margin-left: 0.5rem;">
                                <i class='bx bx-layer'></i> Qty: <?= $d['quantity'] ?>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <div>
                            <span class="status-badge <?php echo $d['status']; ?>">
                                <?php echo getStatusLabel($d['status'], $d['delivery_agent_id'], $d['delivery_method']); ?>
                            </span>
                            <h1 style="font-size: 1.8rem; font-weight: 900; margin-top: 1rem;">Transaction Details #<?php echo $d['id']; ?></h1>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size: 0.85rem; color: var(--text-muted);"><?php echo date('M d, Y', strtotime($d['created_at'])); ?></div>
                        </div>
                    </div>

                    <div class="book-details-grid">
                        <?php 
                            $cover = $d['cover_image'];
                            $fallback = 'https://images.unsplash.com/photo-1543004218-ee141104975a?w=400';
                            $cover = $cover ?: $fallback;
                        ?>
                        <img src="<?php echo htmlspecialchars(html_entity_decode($cover), ENT_QUOTES, 'UTF-8'); ?>" class="book-cover" onerror="this.onerror=null; this.src='<?php echo $fallback; ?>';">
                        <div>
                            <h2 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($d['title']); ?></h2>
                            <p style="color: var(--text-muted); font-size: 1rem;">by <?php echo htmlspecialchars($d['author']); ?></p>
                            
                            <div style="margin-top: 2rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 2rem;">
                                <div>
                                    <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #94a3b8; margin-bottom: 0.5rem;">Lender (Owner)</div>
                                    <div style="font-weight: 700; color: var(--text-main);"><?php echo $d['lender_fname'] . ' ' . $d['lender_lname']; ?></div>
                                    <div style="color: var(--text-muted); font-size: 0.9rem;"><?php echo $d['lender_phone']; ?></div>
                                </div>
                                <div>
                                    <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #94a3b8; margin-bottom: 0.5rem;">Borrower (Receiver)</div>
                                    <div style="font-weight: 700; color: var(--text-main);"><?php echo $d['borrower_fname'] . ' ' . $d['borrower_lname']; ?></div>
                                    <div style="color: var(--text-muted); font-size: 0.9rem;"><?php echo $d['borrower_phone']; ?></div>
                                </div>
                                <?php 
                                    $currentAgentId = $isReturnPhase ? $d['return_agent_id'] : $d['delivery_agent_id'];
                                    if ($currentAgentId): 
                                        $aName = $isReturnPhase ? $d['ret_agent_fname'] . ' ' . $d['ret_agent_lname'] : $d['agent_fname'] . ' ' . $d['agent_lname'];
                                        $aPhone = $isReturnPhase ? $d['ret_agent_phone'] : $d['agent_phone'];
                                        $aRating = $isReturnPhase ? $d['ret_agent_rating'] : $d['agent_rating'];
                                ?>
                                <div>
                                    <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #94a3b8; margin-bottom: 0.5rem;">Delivery Partner</div>
                                    <div style="font-weight: 700; color: var(--text-main);"><?php echo $aName; ?></div>
                                    <div style="color: var(--text-muted); font-size: 0.9rem; display: flex; align-items: center; gap: 0.4rem;">
                                        <i class='bx bx-phone' style="color: var(--primary);"></i> <?php echo $aPhone; ?>
                                    </div>
                                    <div style="display: flex; gap: 0.5rem; justify-content: flex-end; align-items: center; margin-top: 0.4rem;">
                                        <a href="user_profile.php?id=<?php echo $currentAgentId; ?>" style="color: var(--primary); font-size: 0.75rem; font-weight: 700; text-decoration: none; padding: 0.3rem 0.6rem; background: var(--primary-soft); border-radius: 8px;">Show Rating</a>
                                        <?php 
                                            $hasHandedOver = $isReturnPhase ? !empty($d['return_borrower_confirm_at']) : !empty($d['lender_confirm_at']);
                                            if ($hasHandedOver): 
                                        ?>
                                        <button onclick="openAgentReportModal(<?php echo $currentAgentId; ?>, '<?php echo addslashes($aName); ?>')" style="color: #ef4444; font-size: 0.75rem; font-weight: 700; border: none; background: #fee2e2; padding: 0.3rem 0.6rem; border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 0.3rem;">
                                            <i class='bx bx-flag'></i> Report
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($d['delivery_method'] === 'delivery'): ?>
                    <div class="info-section">
                        <h3 class="section-title"><i class='bx bx-rocket'></i> Delivery Progress</h3>
                        <?php 
                        $steps = $isReturnPhase ? ['delivered', 'returning', 'returned'] : ['requested', 'approved', 'active', 'delivered'];
                        $currentIndex = array_search($d['status'], $steps);
                        if($currentIndex === false) $currentIndex = 0;
                        ?>
                        <div class="tracking-steps">
                            <?php foreach($steps as $i => $step): ?>
                                <div class="step <?php echo $i < $currentIndex ? 'completed' : ($i == $currentIndex ? 'active' : ''); ?>">
                                    <div class="step-dot"></div>
                                    <span class="step-label"><?php echo ucfirst($step); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="verification-card">
                            <div style="font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: #94a3b8; margin-bottom: 1rem;">3-Party Verification</div>
                            <?php if (!$isReturnPhase): ?>
                                <div class="v-badge <?php echo !empty($d['lender_confirm_at']) ? 'verified' : ''; ?>">
                                    <i class='bx <?php echo !empty($d['lender_confirm_at']) ? 'bxs-check-circle' : 'bx-circle'; ?>'></i> Sender Confirmation
                                </div>
                                <div class="v-badge <?php echo !empty($d['agent_confirm_delivery_at']) ? 'verified' : ''; ?>">
                                    <i class='bx <?php echo !empty($d['agent_confirm_delivery_at']) ? 'bxs-check-circle' : 'bx-circle'; ?>'></i> Agent Confirmation
                                </div>
                                <div class="v-badge <?php echo !empty($d['borrower_confirm_at']) ? 'verified' : ''; ?>">
                                    <i class='bx <?php echo !empty($d['borrower_confirm_at']) ? 'bxs-check-circle' : 'bx-circle'; ?>'></i> Receiver Confirmation
                                </div>
                            <?php else: ?>
                                <div class="v-badge <?php echo !empty($d['return_borrower_confirm_at']) ? 'verified' : ''; ?>">
                                    <i class='bx <?php echo !empty($d['return_borrower_confirm_at']) ? 'bxs-check-circle' : 'bx-circle'; ?>'></i> Sender (Return) Confirmation
                                </div>
                                <div class="v-badge <?php echo !empty($d['return_agent_confirm_at']) ? 'verified' : ''; ?>">
                                    <i class='bx <?php echo !empty($d['return_agent_confirm_at']) ? 'bxs-check-circle' : 'bx-circle'; ?>'></i> Agent Confirmation
                                </div>
                                <div class="v-badge <?php echo !empty($d['return_lender_confirm_at']) ? 'verified' : ''; ?>">
                                    <i class='bx <?php echo !empty($d['return_lender_confirm_at']) ? 'bxs-check-circle' : 'bx-circle'; ?>'></i> Receiver (Return) Confirmation
                                </div>
                            <?php endif; ?>
                        </div>

                        <div id="map"></div>
                    </div>
                    <?php else: ?>
                    <div class="info-section">
                        <h3 class="section-title"><i class='bx bx-shopping-bag'></i> Collection Information</h3>
                        <div class="verification-card" style="background: #fffbeb; border-color: #fef3c7;">
                            <div style="font-weight: 800; color: #92400e; margin-bottom: 0.5rem;">SELF-COLLECTION ORDER</div>
                            <p style="font-size: 0.9rem; color: #b45309; line-height: 1.5; margin: 0;">
                                You have chosen to pick up this item directly from the owner. 
                                Please use the chat button below to coordinate the pickup time and exact location.
                            </p>
                        </div>
                        
                        <?php if ($d['payment_method'] === 'cod'): ?>
                        <div class="verification-card" style="margin-top: 1rem; border-left: 4px solid #10b981;">
                            <div style="font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase;">Cash Payment at Collection</div>
                            <div style="font-size: 1.2rem; font-weight: 900; color: var(--text-main); margin-top: 0.5rem;">CASH TO PAY: ₹<?php echo number_format($d['book_price'], 2); ?></div>
                        </div>
                        <?php elseif ($d['payment_method'] === 'online'): ?>
                        <div class="verification-card" style="margin-top: 1rem; border-left: 4px solid #3b82f6;">
                            <div style="font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase;">Payment Status</div>
                            <div style="font-size: 1.2rem; font-weight: 900; color: #0284c7; margin-top: 0.5rem;">PAID ONLINE</div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div class="actions-bar">
                        <?php if ($d['status'] !== 'cancelled'): ?>
                            <?php if ($d['status'] === 'requested' && $isLender): ?>
                                <button onclick="handleAction('accept_request')" class="btn btn-primary">Accept Request</button>
                                <button onclick="handleAction('decline_request')" class="btn btn-outline" style="color: #ef4444; border-color: #ef4444;">Decline</button>
                            <?php endif; ?>

                            <?php if (!$isReturnPhase): ?>
                                <?php if (($d['status'] === 'delivered' || ($d['delivery_method'] === 'pickup' && in_array($d['status'], ['approved', 'active']))) && $isBorrower && empty($d['borrower_confirm_at'])): ?>
                                    <button onclick="handleAction('confirm_receive')" class="btn btn-primary">Confirm Receive</button>
                                <?php elseif ($d['status'] === 'delivered' && $isBorrower && !empty($d['borrower_confirm_at']) && $d['transaction_type'] === 'borrow'): ?>
                                    <button onclick="handleAction('request_return_delivery')" class="btn btn-outline" style="color: var(--secondary); border-color: var(--secondary);">
                                        <i class='bx bx-undo'></i> Return Book
                                    </button>
                                    <button onclick="openExtendModal()" class="btn btn-outline">
                                        <i class='bx bx-calendar-plus'></i> Extend Date
                                    </button>
                                <?php endif; ?>
                                
                                <?php if (in_array($d['status'], ['approved', 'assigned', 'active']) && $isLender && empty($d['lender_confirm_at'])): ?>
                                    <button onclick="handleAction('confirm_handover')" class="btn btn-primary">Confirm Handover<?= $d['delivery_method'] === 'pickup' ? '' : ' to Agent' ?></button>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if ($d['status'] === 'returning' && $isBorrower && empty($d['return_borrower_confirm_at'])): ?>
                                    <button onclick="handleAction('confirm_handover')" class="btn btn-primary">Confirm Handover (Return)</button>
                                <?php endif; ?>
                                <?php if ($d['status'] === 'returned' && $isLender && empty($d['return_lender_confirm_at'])): ?>
                                    <button onclick="handleAction('confirm_receive')" class="btn btn-primary">Confirm Receive (Return)</button>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                        <a href="<?php echo APP_URL; ?>/chat/index.php?user=<?php echo $isLender ? $d['borrower_id'] : $d['lender_id']; ?>" class="btn btn-outline">
                            <i class='bx bx-message-square-dots'></i> Chat with <?php echo $isLender ? 'Borrower' : 'Owner'; ?>
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Extension Modal -->
    <div id="extension-modal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header ext-header" style="border-bottom: none; padding-top: 2rem;">
                <div class="ext-icon-wrapper">
                    <i class='bx bx-calendar-plus'></i>
                </div>
                <h2 style="font-weight: 900; font-size: 1.5rem; color: var(--text-main);">Extend Return Date</h2>
                <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 0.5rem;">Need more time? Request an extension from the owner.</p>
            </div>
            <div class="modal-body" style="padding: 1rem 2rem 2rem;">
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
                        <p class="ext-info-text">Credits will be deducted once the owner approves your request.</p>
                    </div>
                </div>
            </div>
            <div class="ext-modal-footer">
                <button onclick="submitExtension()" class="ext-btn-primary">
                    <i class='bx bx-paper-plane'></i> Send Request
                </button>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                    <a href="<?php echo APP_URL; ?>/chat/index.php?user=<?php echo $d['lender_id']; ?>" class="ext-btn-outline" style="text-decoration: none;">
                        <i class='bx bx-message-square-dots'></i> Chat
                    </a>
                    <button onclick="closeExtendModal()" class="ext-btn-outline" style="color: #ef4444; border-color: #fee2e2;">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Agent Modal -->
    <div id="agent-report-modal" class="modal-overlay">
        <div class="modal-card" style="max-width: 480px;">
            <div class="modal-header ext-header" style="border-bottom: none; padding-top: 2rem;">
                <div style="width: 60px; height: 60px; background: #fee2e2; color: #ef4444; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.75rem; margin: 0 auto 1rem;">
                    <i class='bx bx-flag'></i>
                </div>
                <h2 style="font-weight: 900; font-size: 1.5rem; color: var(--text-main);">Report Delivery Agent</h2>
                <p id="report-agent-name" style="color: var(--text-muted); font-size: 0.95rem; margin-top: 0.5rem;"></p>
            </div>
            <div class="modal-body" style="padding: 1rem 2rem 1rem;">
                <div class="form-group">
                    <label class="form-label">Reason for Report</label>
                    <select id="report-reason" class="form-control" style="appearance: none; background: #fff url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2364748B%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22/%3E%3C/svg%3E') no-repeat right 0.75rem center; background-size: 0.65rem auto;">
                        <option value="">Select a reason...</option>
                        <option value="behavior">Unprofessional Behavior</option>
                        <option value="late">Extremely Late Delivery</option>
                        <option value="damaged">Items Damaged in Transit</option>
                        <option value="misconduct">Safety or Misconduct Concern</option>
                        <option value="other">Other Issue</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Describe the Incident</label>
                    <textarea id="report-description" class="form-control" rows="4" placeholder="Please provide details about what happened..."></textarea>
                </div>
                <div style="background: #fff7ed; border: 1px solid #fef3c7; padding: 1rem; border-radius: 12px; color: #9a3412; font-size: 0.85rem; line-height: 1.4; margin-bottom: 1rem;">
                    <i class='bx bx-info-circle'></i> <strong>Note:</strong> False reports may result in penalties. Our team will investigate.
                </div>
            </div>
            <div class="modal-footer" style="padding: 1.5rem 2rem 2rem; border-top: none; display: flex; flex-direction: column; gap: 0.75rem;">
                <button onclick="submitAgentReport()" class="ext-btn-primary" style="background: #ef4444; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);">
                    <i class='bx bx-flag'></i> Submit Report
                </button>
                <button onclick="closeAgentReportModal()" class="ext-btn-outline">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const d = <?php echo json_encode($d); ?>;
        if (d.pickup_lat && d.order_lat) {
            const map = L.map('map').setView([d.pickup_lat, d.pickup_lng], 13);
            L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}.png').addTo(map);
            
            L.marker([d.pickup_lat, d.pickup_lng]).addTo(map).bindPopup('Pickup Location');
            L.marker([d.order_lat, d.order_lng]).addTo(map).bindPopup('Delivery Location');
            
            const line = L.polyline([[d.pickup_lat, d.pickup_lng], [d.order_lat, d.order_lng]], {color: 'var(--primary)', dashArray: '5, 10'}).addTo(map);
            map.fitBounds(line.getBounds().pad(0.2));
        }

        async function handleAction(action) {
            if (!confirm('Confirm this action?')) return;
            
            const formData = new FormData();
            formData.append('action', action);
            formData.append('transaction_id', d.id);

            try {
                const response = await fetch('../actions/request_action.php', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) {
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (err) {
                alert('Connection failed');
            }
        }

        function openExtendModal() {
            document.getElementById('ext-date').value = d.due_date;
            document.getElementById('ext-reason').value = '';
            document.getElementById('extension-modal').style.display = 'flex';
        }

        function closeExtendModal() {
            document.getElementById('extension-modal').style.display = 'none';
        }

        async function submitExtension() {
            const newDate = document.getElementById('ext-date').value;
            const reason = document.getElementById('ext-reason').value;

            if (!newDate) {
                alert('Please select a new due date');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'request_extension');
            formData.append('transaction_id', d.id);
            formData.append('new_date', newDate);
            formData.append('reason', reason);

            try {
                const response = await fetch('../actions/request_action.php', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) {
                    alert('Extension request sent to owner!');
                    closeExtendModal();
                } else {
                    alert(result.message || 'Failed to send request');
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
            formData.append('type', 'user');

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
    </script>
</body>
</html>
