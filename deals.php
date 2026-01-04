<?php
require_once 'includes/db_helper.php';
require_once 'paths.php';
include 'includes/dashboard_header.php';

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
    header("Location: login.php");
    exit();
}

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
    <link rel="stylesheet" href="assets/css/style.css">
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
    </style>
</head>
<body>
    <div id="statusMessage"></div>
    
    <div class="dashboard-wrapper">
        <?php include 'includes/dashboard_sidebar.php'; ?>

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
                                    <div class="meta-item"><i class='bx bx-user'></i> From: <strong><?php echo htmlspecialchars($deal['borrower_name']); ?></strong></div>
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
                                <?php elseif ($deal['status'] === 'approved' || $deal['status'] === 'active'): ?>
                                    <button onclick="handleDeal(<?php echo $deal['id']; ?>, 'mark_returned')" class="btn btn-primary btn-sm">
                                        <i class='bx bx-check-circle'></i> Mark Returned
                                    </button>
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
                                    <div class="meta-item"><i class='bx bx-store-alt'></i> Owner: <strong><?php echo htmlspecialchars($deal['lender_name']); ?></strong></div>
                                    <div class="meta-item"><i class='bx bx-calendar'></i> <?php echo date('M d, Y', strtotime($deal['created_at'])); ?></div>
                                </div>
                            </div>
                            <div class="deal-actions">
                                <span class="status-pill pill-<?php echo $deal['status']; ?>"><?php echo $deal['status']; ?></span>
                                <a href="chat/index.php?user=<?php echo $deal['lender_id']; ?>" class="btn btn-outline btn-sm">
                                    <i class='bx bx-message-square-dots'></i> Chat
                                </a>
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

                const response = await fetch('request_action.php', {
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

                    const response = await fetch('listing_action.php', {
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
    </script>
</body>
</html>
