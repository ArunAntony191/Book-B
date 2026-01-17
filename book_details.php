<?php
require_once 'includes/db_helper.php';
require_once 'paths.php';
session_start();

$userId = $_SESSION['user_id'] ?? 0;
$listingId = $_GET['id'] ?? 0;

$pdo = getDBConnection();
$stmt = $pdo->prepare("
    SELECT l.*, b.title, b.author, b.description, b.cover_image, b.category, 
           u.firstname, u.lastname, u.role, u.reputation_score, l.quantity
    FROM listings l
    JOIN books b ON l.book_id = b.id
    JOIN users u ON l.user_id = u.id
    WHERE l.id = ?
");
$stmt->execute([$listingId]);
$book = $stmt->fetch();

if (!$book) { header("Location: explore.php"); exit(); }

// Check Wishlist Status
$inWishlist = false;
if ($userId) {
    $wStmt = $pdo->prepare("SELECT id FROM wishlist WHERE user_id = ? AND listing_id = ?");
    $wStmt->execute([$userId, $listingId]);
    if ($wStmt->fetch()) $inWishlist = true;
}

// Check user tokens and requirement
$currentUser = $userId ? getUserById($userId) : null;
$hasMinTokens = $userId ? hasMinimumTokens($userId) : false;
$userTokens = $userId ? getUserCredits($userId) : 0;

// Check if delivery is available for this listing's location
$deliveryAvailable = false;
if ($book['latitude'] && $book['longitude']) {
    $deliveryAvailable = checkDeliveryServiceAvailability(
        $book['latitude'], 
        $book['longitude'], 
        $book['district']
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($book['title']); ?> | BOOK-B</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        .details-grid {
            display: grid;
            grid-template-columns: 350px 1fr 340px;
            gap: 2.5rem;
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }
        .book-img-large {
            width: 100%;
            border-radius: var(--radius-lg);
            box-shadow: 0 10px 30px -5px rgba(0,0,0,0.1);
            object-fit: cover;
            aspect-ratio: 2/3;
        }
        .section-card {
            background: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
        }
        .mini-map {
            height: 200px;
            width: 100%;
            border-radius: var(--radius-md);
            margin-top: 1rem;
            border: 1px solid var(--border-color);
        }
        /* Wishlist Button */
        .wishlist-btn {
            background: white;
            border: 1px solid var(--border-color);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.2s;
        }
        .wishlist-btn:hover { border-color: #ef4444; color: #ef4444; }
        .wishlist-btn.active { background: #fee2e2; border-color: #ef4444; color: #ef4444; }

        /* Modal & Form Styles */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            display: none; align-items: center; justify-content: center; z-index: 1000;
        }
        .modal-card {
            background: white; 
            border-radius: var(--radius-lg); 
            width: 500px;
            box-shadow: var(--shadow-xl);
            max-width: 90vw;
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            padding: 0;
            overflow: hidden;
        }
        .modal-header {
            padding: 2rem 2rem 1rem;
            flex-shrink: 0;
        }
        .modal-body {
            padding: 0 2rem;
            overflow-y: auto;
            flex-grow: 1;
            /* Custom Scrollbar for better UX */
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 transparent;
        }
        .modal-body::-webkit-scrollbar {
            width: 6px;
        }
        .modal-body::-webkit-scrollbar-track {
            background: transparent;
        }
        .modal-body::-webkit-scrollbar-thumb {
            background-color: #cbd5e1;
            border-radius: 20px;
        }
        .modal-footer {
            padding: 1rem 2rem 2rem;
            flex-shrink: 0;
            background: white;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }
        .form-input, .map-search-input {
            width: 100%;
            padding: 0.8rem 1rem;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            font-size: 0.95rem;
            outline: none;
            transition: border-color 0.2s;
            box-sizing: border-box;
            background: #fff;
            color: var(--text-main);
        }
        .form-input:focus, .map-search-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
        }
        
        .map-search-container {
            position: relative;
            margin-bottom: 0.8rem;
        }
        .map-search-input {
            padding-left: 2.5rem;
            padding-right: 6rem;
        }
        .map-search-icon {
            position: absolute;
            left: 0.9rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1.2rem;
            pointer-events: none;
        }
        .locate-btn {
            position: absolute;
            right: 0.5rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--primary);
            cursor: pointer;
            display: flex;
            align-items: center;
            padding: 0.4rem;
            border-radius: 50%;
            transition: background 0.2s;
        }
        .locate-btn:hover { background: #f0f9ff; }
        
        #delivery-map {
            height: 250px;
            width: 100%;
            border-radius: var(--radius-md);
            margin-top: 0.5rem;
            border: 1px solid var(--border-color);
        }
        .geocoding-loader {
            display: none;
            font-size: 0.8rem;
            color: var(--primary);
            margin-top: 0.5rem;
            font-weight: 500;
        }
        
        .delivery-banner {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: #f0fdf4;
            border: 1px solid #dcfce7;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
            color: #166534;
        }
        .delivery-banner.delivery-unavailable {
            background: #fff1f2;
            border-color: #ffe4e6;
            color: #9f1239;
        }

        .credit-summary {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: var(--radius-md);
            padding: 1rem;
            margin-top: 1rem;
        }
        .credit-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        .credit-total {
            border-top: 1px solid #e2e8f0;
            padding-top: 0.5rem;
            margin-top: 0.5rem;
            font-weight: 800;
            color: var(--primary);
        }
        .token-warning {
            background: #fef2f2;
            border: 1px solid #fee2e2;
            color: #991b1b;
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
    </style>
    <style>
        /* Map Suggestions */
        .map-search-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-lg);
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            margin-top: 0.5rem;
        }
        .map-suggestion-item {
            padding: 0.8rem 1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-bottom: 1px solid var(--border-color);
            transition: background 0.2s;
        }
        .map-suggestion-item:last-child { border-bottom: none; }
        .map-suggestion-item:hover { background: #f8fafc; }
        .map-suggestion-item i { color: var(--text-muted); font-size: 1.2rem; }

        /* Pulsing Marker */
        .pulsing-marker {
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
        }
        .pin {
            width: 12px;
            height: 12px;
            background: var(--primary);
            border-radius: 50%;
            border: 2px solid #fff;
            box-shadow: 0 0 4px rgba(0,0,0,0.3);
            z-index: 10;
        }
        .pulse {
            background: rgba(37, 99, 235, 0.2);
            border-radius: 50%;
            height: 14px;
            width: 14px;
            position: absolute;
            z-index: 1;
            animation: pulse 2s ease-out infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            100% { transform: scale(3); opacity: 0; }
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/dashboard_sidebar.php'; ?>
        
        <div class="content-wrapper">
            <div class="details-grid">
                <!-- Left: Image -->
                <div class="column-left">
                    <img src="<?php echo $book['cover_image'] ?: 'https://images.unsplash.com/photo-1543004218-ee141104975a?auto=format&fit=crop&q=80&w=800'; ?>" 
                         class="book-img-large" alt="Book Cover"
                         onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1543004218-ee141104975a?auto=format&fit=crop&q=80&w=800';">
                </div>
                
                <!-- Center: Info -->
                <div class="column-main">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                        <div>
                            <h1 style="font-size: 2.2rem; font-weight: 800; line-height: 1.2; margin-bottom: 0.5rem; color: var(--text-main);"><?php echo htmlspecialchars($book['title']); ?></h1>
                            <p style="font-size: 1.1rem; color: var(--text-muted);">by <span style="font-weight: 600; color: var(--primary);"><?php echo htmlspecialchars($book['author']); ?></span></p>
                        </div>
                        <button class="wishlist-btn <?php echo $inWishlist ? 'active' : ''; ?>" onclick="toggleWishlist()">
                            <i class='bx <?php echo $inWishlist ? 'bxs-heart' : 'bx-heart'; ?>'></i>
                        </button>
                    </div>
                    
                    <div class="section-card">
                        <h3 style="margin-bottom: 1rem; font-weight: 700;">Description</h3>
                        <p style="line-height: 1.7; color: var(--text-muted);"><?php echo nl2br(htmlspecialchars($book['description'] ?: 'No description available.')); ?></p>
                        
                        <div style="margin-top: 1.5rem; display: flex; gap: 0.5rem;">
                            <?php 
                            $cats = explode(',', $book['category']);
                            foreach($cats as $c) {
                                if(trim($c)) echo "<span class='badge badge-gray'>" . trim($c) . "</span>";
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <div class="column-right">
                    <div class="section-card">
                        <div style="font-weight: 700; margin-bottom: 1.5rem; font-size: 1.1rem;">Listing Details</div>
                        
                        <div style="display:flex; justify-content: space-between; margin-bottom: 1rem; align-items: center;">
                            <span style="color: var(--text-muted);">Price</span>
                            <span style="font-size: 1.5rem; font-weight: 800; color: var(--text-main);">
                                <?php echo $book['price'] > 0 ? "₹" . $book['price'] : "Free"; ?>
                            </span>
                        </div>
                        
                        <div style="display:flex; justify-content: space-between; margin-bottom: 1rem; align-items: center;">
                            <span style="color: var(--text-muted);">Type</span>
                            <span class="badge badge-<?php echo $book['listing_type']; ?>" style="font-size: 0.9rem; padding: 0.3rem 0.8rem;">
                                <?php echo ucfirst($book['listing_type']); ?>
                            </span>
                        </div>

                        <!-- Delivery Status -->
                        <?php if ($deliveryAvailable): ?>
                            <div class="delivery-banner">
                                <i class='bx bxs-truck bx-tada'></i>
                                <div>
                                    <strong style="display: block;">Delivery Available</strong>
                                    <span style="font-size: 0.85rem;">Local agents cover this route!</span>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="delivery-banner delivery-unavailable">
                                <i class='bx bx-x-circle'></i>
                                <div>
                                    <strong style="display: block;">Pickup Only</strong>
                                    <span style="font-size: 0.85rem;">No active agents in this area.</span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div style="display:flex; justify-content: space-between; margin-bottom: 2rem; align-items: center;">
                            <span style="color: var(--text-muted);">Availability</span>
                            <span style="font-weight: 700; color: <?php echo $book['quantity'] > 0 ? '#15803d' : '#ef4444'; ?>;">
                                <?php echo $book['quantity'] > 0 ? $book['quantity'] . " in stock" : "Out of Stock"; ?>
                            </span>
                        </div>

                        <!-- Action Buttons -->
                        <div style="margin-bottom: 1.5rem; display: flex; flex-direction: column; gap: 0.75rem;">
                            <?php if ($book['quantity'] > 0): ?>
                                <?php if (!$hasMinTokens && $userId): ?>
                                    <div class="token-warning">
                                        <i class='bx bx-error-circle' style="font-size: 1.2rem;"></i>
                                        <span>Min. <?php echo MIN_TOKEN_LIMIT; ?> tokens required to request books. (You have <?php echo $userTokens; ?>)</span>
                                    </div>
                                    <button class="btn btn-primary w-full disabled" style="justify-content: center; padding: 0.8rem; opacity: 0.6; cursor: not-allowed;" disabled>
                                        Insufficient Tokens
                                    </button>
                                <?php elseif ($book['listing_type'] === 'borrow'): ?>
                                    <button onclick="openRequestModal('borrow')" class="btn btn-primary w-full" style="justify-content: center; padding: 0.8rem;">
                                        Request to Borrow
                                    </button>
                                <?php elseif ($book['listing_type'] === 'sell'): ?>
                                    <button onclick="openRequestModal('sell')" class="btn btn-primary w-full" style="justify-content: center; padding: 0.8rem;">
                                        Buy Now
                                    </button>
                                <?php else: ?>
                                    <button onclick="openRequestModal('exchange')" class="btn btn-primary w-full" style="justify-content: center; padding: 0.8rem;">
                                        Request Swap
                                    </button>
                                <?php endif; ?>
                            <?php else: ?>
                                <button class="btn btn-primary w-full" style="justify-content: center; padding: 0.8rem; background: var(--text-muted); cursor: not-allowed;" disabled>
                                    Currently Unavailable
                                </button>
                            <?php endif; ?>

                            <?php if ($book['role'] !== 'admin'): ?>
                                <a href="chat/index.php?user=<?php echo $book['user_id']; ?>" class="btn btn-outline w-full" style="justify-content: center; padding: 0.8rem;">
                                    <i class='bx bx-message-rounded-dots'></i> Chat with Owner
                                </a>
                            <?php endif; ?>
                        </div>

                        <hr style="border:0; border-top:1px solid var(--border-color); margin: 2rem 0;">

                        <div style="font-weight: 700; margin-bottom: 1rem;">Provider</div>
                        <a href="user_profile.php?id=<?php echo $book['user_id']; ?>" style="display:flex; align-items: center; gap: 1rem; margin-bottom: 1rem; text-decoration: none;">
                            <div style="width:45px; height:45px; background: #e2e8f0; border-radius: 50%; display:flex; align-items:center; justify-content:center; font-weight: 700; color: var(--text-muted); text-decoration: none;">
                                <?php echo strtoupper(substr($book['firstname'], 0, 1)); ?>
                            </div>
                            <div>
                                <div style="font-weight: 700; color: var(--text-main);"><?php echo $book['firstname'] . ' ' . $book['lastname']; ?></div>
                                <div style="font-size: 0.85rem; color: var(--text-muted);"><?php echo ucfirst($book['role']); ?></div>
                                <div style="font-size: 0.75rem; color: var(--primary); font-weight: 600; margin-top: 2px;">
                                    <i class='bx bx-star'></i> View Ratings & Feedback
                                </div>
                            </div>
                        </a>

                        <?php if ($book['latitude'] && $book['longitude']): ?>
                            <div style="font-weight: 600; font-size: 0.85rem; color: var(--text-muted); margin-top: 1.5rem;">Pick-up Location</div>
                            <div id="mini-map" class="mini-map"></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Request Modal -->
    <div id="req-modal" class="modal-overlay">
        <div class="modal-card">
        <div class="modal-header">
            <h2 id="modal-title" style="margin-bottom: 1rem; font-size: 1.4rem;">Confirm Request</h2>
            <p id="modal-desc" style="color: var(--text-muted); margin-bottom: 0;">Are you sure you want to proceed?</p>
        </div>
        <div class="modal-body">
            
            <div id="date-group" style="margin-bottom: 1.5rem; display: none;">
                <div style="background: #fff7ed; color: #c2410c; padding: 0.8rem; border-radius: var(--radius-md); font-size: 0.9rem; margin-bottom: 1rem; border: 1px solid #ffedd5;">
                    <i class='bx bx-info-circle'></i> <strong>Important:</strong> Please discuss and agree on this due date with the owner via chat <u>before</u> submitting this request.
                </div>
                <label style="display: block; font-weight: 600; margin-bottom: 0.5rem;">Return Date (Mandatory)</label>
                <input type="date" id="due-date" class="form-input" min="<?php echo date('Y-m-d'); ?>">
            </div>

            <div id="delivery-section" style="margin-bottom: 1.5rem;">
                <label class="checkbox-label" style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
                    <input type="checkbox" id="want-delivery" onchange="toggleDeliveryMap()">
                    <span>I want door-step delivery</span>
                </label>
                
                <div id="delivery-setup" style="display: none;">
                    <label style="display: block; font-weight: 600; margin-bottom: 0.5rem;">Confirm Drop-off Location</label>
                    
                    <div class="map-search-container">
                        <i class='bx bx-search map-search-icon'></i>
                        <input type="text" id="map-search" class="map-search-input" placeholder="Search for area, street, or building..." autocomplete="off">
                        <button type="button" class="locate-btn" onclick="getCurrentLocation()" title="Use my current location" style="right: 0.8rem;">
                            <i class='bx bx-target-lock' style="font-size: 1.2rem;"></i>
                        </button>
                        <button type="button" class="locate-btn" onclick="useProfileLocation()" title="Use my home location" style="right: 3.2rem;">
                            <i class='bx bxs-home' style="font-size: 1.2rem;"></i>
                        </button>
                        <div id="delivery-search-suggestions" class="map-search-suggestions"></div>
                    </div>

                    <div id="delivery-map"></div>
                    <div id="geocoding-status" class="geocoding-loader">
                        <i class='bx bx-loader-alt bx-spin'></i> Fetching address...
                    </div>
                    
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 1rem; margin-top: 0.5rem; display: flex; justify-content: space-between;">
                        <span><i class='bx bx-map-pin'></i> Click on map to adjust</span>
                        <span id="coord-display"></span>
                    </div>

                    <label style="display: block; font-weight: 600; margin-bottom: 0.5rem;">Specific Address Details</label>
                    <textarea id="delivery-address" class="form-input" rows="2" placeholder="Building name, Street, Apartment number..."></textarea>
                    
                    <label style="display: block; font-weight: 600; margin: 1rem 0 0.5rem;">Nearby Reference Point *</label>
                    <input type="text" id="delivery-landmark" class="form-input" placeholder="e.g. Opposite Big Bazaar, Near Green Park" value="">
                    
                    <input type="hidden" id="order-lat" value="">
                    <input type="hidden" id="order-lng" value="">
                </div>
            </div>

            <!-- Token Summary -->
            <div class="credit-summary">
                <div class="profile-main-info">
                    <h1><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></h1>
                    <p class="role-badge"><?php echo ucfirst($user['role']); ?></p>
                    <a href="user_profile.php?id=<?php echo $userId; ?>" class="btn btn-outline btn-sm" style="margin-top: 1rem;">
                        <i class='bx bx-show'></i> View My Public Profile
                    </a>
                </div>
                <div id="delivery-fee-row" class="credit-row" style="display: none;">
                    <span>Delivery Fee</span>
                    <span>+10 Tokens</span>
                </div>
                <div class="credit-row credit-total">
                    <span>Total to Spend</span>
                    <span><span id="total-cost"><?php echo $book['credit_cost'] ?? 10; ?></span> Tokens</span>
                </div>
            </div>

        </div>
        <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeModal()">Cancel</button>
                <button class="btn btn-primary" onclick="submitRequest()">Send Request</button>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const listingId = <?php echo $listingId; ?>;
        const ownerId = <?php echo $book['user_id']; ?>;
        const bookTitle = "<?php echo addslashes($book['title']); ?>";
        const lenderLat = <?php echo $book['latitude'] ?: 'null'; ?>;
        const lenderLng = <?php echo $book['longitude'] ?: 'null'; ?>;
        const userLatDefault = <?php echo $currentUser['service_start_lat'] ?? '9.4124'; ?>;
        const userLngDefault = <?php echo $currentUser['service_start_lng'] ?? '76.6946'; ?>;
        let currentType = '';
        let dMap = null;
        let dMarker = null;

        // Auto-check availability on load
        window.addEventListener('load', () => {
             checkAvailabilityGlobal(lenderLat, lenderLng, userLatDefault, userLngDefault);
        });

        async function checkAvailabilityGlobal(lLat, lLng, bLat, bLng) {
            if(!lLat || !lLng) return;
            try {
                const res = await fetch('request_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=check_delivery&l_lat=${lLat}&l_lng=${lLng}&b_lat=${bLat}&b_lng=${bLng}`
                });
                const data = await res.json();
                if(data.available) {
                    document.getElementById('delivery-info').style.display = 'flex';
                    document.getElementById('delivery-none').style.display = 'none';
                    document.getElementById('delivery-section').style.display = 'block';
                } else {
                    document.getElementById('delivery-info').style.display = 'none';
                    document.getElementById('delivery-none').style.display = 'flex';
                    document.getElementById('delivery-section').style.display = 'none';
                }
            } catch(e) {}
        }

        // Wishlist
        function toggleWishlist() {
            fetch('request_action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=toggle_wishlist&listing_id=${listingId}`
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    const btn = document.querySelector('.wishlist-btn');
                    const icon = btn.querySelector('i');
                    if(data.status === 'added') {
                        btn.classList.add('active');
                        icon.classList.remove('bx-heart'); icon.classList.add('bxs-heart');
                    } else {
                        btn.classList.remove('active');
                        icon.classList.remove('bxs-heart'); icon.classList.add('bx-heart');
                    }
                }
            });
        }

        // Modal Logic
        function openRequestModal(type) {
            currentType = type;
            document.getElementById('req-modal').style.display = 'flex';
            document.getElementById('date-group').style.display = 'none';
            const title = document.getElementById('modal-title');
            const desc = document.getElementById('modal-desc');

            if (type === 'borrow') {
                title.innerText = 'Request to Borrow';
                desc.innerText = 'Please select a return date for this item.';
                document.getElementById('date-group').style.display = 'block';
            } else if (type === 'sell') {
                title.innerText = 'Confirm Purchase';
                desc.innerText = 'Notify the owner that you want to buy this book?';
            } else {
                title.innerText = 'Request Swap';
                desc.innerText = 'Notify the owner that you are interested in a swap?';
            }
        }

        function closeModal() {
            document.getElementById('req-modal').style.display = 'none';
        }

        function toggleDeliveryMap() {
            const isChecked = document.getElementById('want-delivery').checked;
            document.getElementById('delivery-setup').style.display = isChecked ? 'block' : 'none';
            
            // Update Credit Summary
            const baseCost = parseInt(document.getElementById('base-cost').innerText);
            const deliveryFee = isChecked ? 10 : 0;
            document.getElementById('delivery-fee-row').style.display = isChecked ? 'flex' : 'none';
            document.getElementById('total-cost').innerText = baseCost + deliveryFee;

            if(isChecked && !dMap) {
                setTimeout(() => {
                    dMap = L.map('delivery-map', {
                        zoomControl: true,
                        scrollWheelZoom: true
                    }).setView([userLatDefault, userLngDefault], 14);
                    
                    const cartoTiles = 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png';
                    const cartoAttr = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>';
                    L.tileLayer(cartoTiles, { attribution: cartoAttr }).addTo(dMap);
                    
                    // Custom Pulsing Pin Icon
                    const pulsingIcon = L.divIcon({
                        className: 'pulsing-marker',
                        html: '<div class="pin"></div><div class="pulse"></div>',
                        iconSize: [20, 20],
                        iconAnchor: [10, 10]
                    });

                    // Add click handler
                    dMap.on('click', (e) => {
                        updateMarkerAndAddress(e.latlng.lat, e.latlng.lng);
                    });

                    // Handle Search Logic
                    const searchInput = document.getElementById('map-search');
                    const suggestionsBox = document.getElementById('delivery-search-suggestions');
                    let searchTimeout;

                    searchInput.addEventListener('input', (e) => {
                        clearTimeout(searchTimeout);
                        const query = e.target.value.trim();
                        if(query.length < 3) {
                            suggestionsBox.style.display = 'none';
                            return;
                        }

                        searchTimeout = setTimeout(async () => {
                            try {
                                const res = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5&countrycodes=in`);
                                const data = await res.json();
                                
                                suggestionsBox.innerHTML = '';
                                if (data && data.length > 0) {
                                    data.forEach(item => {
                                        const div = document.createElement('div');
                                        div.className = 'map-suggestion-item';
                                        div.innerHTML = `<i class='bx bx-map-pin'></i> <span>${item.display_name}</span>`;
                                        div.onclick = () => {
                                            dMap.setView([item.lat, item.lon], 17);
                                            updateMarkerAndAddress(parseFloat(item.lat), parseFloat(item.lon), item.display_name);
                                            suggestionsBox.style.display = 'none';
                                            searchInput.value = item.display_name;
                                        };
                                        suggestionsBox.appendChild(div);
                                    });
                                    suggestionsBox.style.display = 'block';
                                } else {
                                    suggestionsBox.style.display = 'none';
                                }
                            } catch(e) { console.error(e); }
                        }, 500);
                    });
                    
                    // Enter key support
                    searchInput.addEventListener('keydown', async (e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            const query = e.target.value.trim();
                            if (query.length < 3) return;

                            clearTimeout(searchTimeout);
                            
                            try {
                                const res = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=1&countrycodes=in`);
                                const data = await res.json();
                                
                                if (data.length > 0) {
                                    const item = data[0];
                                    dMap.setView([item.lat, item.lon], 17);
                                    updateMarkerAndAddress(parseFloat(item.lat), parseFloat(item.lon), item.display_name);
                                    suggestionsBox.style.display = 'none';
                                    searchInput.value = item.display_name;
                                } else {
                                    alert("Location not found.");
                                }
                            } catch (e) {
                                alert("Search failed.");
                            }
                        }
                    });

                    // Initial marker if valid location exists
                    /* if (userLatDefault && userLngDefault) {
                        // Optional: dMarker = L.marker... 
                        // But usually we wait for user to click or use current location
                    } */

                }, 100);
            }
        }

        // Redundant searchAddress function (removed/integrated above)

        function getCurrentLocation() {
            if (navigator.geolocation) {
                document.getElementById('geocoding-status').style.display = 'block';
                navigator.geolocation.getCurrentPosition((position) => {
                    const { latitude, longitude } = position.coords;
                    dMap.setView([latitude, longitude], 17);
                    updateMarkerAndAddress(latitude, longitude);
                }, (error) => {
                    document.getElementById('geocoding-status').style.display = 'none';
                    let msg = "Unable to retrieve your location.";
                    if (error.code === error.TIMEOUT) msg = "Location request timed out. Please try again.";
                    else if (error.code === error.PERMISSION_DENIED) msg = "Geolocation permission denied.";
                    alert(msg);
                }, {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                });
            } else {
                alert("Geolocation is not supported by your browser.");
            }
        }

        function useProfileLocation() {
            if (!dMap) return;
            const profileLat = <?php echo $currentUser['service_start_lat'] ?? 'null'; ?>;
            const profileLng = <?php echo $currentUser['service_start_lng'] ?? 'null'; ?>;
            const profileAddr = <?php echo json_encode($currentUser['address'] ?? ''); ?>;
            const profileLandmark = <?php echo json_encode($currentUser['landmark'] ?? ''); ?>;

            if (!profileLat || !profileLng) {
                alert("No home location saved in your profile!");
                return;
            }

            dMap.setView([profileLat, profileLng], 17);
            updateMarkerAndAddress(profileLat, profileLng, profileAddr);
            document.getElementById('delivery-landmark').value = profileLandmark;
        }

        async function updateMarkerAndAddress(lat, lng, manualAddress = null) {
            const pulsingIcon = L.divIcon({
                className: 'pulsing-marker',
                html: '<div class="pin"></div><div class="pulse"></div>',
                iconSize: [20, 20],
                iconAnchor: [10, 10]
            });

            if(dMarker) dMap.removeLayer(dMarker);
            dMarker = L.marker([lat, lng], { icon: pulsingIcon }).addTo(dMap);

            document.getElementById('order-lat').value = lat;
            document.getElementById('order-lng').value = lng;
            document.getElementById('coord-display').innerText = `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
            
            // Re-check availability
            checkAvailabilityGlobal(lenderLat, lenderLng, lat, lng);

            // If manual address is provided (including empty string), set it immediately and skip fetch
            if (manualAddress !== null) {
                document.getElementById('delivery-address').value = manualAddress;
                document.getElementById('geocoding-status').style.display = 'none';
                return;
            }

            document.getElementById('geocoding-status').style.display = 'block';
            
            try {
                // Reverse geocoding for deep parsing
                const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`);
                const data = await res.json();
                
                if(data && data.address) {
                     const addr = data.address;
                    // Deep parsing logic
                    const house = addr.house_number || '';
                    const road = addr.road || addr.pedestrian || '';
                    const suburb = addr.suburb || addr.neighbourhood || addr.residential || '';
                    const city = addr.city || addr.town || addr.village || '';
                    const rural = addr.village || addr.hamlet || addr.isolated_dwelling || '';

                    let parts = [];
                    if (house) parts.push(house);
                    if (road) parts.push(road);
                    if (suburb) parts.push(suburb);
                    if (city) parts.push(city);
                    if (!city && rural) parts.push(rural);

                    const shortAddr = parts.join(', ') + (parts.length > 0 ? ', ' : '') + data.display_name;
                    document.getElementById('delivery-address').value = shortAddr;
                }
            } catch(e) {
                console.error("Geocoding failed", e);
            } finally {
                document.getElementById('geocoding-status').style.display = 'none';
            }
        }

        function submitRequest() {
            const dueDate = document.getElementById('due-date').value;
            const wantDelivery = document.getElementById('want-delivery').checked;
            const address = document.getElementById('delivery-address').value;
            const landmark = document.getElementById('delivery-landmark').value;
            const lat = document.getElementById('order-lat').value;
            const lng = document.getElementById('order-lng').value;
            
            if (currentType === 'borrow' && !dueDate) {
                alert('Please select a return date!');
                return;
            }

            if (wantDelivery && !lat) {
                alert('Please click on the map to set your delivery location!');
                return;
            }

            fetch('request_action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=create_request&listing_id=${listingId}&owner_id=${ownerId}&type=${currentType}&due_date=${dueDate}&book_title=${encodeURIComponent(bookTitle)}&delivery=${wantDelivery?1:0}&address=${encodeURIComponent(address)}&landmark=${encodeURIComponent(landmark)}&lat=${lat}&lng=${lng}`
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    alert(data.message);
                    closeModal();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }

        // Map
        <?php if ($book['latitude'] && $book['longitude']): ?>
            const map = L.map('mini-map', { zoomControl: false, dragging: false }).setView([<?php echo $book['latitude']; ?>, <?php echo $book['longitude']; ?>], 14);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
            L.marker([<?php echo $book['latitude']; ?>, <?php echo $book['longitude']; ?>]).addTo(map);
        <?php endif; ?>
    </script>
</body>
</html>
