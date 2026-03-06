<?php
require_once '../includes/db_helper.php';
require_once '../paths.php';
include '../includes/dashboard_header.php';

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
    header("Location: login.php");
    exit();
}

// Mark request notifications as read when visiting this page
// markNotificationsAsReadByType($userId, ['borrow_request', 'sell_request', 'request_accepted', 'request_declined']);

$deals = getUserDeals($userId);

// Data Integrity Fix: Cleanup duplicate damage fine records and sync unpaid_fines balance
try {
    $pdo = getDBConnection();
    // 1. Remove duplicate damage fine records (keeping the first one created)
    $pdo->exec("
        DELETE p1 FROM penalties p1 
        INNER JOIN penalties p2 
        ON p1.transaction_id = p2.transaction_id 
        AND p1.penalty_type = 'damage_fine' 
        AND p2.penalty_type = 'damage_fine'
        AND p1.id > p2.id
    ");
    
    // 2. Re-calculate user's total unpaid fines from the cleaned penalties table
    syncUserUnpaidFines($userId);
} catch (Exception $e) {
    error_log("Maintenance error in deals.php: " . $e->getMessage());
}

// Re-fetch deals after cleanup to ensure UI is fresh
$deals = getUserDeals($userId);
$all_deals = $deals; // All deals
$incoming = array_filter($deals, fn($d) => $d['lender_id'] == $userId);
$outgoing = array_filter($deals, fn($d) => $d['borrower_id'] == $userId);
$returns = array_filter($deals, fn($d) => in_array($d['status'], ['returning', 'returned']));

// Fetch detailed pending penalties for the consolidation view
$pendingPenalties = getUserPendingPenalties($userId);

// Get user's listings
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT l.*, b.title, b.author, b.cover_image, b.category
        FROM listings l
        JOIN books b ON l.book_id = b.id
        WHERE l.user_id = ?
        ORDER BY l.created_at DESC
    ");
    $stmt->execute([$userId]);
    $myListings = $stmt->fetchAll();
} catch (Exception $e) {
    $myListings = [];
}

// Filter payments — exclude cancelled orders
$payments = array_filter($all_deals, function($d) {
    if ($d['status'] === 'cancelled') return false;
    return $d['transaction_type'] === 'purchase' || (!empty($d['payment_status']) && $d['payment_status'] !== 'unpaid');
});

// Fetch current user's unpaid fines for consolidated view
$stmt = $pdo->prepare("SELECT unpaid_fines FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userUnpaidFines = (float)$stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Deals | BOOK-B</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=1.2">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <style>
        /* Status Message */
        #statusMessage {
            position: fixed;
            top: 80px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: var(--radius-lg);
            font-weight: 600;
            display: none;
            z-index: 1000;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            animation: slideIn 0.3s ease-out;
        }
        #statusMessage.success {
            background: #10b981;
            color: white;
        }
        #statusMessage.error {
            background: #ef4444;
            color: white;
        }
        @keyframes slideIn {
            from { transform: translateX(400px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        .deals-wrapper { max-width: 1200px; margin: 0 auto; }
        .page-header { margin-bottom: 2rem; }
        .tabs-header {
            display: flex;
            gap: 2rem;
            margin-bottom: 2rem;
            border-bottom: 1px solid var(--border-color);
        }
        .tab-btn {
            padding: 1rem 0;
            font-weight: 700;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            background: none;
            border: none;
            font-size: 1rem;
        }
        .tab-btn.active {
            color: var(--primary);
        }
        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--primary);
            border-radius: 3px;
        }
        .tab-count {
            background: var(--bg-body);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            margin-left: 6px;
        }
        .tab-btn.active .tab-count {
            background: var(--primary);
            color: white;
        }

        .deal-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 2rem;
            transition: all 0.3s;
        }
        .deal-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-color: var(--primary);
        }

        .deal-visual {
            position: relative;
            width: 100px;
            height: 140px;
            flex-shrink: 0;
        }
        .deal-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
        }
        .deal-type-tag {
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--primary);
            color: white;
            padding: 2px 10px;
            border-radius: 10px;
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .deal-main { flex: 1; }
        .deal-title {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 0.5rem;
        }
        .deal-meta {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .deal-actions {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 1rem;
            min-width: 180px;
        }

        .status-pill {
            padding: 0.5rem 1.25rem;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 800;
            text-transform: uppercase;
        }
        .pill-requested { background: #fef3c7; color: #d97706; }
        .pill-approved { background: #dcfce7; color: #16a34a; }
        .pill-cancelled { background: #fee2e2; color: #dc2626; }
        .pill-returned { background: #f1f5f9; color: #475569; }
        .pill-active { background: #dbeafe; color: #3b82f6; }
        .pill-available { background: #d1fae5; color: #10b981; }
        .pill-unavailable { background: #f3f4f6; color: #6b7280; }

        .btn-group {
            display: flex;
            gap: 0.75rem;
        }

        .empty-deals {
            text-align: center;
            padding: 5rem 2rem;
            color: var(--text-muted);
        }

        /* Feedback Modal */
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
            border-radius: var(--radius-lg); 
            width: 450px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25); 
            overflow: hidden;
            animation: modalFadeIn 0.3s ease-out;
        }
        @keyframes modalFadeIn {
            from { 
                opacity: 0; 
                transform: translateY(-20px) scale(0.95);
            }
            to { 
                opacity: 1; 
                transform: translateY(0) scale(1);
            }
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
            background: var(--bg-card); 
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
        
        .form-group { margin-bottom: 1.25rem; }
        .form-label { display: block; font-weight: 700; margin-bottom: 0.5rem; color: var(--text-main); font-size: 0.9rem; }
        .form-control {
            width: 100%; border: 1px solid var(--border-color); border-radius: var(--radius-md);
            padding: 0.75rem; font-family: inherit; outline: none; transition: border-color 0.3s;
        }
        .form-control:focus { border-color: var(--primary); }

        /* Premium Extension Modal Styles */
        .ext-header {
            text-align: center;
            padding-bottom: 0.5rem;
        }
        .ext-icon-wrapper {
            width: 64px;
            height: 64px;
            background: rgba(79, 70, 229, 0.1);
            color: var(--primary);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 1.25rem;
        }
        .ext-info-box {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.2);
            padding: 1rem;
            border-radius: var(--radius-lg);
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            margin-top: 1.25rem;
            transition: all 0.3s;
        }
        .ext-info-box:hover {
            transform: scale(1.02);
            border-color: #fcd34d;
        }
        .ext-info-icon {
            color: #d97706;
            font-size: 1.25rem;
            margin-top: 0.1rem;
        }
        .ext-info-content {
            flex: 1;
        }
        .ext-info-title {
            font-weight: 700;
            color: #92400e;
            font-size: 0.9rem;
            margin-bottom: 0.2rem;
        }
        .ext-info-text {
            color: #a16207;
            font-size: 0.8rem;
            line-height: 1.4;
        }
        .ext-modal-footer {
            padding: 1.5rem;
            background: var(--bg-body);
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .ext-btn-primary {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.875rem;
            border-radius: var(--radius-md);
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.3s;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
        }
        .ext-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(79, 70, 229, 0.3);
            filter: brightness(1.1);
        }
        .ext-btn-outline {
            background: var(--bg-card);
            color: var(--text-main);
            border: 1px solid var(--border-color);
            padding: 0.875rem;
            border-radius: var(--radius-md);
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.3s;
            cursor: pointer;
        }
        .ext-btn-outline:hover {
            background: #f1f5f9;
            border-color: var(--text-muted);
        }

        .report-btn {
            background: #fee2e2;
            color: #ef4444;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .report-btn:hover {
            background: #fecaca;
            transform: scale(1.1);
        }
    </style>
</head>
<body>
    <div id="statusMessage"></div>
    
    <div class="dashboard-wrapper">
        <?php include '../includes/dashboard_sidebar.php'; ?>

        <main class="main-content">
            <div class="deals-wrapper">
                <div class="page-header">
                    <h1>Deals & Listings</h1>
                    <p>Manage your transactions and book inventory</p>
                </div>

                <div class="tabs-header">
                    <button class="tab-btn active" onclick="switchTab('all', this)">
                        All <span class="tab-count"><?php echo count($all_deals); ?></span>
                    </button>
                    <button class="tab-btn" onclick="switchTab('incoming', this)">
                        Incoming Offers <span class="tab-count"><?php echo count($incoming); ?></span>
                    </button>
                    <button class="tab-btn" onclick="switchTab('outgoing', this)">
                        My Requests <span class="tab-count"><?php echo count($outgoing); ?></span>
                    </button>
                    <button class="tab-btn" onclick="switchTab('listings', this)">
                        My Listings <span class="tab-count"><?php echo count($myListings); ?></span>
                    </button>
                    <button class="tab-btn" onclick="switchTab('returns', this)">
                        Returns <span class="tab-count"><?php echo count($returns); ?></span>
                    </button>
                    <button class="tab-btn" onclick="switchTab('payments', this)">
                        Payments <span class="tab-count"><?php echo count($payments); ?></span>
                    </button>
                </div>

                <!-- All Deals Tab -->
                <div id="all-list">
                    <?php if (empty($all_deals)): ?>
                        <div class="empty-deals">
                            <i class='bx bx-archive-in' style="font-size: 4rem; margin-bottom: 1.5rem; display: block; opacity: 0.3;"></i>
                            <p style="font-size: 1.1rem; font-weight: 500;">No transactions yet.</p>
                        </div>
                    <?php endif; ?>
                    <?php foreach ($all_deals as $deal): ?>
                        <?php $isIncoming = ($deal['lender_id'] == $userId); ?>
                        <div class="deal-card">
                            <div class="deal-visual">
                                <?php 
                                    $cover = $deal['cover_image'];
                                    $cover = $cover ?: 'https://images.unsplash.com/photo-1543004218-ee141104975a?w=200';
                                ?>
                                <img src="<?php echo htmlspecialchars(html_entity_decode($cover), ENT_QUOTES, 'UTF-8'); ?>" class="deal-img" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1543004218-ee141104975a?w=400';">
                                <span class="deal-type-tag"><?php echo $deal['listing_type']; ?></span>
                            </div>
                            <div class="deal-main">
                                <div class="deal-title"><?php echo htmlspecialchars($deal['title']); ?></div>
                                <div class="deal-meta">
                                    <div class="meta-item">
                                        <?php if ($deal['lender_id'] == $userId): ?>
                                            <i class='bx bx-user'></i> Borrower: 
                                            <a href="user_profile.php?id=<?php echo $deal['borrower_id']; ?>" style="color: var(--primary); text-decoration: none; font-weight: 700;">
                                                <?php echo htmlspecialchars($deal['borrower_name']); ?>
                                            </a>
                                        <?php else: ?>
                                            <i class='bx bx-store-alt'></i> Owner: 
                                            <a href="user_profile.php?id=<?php echo $deal['lender_id']; ?>" style="color: var(--primary); text-decoration: none; font-weight: 700;">
                                                <?php echo htmlspecialchars($deal['lender_name']); ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    <div class="meta-item"><i class='bx bx-calendar'></i> <?php echo date('M d, Y', strtotime($deal['created_at'])); ?></div>
                                </div>
                            </div>
                            <div class="deal-actions">
                                <span class="status-pill pill-<?php echo $deal['status']; ?>"><?php echo $deal['status']; ?></span>
                                <!-- Actions logic copied from specific tabs logic below, simplified or linking to detailed tab -->
                                <button onclick="window.location.href='track_deliveries.php'" class="btn btn-outline btn-sm">
                                    <i class='bx bx-radar'></i> Track
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Incoming Offers Tab -->
                <div id="incoming-list">
                    <?php if (empty($incoming)): ?>
                        <div class="empty-deals">
                            <i class='bx bx-mail-send' style="font-size: 4rem; margin-bottom: 1.5rem; display: block; opacity: 0.3;"></i>
                            <p style="font-size: 1.1rem; font-weight: 500;">No incoming requests yet.</p>
                        </div>
                    <?php endif; ?>
                    <?php foreach ($incoming as $deal): ?>
                        <?php $isIncoming = true; ?>
                        <div class="deal-card" id="deal-<?php echo $deal['id']; ?>">
                            <div class="deal-visual">
                                <?php 
                                    $cover = $deal['cover_image'];
                                    $cover = $cover ?: 'https://images.unsplash.com/photo-1543004218-ee141104975a?w=200';
                                ?>
                                <img src="<?php echo htmlspecialchars(html_entity_decode($cover), ENT_QUOTES, 'UTF-8'); ?>" class="deal-img" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1543004218-ee141104975a?w=400';">
                                <span class="deal-type-tag"><?php echo $deal['listing_type']; ?></span>
                            </div>
                            <div class="deal-main">
                                <div class="deal-title"><?php echo htmlspecialchars($deal['title']); ?></div>
                                <div class="deal-meta">
                                    <div class="meta-item">
                                        <i class='bx bx-user'></i> From: 
                                        <a href="user_profile.php?id=<?php echo $deal['borrower_id']; ?>" style="color: var(--primary); text-decoration: none; font-weight: 700;">
                                            <?php echo htmlspecialchars($deal['borrower_name']); ?>
                                            <span style="display: block; font-size: 0.7rem; font-weight: 500; margin-top: 2px;">(Click to view reviews)</span>
                                        </a>
                                    </div>
                                    <div class="meta-item"><i class='bx bx-calendar'></i> <?php echo date('M d, Y', strtotime($deal['created_at'])); ?></div>
                                    <?php if (!empty($deal['quantity']) && $deal['quantity'] > 1): ?>
                                    <div class="meta-item">
                                        <i class='bx bx-layer'></i>
                                        <span>Qty: <strong><?php echo $deal['quantity']; ?></strong></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($deal['request_message'])): ?>
                                <div class="deal-message" style="background: #f8fafc; padding: 0.5rem; border-radius: 4px; font-size: 0.85rem; margin-top: 0.5rem; color: #475569;">
                                    <strong>Reason:</strong> "<?php echo htmlspecialchars($deal['request_message']); ?>"
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="deal-actions">
                                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                                    <span class="status-pill pill-<?php echo $deal['status']; ?>"><?php echo $deal['status']; ?></span>
                                    <button onclick="openUserReportModal(<?php echo $deal['borrower_id']; ?>, '<?php echo addslashes($deal['borrower_name']); ?>')" class="report-btn" title="Report Borrower">
                                        <i class='bx bx-flag'></i>
                                    </button>
                                </div>
                                <?php if ($deal['status'] === 'requested'): ?>
                                    <div class="btn-group">
                                        <button onclick="handleDeal(<?php echo $deal['id']; ?>, 'accept_request')" class="btn btn-primary btn-sm">Accept</button>
                                        <button onclick="handleDeal(<?php echo $deal['id']; ?>, 'decline_request')" class="btn btn-sm" style="background: #fee2e2; color: #dc2626; border: none;">Decline</button>
                                    </div>
                                <?php elseif (!$isIncoming && in_array($deal['status'], ['requested', 'active'])): ?>
                                     <!-- Moved Cancel to Outgoing tab logic mostly, but keeping checks distinct -->
                                <?php elseif ($deal['transaction_type'] == 'purchase' && $deal['status'] == 'approved' && $deal['payment_status'] != 'paid'): ?>
                                    <button onclick="handleDeal(<?php echo $deal['id']; ?>, 'cancel_order')" class="btn btn-sm" style="background: #fee2e2; color: #dc2626; border: none;">Cancel Order</button>

                                <?php elseif (($deal['status'] === 'approved' || $deal['status'] === 'active' || $deal['status'] === 'delivered') && $deal['listing_type'] === 'borrow'): ?>
                                    
                                    <?php if (!empty($deal['pending_due_date'])): ?>
                                        <div style="background: #fffbeb; border: 1px solid #fcd34d; padding: 1rem; border-radius: 12px; margin-bottom: 0.75rem; width: 100%;">
                                            <p style="font-size: 0.8rem; font-weight: 700; color: #92400e; margin-bottom: 0.5rem;">Extension Requested: <?php echo date('M d, Y', strtotime($deal['pending_due_date'])); ?></p>
                                            <div class="btn-group" style="width: 100%;">
                                                <button onclick="handleDeal(<?php echo $deal['id']; ?>, 'approve_extension')" class="btn btn-primary btn-sm" style="flex: 1;">Approve</button>
                                                <button onclick="handleDeal(<?php echo $deal['id']; ?>, 'decline_extension')" class="btn btn-sm" style="flex: 1; background: #fee2e2; color: #dc2626; border: none;">Decline</button>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($deal['status'] !== 'delivered'): ?>
                                        <button onclick="openReturnModal(<?php echo $deal['id']; ?>)" class="btn btn-primary btn-sm">
                                            <i class='bx bx-check-circle'></i> Mark Returned
                                        </button>
                                    <?php endif; ?>
                                <?php elseif (($deal['status'] === 'approved' || $deal['status'] === 'active') && $deal['listing_type'] === 'borrow'): ?>
                                    <button onclick="openReturnModal(<?php echo $deal['id']; ?>)" class="btn btn-primary btn-sm">
                                        <i class='bx bx-check-circle'></i> Mark Returned
                                    </button>

                                <?php elseif ($deal['status'] === 'delivered' || $deal['status'] === 'returned'): ?>
                                    <div class="btn-group">
                                        <?php if ($deal['status'] === 'returned' && empty($deal['is_restocked'])): ?>
                                            <button onclick="showRestockModal(<?php echo $deal['id']; ?>)" class="btn btn-primary btn-sm">
                                                <i class='bx bx-package'></i> Restock
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($deal['status'] === 'returned' && $deal['transaction_type'] === 'borrow' && $deal['lender_id'] == $userId): ?>
                                            <?php if (empty($deal['damage_fine_status'])): ?>
                                                <button onclick="openPostReturnFineModal(<?php echo $deal['id']; ?>, '<?php echo addslashes($deal['title']); ?>')" class="btn btn-sm" style="background: #fee2e2; color: #dc2626; border: none; font-size: 0.75rem; padding: 0.5rem 0.75rem;">
                                                    <i class='bx bx-error-alt'></i> Apply Fine
                                                </button>
                                            <?php elseif ($deal['damage_fine_status'] === 'pending'): ?>
                                                <span style="font-size: 0.75rem; font-weight: 700; color: #ef4444; background: #fff1f2; padding: 0.25rem 0.75rem; border-radius: 99px; border: 1px solid #fecdd3;">
                                                    <i class='bx bx-time-five'></i> Fine Pending
                                                </span>
                                            <?php else: ?>
                                                <span style="font-size: 0.75rem; font-weight: 700; color: #059669; background: #ecfdf5; padding: 0.25rem 0.75rem; border-radius: 99px; border: 1px solid #10b981;">
                                                    <i class='bx bx-check'></i> Due Paid
                                                </span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <button onclick="openFeedbackModal(<?php echo $deal['id']; ?>, <?php echo $deal['borrower_id']; ?>, '<?php echo addslashes($deal['borrower_name']); ?>')" class="btn btn-outline btn-sm">
                                            <i class='bx bx-star'></i> Rate Partner
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Outgoing Requests Tab -->
                <div id="outgoing-list" style="display: none;">
                    <?php if (empty($outgoing)): ?>
                        <div class="empty-deals">
                            <i class='bx bx-paper-plane' style="font-size: 4rem; margin-bottom: 1.5rem; display: block; opacity: 0.3;"></i>
                            <p style="font-size: 1.1rem; font-weight: 500;">You haven't requested any books yet.</p>
                        </div>
                    <?php endif; ?>
                    <?php foreach ($outgoing as $deal): ?>
                        <?php $isIncoming = false; ?>
                        <div class="deal-card">
                            <div class="deal-visual">
                                <?php 
                                    $cover = $deal['cover_image'];
                                    $cover = $cover ?: 'https://images.unsplash.com/photo-1543004218-ee141104975a?w=200';
                                ?>
                                <img src="<?php echo htmlspecialchars(html_entity_decode($cover), ENT_QUOTES, 'UTF-8'); ?>" class="deal-img" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1543004218-ee141104975a?w=400';">
                                <span class="deal-type-tag"><?php echo $deal['listing_type']; ?></span>
                            </div>
                            <div class="deal-main">
                                <div class="deal-title"><?php echo htmlspecialchars($deal['title']); ?></div>
                                <div class="deal-meta">
                                    <div class="meta-item">
                                        <i class='bx bx-store-alt'></i> Owner: 
                                        <a href="user_profile.php?id=<?php echo $deal['lender_id']; ?>" style="color: var(--primary); text-decoration: none; font-weight: 700;">
                                            <?php echo htmlspecialchars($deal['lender_name']); ?>
                                            <span style="display: block; font-size: 0.7rem; font-weight: 500; margin-top: 2px;">(Click to view reviews)</span>
                                        </a>
                                    </div>
                                    <div class="meta-item"><i class='bx bx-calendar'></i> Requested: <?php echo date('M d', strtotime($deal['created_at'])); ?></div>
                                    <?php if ($deal['listing_type'] === 'borrow' && $deal['due_date']): ?>
                                        <?php 
                                            $dueDate = new DateTime($deal['due_date']);
                                            $today = new DateTime();
                                            $diff = $today->diff($dueDate);
                                            $isDueSoon = ($dueDate > $today && $diff->days <= 2);
                                            $isOverdue = ($dueDate <= $today);
                                        ?>
                                        <div class="meta-item" style="<?php echo $isOverdue ? 'color: #ef4444;' : ($isDueSoon ? 'color: #f59e0b;' : ''); ?>">
                                            <i class='bx <?php echo $isOverdue ? 'bx-error-circle' : 'bx-time-five'; ?>'></i> 
                                            Due: <strong><?php echo date('M d, Y', strtotime($deal['due_date'])); ?></strong>
                                            <?php if ($isOverdue): ?> <span class="badge" style="background: #fee2e2; color: #ef4444; font-size: 0.65rem;">OVERDUE</span> <?php endif; ?>
                                            <?php if ($isDueSoon): ?> <span class="badge" style="background: #fef3c7; color: #92400e; font-size: 0.65rem;">DUE SOON</span> <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($deal['pending_due_date']): ?>
                                        <div class="meta-item" style="color: #d97706;"><i class='bx bx-time'></i> Ext. Pending: <?php echo date('M d', strtotime($deal['pending_due_date'])); ?></div>
                                    <?php endif; ?>

                                </div>
                            </div>
                            <div class="deal-actions">
                                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                                    <span class="status-pill pill-<?php echo $deal['status']; ?>"><?php echo $deal['status']; ?></span>
                                    <button onclick="openUserReportModal(<?php echo $deal['lender_id']; ?>, '<?php echo addslashes($deal['lender_name']); ?>')" class="report-btn" title="Report Owner">
                                        <i class='bx bx-flag'></i>
                                    </button>
                                </div>
                                
                                <!-- Cancel Order for Borrowers/Buyers -->
                                <?php 
                                    $canCancel = in_array($deal['status'], ['requested', 'approved', 'active']) 
                                               && $deal['status'] !== 'delivered' 
                                               && empty($deal['delivery_agent_id']) 
                                               && empty($deal['return_agent_id']);
                                ?>
                                <?php if ($canCancel): ?>
                                     <button onclick="handleDeal(<?php echo $deal['id']; ?>, 'cancel_order', '<?php echo $deal['status']; ?>')" class="btn btn-sm" style="background: #fee2e2; color: #dc2626; border: none; margin-bottom: 0.5rem;">
                                         <i class='bx bx-x-circle'></i> Cancel Order
                                     </button>
                                <?php endif; ?>

                                <div class="btn-group">
                                    <?php if ($deal['lender_id'] != $userId): ?>
                                     <a href="<?php echo APP_URL; ?>/chat/index.php?user=<?php echo $deal['lender_id']; ?>" class="btn btn-outline btn-sm">
                                         <i class='bx bx-message-square-dots'></i> Chat
                                     </a>
                                    <?php endif; ?>
                                    <?php if ($deal['status'] === 'delivered' && $deal['transaction_type'] === 'borrow'): ?>
                                        <button onclick="handleDeal(<?= $deal['id']; ?>, 'request_return_delivery')" class="btn btn-primary btn-sm">
                                            <i class='bx bx-undo'></i> Return
                                        </button>
                                        <button onclick="openExtendModal(<?= $deal['id']; ?>, '<?= $deal['due_date']; ?>', <?= $deal['lender_id']; ?>)" class="btn btn-outline btn-sm">
                        <i class='bx bx-calendar-plus'></i> Extend
                    </button>
                                    <?php endif; ?>
                                    <?php if (($deal['status'] === 'delivered' || $deal['status'] === 'returned') && empty($deal['is_reviewed'])): ?>
                                        <button onclick="openFeedbackModal(<?php echo $deal['id']; ?>, <?php echo $deal['lender_id']; ?>, '<?php echo addslashes($deal['lender_name']); ?>')" class="btn btn-outline btn-sm">
                                            <i class='bx bx-star'></i> Rate
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- My Listings Tab -->
                <div id="listings-list" style="display: none;">
                    <?php if (empty($myListings)): ?>
                        <div class="empty-deals">
                            <i class='bx bx-book-add' style="font-size: 4rem; margin-bottom: 1.5rem; display: block; opacity: 0.3;"></i>
                            <p style="font-size: 1.1rem; font-weight: 500;">You haven't listed any books yet.</p>
                            <a href="add_listing.php" class="btn btn-primary" style="margin-top: 1.5rem;">
                                <i class='bx bx-plus-circle'></i> Add Your First Book
                            </a>
                        </div>
                    <?php endif; ?>
                    <?php foreach ($myListings as $listing): ?>
                        <div class="deal-card" id="listing-<?php echo $listing['id']; ?>">
                            <div class="deal-visual">
                                <?php 
                                    $cover = $listing['cover_image'];
                                    $fallback = 'https://images.unsplash.com/photo-1543004218-ee141104975a?w=400';
                                    $cover = $cover ?: $fallback;
                                ?>
                                <img src="<?php echo htmlspecialchars(html_entity_decode($cover), ENT_QUOTES, 'UTF-8'); ?>" class="deal-img" onerror="this.onerror=null; this.src='<?php echo $fallback; ?>';">
                                <span class="deal-type-tag"><?php echo $listing['listing_type']; ?></span>
                            </div>
                            <div class="deal-main">
                                <div class="deal-title"><?php echo htmlspecialchars($listing['title']); ?></div>
                                <div class="deal-meta">
                                    <div class="meta-item"><i class='bx bx-package'></i> Quantity: <strong><?php echo $listing['quantity'] ?? 1; ?></strong></div>
                                    <div class="meta-item"><i class='bx bx-wallet'></i> <?php echo $listing['credit_cost'] ?? 10; ?> credits</div>
                                    <div class="meta-item"><i class='bx bx-calendar'></i> <?php echo date('M d', strtotime($listing['created_at'])); ?></div>
                                </div>
                            </div>
                            <div class="deal-actions">
                                <span class="status-pill pill-<?php echo $listing['availability_status']; ?>"><?php echo $listing['availability_status']; ?></span>
                                <div class="btn-group">
                                    <button onclick="editListing(<?php echo $listing['id']; ?>)" class="btn btn-outline btn-sm">
                                        <i class='bx bx-edit'></i> Edit
                                    </button>
                                    <button onclick="deleteListing(<?php echo $listing['id']; ?>)" class="btn btn-sm" style="background: #fee2e2; color: #dc2626; border: none;">
                                        <i class='bx bx-trash'></i> Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Returns Tab -->
                <div id="returns-list" style="display: none;">
                    <?php if (empty($returns)): ?>
                        <div class="empty-deals">
                            <i class='bx bx-undo' style="font-size: 4rem; margin-bottom: 1.5rem; display: block; opacity: 0.3;"></i>
                            <p style="font-size: 1.1rem; font-weight: 500;">No return transactions found.</p>
                        </div>
                    <?php endif; ?>
                    <?php foreach ($returns as $deal): ?>
                        <div class="deal-card">
                            <div class="deal-visual">
                                <?php 
                                    $cover = $deal['cover_image'] ?: 'https://images.unsplash.com/photo-1543004218-ee141104975a?w=200';
                                ?>
                                <img src="<?php echo htmlspecialchars(html_entity_decode($cover), ENT_QUOTES, 'UTF-8'); ?>" class="deal-img" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1543004218-ee141104975a?w=400';">
                                <span class="deal-type-tag">RETURN</span>
                            </div>
                            <div class="deal-main">
                                <div class="deal-title"><?php echo htmlspecialchars($deal['title']); ?></div>
                                <div class="deal-meta">
                                    <div class="meta-item">
                                        <?php if ($deal['lender_id'] == $userId): ?>
                                            <i class='bx bx-user'></i> From: <?php echo htmlspecialchars($deal['borrower_name']); ?>
                                        <?php else: ?>
                                            <i class='bx bx-store-alt'></i> To: <?php echo htmlspecialchars($deal['lender_name']); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="meta-item"><i class='bx bx-calendar'></i> Updated: <?php echo date('M d', strtotime($deal['updated_at'] ?? $deal['created_at'])); ?></div>
                                </div>
                            </div>
                            <div class="deal-actions">
                                <span class="status-pill pill-<?php echo $deal['status']; ?>"><?php echo $deal['status']; ?></span>
                                <div style="display: flex; gap: 0.5rem; margin-top: 0.5rem; flex-wrap: wrap; justify-content: flex-end;">
                                    <?php if ($deal['status'] === 'returned' && $deal['transaction_type'] === 'borrow' && $deal['lender_id'] == $userId && $user['role'] === 'library'): ?>
                                        <?php if (empty($deal['damage_fine_status'])): ?>
                                            <button onclick="openPostReturnFineModal(<?php echo $deal['id']; ?>, '<?php echo addslashes($deal['title']); ?>')" class="btn btn-sm" style="background: #fee2e2; color: #dc2626; border: none; font-size: 0.75rem; padding: 0.5rem 0.75rem;">
                                                <i class='bx bx-error-alt'></i> Apply Fine
                                            </button>
                                        <?php elseif ($deal['damage_fine_status'] === 'pending'): ?>
                                            <span style="font-size: 0.75rem; font-weight: 700; color: #ef4444; background: #fff1f2; padding: 0.25rem 0.75rem; border-radius: 99px; border: 1px solid #fecdd3;">
                                                <i class='bx bx-time-five'></i> Fine Pending
                                            </span>
                                        <?php else: ?>
                                            <span style="font-size: 0.75rem; font-weight: 700; color: #059669; background: #ecfdf5; padding: 0.25rem 0.75rem; border-radius: 99px; border: 1px solid #10b981;">
                                                <i class='bx bx-check'></i> Due Paid
                                            </span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <button onclick="window.location.href='track_deliveries.php'" class="btn btn-outline btn-sm" style="font-size: 0.75rem; padding: 0.5rem 0.75rem;">
                                        <i class='bx bx-radar'></i> Track Return
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Payments Tab -->
                <div id="payments-list" style="display: none;">
                    <?php if (empty($payments)): ?>
                        <div class="empty-deals">
                            <i class='bx bx-credit-card' style="font-size: 4rem; margin-bottom: 1.5rem; display: block; opacity: 0.3;"></i>
                            <p style="font-size: 1.1rem; font-weight: 500;">No payment history found.</p>
                        </div>
                    <?php endif; ?>

                    <div style="margin-bottom: 2rem;">
                        <h3 style="margin-bottom: 1rem; color: var(--text-main); font-weight: 800; font-size: 1.2rem;">Purchases (Outgoing)</h3>
                        <?php 
                        $purchases = array_filter($payments, fn($p) => $p['borrower_id'] == $userId);
                        
                        // Calculate total due — exclude cancelled, COD-confirmed pickups
                        $total_due = 0;
                        foreach ($purchases as $p) {
                            if ($p['status'] === 'cancelled') continue;
                            $isCodPickupConfirmed = ($p['payment_method'] === 'cod' && $p['delivery_method'] === 'pickup' && !empty($p['lender_confirm_at']));
                            if ($p['payment_status'] !== 'paid' && !$isCodPickupConfirmed) {
                                $amt = (float)($p['price_at_transaction'] ?? 0);
                                if ($amt <= 0) $amt = (float)($p['book_price'] ?? 0);
                                if ($amt <= 0) $amt = (float)($p['listing_price'] ?? 0);
                                $total_due += ($amt * ($p['quantity'] ?: 1));
                            }
                        }
                        
                        // Consolidated Total including Penalty Fines
                        $grand_total_due = $total_due + $userUnpaidFines;

                        if ($grand_total_due > 0): ?>
                            <div style="background: #fffbeb; border: 1px solid #fef3c7; padding: 1.25rem; border-radius: var(--radius-lg); margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; box-shadow: var(--shadow-sm);">
                                <div style="display: flex; align-items: center; gap: 1rem;">
                                    <div style="background: #fef3c7; color: #92400e; width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                                        <i class='bx bx-error-circle'></i>
                                    </div>
                                    <div>
                                        <p style="margin: 0; font-weight: 700; color: #92400e; font-size: 1.1rem;">Pending Payments & Consolidation</p>
                                        <div style="display: flex; gap: 1rem; margin-top: 0.25rem;">
                                            <?php if ($total_due > 0): ?>
                                                <span style="font-size: 0.8rem; color: #a16207;">Purchases: ₹<?php echo number_format($total_due, 2); ?></span>
                                            <?php endif; ?>
                                            <?php if ($userUnpaidFines > 0): ?>
                                                <div style="display: flex; flex-direction: column; gap: 0.25rem; margin-top: 0.5rem; background: #fff1f2; padding: 0.5rem 0.75rem; border-radius: 8px; border: 1px solid #fecdd3;">
                                                    <span style="font-size: 0.8rem; color: #ef4444; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;">Pending Fines Breakdown:</span>
                                                    <?php foreach ($pendingPenalties as $pen): ?>
                                                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; color: #b91c1c;">
                                                            <span>
                                                                <i class='bx bx-book' style="opacity: 0.7;"></i> 
                                                                <strong><?php echo htmlspecialchars($pen['book_title'] ?: 'General Penalty'); ?></strong>
                                                                <span style="font-size: 0.75rem; opacity: 0.8;">(Order #<?php echo $pen['transaction_id']; ?>)</span>
                                                            </span>
                                                            <strong style="margin-left: 1rem;">₹<?php echo number_format($pen['monetary_penalty'], 2); ?></strong>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <p style="margin: 0; font-size: 0.85rem; color: #a16207; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Grand Total Due</p>
                                    <p style="margin: 0; font-size: 1.75rem; font-weight: 900; color: #92400e;">₹<?php echo number_format($grand_total_due, 2); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (empty($purchases)): ?>
                            <p style="color: var(--text-muted);">No purchases made yet.</p>
                        <?php else: ?>
                            <?php foreach ($purchases as $p): ?>
                                <div class="deal-card">
                                    <div class="deal-visual">
                                        <img src="<?php echo htmlspecialchars(html_entity_decode($p['cover_image'] ?: 'https://images.unsplash.com/photo-1543004218-ee141104975a?w=200'), ENT_QUOTES, 'UTF-8'); ?>" class="deal-img">
                                        <span class="deal-type-tag"><?php echo $p['transaction_type']; ?></span>
                                    </div>
                                    <div class="deal-main">
                                        <div class="deal-title"><?php echo htmlspecialchars($p['title']); ?></div>
                                        <div class="deal-meta">
                                            <div class="meta-item"><i class='bx bx-user'></i> Seller: <?php echo htmlspecialchars($p['lender_name']); ?></div>
                                            <div class="meta-item"><i class='bx bx-calendar'></i> <?php echo date('M d, Y', strtotime($p['created_at'])); ?></div>
                                            <div class="meta-item"><i class='bx bx-receipt'></i> Order #<?php echo $p['id']; ?></div>
                                        </div>
                                        <div style="margin-top: 0.75rem; display: flex; gap: 1rem; align-items: center;">
                                            <?php 
                                                $unitAmt = (float)($p['price_at_transaction'] ?? 0);
                                                if ($unitAmt <= 0) $unitAmt = (float)($p['book_price'] ?? 0);
                                                if ($unitAmt <= 0) $unitAmt = (float)($p['listing_price'] ?? 0);
                                                $displayAmt = $unitAmt * ($p['quantity'] ?: 1);
                                            ?>
                                            <span style="font-weight: 700; color: var(--primary);">₹<?php echo number_format($displayAmt, 2); ?></span>
                                            <?php 
                                            $isCodPickupConfirmed = ($p['payment_method'] === 'cod' && $p['delivery_method'] === 'pickup' && !empty($p['lender_confirm_at']));
                                            if ($p['payment_status'] === 'paid' || $isCodPickupConfirmed): ?>
                                                <span class="status-pill pill-approved" style="font-size: 0.7rem;">collected</span>
                                            <?php else: ?>
                                                <span class="status-pill pill-requested" style="font-size: 0.7rem;"><?php echo $p['payment_status'] ?: 'unpaid'; ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($p['payment_method'])): ?>
                                                <span style="font-size: 0.8rem; color: var(--text-muted);"><i class='bx bx-wallet'></i> <?php echo ucfirst($p['payment_method']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="deal-actions">
                                        <?php 
                                        $isCodPickupConfirmed = ($p['payment_method'] === 'cod' && $p['delivery_method'] === 'pickup' && !empty($p['lender_confirm_at']));
                                        if ($p['status'] === 'cancelled'): ?>
                                            <span class="status-pill pill-cancelled" style="font-size: 0.7rem;">Cancelled</span>
                                        <?php elseif ($p['payment_status'] === 'paid' || $isCodPickupConfirmed): ?>
                                            <div style="font-size: 0.7rem; color: var(--text-muted); text-align: right;">
                                                <?php if ($isCodPickupConfirmed && $p['payment_status'] !== 'paid'): ?>
                                                    <span style="color: #10b981; font-weight: 700;">✓ Collected (COD)</span><br>
                                                <?php else: ?>
                                                    ID: <?php echo $p['razorpay_payment_id'] ?: 'N/A'; ?><br>
                                                <?php endif; ?>
                                                TX: #<?php echo $p['id']; ?>
                                            </div>
                                        <?php else: ?>
                                            <button onclick="payForBulk(<?php echo $p['id']; ?>, <?php echo $p['listing_id']; ?>, <?php echo $p['quantity'] ?: 1; ?>, <?php echo $unitAmt; ?>)" class="btn btn-primary btn-sm">Pay Now</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div>
                        <h3 style="margin-bottom: 1rem; color: var(--text-main); font-weight: 800; font-size: 1.2rem;">Sales (Incoming)</h3>
                        <?php 
                        $sales = array_filter($payments, fn($p) => $p['lender_id'] == $userId);
                        if (empty($sales)): ?>
                            <p style="color: var(--text-muted);">No sales made yet.</p>
                        <?php else: ?>
                            <?php foreach ($sales as $s): ?>
                                <div class="deal-card">
                                    <div class="deal-visual">
                                        <img src="<?php echo htmlspecialchars(html_entity_decode($s['cover_image'] ?: 'https://images.unsplash.com/photo-1543004218-ee141104975a?w=200'), ENT_QUOTES, 'UTF-8'); ?>" class="deal-img">
                                        <span class="deal-type-tag">SALE</span>
                                    </div>
                                    <div class="deal-main">
                                        <div class="deal-title"><?php echo htmlspecialchars($s['title']); ?></div>
                                        <div class="deal-meta">
                                            <div class="meta-item"><i class='bx bx-user'></i> Buyer: <?php echo htmlspecialchars($s['borrower_name']); ?></div>
                                            <div class="meta-item"><i class='bx bx-calendar'></i> <?php echo date('M d, Y', strtotime($s['created_at'])); ?></div>
                                        </div>
                                        <div style="margin-top: 0.75rem; display: flex; gap: 1rem; align-items: center;">
                                            <?php 
                                                $unitAmtS = (float)($s['price_at_transaction'] ?? 0);
                                                if ($unitAmtS <= 0) $unitAmtS = (float)($s['book_price'] ?? 0);
                                                if ($unitAmtS <= 0) $unitAmtS = (float)($s['listing_price'] ?? 0);
                                                $displayAmtS = $unitAmtS * ($s['quantity'] ?: 1);
                                            ?>
                                            <span style="font-weight: 700; color: #10b981;">+₹<?php echo number_format($displayAmtS, 2); ?></span>
                                            <span class="status-pill <?php echo ($s['payment_status'] === 'paid') ? 'pill-approved' : 'pill-requested'; ?>" style="font-size: 0.7rem;">
                                                <?php echo $s['payment_status'] ?: 'unpaid'; ?>
                                            </span>
                                            <?php if (!empty($s['payment_method'])): ?>
                                                <span style="font-size: 0.8rem; color: var(--text-muted);"><i class='bx bx-wallet'></i> <?php echo ucfirst($s['payment_method']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="deal-actions">
                                        <?php if ($s['payment_status'] !== 'paid'): ?>
                                            <button onclick="markSalePaid(<?php echo $s['id']; ?>, this)" class="btn btn-sm" style="background: #10b981; color: white; border: none; font-size: 0.75rem; margin-bottom: 0.5rem; width: 100%;">
                                                <i class='bx bx-check-double'></i> Mark Paid (Cash)
                                            </button>
                                        <?php endif; ?>
                                        <div style="font-size: 0.7rem; color: var(--text-muted); text-align: right;">
                                            ID: <?php echo $s['razorpay_payment_id'] ?: 'N/A'; ?><br>
                                            TX: #<?php echo $s['id']; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Report User Modal -->
    <div id="user-report-modal" class="modal-overlay">
        <div class="modal-card" style="max-width: 450px;">
            <div class="modal-header ext-header" style="border-bottom: none; padding-top: 2rem;">
                <div class="ext-icon-wrapper" style="background: #fee2e2; color: #ef4444;">
                    <i class='bx bx-flag'></i>
                </div>
                <h2 style="font-weight: 800; font-size: 1.25rem;">Report User</h2>
                <p id="report-user-name" style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.25rem;"></p>
            </div>
            <div class="modal-body" style="padding: 1rem 1.5rem;">
                <div class="form-group">
                    <label class="form-label">Reason for Report</label>
                    <select id="report-reason" class="form-control" style="appearance: none; background: #fff url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2364748B%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22/%3E%3C/svg%3E') no-repeat right 0.75rem center; background-size: 0.65rem auto;">
                        <option value="">Select a reason...</option>
                        <option value="fake">Fake Profile / Scam</option>
                        <option value="harassment">Harassment / Abusive Language</option>
                        <option value="no_show">No-show / Item Not Handed Over</option>
                        <option value="bad_item">Book Not in Described Condition</option>
                        <option value="other">Other Issue</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea id="report-description" class="form-control" rows="4" placeholder="Provide more context..."></textarea>
                </div>
            </div>
            <div class="ext-modal-footer">
                <button onclick="submitUserReport()" class="ext-btn-primary" style="background: #ef4444; border-radius: 12px;">
                    Submit Report
                </button>
                <button onclick="closeUserReportModal()" class="ext-btn-outline" style="border-radius: 12px;">
                    Cancel
                </button>
            </div>
        </div>
    </div>



    <!-- Extension Modal -->
    <div id="extension-modal" class="modal-overlay">
        <div class="modal-card" style="max-width: 480px;">
            <div class="modal-header ext-header" style="border-bottom: none; padding-top: 2rem;">
                <div class="ext-icon-wrapper">
                    <i class='bx bx-calendar-plus'></i>
                </div>
                <h2 style="font-weight: 900; font-size: 1.5rem; color: var(--text-main);">Extend Return Date</h2>
                <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 0.5rem;">Need more time? Request an extension from the owner.</p>
            </div>
            <div class="modal-body" style="padding: 1rem 2rem 2rem;">
                <input type="hidden" id="ext-tx-id">
                
                <div class="form-group">
                    <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem;">
                        <i class='bx bx-calendar-event' style="color: var(--primary);"></i>
                        <span>New Due Date</span>
                    </label>
                    <input type="date" id="ext-date" class="form-control" style="border-radius: 12px; padding: 0.8rem; border: 2px solid #e2e8f0; transition: border-color 0.3s;">
                </div>
                
                <div class="form-group">
                    <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem;">
                        <i class='bx bx-comment-detail' style="color: var(--primary);"></i>
                        <span>Reason for Extension</span>
                    </label>
                    <textarea id="ext-reason" class="form-control" rows="3" placeholder="A short explanation helps get your request approved faster..." style="border-radius: 12px; padding: 0.8rem; border: 2px solid #e2e8f0; resize: none;"></textarea>
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
                    <i class='bx bx-paper-plane'></i> Send Extension Request
                </button>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                    <a id="ext-chat-btn" href="#" class="ext-btn-outline" style="text-decoration: none;">
                        <i class='bx bx-message-square-dots'></i> Chat with Owner
                    </a>
                    <button onclick="closeExtendModal()" class="ext-btn-outline" style="color: #ef4444; border-color: #fee2e2;">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Feedback Modal -->
    <div id="feedback-modal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h2 style="font-weight: 800; font-size: 1.25rem;">Rate Your Experience</h2>
                <p id="rating-target-name" style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.25rem;">with Alex</p>
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
                <h2 style="font-weight: 800; font-size: 1.25rem;">Restock Book to Inventory</h2>
                <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.25rem;">Add this book back to your available stock?</p>
            </div>
            <div class="modal-body">
                <div style="background: #f0fdf4; border: 1px solid #86efac; padding: 1.25rem; border-radius: 12px; margin-bottom: 1rem;">
                    <div style="display: flex; align-items: center; gap: 0.75rem; color: #16a34a;">
                        <i class='bx bx-package' style="font-size: 1.5rem;"></i>
                        <div>
                            <p style="font-weight: 700; margin: 0;">Confirm Restocking</p>
                            <p style="font-size: 0.85rem; margin: 0.25rem 0 0 0; opacity: 0.9;">This will increase your available quantity by 1</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button onclick="closeRestockModal()" class="btn btn-outline">Cancel</button>
                <button onclick="confirmRestock()" class="btn btn-primary">Yes, Restock Book</button>
            </div>
        </div>
    </div>

    <!-- Payment Selection Modal -->
    <div id="payment-modal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h2 style="font-weight: 800; font-size: 1.25rem;">Complete Your Purchase</h2>
                <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.25rem;">Choose how you want to pay.</p>
            </div>
            <div class="modal-body">
                <div style="background: #f8fafc; padding: 1.25rem; border-radius: 12px; margin-bottom: 1.5rem; border: 1px solid var(--border-color);">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span style="color: var(--text-muted);">Quantity</span>
                        <strong id="pay-qty">1</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span style="color: var(--text-muted);">Price per Unit</span>
                        <strong id="pay-price">₹0</strong>
                    </div>
                    <div style="border-top: 1px dashed var(--border-color); margin: 0.75rem 0;"></div>
                    <div style="display: flex; justify-content: space-between; font-size: 1.1rem;">
                        <span style="font-weight: 700;">Total Amount</span>
                        <strong id="pay-total" style="color: var(--primary);">₹0</strong>
                    </div>
                </div>

                <p style="font-weight: 700; margin-bottom: 1rem;">Select Payment Method:</p>
                <div style="display: grid; gap: 1rem; grid-template-columns: 1fr 1fr;">
                    <button id="btn-pay-online" class="btn btn-primary" style="justify-content: center; flex-direction: column; gap: 0.5rem; padding: 1.5rem;">
                        <i class='bx bx-credit-card' style="font-size: 1.5rem;"></i>
                        <span>Online Pay</span>
                    </button>
                    <button id="btn-pay-cash" class="btn btn-outline" style="justify-content: center; flex-direction: column; gap: 0.5rem; padding: 1.5rem; border-color: var(--border-color);">
                        <i class='bx bx-money' style="font-size: 1.5rem;"></i>
                        <span>Cash / COD</span>
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button onclick="document.getElementById('payment-modal').style.display='none'" class="btn btn-outline">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Return Confirmation Modal -->
    <div id="return-confirm-modal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h2 style="font-weight: 800; font-size: 1.25rem;">Confirm Book Return</h2>
                <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.25rem;">The borrower has returned the book. Do you want to restock it now?</p>
            </div>
            <div class="modal-body">
                <div style="background: #f0fdf4; border: 1px solid #86efac; padding: 1.25rem; border-radius: 12px; margin-bottom: 1rem;">
                    <p style="font-size: 0.85rem; margin: 0; color: #16a34a;">
                        <strong>Restocking</strong> will add the book back to your active listings. 
                        Choose <strong>Mark Only</strong> if you wish to inspect the book first.
                    </p>
                </div>

                <?php if ($user['role'] === 'library'): ?>
                    <div class="form-group" style="margin-top: 1.5rem;">
                        <label class="form-label" style="color: #ef4444; display: flex; align-items: center; gap: 0.5rem;">
                            <i class='bx bx-error-alt'></i> Damage Fine (Optional)
                        </label>
                        <div style="position: relative;">
                            <span style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); font-weight: 700; color: var(--text-muted);">₹</span>
                            <input type="number" id="damage-fine-amt" class="form-control" placeholder="0.00" min="0" step="1" style="padding-left: 2rem; border-radius: 12px; border: 2px solid #fee2e2;">
                        </div>
                        <p style="font-size: 0.75rem; color: #ef4444; margin-top: 0.5rem;">Enter an amount only if the book is damaged. This will be added to user's dues.</p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer" style="display: flex; flex-direction: column; gap: 0.75rem;">
                <button id="btn-confirm-return" onclick="processReturn(true, this)" class="btn btn-primary" style="width: 100%;">
                    <i class='bx bx-package'></i> Yes, Restock & Confirm
                </button>
                <button id="btn-mark-only" onclick="processReturn(false, this)" class="btn btn-outline" style="width: 100%;">
                    <i class='bx bx-check'></i> Mark as Returned Only
                </button>
                <button onclick="closeReturnModal()" class="btn btn-sm" style="border: none; background: transparent; color: var(--text-muted);">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Post-Return Damage Fine Modal -->
    <div id="post-return-fine-modal" class="modal-overlay">
        <div class="modal-card" style="max-width: 450px;">
            <div class="modal-header" style="background: #fff1f2; border-bottom: 2px solid #fecdd3;">
                <div style="background: #fee2e2; color: #e11d48; width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 1rem;">
                    <i class='bx bx-error-alt'></i>
                </div>
                <h2 style="font-weight: 800; font-size: 1.25rem;">Apply Damage Fine</h2>
                <p id="fine-book-title" style="color: #be123c; font-size: 0.9rem; font-weight: 600; margin-top: 0.25rem;"></p>
            </div>
            <div class="modal-body" style="padding: 1.5rem;">
                <input type="hidden" id="fine-tx-id">
                <div class="form-group">
                    <label class="form-label" style="font-weight: 700;">Fine Amount (₹)</label>
                    <div style="position: relative;">
                        <span style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); font-weight: 700; color: #64748b;">₹</span>
                        <input type="number" id="post-fine-amt" class="form-control" placeholder="0.00" min="1" step="1" style="padding-left: 2rem; border-radius: 12px; border: 2px solid #fb7185;">
                    </div>
                </div>
                <div class="form-group" style="margin-top: 1rem;">
                    <label class="form-label" style="font-weight: 700;">Damage Description / Reason</label>
                    <textarea id="post-fine-reason" class="form-control" rows="3" placeholder="Describe the damage (e.g., torn pages, water damage)..." style="border-radius: 12px; border: 2px solid #e2e8f0; resize: none;"></textarea>
                </div>
                <div class="form-group" style="margin-top: 1rem; background: #fff1f2; padding: 1rem; border-radius: 12px; border: 1px solid #fecdd3;">
                    <p style="font-size: 0.85rem; color: #be123c; margin: 0; font-weight: 600;">
                        <i class='bx bx-info-circle'></i> Fines can be settled in the Library Fines dashboard after receipt of cash.
                    </p>
                </div>
                <div style="background: #fffbeb; border: 1px solid #fde68a; padding: 1rem; border-radius: 12px; margin-top: 1rem;">
                    <p style="font-size: 0.8rem; color: #92400e; margin: 0;">
                        <strong>Note:</strong> This fine will be added to the borrower's pending dues immediately. Please ensure the amount is fair according to the book's value.
                    </p>
                </div>
            </div>
            <div class="modal-footer" style="padding: 1rem 1.5rem 1.5rem; border-top: none;">
                <button id="btn-apply-fine" onclick="submitPostReturnFine(this)" class="btn btn-primary" style="width: 100%; background: #e11d48; border-color: #e11d48;">Apply Fine</button>
                <button onclick="closePostReturnFineModal()" class="btn btn-outline" style="width: 100%; margin-top: 0.5rem; border-color: #e2e8f0;">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tab, el) {
            document.querySelectorAll('.tab-btn').forEach(t => t.classList.remove('active'));
            el.classList.add('active');
            
            document.getElementById('all-list').style.display = tab === 'all' ? 'block' : 'none';
            document.getElementById('incoming-list').style.display = tab === 'incoming' ? 'block' : 'none';
            document.getElementById('outgoing-list').style.display = tab === 'outgoing' ? 'block' : 'none';
            document.getElementById('listings-list').style.display = tab === 'listings' ? 'block' : 'none';
            document.getElementById('returns-list').style.display = tab === 'returns' ? 'block' : 'none';
            document.getElementById('payments-list').style.display = tab === 'payments' ? 'block' : 'none';
        }


        async function handleDeal(transactionId, action, status = '') {
            if (action === 'cancel_order') {
                const msg = status === 'requested' 
                    ? "Are you sure you want to cancel this request? (Cancellation is FREE before owner approval)"
                    : "Are you sure you want to cancel this order? (A 5-credit penalty will be deducted)";
                const confirmed = await Popup.confirm('Cancel Order', msg, { confirmText: 'Yes, Cancel', confirmStyle: 'danger' });
                if (!confirmed) return;
            }
            try {
                const formData = new FormData();
                formData.append('action', action);
                formData.append('transaction_id', transactionId);

                const response = await fetch('../actions/request_action.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    showToast(result.message, 'success');
                    
                    // Refresh the deal card
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showToast(result.message || 'Action failed', 'error');
                }
            } catch (error) {
                showToast('Network error. Please try again.', 'error');
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
                showToast('Please select a new due date', 'warning');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'request_extension');
            formData.append('transaction_id', txId);
            formData.append('new_date', newDate);
            formData.append('reason', reason);

            try {
                const response = await fetch('../actions/request_action.php', { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) {
                    showToast('Extension request sent to owner!', 'success');
                    closeExtendModal();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(result.message || 'Failed to send request', 'error');
                }
            } catch (e) {
                showToast('Error sending request', 'error');
            }
        }

        function editListing(listingId) {
            window.location.href = `add_listing.php?edit=${listingId}`;
        }

        async function deleteListing(listingId) {
            const card = document.getElementById(`listing-${listingId}`);
            card.style.opacity = '0.5';
            
            const confirmed = await Popup.confirm('Delete Listing', 'Are you sure you want to delete this listing?', { confirmText: 'Yes, Delete', confirmStyle: 'danger' });
            
            if (confirmed) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'delete_listing');
                    formData.append('listing_id', listingId);

                    const response = await fetch('../actions/listing_action.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        card.style.display = 'none';
                        showToast('Listing deleted successfully', 'success');
                    } else {
                        card.style.opacity = '1';
                        showToast(result.message || 'Delete failed', 'error');
                    }
                } catch (error) {
                    card.style.opacity = '1';
                    showToast('Network error. Please try again.', 'error');
                }
            } else {
                card.style.opacity = '1';
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
                    showToast(result.message, 'error');
                }
            } catch (err) {
                showToast('Network error. Please try again.', 'error');
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

        async function confirmRestock() {
            try {
                const formData = new FormData();
                formData.append('action', 'confirm_receive');
                formData.append('transaction_id', currentRestockTxId);
                formData.append('restock', '1');

                const response = await fetch('../actions/request_action.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    closeRestockModal();
                    showToast(result.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(result.message, 'error');
                }
            } catch (error) {
                showToast('Network error. Please try again.', 'error');
            }
        }

        /* Return Confirmation Functions */
        let currentReturnTxId = 0;

        function openReturnModal(txId) {
            currentReturnTxId = txId;
            document.getElementById('return-confirm-modal').style.display = 'flex';
        }

        function closeReturnModal() {
            document.getElementById('return-confirm-modal').style.display = 'none';
            currentReturnTxId = 0;
        }

        async function processReturn(shouldRestock, btnElement) {
            try {
                // Single-click protection
                if (btnElement) {
                    btnElement.disabled = true;
                    btnElement.innerHTML = "<i class='bx bx-loader-alt bx-spin'></i> Processing...";
                }

                const formData = new FormData();
                formData.append('action', 'mark_returned');
                formData.append('transaction_id', currentReturnTxId);
                if (shouldRestock) {
                    formData.append('restock', '1');
                }

                const fineAmt = document.getElementById('damage-fine-amt').value;
                if (fineAmt > 0) {
                    formData.append('damage_fine', fineAmt);
                }

                const response = await fetch('../actions/request_action.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    closeReturnModal();
                    showToast(result.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(result.message || 'Action failed', 'error');
                }
            } catch (error) {
                showToast('Network error. Please try again.', 'error');
            }
        }

        function payForBulk(transactionId, listingId, quantity, pricePerUnit) {
            // Open Selection Modal
            document.getElementById('payment-modal').style.display = 'flex';
            
            // Calc Total
            const total = quantity * pricePerUnit;
            
            document.getElementById('pay-qty').innerText = quantity;
            document.getElementById('pay-price').innerText = '₹' + pricePerUnit;
            document.getElementById('pay-total').innerText = '₹' + total;

            // Bind Actions
            document.getElementById('btn-pay-online').onclick = function() {
                initiateOnlinePayment(transactionId, listingId, quantity);
            };

            document.getElementById('btn-pay-cash').onclick = function() {
                processCashPayment(transactionId, listingId, quantity);
            };
        }

        function initiateOnlinePayment(transactionId, listingId, quantity) {
            const formData = new FormData();
            formData.append('action', 'create_order');
            formData.append('listing_id', listingId);
            formData.append('quantity', quantity);
            formData.append('transaction_id', transactionId);
            formData.append('delivery', '0'); 
            
            fetch('../actions/payment_action.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const options = {
                        "key": data.key_id,
                        "amount": data.amount,
                        "currency": "INR",
                        "name": "BOOK-B",
                        "description": "Bulk Purchase Payment",
                        "order_id": data.order_id,
                        "handler": function (response){
                            verifyBulkPayment(response, transactionId, listingId, quantity);
                        },
                        "prefill": {
                            "name": data.name,
                            "email": data.email
                        },
                        "theme": { "color": "#4F46E5" }
                    };
                    const rzp1 = new Razorpay(options);
                    rzp1.open();
                    document.getElementById('payment-modal').style.display = 'none';
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('Payment initiation failed', 'error');
            });
        }

        async function processCashPayment(transactionId, listingId, quantity) {
             const confirmed = await Popup.confirm('Confirm Cash Payment', 'Confirm paying with Cash? This will mark the order as confirmed and deduct stock.', { confirmText: 'Confirm' });
             if (!confirmed) return;

             const formData = new FormData();
             formData.append('action', 'confirm_cod_payment');
             formData.append('transaction_id', transactionId);
             formData.append('listing_id', listingId);
             formData.append('quantity', quantity);

             fetch('../actions/request_action.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('Order Confirmed! Please pay cash upon receipt.', 'success');
                    document.getElementById('payment-modal').style.display = 'none';
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('Failed to process cash request', 'error');
            });
        }

        function verifyBulkPayment(response, transactionId, listingId, quantity) {
            const formData = new FormData();
            formData.append('action', 'verify_payment');
            formData.append('razorpay_payment_id', response.razorpay_payment_id);
            formData.append('razorpay_order_id', response.razorpay_order_id);
            formData.append('razorpay_signature', response.razorpay_signature);
            formData.append('listing_id', listingId);
            formData.append('owner_id', 0); // Not needed for update probably, or should fetch
            formData.append('quantity', quantity);
            formData.append('transaction_id', transactionId); // Key to update existing
            
            // Mock order info for update (existing tx has address)
            const orderInfo = {
                delivery: 0, address: '', landmark: '', lat: '', lng: ''
            };
            formData.append('order_info', JSON.stringify(orderInfo));

            fetch('../actions/payment_action.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('Payment Successful!', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('Payment verification failed', 'error');
            });
        }

        // Handle deep linking to tabs
        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab');
            if (tab) {
                const tabBtn = document.querySelector(`button[onclick*="'${tab}'"]`);
                if (tabBtn) {
                    tabBtn.click();
                }
            }
        });

        /* User Report Functions */
        let currentReportedUserId = 0;

        function openUserReportModal(userId, name) {
            currentReportedUserId = userId;
            document.getElementById('report-user-name').textContent = `Reporting: ${name}`;
            document.getElementById('report-reason').value = '';
            document.getElementById('report-description').value = '';
            document.getElementById('user-report-modal').style.display = 'flex';
        }

        function closeUserReportModal() {
            document.getElementById('user-report-modal').style.display = 'none';
        }

        async function submitUserReport() {
            const reason = document.getElementById('report-reason').value;
            const description = document.getElementById('report-description').value;

            if (!reason) {
                showToast('Please select a reason', 'error');
                return;
            }
            if (!description.trim()) {
                showToast('Please provide a description', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'submit_report');
            formData.append('reported_id', currentReportedUserId);
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
                    showToast('Report submitted. Our team will investigate.', 'success');
                    closeUserReportModal();
                } else {
                    showToast(result.message || 'Failed to submit report', 'error');
                }
            } catch (error) {
                showToast('Connection error', 'error');
            }
        }
        let postFineTxId = 0;
        function openPostReturnFineModal(txId, title) {
            postFineTxId = txId;
            document.getElementById('fine-book-title').innerText = title;
            document.getElementById('post-fine-amt').value = '';
            document.getElementById('post-fine-reason').value = '';
            
            document.getElementById('post-return-fine-modal').style.display = 'flex';
        }


        function closePostReturnFineModal() {
            document.getElementById('post-return-fine-modal').style.display = 'none';
        }

        async function submitPostReturnFine(btnElement) {
            const amt = document.getElementById('post-fine-amt').value;
            const reason = document.getElementById('post-fine-reason').value;

            if (!amt || amt <= 0) {
                showToast('Please enter a valid fine amount.', 'warning', 4000);
                return;
            }
            if (!reason) {
                showToast('Please provide a reason for the damage fine.', 'warning', 4000);
                return;
            }

            try {
                // Disable button
                if (btnElement) {
                    btnElement.disabled = true;
                    btnElement.innerHTML = "<i class='bx bx-loader-alt bx-spin'></i> Applying...";
                }

                const formData = new FormData();
                formData.append('action', 'apply_damage_fine_post');
                formData.append('transaction_id', postFineTxId);
                formData.append('amount', amt);
                formData.append('reason', reason);

                const response = await fetch('../actions/request_action.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    showToast(result.message, 'success');
                    closePostReturnFineModal();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(result.message || 'Failed to apply fine.', 'error', 5000);
                    if (btnElement) {
                        btnElement.disabled = false;
                        btnElement.innerHTML = "Apply Fine";
                    }
                }
            } catch (err) {
                showToast('An error occurred. Please try again.', 'error', 4000);
                if (btnElement) {
                    btnElement.disabled = false;
                    btnElement.innerHTML = "Apply Fine";
                }
            }
        }

        async function markSalePaid(txId, btn) {
            const confirmed = await Popup.confirm('Confirm Payment', 'Confirm that you have received the cash payment for this sale?', { confirmText: 'Yes, Received' });
            if (!confirmed) return;

            try {
                btn.disabled = true;
                btn.innerHTML = "<i class='bx bx-loader-alt bx-spin'></i> Updating...";

                const formData = new FormData();
                formData.append('action', 'mark_transaction_paid_offline');
                formData.append('transaction_id', txId);

                const response = await fetch('../actions/request_action.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    showToast(result.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(result.message || 'Failed to update.', 'error', 5000);
                    btn.disabled = false;
                    btn.innerHTML = "<i class='bx bx-check-double'></i> Mark Paid (Cash)";
                }
            } catch (err) {
                console.error(err);
                btn.disabled = false;
                btn.innerHTML = "<i class='bx bx-check-double'></i> Mark Paid (Cash)";
            }
        }
    </script>
</body>
</html>
