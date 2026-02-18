<?php
require_once '../includes/db_helper.php';
require_once '../paths.php';
session_start();

$userId = $_SESSION['user_id'] ?? 0;
$user = $userId ? getUserById($userId) : null;

// Handle AJAX requests for live map updates
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    $filters = [
        'query'    => $_GET['query'] ?? '',
        'role'     => $_GET['role'] ?? '',
        'type'     => $_GET['type'] ?? '',
        'category' => $_GET['category'] ?? '',
        'min_rating' => $_GET['min_rating'] ?? ''
    ];
    
    if (isset($_GET['sw_lat'], $_GET['ne_lat'], $_GET['sw_lng'], $_GET['ne_lng'])) {
        $filters['bounds'] = [
            'sw_lat' => $_GET['sw_lat'],
            'ne_lat' => $_GET['ne_lat'],
            'sw_lng' => $_GET['sw_lng'],
            'ne_lng' => $_GET['ne_lng']
        ];
    }
    
    if (isset($_GET['c_lat'], $_GET['c_lng'])) {
        $filters['center_lat'] = $_GET['c_lat'];
        $filters['center_lng'] = $_GET['c_lng'];
    }

    $results = searchListingsAdvanced($filters, 50);
    header('Content-Type: application/json');
    echo json_encode($results);
    exit();
}

// Initial page load filters
$filters = [
    'query'    => $_GET['query'] ?? '',
    'role'     => $_GET['role'] ?? '',
    'type'     => $_GET['type'] ?? '',
    'category' => $_GET['category'] ?? '',
    'min_price' => isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (float)$_GET['min_price'] : null,
    'max_price' => isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (float)$_GET['max_price'] : null,
    'min_rating' => isset($_GET['min_rating']) && $_GET['min_rating'] !== '' ? (float)$_GET['min_rating'] : null
];

$results = searchListingsAdvanced($filters);

// Separate Rare Books for Spotlight
$rareResults = getRareBooks(10);
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $_SESSION['theme_mode'] ?? 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Explore Books | BOOK-B</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=1.2">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        :root {
            --sidebar-width: 580px;
            --glass-bg: rgba(255, 255, 255, 0.85);
            --glass-border: rgba(255, 255, 255, 0.5);
            --accent-gold: #f59e0b;
        }

        [data-theme="dark"] {
            --glass-bg: rgba(15, 23, 42, 0.85);
            --glass-border: rgba(51, 65, 85, 0.5);
        }

        /* Full Screen Adjustment */
        .main-content {
            padding: 0 !important;
            max-width: none !important;
            height: calc(100vh - 64px);
            overflow: hidden;
            display: flex;
            background: var(--bg-body);
        }

        .explore-wrapper {
            display: flex;
            height: 100%;
            width: 100%;
            position: relative;
        }

        /* Premium Sidebar */
        .premium-sidebar {
            width: var(--sidebar-width);
            height: 100%;
            background: var(--bg-card);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            z-index: 10;
            box-shadow: 15px 0 45px rgba(0,0,0,0.04);
        }

        .sidebar-header {
            padding: 2.5rem 2rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-body) 100%);
        }

        .sidebar-content {
            flex: 1;
            overflow-y: auto;
            padding: 2rem;
            scroll-behavior: smooth;
        }

        /* Custom Modern Scrollbar */
        .sidebar-content::-webkit-scrollbar { width: 5px; }
        .sidebar-content::-webkit-scrollbar-track { background: transparent; }
        .sidebar-content::-webkit-scrollbar-thumb { 
            background: var(--border-color);
            border-radius: 10px;
        }
        .sidebar-content::-webkit-scrollbar-thumb:hover { background: var(--text-muted); }

        /* Filter Section */
        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.25rem;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }

        .premium-select {
            appearance: none;
            background: var(--bg-body) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") no-repeat right 0.75rem center;
            background-size: 1rem;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 0.75rem 1rem;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-main);
            cursor: pointer;
            transition: all 0.2s;
        }

        .premium-select:hover { border-color: var(--primary); background-color: var(--bg-card); }

        /* Scroller for Rare Books */
        .exclusive-banner {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            border: 1px solid #fde68a;
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 2.5rem;
            box-shadow: 0 10px 20px rgba(245, 158, 11, 0.05);
            position: relative;
        }

        [data-theme="dark"] .exclusive-banner {
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%);
            border-color: #4338ca;
        }

        .rare-scroller {
            display: flex;
            gap: 1.25rem;
            overflow-x: auto;
            padding: 0.5rem 0;
            scrollbar-width: none;
        }
        .rare-scroller::-webkit-scrollbar { display: none; }

        .rare-item-card {
            flex: 0 0 140px;
            background: #fffbeb;
            padding: 0.75rem;
            border-radius: 14px;
            border: 2px solid #f59e0b;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            cursor: pointer;
            text-align: center;
            position: relative;
        }

        .rare-item-card:hover {
            transform: translateY(-8px) scale(1.02);
            border-color: #d97706;
            box-shadow: 0 15px 30px rgba(245, 158, 11, 0.15);
        }

        .rare-badge-mini {
            position: absolute;
            top: 5px;
            left: 5px;
            background: #f59e0b;
            color: white;
            padding: 2px 6px;
            border-radius: 8px;
            font-size: 0.55rem;
            font-weight: 800;
            z-index: 10;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .rare-item-img {
            width: 100%;
            aspect-ratio: 2/3;
            border-radius: 10px;
            object-fit: cover;
            margin-bottom: 0.75rem;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        /* Modern Result Cards */
        .result-card-premium {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 2px solid transparent;
            padding: 1.25rem;
            display: flex;
            gap: 1.5rem;
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
            cursor: pointer;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }

        .result-card-premium.rare-result {
            border-color: #f59e0b;
            background: #fffbeb;
        }

        .result-card-premium:hover {
            transform: translateX(8px);
            border-color: var(--primary);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .result-card-premium.rare-result:hover {
            border-color: #d97706;
        }

        .result-card-premium::after {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 4px; height: 100%;
            background: var(--primary);
            opacity: 0;
            transition: opacity 0.3s;
        }
        .result-card-premium:hover::after { opacity: 1; }

        .result-img-premium {
            width: 110px;
            height: 150px;
            border-radius: 14px;
            object-fit: cover;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }

        .result-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .result-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--text-main);
            line-height: 1.3;
            margin-bottom: 0.25rem;
        }

        .result-author {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }

        .pill-group {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .premium-pill {
            font-size: 0.65rem;
            font-weight: 800;
            padding: 4px 12px;
            border-radius: 10px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .result-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 1.5rem;
        }

        .price-display {
            font-size: 1.25rem;
            font-weight: 900;
            color: var(--primary);
            letter-spacing: -0.5px;
        }

        /* Map UI Controls */
        #map {
            flex: 1;
            height: 100%;
            z-index: 1;
        }

        .map-overlay-controls {
            position: absolute;
            top: 2rem;
            right: 2rem;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            align-items: flex-end;
        }

        .glass-box {
            background: var(--glass-bg);
            backdrop-filter: blur(15px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 0.75rem;
            box-shadow: 0 15px 35px rgba(0,0,0,0.12);
            display: flex;
            gap: 0.75rem;
        }

        .search-container {
            display: flex;
            align-items: center;
            background: var(--bg-card);
            border-radius: 14px;
            padding: 0 1.25rem;
            border: 1px solid var(--border-color);
            width: 320px;
            transition: all 0.3s;
        }

        .search-container:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }

        .search-container input {
            border: none;
            background: none;
            padding: 0.85rem 0.75rem;
            width: 100%;
            outline: none;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-main);
        }

        .icon-btn {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary);
            color: white;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 1.25rem;
        }

        .icon-btn:hover { background: var(--primary-dark); transform: translateY(-2px); }
        .icon-btn.white { background: var(--bg-card); color: var(--text-main); border: 1px solid var(--border-color); }
        .icon-btn.white:hover { border-color: var(--primary); color: var(--primary); }

        /* Animation */
        @keyframes pulse {
            0% { transform: scale(1); opacity: 0.8; }
            50% { transform: scale(1.5); opacity: 0.4; }
            100% { transform: scale(1); opacity: 0.8; }
        }

        .switch { position: relative; display: inline-block; width: 44px; height: 22px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: var(--primary); }
        input:checked + .slider:before { transform: translateX(22px); }
    </style>
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" />
</head>
<body>
    <?php include '../includes/dashboard_header.php'; ?>
    <div class="dashboard-wrapper">
        <?php include '../includes/dashboard_sidebar.php'; ?>
        
        <main class="main-content">
            <div class="explore-wrapper">
                <!-- Premium Sidebar -->
                <aside class="premium-sidebar">
                    <div class="sidebar-header">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div>
                                <h1 style="font-size: 1.75rem; font-weight: 900; color: var(--text-main); letter-spacing: -1px; margin-bottom: 0.25rem;">Explore</h1>
                                <p style="color: var(--text-muted); font-size: 0.85rem; font-weight: 500;">Discover literature in your city.</p>
                            </div>
                            <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 4px;">
                                <span style="font-size: 0.65rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Live Sync</span>
                                <label class="switch">
                                    <input type="checkbox" id="live-search-toggle" checked>
                                    <span class="slider round"></span>
                                </label>
                            </div>
                        </div>

                        <!-- Listing Type Pills -->
                        <div style="margin-top: 1.5rem;">
                            <h4 style="font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 1rem;">Listing Type</h4>
                            <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                                <?php
                                $currentType = $filters['type'];
                                $typeOptions = [
                                    '' => ['label' => 'All', 'icon' => 'bx-list-ul'],
                                    'borrow' => ['label' => 'Borrow', 'icon' => 'bx-book-reader'],
                                    'sell' => ['label' => 'Buy', 'icon' => 'bx-shopping-bag']
                                ];
                                foreach($typeOptions as $value => $option):
                                    $isActive = ($currentType === $value);
                                    $activeClass = $isActive ? 'active' : '';
                                ?>
                                    <a href="?type=<?php echo $value; ?>&category=<?php echo urlencode($filters['category']); ?>&query=<?php echo urlencode($filters['query']); ?>" 
                                       class="type-pill <?php echo $activeClass; ?>" 
                                       style="flex: 1; min-width: 100px; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.75rem 1rem; border-radius: 12px; font-size: 0.85rem; font-weight: 700; transition: all 0.2s; <?php echo $isActive ? 'background: var(--primary); color: white; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);' : 'background: var(--bg-body); color: var(--text-main); border: 1px solid var(--border-color);'; ?>">
                                        <i class='bx <?php echo $option['icon']; ?>' style="font-size: 1.1rem;"></i>
                                        <?php echo $option['label']; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Genre Filter -->
                        <form action="explore.php" method="GET" style="margin-top: 1.5rem;" id="filters-form">
                            <h4 style="font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 1rem;">Genre</h4>
                            <select name="category" class="premium-select" onchange="this.form.submit()" style="width: 100%; margin-bottom: 1.5rem;">
                                <option value="">All Genres</option>
                                <?php 
                                $cats = ['Fiction', 'Non-Fiction', 'Education', 'Sci-Fi', 'Romance', 'Mystery', 'Self-Help', 'Business', 'History'];
                                foreach($cats as $c) {
                                    $sel = ($filters['category'] == $c) ? 'selected' : '';
                                    echo "<option value='$c' $sel>$c</option>";
                                }
                                ?>
                            </select>

                            <h4 style="font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 1rem;">Lender Rating</h4>
                            <select name="min_rating" class="premium-select" onchange="this.form.submit()" style="width: 100%;">
                                <option value="">All Ratings</option>
                                <option value="4.5" <?php echo ($filters['min_rating'] == 4.5) ? 'selected' : ''; ?>>4.5+ Stars</option>
                                <option value="4.0" <?php echo ($filters['min_rating'] == 4.0) ? 'selected' : ''; ?>>4.0+ Stars</option>
                                <option value="3.5" <?php echo ($filters['min_rating'] == 3.5) ? 'selected' : ''; ?>>3.5+ Stars</option>
                                <option value="3.0" <?php echo ($filters['min_rating'] == 3.0) ? 'selected' : ''; ?>>3.0+ Stars</option>
                            </select>

                            <input type="hidden" name="type" value="<?php echo htmlspecialchars($filters['type']); ?>">
                            <input type="hidden" name="query" value="<?php echo htmlspecialchars($filters['query']); ?>">
                        </form>
                    </div>

                    <div class="sidebar-content">
                        <!-- Rare Books Spotlight -->
                        <?php if (!empty($rareResults)): ?>
                        <div class="exclusive-banner">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                                <h3 style="font-size: 0.8rem; font-weight: 800; color: #92400e; display: flex; align-items: center; gap: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">
                                    <i class='bx bxs-diamond'></i> Rare Spotlight
                                </h3>
                                <div style="display: flex; gap: 3px;">
                                    <div style="width: 4px; height: 4px; background: #f59e0b; border-radius: 50%;"></div>
                                    <div style="width: 4px; height: 4px; background: #f59e0b; border-radius: 50%; opacity: 0.5;"></div>
                                    <div style="width: 4px; height: 4px; background: #f59e0b; border-radius: 50%; opacity: 0.3;"></div>
                                </div>
                            </div>
                            <div class="rare-scroller">
                                <?php foreach ($rareResults as $rare): 
                                    $rCover = $rare['cover_image'];
                                    // Local images are stored relative to pages/ directory
                                    if ($rCover && preg_match('/^https?:\/\//', $rCover)) {
                                        // Absolute URL, keep as is
                                    } else if ($rCover) {
                                        // Relative path, already in correct context for explore.php
                                    }
                                    $fallback = 'https://images.unsplash.com/photo-1543004218-ee141104975a?auto=format&fit=crop&q=80&w=400';
                                    $rCover = $rCover ?: $fallback;
                                ?>
                                <div class="rare-item-card" onclick="window.location.href='book_details.php?id=<?php echo $rare['id']; ?>'">
                                    <span class="rare-badge-mini">RARE</span>
                                    <img src="<?php echo $rCover; ?>" 
                                         class="rare-item-img" alt="Rare Book">
                                    <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); line-height: 1.2; height: 2.4em; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">
                                        <?php echo htmlspecialchars($rare['title']); ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Standard Results -->
                        <div style="margin-bottom: 1.5rem;">
                            <h3 class="results-info-text" style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.1em;">
                                Discoveries (<?php echo count($results); ?>)
                            </h3>
                        </div>

                        <div class="results-list">
                            <?php foreach ($results as $item): 
                                $iCover = $item['cover_image'];
                                $fallback = 'https://images.unsplash.com/photo-1543004218-ee141104975a?auto=format&fit=crop&q=80&w=800';
                                $iCover = $iCover ?: $fallback;
                                $isRare = $item['is_rare'] ?? 0;
                            ?>
                                <div class="result-card-premium <?php echo $isRare ? 'rare-result' : ''; ?>" onclick="window.location.href='book_details.php?id=<?php echo $item['id']; ?>'">
                                    <?php if ($isRare): ?>
                                        <span class="rare-badge-mini" style="top: 10px; left: 10px;">RARE</span>
                                    <?php endif; ?>
                                    <img src="<?php echo htmlspecialchars($iCover, ENT_QUOTES, 'UTF-8', false); ?>" 
                                         class="result-img-premium" alt="Book Cover"
                                         onerror="this.onerror=null; this.src='<?php echo $fallback; ?>';">
                                    
                                    <div class="result-info">
                                        <div>
                                            <div class="result-title"><?php echo htmlspecialchars($item['title']); ?></div>
                                            <div class="result-author">by <?php echo htmlspecialchars($item['author']); ?></div>
                                            
                                            <div class="pill-group">
                                                <span class="premium-pill badge-<?php echo $item['listing_type']; ?>"><?php echo ucfirst($item['listing_type']); ?></span>
                                                <?php if (isset($item['distance'])): ?>
                                                    <span class="premium-pill" style="background: var(--bg-body); color: var(--primary); border: 1px solid var(--border-color);">
                                                        <i class='bx bx-navigation'></i> <?php echo number_format($item['distance'], 1); ?> km
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="result-footer">
                                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                <div style="width: 32px; height: 32px; background: var(--bg-body); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #f59e0b;">
                                                    <i class='bx bxs-star'></i>
                                                </div>
                                                <span style="font-weight: 700; color: var(--text-main); font-size: 0.9rem;"><?php echo number_format($item['average_rating'], 1); ?></span>
                                            </div>
                                            <?php if ($item['listing_type'] === 'sell'): ?>
                                                <div class="price-display">₹<?php echo number_format($item['price'], 0); ?></div>
                                            <?php else: ?>
                                                <div style="font-size: 0.85rem; font-weight: 700; color: #10b981; background: #d1fae5; padding: 0.4rem 1rem; border-radius: 10px;">FREE</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <?php if ($item['quantity'] <= 0): ?>
                                        <div style="position: absolute; inset: 0; background: rgba(var(--bg-body-rgb), 0.6); backdrop-filter: blur(3px); display: flex; align-items: center; justify-content: center; z-index: 5;">
                                            <span style="background: var(--text-main); color: var(--bg-card); padding: 8px 24px; border-radius: 30px; font-weight: 900; font-size: 0.75rem; letter-spacing: 1px;">SOLD OUT</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </aside>

                <!-- Interactive Map Area -->
                <div id="map"></div>

                <!-- Floating Overlays -->
                <div class="map-overlay-controls">
                    <div class="glass-box">
                        <div class="search-container">
                            <i class='bx bx-map-pin' style="color: var(--primary); font-size: 1.2rem;"></i>
                            <input type="text" id="map-loc-search" placeholder="Search any neighborhood..." 
                                   onkeydown="if(event.key === 'Enter') searchMapLoc()">
                        </div>
                        <button class="icon-btn" onclick="searchMapLoc()" title="Navigate">
                            <i class='bx bx-right-arrow-alt'></i>
                        </button>
                    </div>

                    <div class="glass-box">
                        <button class="icon-btn white" onclick="useMyLoc()" title="Recenter to my position">
                            <i class='bx bx-target-lock'></i>
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>


    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>
    <script>
        const initialLat = <?php echo floatval($user['service_start_lat'] ?? 12.9716); ?>;
        const initialLng = <?php echo floatval($user['service_start_lng'] ?? 77.5946); ?>;
        const map = L.map('map').setView([initialLat, initialLng], 12);

        L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}.png', {
            attribution: '©OpenStreetMap ©CartoDB'
        }).addTo(map);

        // Ensure map layout is correct
        setTimeout(() => { map.invalidateSize(); }, 500);

        // Marker Cluster Group
        const markerCluster = L.markerClusterGroup({
            showCoverageOnHover: false,
            spiderfyOnMaxZoom: true,
            maxClusterRadius: 50
        });
        map.addLayer(markerCluster);

        let searchTimeout;

        function updateMarkers(listings) {
            markerCluster.clearLayers();
            const resultsList = document.querySelector('.results-list');
            resultsList.innerHTML = ''; 

            if (!listings || listings.length === 0) {
                resultsList.innerHTML = '<div class="empty-state" style="padding: 2rem; text-align: center; color: var(--text-muted);">No books found in this area.</div>';
                document.querySelector('.results-info-text').innerText = 'Discoveries (0)';
                return;
            }

            listings.forEach(m => {
                let mCover = m.cover_image;
                const fallback = 'https://images.unsplash.com/photo-1543004218-ee141104975a?auto=format&fit=crop&q=80&w=800';
                mCover = mCover || fallback;

                if (m.latitude && m.longitude) {
                    const marker = L.marker([m.latitude, m.longitude])
                        .bindPopup(`
                            <div style="font-family: inherit; padding: 10px; min-width: 220px; border-radius: 16px;">
                                <img src="${mCover}" 
                                     style="width: 100%; height: 120px; object-fit: cover; border-radius: 12px; margin-bottom: 12px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);"
                                     onerror="this.onerror=null; this.src='${fallback}';">
                                <strong style="display:block; font-size: 1.1rem; margin-bottom: 2px; color: var(--text-main); line-height: 1.2;">${m.title}</strong>
                                <span style="display:block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 12px;">by ${m.author}</span>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                    <span class="premium-pill badge-${m.listing_type}" style="font-size: 0.65rem;">${m.listing_type}</span>
                                    <span style="color: #f59e0b; font-size: 0.9rem; font-weight: 800;"><i class='bx bxs-star'></i> ${m.average_rating}</span>
                                </div>
                                ${m.listing_type === 'sell' ? 
                                    `<div style="font-size: 1.1rem; font-weight: 900; color: var(--primary); margin-bottom: 15px;">\u20b9${new Intl.NumberFormat().format(m.price)}</div>` : 
                                    `<div style="font-size: 0.85rem; font-weight: 700; color: #10b981; background: #d1fae5; padding: 0.5rem 1rem; border-radius: 10px; text-align: center; margin-bottom: 15px;">FREE</div>`
                                }
                                <div style="display: grid; gap: 0.75rem;">
                                    <a href="book_details.php?id=${m.id}" class="icon-btn" style="width: 100%; height: 40px; font-size: 0.9rem; text-decoration: none;">View Details</a>
                                    <a href="chat/index.php?user=${m.user_id}" class="icon-btn white" style="width: 100%; height: 40px; font-size: 0.9rem; text-decoration: none;">Chat Owner</a>
                                </div>
                            </div>
                        `, { className: 'premium-popup' });
                    markerCluster.addLayer(marker);
                }

                // Sidebar Card
                const isRare = m.is_rare == 1;
                const rareClass = isRare ? 'rare-result' : '';
                const rareBadge = isRare ? '<span class="rare-badge-mini" style="top: 10px; left: 10px;">RARE</span>' : '';
                
                const card = document.createElement('div');
                card.className = `result-card-premium ${rareClass}`;
                card.onclick = () => window.location.href = `book_details.php?id=${m.id}`;
                
                let quantityOverlay = m.quantity <= 0 ? `
                    <div style="position: absolute; inset: 0; background: rgba(255,255,255,0.6); backdrop-filter: blur(3px); display: flex; align-items: center; justify-content: center; z-index: 5; border-radius: 20px;">
                        <span style="background: #000; color: white; padding: 6px 18px; border-radius: 30px; font-weight: 900; font-size: 0.75rem;">TAKEN</span>
                    </div>
                ` : '';

                card.innerHTML = `
                    ${rareBadge}
                    <img src="${mCover}" 
                         class="result-img-premium" alt="Book Cover"
                         onerror="this.onerror=null; this.src='${fallback}';">
                    <div class="result-info">
                        <div>
                            <div class="result-title">${m.title}</div>
                            <div class="result-author">by ${m.author}</div>
                            <div class="pill-group">
                                <span class="premium-pill badge-${m.listing_type}">${m.listing_type.charAt(0).toUpperCase() + m.listing_type.slice(1)}</span>
                                ${m.distance ? `
                                    <span class="premium-pill" style="background: var(--bg-body); color: var(--primary); border: 1px solid var(--border-color);">
                                        <i class='bx bx-navigation'></i> ${parseFloat(m.distance).toFixed(1)} km
                                    </span>
                                ` : ''}
                            </div>
                        </div>
                        <div class="result-footer">
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <div style="width: 32px; height: 32px; background: var(--bg-body); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #f59e0b;">
                                    <i class='bx bxs-star'></i>
                                </div>
                                <span style="font-weight: 700; color: var(--text-main); font-size: 0.9rem;">${parseFloat(m.average_rating).toFixed(1)}</span>
                            </div>
                            ${m.listing_type === 'sell' ? 
                                `<div class="price-display">₹${new Intl.NumberFormat().format(m.price)}</div>` : 
                                `<div style="font-size: 0.85rem; font-weight: 700; color: #10b981; background: #d1fae5; padding: 0.4rem 1rem; border-radius: 10px;">FREE</div>`
                            }
                        </div>
                    </div>
                    ${quantityOverlay}
                `;
                resultsList.appendChild(card);
            });
            
            document.querySelector('.results-info-text').innerText = `Discoveries (${listings.length})`;
        }

        async function fetchNewResults() {
            if (!document.getElementById('live-search-toggle').checked) return;

            const bounds = map.getBounds();
            const center = map.getCenter();
            
            const params = new URLSearchParams({
                ajax: '1',
                sw_lat: bounds.getSouthWest().lat,
                ne_lat: bounds.getNorthEast().lat,
                sw_lng: bounds.getSouthWest().lng,
                ne_lng: bounds.getNorthEast().lng,
                c_lat: center.lat,
                c_lng: center.lng,
                query: <?php echo json_encode($filters['query']); ?>,
                role: <?php echo json_encode($filters['role']); ?>,
                type: <?php echo json_encode($filters['type']); ?>,
                category: <?php echo json_encode($filters['category']); ?>,
                min_rating: <?php echo json_encode($filters['min_rating']); ?>
            });

            try {
                const response = await fetch(`explore.php?${params.toString()}`);
                const data = await response.json();
                updateMarkers(data);
            } catch (err) {
                console.error("Live search failed:", err);
            }
        }

        map.on('moveend', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(fetchNewResults, 400); 
        });

        // Initial Load
        const initialData = <?php echo json_encode($results); ?>;
        updateMarkers(initialData);

        if (initialData.length > 0) {
            // Fit bounds only on first load if markers exist
            const group = L.featureGroup(initialData.map(m => m.latitude && L.marker([m.latitude, m.longitude])));
            if (group.getLayers().length > 0) map.fitBounds(group.getBounds().pad(0.1));
        }

        function searchMapLoc() {
            const query = document.getElementById('map-loc-search').value;
            if(!query) return;

            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=1&countrycodes=in`)
                .then(res => res.json())
                .then(data => {
                    if(data && data.length > 0) {
                        map.setView([data[0].lat, data[0].lon], 16);
                        L.marker([data[0].lat, data[0].lon]).addTo(map).bindPopup(data[0].display_name).openPopup();
                    } else {
                        showToast('Location not found in India', 'warning');
                    }
                });
        }

        let userLocMarker = null;
        let userLocCircle = null;

        function useMyLoc() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(position => {
                    const { latitude, longitude, accuracy } = position.coords;
                    
                    if (userLocCircle) map.removeLayer(userLocCircle);
                    if (userLocMarker) map.removeLayer(userLocMarker);

                    userLocCircle = L.circle([latitude, longitude], {
                        radius: accuracy,
                        color: 'var(--primary)',
                        fillOpacity: 0.1,
                        weight: 1
                    }).addTo(map);

                    const pulsingIcon = L.divIcon({
                        className: 'pulsing-marker',
                        html: '<div class="pin"></div><div class="pulse"></div>',
                        iconSize: [20, 20],
                        iconAnchor: [10, 10]
                    });

                    userLocMarker = L.marker([latitude, longitude], { icon: pulsingIcon }).addTo(map)
                        .bindPopup("Your estimated location").openPopup();

                    map.setView([latitude, longitude], 16);
                }, (error) => {
                    let msg = "Unable to retrieve your location.";
                    if (error.code === error.TIMEOUT) msg = "Location request timed out. Please try again.";
                    else if (error.code === error.PERMISSION_DENIED) msg = "Geolocation permission denied.";
                    showToast(msg, 'error');
                }, {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                });
            } else {
                showToast('Geolocation is not supported by your browser', 'error');
            }
        }
    </script>
</body>
</html>
