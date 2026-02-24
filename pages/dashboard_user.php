<?php 
require_once '../includes/db_helper.php';
require_once '../paths.php';
include '../includes/dashboard_header.php'; 

// Fetch enhanced stats with credits and trust
$userId = $_SESSION['user_id'] ?? 0;
$stats = getUserStatsEnhanced($userId);
$books = getAllBooks(4);
$trustRating = $stats['trust_rating'] ?? getTrustScoreRating(50);
$hasMinTokens = hasMinimumTokens($userId);

// Fetch latest reviews for the modal
$userReviews = getUserReviews($userId, 5); 


?>

<div class="dashboard-wrapper">
    <?php include '../includes/dashboard_sidebar.php'; ?>

    <main class="main-content">
        <?php include '../includes/due_date_reminder.php'; ?>
        <?php include '../includes/announcements_component.php'; ?>

        <div class="section-header">
            <div>
                <h1>Welcome back, <strong><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></strong>! 👋</h1>
                <p>Here's your reading journey and community impact today.</p>
            </div>
            <div style="display: flex; gap: 1rem; align-items: center;">
                <?php if (!$hasMinTokens): ?>
                    <div style="background: #fef2f2; border: 1px solid #fee2e2; color: #991b1b; padding: 0.5rem 1rem; border-radius: 8px; font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class='bx bx-error-circle'></i>
                        Maintenance Required: Min. <?php echo MIN_TOKEN_LIMIT; ?> credits needed to list/borrow.
                    </div>
                <?php endif; ?>
                <a href="add_listing.php" class="btn btn-primary <?php echo !$hasMinTokens ? 'disabled' : ''; ?>" <?php echo !$hasMinTokens ? 'style="opacity: 0.6; pointer-events: none;"' : ''; ?>>
                    <i class='bx bx-plus-circle'></i> List a Book
                </a>
            </div>
        </div>

        <!-- Enhanced Widgets Grid -->
        <div class="widgets-grid" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
            <!-- Credits Widget -->
            <div class="widget-card gradient-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none;">
                <div class="widget-title" style="justify-content: center; color: rgba(255,255,255,0.9);">
                    <span><i class='bx bx-wallet'></i> Token Balance</span>
                </div>
                <div style="font-size: 3rem; font-weight: 900; text-align: center; margin: 1rem 0;">
                    <?php echo $stats['credits'] ?? 100; ?>
                </div>
                <div style="text-align: center; opacity: 0.9; font-size: 0.85rem;">
                    Minimum required: <?php echo MIN_TOKEN_LIMIT; ?>
                </div>
                <a href="credit_history.php" style="display: block; text-align: center; margin-top: 1rem; color: white; text-decoration: underline; font-size: 0.85rem;">
                    View History →
                </a>
            </div>

            <!-- Trust Score Widget -->
            <div class="widget-card" style="background: linear-gradient(135deg, <?php echo $trustRating['color']; ?>15 0%, <?php echo $trustRating['color']; ?>05 100%); border: 2px solid <?php echo $trustRating['color']; ?>;">
                <div class="widget-title" style="justify-content: center;">
                    <span><i class='bx bx-shield-alt-2'></i> Trust Score</span>
                </div>
                <div style="text-align: center; margin: 1rem 0;">
                    <div style="font-size: 2.5rem; font-weight: 900; color: <?php echo $trustRating['color']; ?>;">
                        <?php echo $stats['trust_score'] ?? 50; ?>/100
                    </div>
                    <div style="margin-top: 0.5rem; padding: 0.4rem 1.2rem; background: <?php echo $trustRating['color']; ?>; color: white; border-radius: 20px; display: inline-block; font-weight: 700; font-size: 0.85rem;">
                        <?php echo $trustRating['label']; ?>
                    </div>
                </div>
                <div style="text-align: center; color: var(--text-muted); font-size: 0.8rem;">
                    Built on reliability
                </div>
            </div>

            <!-- Rating Widget -->
            <div class="widget-card" style="text-align: center; background: linear-gradient(135deg, #fbbf2415 0%, #fbbf2405 100%); border: 2px solid #fbbf24; cursor: pointer;" onclick="openReviewsModal()">
                <div class="widget-title" style="justify-content: center;">
                    <span><i class='bx bxs-star'></i> Your Rating</span>
                </div>
                <div style="margin: 1rem 0;">
                    <div style="font-size: 2.5rem; font-weight: 900; color: #fbbf24;">
                        <?php 
                        $avgRating = $stats['average_rating'] ?? 0;
                        echo $avgRating > 0 ? number_format($avgRating, 1) : '—'; 
                        ?>
                    </div>
                    <div style="color: #fbbf24; font-size: 1.2rem; margin-top: 0.5rem;">
                        <?php for($i = 1; $i <= 5; $i++): ?>
                            <i class='bx <?php echo $i <= round($avgRating) ? "bxs-star" : "bx-star"; ?>'></i>
                        <?php endfor; ?>
                    </div>
                </div>
                <div style="color: var(--text-muted); font-size: 0.8rem;">
                    <?php echo $stats['total_ratings'] ?? 0; ?> reviews
                </div>
            </div>

            <!-- My Listings -->
            <div class="widget-card" style="text-align: center; cursor: pointer; transition: all 0.3s;" onclick="window.location.href='deals.php?tab=listings'">
                <div class="widget-title" style="justify-content: center;">
                    <span><i class='bx bx-book-bookmark'></i> My Listings</span>
                </div>
                <div style="font-size: 2.5rem; font-weight: 800; color: var(--primary); margin: 1rem 0;">
                    <?php echo $stats['total_listings'] ?? 0; ?>
                </div>
                <div style="color: var(--text-muted); font-size: 0.8rem;">Books shared</div>
            </div>

            <!-- Active Borrows -->
            <div class="widget-card" style="text-align: center; cursor: pointer; transition: all 0.3s;" onclick="window.location.href='deals.php'">
                <div class="widget-title" style="justify-content: center;">
                    <span><i class='bx bx-book-reader'></i> Active Borrows</span>
                </div>
                <div style="font-size: 2.5rem; font-weight: 800; color: var(--success); margin: 1rem 0;">
                    <?php echo $stats['active_borrows'] ?? 0; ?>
                </div>
                <div style="color: var(--text-muted); font-size: 0.8rem;">Currently reading</div>
            </div>

            <!-- Pending Requests -->
            <div class="widget-card" style="text-align: center; cursor: pointer; transition: all 0.3s;" onclick="window.location.href='deals.php?filter=pending'">
                <div class="widget-title" style="justify-content: center;">
                    <span><i class='bx bx-time-five'></i> Pending Requests</span>
                </div>
                <div style="font-size: 2.5rem; font-weight: 800; color: #f59e0b; margin: 1rem 0;">
                    <?php echo $stats['pending_requests'] ?? 0; ?>
                </div>
                <div style="color: var(--text-muted); font-size: 0.8rem;">Awaiting response</div>
            </div>

            <!-- Pending Dues Widget -->
            <div class="widget-card" style="text-align: center; background: linear-gradient(135deg, #ef444415 0%, #ef444405 100%); border: 2px solid #ef4444; cursor: pointer;" onclick="openPaymentModal(<?php echo $stats['unpaid_fines'] ?? 0; ?>)">
                <div class="widget-title" style="justify-content: center; color: #ef4444;">
                    <span><i class='bx bx-money'></i> Pending Dues</span>
                </div>
                <div style="margin: 1rem 0;">
                    <div style="font-size: 2.5rem; font-weight: 900; color: #ef4444;">
                        ₹<?php echo number_format($stats['unpaid_fines'] ?? 0, 2); ?>
                    </div>
                </div>
                <div style="color: #ef4444; font-size: 0.8rem; font-weight: 700;">
                    <?php echo ($stats['unpaid_fines'] ?? 0) > 0 ? "Pay Now to avoid account suspension" : "No outstanding fines"; ?>
                </div>
            </div>
        </div>



        <!-- Your Interests Books Section -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem;">
                <i class='bx bx-heart' style="color: #ef4444;"></i>
                Your Interests Books
            </h2>
            <a href="explore.php" class="btn btn-outline" style="font-weight: 600;">
                View All <i class='bx bx-right-arrow-alt'></i>
            </a>
        </div>

        <?php 
        $favoriteCategory = $user['favorite_category'] ?? '';
        $categoryList = !empty($favoriteCategory) ? array_map('trim', explode(',', $favoriteCategory)) : [];
        $categoryFilter = !empty($categoryList) ? [
            'category' => $categoryList,
            'exclude_user_id' => $userId
        ] : [];
        $listings = !empty($categoryFilter) ? searchListingsAdvanced($categoryFilter, 4) : []; 
        ?>
        <div class="book-grid">
            <?php if (empty($favoriteCategory)): ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 2rem; background: var(--bg-card); border-radius: var(--radius-lg); border: 1px dashed var(--border-color);">
                    <i class='bx bx-heart' style="font-size: 3rem; color: #cbd5e1; margin-bottom: 0.5rem;"></i>
                    <p style="color: var(--text-muted); margin-bottom: 1rem;">Set your interests to see personalized recommendations!</p>
                    <a href="profile.php" class="btn btn-primary btn-sm">Set Interests</a>
                </div>
            <?php elseif (empty($listings)): ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 2rem; background: var(--bg-card); border-radius: var(--radius-lg); border: 1px dashed var(--border-color);">
                    <i class='bx bx-search' style="font-size: 3rem; color: #cbd5e1; margin-bottom: 0.5rem;"></i>
                    <p style="color: var(--text-muted);">No books matching your specific interests right now.</p>
                </div>
            <?php endif; ?>
            <?php foreach ($listings as $item): 
                $isRare = $item['is_rare'] ?? 0;
            ?>
            <div class="book-card <?php echo $isRare ? 'rare-card' : ''; ?>" style="transition: all 0.3s; cursor: pointer;" onclick="window.location.href='book_details.php?id=<?php echo $item['id']; ?>'">
                <div class="book-cover">
                    <?php if ($isRare): ?>
                        <span class="rare-badge">RARE</span>
                    <?php endif; ?>
                    <span style="position: absolute; top: 10px; right: 10px; background: var(--bg-card); color: var(--text-main); padding: 4px 10px; border-radius:12px; font-size:0.7rem; font-weight:700; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border: 1px solid var(--border-color);">Available</span>
                    <?php 
                        $cover = $item['cover_image'];
                        $fallback = 'https://images.unsplash.com/photo-1543004218-ee141104975a?auto=format&fit=crop&q=80&w=800';
                        $cover = $cover ?: $fallback;
                    ?>
                    <img src="<?php echo htmlspecialchars(html_entity_decode($cover), ENT_QUOTES, 'UTF-8'); ?>" 
                         alt="<?php echo htmlspecialchars($item['title']); ?>"
                         onerror="this.onerror=null; this.src='<?php echo $fallback; ?>';">
                </div>
                <div class="book-info">
                    <div class="book-title"><?php echo htmlspecialchars($item['title']); ?></div>
                    <div class="book-author"><?php echo htmlspecialchars($item['author']); ?></div>
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid var(--border-color);">
                        <span style="color: var(--primary); font-weight: 700; font-size: 0.9rem;">
                            <?php if ($item['listing_type'] === 'sell'): ?>
                                ₹<?php echo number_format($item['price'], 2); ?>
                            <?php else: ?>
                                <i class='bx bx-wallet'></i> <?php echo $item['credit_cost'] ?: 10; ?> credits
                            <?php endif; ?>
                        </span>
                        <button class="btn btn-primary btn-sm" style="padding: 0.4rem 1rem;" onclick="event.stopPropagation(); window.location.href='book_details.php?id=<?php echo $item['id']; ?>'">
                            <?php echo $item['listing_type'] === 'sell' ? 'Buy' : 'Borrow'; ?>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Community Books Section -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; margin-top: 3rem;">
            <h2 style="font-size: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem;">
                <i class='bx bx-group' style="color: var(--primary);"></i>
                Community Books
            </h2>
            <a href="community.php" class="btn btn-outline" style="font-weight: 600;">
                View Communities <i class='bx bx-right-arrow-alt'></i>
            </a>
        </div>

        <?php 
        $communityBooks = getUserCommunityBooks($userId, 4);
        ?>
        <div class="book-grid">
            <?php if (empty($communityBooks)): ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 2rem; background: var(--bg-card); border-radius: var(--radius-lg); border: 1px dashed var(--border-color);">
                    <i class='bx bx-group' style="font-size: 3rem; color: #cbd5e1; margin-bottom: 0.5rem;"></i>
                    <p style="color: var(--text-muted); margin-bottom: 1rem;">No books from your communities yet.</p>
                    <a href="community.php" class="btn btn-primary btn-sm">Join Communities</a>
                </div>
            <?php endif; ?>
            <?php foreach ($communityBooks as $item): 
                $isRare = $item['is_rare'] ?? 0;
            ?>
            <div class="book-card <?php echo $isRare ? 'rare-card' : ''; ?>" style="transition: all 0.3s; cursor: pointer;" onclick="window.location.href='book_details.php?id=<?php echo $item['id']; ?>'">
                <div class="book-cover">
                    <?php if ($isRare): ?>
                        <span class="rare-badge">RARE</span>
                    <?php endif; ?>
                    <span style="position: absolute; top: 10px; right: 10px; background: var(--primary); color: white; padding: 4px 10px; border-radius:12px; font-size:0.7rem; font-weight:700; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"><?php echo htmlspecialchars($item['community_name']); ?></span>
                    <?php 
                        $cover = $item['cover_image'];
                        $fallback = 'https://images.unsplash.com/photo-1543004218-ee141104975a?auto=format&fit=crop&q=80&w=800';
                        $cover = $cover ?: $fallback;
                    ?>
                    <img src="<?php echo htmlspecialchars(html_entity_decode($cover), ENT_QUOTES, 'UTF-8'); ?>" 
                         alt="<?php echo htmlspecialchars($item['title']); ?>"
                         onerror="this.onerror=null; this.src='<?php echo $fallback; ?>';">
                </div>
                <div class="book-info">
                    <div class="book-title"><?php echo htmlspecialchars($item['title']); ?></div>
                    <div class="book-author"><?php echo htmlspecialchars($item['author']); ?></div>
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid var(--border-color);">
                        <span style="color: var(--primary); font-weight: 700; font-size: 0.9rem;">
                            <?php if ($item['listing_type'] === 'sell'): ?>
                                ₹<?php echo number_format($item['price'], 2); ?>
                            <?php else: ?>
                                <i class='bx bx-wallet'></i> <?php echo $item['credit_cost'] ?: 10; ?> credits
                            <?php endif; ?>
                        </span>
                        <button class="btn btn-primary btn-sm" style="padding: 0.4rem 1rem;" onclick="event.stopPropagation(); window.location.href='book_details.php?id=<?php echo $item['id']; ?>'">
                            <?php echo $item['listing_type'] === 'sell' ? 'Buy' : 'View'; ?>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>

    <!-- Reviews Modal -->
    <div id="reviewsModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2 style="font-weight: 800; display: flex; align-items: center; gap: 0.5rem; color: var(--text-main);">
                    <i class='bx bxs-star' style="color: #fbbf24;"></i> Your Reviews
                </h2>
                <button class="modal-close" onclick="closeReviewsModal()">&times;</button>
            </div>
            <div class="modal-body">
                <?php if (empty($userReviews)): ?>
                    <div style="text-align: center; padding: 3rem; color: var(--text-muted);">
                        <i class='bx bx-message-rounded-dots' style="font-size: 3rem; opacity: 0.3;"></i>
                        <p>No reviews received yet.</p>
                    </div>
                <?php else: ?>
                    <div class="reviews-list">
                        <?php foreach ($userReviews as $r): ?>
                            <div class="review-item">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                                    <span style="font-weight: 700; color: var(--text-main);">
                                        <?php echo htmlspecialchars($r['firstname'] . ' ' . $r['lastname']); ?>
                                    </span>
                                    <span style="font-size: 0.75rem; color: var(--text-muted);">
                                        <?php echo date('M d, Y', strtotime($r['created_at'])); ?>
                                    </span>
                                </div>
                                <div style="color: #fbbf24; font-size: 0.85rem; margin-bottom: 0.5rem;">
                                    <?php for($i=1; $i<=5; $i++): ?>
                                        <i class='bx <?php echo $i <= $r['rating'] ? "bxs-star" : "bx-star"; ?>'></i>
                                    <?php endfor; ?>
                                </div>
                                <?php if ($r['comment']): ?>
                                    <p style="font-size: 0.9rem; color: var(--text-body); line-height: 1.5;">
                                        <?php echo nl2br(htmlspecialchars($r['comment'])); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div style="margin-top: 2rem; text-align: center;">
                    <a href="user_profile.php?id=<?php echo $userId; ?>#reviews" class="btn btn-outline btn-sm">View All Reviews on Profile</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Fine Payment Modal -->
    <div id="paymentModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2 style="font-weight: 800; display: flex; align-items: center; gap: 0.5rem; color: #ef4444;">
                    <i class='bx bx-money'></i> Clearance of Pending Dues
                </h2>
                <button class="modal-close" onclick="closePaymentModal()">&times;</button>
            </div>
            <div class="modal-body" style="text-align: center;">
                <div style="font-size: 1.1rem; margin-bottom: 1.5rem; color: var(--text-body);">
                    You have total outstanding fines of:
                    <div style="font-size: 2.5rem; font-weight: 900; color: #ef4444; margin: 0.5rem 0;">
                        ₹<span id="fine-amount-display">0.00</span>
                    </div>
                    <p style="font-size: 0.85rem; color: var(--text-muted);">This fine was applied due to late returns without approved extensions.</p>
                </div>
                
                <button id="pay-fine-btn" class="btn btn-primary" style="width: 100%; padding: 1rem; font-size: 1.1rem; background: #ef4444; border-color: #ef4444;" onclick="startFinePayment()">
                    Pay with Razorpay
                </button>
                
                <p style="margin-top: 1rem; font-size: 0.8rem; color: var(--text-muted);">
                    <i class='bx bx-lock-alt'></i> Secure encrypted payment via Razorpay
                </p>
            </div>
        </div>
    </div>

    <script>
        function openReviewsModal() {
            document.getElementById('reviewsModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeReviewsModal() {
            document.getElementById('reviewsModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        // Close on overlay click
        document.getElementById('reviewsModal').addEventListener('click', function(e) {
            if (e.target === this) closeReviewsModal();
        });

        // Fine Payment Logic
        function openPaymentModal(amount) {
            if (amount <= 0) {
                showToast('You have no pending dues!', 'success');
                return;
            }
            document.getElementById('fine-amount-display').innerText = parseFloat(amount).toFixed(2);
            document.getElementById('paymentModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closePaymentModal() {
            document.getElementById('paymentModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        async function startFinePayment() {
            const btn = document.getElementById('pay-fine-btn');
            btn.disabled = true;
            btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Processing...';

            try {
                const response = await fetch('../actions/payment_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=create_fine_order`
                });

                const data = await response.json();
                if (!data.success) throw new Exception(data.message);

                const options = {
                    key: data.key_id,
                    amount: data.amount,
                    currency: "INR",
                    name: "BOOK-B Platform",
                    description: "Clearance of Late Return Fines",
                    order_id: data.order_id,
                    handler: function (response) {
                        verifyFinePayment(response);
                    },
                    prefill: {
                        name: data.name,
                        email: data.email
                    },
                    theme: { color: "#ef4444" }
                };

                const rzp = new Razorpay(options);
                rzp.open();
            } catch (error) {
                showToast(error.message || 'Payment initiation failed', 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = 'Pay with Razorpay';
            }
        }

        async function verifyFinePayment(payment) {
            try {
                const response = await fetch('../actions/payment_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=verify_fine_payment&razorpay_payment_id=${payment.razorpay_payment_id}&razorpay_order_id=${payment.razorpay_order_id}&razorpay_signature=${payment.razorpay_signature}`
                });

                const data = await response.json();
                if (data.success) {
                    showToast('Fines cleared successfully! Redirecting...', 'success');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showToast(data.message, 'error');
                }
            } catch (error) {
                showToast('Payment verification failed', 'error');
            }
        }
    </script>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
</div>

<style>
.gradient-card {
    position: relative;
    overflow: hidden;
}
.gradient-card::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    pointer-events: none;
}
.widget-card {
    transition: all 0.3s ease;
}
.widget-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}
.book-card {
    border: 2px solid transparent;
}
.book-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15);
    border-color: var(--primary);
}
.book-card.rare-card {
    border-color: #f59e0b;
    background: rgba(245, 158, 11, 0.05);
}
.book-card.rare-card:hover {
    border-color: #d97706;
}
.rare-badge {
    position: absolute;
    top: 10px;
    left: 10px;
    background: #f59e0b;
    color: white;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 700;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    z-index: 10;
}

/* Modal Styles */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.5);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2000;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.modal-overlay.active {
    opacity: 1;
    visibility: visible;
}

.modal-content {
    background: var(--bg-card);
    width: 90%;
    max-width: 500px;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-lg);
    border: 1px solid var(--border-color);
    transform: translateY(20px);
    transition: all 0.3s ease;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
}

.modal-overlay.active .modal-content {
    transform: translateY(0);
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: var(--text-muted);
    cursor: pointer;
    line-height: 1;
}

.modal-body {
    padding: 1.5rem;
    overflow-y: auto;
}

.reviews-list {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.review-item {
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--border-color);
}

.review-item:last-child {
    border-bottom: none;
}
</style>

</body>
</html>

