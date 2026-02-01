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
markNotificationsAsReadByType($userId, ['borrow_request', 'sell_request', 'exchange_request', 'request_accepted', 'request_declined']);

$deals = getUserDeals($userId);
$incoming = array_filter($deals, fn($d) => $d['lender_id'] == $userId);
$outgoing = array_filter($deals, fn($d) => $d['borrower_id'] == $userId);

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Deals | BOOK-B</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
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
            background: #f1f5f9;
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
            background: white;
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
            background: white; 
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
                    <button class="tab-btn active" onclick="switchTab('incoming', this)">
                        Incoming Offers <span class="tab-count"><?php echo count($incoming); ?></span>
                    </button>
                    <button class="tab-btn" onclick="switchTab('outgoing', this)">
                        My Requests <span class="tab-count"><?php echo count($outgoing); ?></span>
                    </button>
                    <button class="tab-btn" onclick="switchTab('listings', this)">
                        My Listings <span class="tab-count"><?php echo count($myListings); ?></span>
                    </button>
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
                        <div class="deal-card" id="deal-<?php echo $deal['id']; ?>">
                            <div class="deal-visual">
                                <img src="<?php echo $deal['cover_image'] ?: 'https://images.unsplash.com/photo-1543004218-ee141104975a?w=200'; ?>" class="deal-img">
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
                                </div>
                            </div>
                            <div class="deal-actions">
                                <span class="status-pill pill-<?php echo $deal['status']; ?>"><?php echo $deal['status']; ?></span>
                                <?php if ($deal['status'] === 'requested'): ?>
                                    <div class="btn-group">
                                        <button onclick="handleDeal(<?php echo $deal['id']; ?>, 'accept_request')" class="btn btn-primary btn-sm">Accept</button>
                                        <button onclick="handleDeal(<?php echo $deal['id']; ?>, 'decline_request')" class="btn btn-sm" style="background: #fee2e2; color: #dc2626; border: none;">Decline</button>
                                    </div>
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
                                        <button onclick="handleDeal(<?php echo $deal['id']; ?>, 'mark_returned')" class="btn btn-primary btn-sm">
                                            <i class='bx bx-check-circle'></i> Mark Returned
                                        </button>
                                    <?php endif; ?>
                                <?php elseif ($deal['status'] === 'approved' || $deal['status'] === 'active'): ?>
                                    <button onclick="handleDeal(<?php echo $deal['id']; ?>, 'mark_returned')" class="btn btn-primary btn-sm">
                                        <i class='bx bx-check-circle'></i> Mark Returned
                                    </button>

                                <?php elseif ($deal['status'] === 'delivered' || $deal['status'] === 'returned'): ?>
                                    <div class="btn-group">
                                        <?php if ($deal['status'] === 'returned' && empty($deal['is_restocked'])): ?>
                                            <button onclick="showRestockModal(<?php echo $deal['id']; ?>)" class="btn btn-primary btn-sm">
                                                <i class='bx bx-package'></i> Restock
                                            </button>
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
                        <div class="deal-card">
                            <div class="deal-visual">
                                <img src="<?php echo $deal['cover_image'] ?: 'https://images.unsplash.com/photo-1543004218-ee141104975a?w=200'; ?>" class="deal-img">
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
                                    <?php if ($deal['due_date']): ?>
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
                                <span class="status-pill pill-<?php echo $deal['status']; ?>"><?php echo $deal['status']; ?></span>
                                <div class="btn-group">
                                    <?php if ($deal['lender_id'] != $userId): ?>
                                     <a href="<?php echo APP_URL; ?>/chat/index.php?user=<?php echo $deal['lender_id']; ?>" class="btn btn-outline btn-sm">
                                         <i class='bx bx-message-square-dots'></i> Chat
                                     </a>
                                    <?php endif; ?>
                                    <?php if ($deal['status'] === 'delivered' || $deal['status'] === 'returned'): ?>
                                        <button onclick="openFeedbackModal(<?php echo $deal['id']; ?>, <?php echo $deal['lender_id']; ?>, '<?php echo addslashes($deal['lender_name']); ?>')" class="btn btn-primary btn-sm">
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
                                <img src="<?php echo $listing['cover_image'] ?: 'https://images.unsplash.com/photo-1543004218-ee141104975a?w=200'; ?>" class="deal-img">
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
            </div>
        </main>
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

    <script>
        function switchTab(tab, el) {
            document.querySelectorAll('.tab-btn').forEach(t => t.classList.remove('active'));
            el.classList.add('active');
            
            document.getElementById('incoming-list').style.display = tab === 'incoming' ? 'block' : 'none';
            document.getElementById('outgoing-list').style.display = tab === 'outgoing' ? 'block' : 'none';
            document.getElementById('listings-list').style.display = tab === 'listings' ? 'block' : 'none';
        }

        function showMessage(message, type) {
            const msgEl = document.getElementById('statusMessage');
            msgEl.textContent = message;
            msgEl.className = type;
            msgEl.style.display = 'block';
            
            setTimeout(() => {
                msgEl.style.display = 'none';
            }, 3000);
        }

        async function handleDeal(transactionId, action) {
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
                    showMessage(result.message, 'success');
                    
                    // Refresh the deal card
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showMessage(result.message || 'Action failed', 'error');
                }
            } catch (error) {
                showMessage('Network error. Please try again.', 'error');
            }
        }

        function editListing(listingId) {
            window.location.href = `add_listing.php?edit=${listingId}`;
        }

        async function deleteListing(listingId) {
            const card = document.getElementById(`listing-${listingId}`);
            card.style.opacity = '0.5';
            
            const confirmed = confirm('Are you sure you want to delete this listing?');
            
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
                        showMessage('Listing deleted successfully', 'success');
                    } else {
                        card.style.opacity = '1';
                        showMessage(result.message || 'Delete failed', 'error');
                    }
                } catch (error) {
                    card.style.opacity = '1';
                    showMessage('Network error. Please try again.', 'error');
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
                    showMessage(result.message, 'success');
                    closeFeedbackModal();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showMessage(result.message, 'error');
                }
            } catch (err) {
                showMessage('Network error. Please try again.', 'error');
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
                formData.append('action', 'confirm_receipt');
                formData.append('transaction_id', currentRestockTxId);
                formData.append('restock', '1');

                const response = await fetch('../actions/request_action.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    closeRestockModal();
                    showMessage(result.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showMessage(result.message, 'error');
                }
            } catch (error) {
                showMessage('Network error. Please try again.', 'error');
            }
        }
    </script>
</body>
</html>
