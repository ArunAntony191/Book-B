<?php
require_once 'includes/db_helper.php';
require_once 'paths.php';
session_start();

// Get filters
$filters = [
    'query'    => $_GET['query'] ?? '',
    'role'     => $_GET['role'] ?? '',
    'type'     => $_GET['type'] ?? '',
    'category' => $_GET['category'] ?? '',
    'min_price' => isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (float)$_GET['min_price'] : null,
    'max_price' => isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (float)$_GET['max_price'] : null,
    'has_location' => true
];

$results = searchListingsAdvanced($filters);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Explore Books | BOOK-B</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        .explore-container {
            display: grid;
            grid-template-columns: 380px 1fr;
            height: calc(100vh - 120px);
            background: white;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
        }
        .search-sidebar {
            padding: 2rem;
            border-right: 1px solid var(--border-color);
            overflow-y: auto;
            background: #f8fafc;
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }
        .filter-section-title {
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }
        #map {
            height: 100%;
            width: 100%;
            z-index: 1;
        }
        .results-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .book-card-mini {
            background: white;
            padding: 1.25rem;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            gap: 1rem;
            position: relative;
        }
        .book-card-mini:hover {
            transform: translateY(-3px);
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        .book-card-mini.active {
            border-color: var(--primary);
            background: #f5f3ff;
            box-shadow: 0 0 0 1px var(--primary);
        }
        .book-mini-img {
            width: 70px;
            height: 90px;
            border-radius: var(--radius-sm);
            object-fit: cover;
            box-shadow: var(--shadow-sm);
        }
        .book-mini-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .chat-btn-mini {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f1f5f9;
            color: var(--text-muted);
            transition: all 0.2s;
            text-decoration: none;
        }
        .chat-btn-mini:hover {
            background: var(--primary);
            color: white;
            transform: scale(1.1);
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/dashboard_sidebar.php'; ?>
        
        <main class="main-content">
            <div class="explore-container">
                <div class="search-sidebar">
                    <div>
                        <h2 style="font-size: 1.5rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.5rem;">Explore</h2>
                        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.5rem;">Find books available near you</p>
                        
                        <form action="explore.php" method="GET">
                            <div class="form-group">
                                <label class="form-label">Search</label>
                                <div style="position: relative;">
                                    <i class='bx bx-search' style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                                    <input type="text" name="query" class="form-input" style="padding-left: 2.75rem;" value="<?php echo htmlspecialchars($filters['query']); ?>" placeholder="Title, author, or ISBN...">
                                </div>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div class="form-group">
                                    <label class="form-label">Provider</label>
                                    <select name="role" class="form-input">
                                        <option value="">All</option>
                                        <option value="user" <?php echo $filters['role'] == 'user' ? 'selected' : ''; ?>>Users</option>
                                        <option value="library" <?php echo $filters['role'] == 'library' ? 'selected' : ''; ?>>Library</option>
                                        <option value="bookstore" <?php echo $filters['role'] == 'bookstore' ? 'selected' : ''; ?>>Store</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Type</label>
                                    <select name="type" class="form-input">
                                        <option value="">Any</option>
                                        <option value="borrow" <?php echo $filters['type'] == 'borrow' ? 'selected' : ''; ?>>Borrow</option>
                                        <option value="sell" <?php echo $filters['type'] == 'sell' ? 'selected' : ''; ?>>Buy</option>
                                        <option value="exchange" <?php echo $filters['type'] == 'exchange' ? 'selected' : ''; ?>>Trade</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 2rem;">
                                <label class="form-label">Category</label>
                                <select name="category" class="form-input">
                                    <option value="">All Categories</option>
                                    <?php 
                                    $cats = ['Authentication', 'Education', 'Fiction', 'Non-Fiction', 'Sci-Fi', 'Romance', 'Mystery', 'Self-Help', 'Business', 'History'];
                                    foreach($cats as $c) {
                                        $sel = ($filters['category'] == $c) ? 'selected' : '';
                                        echo "<option value='$c' $sel>$c</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary w-full">Apply Filters</button>
                        </form>
                    </div>

                    <div>
                        <div class="filter-section-title">Results (<?php echo count($results); ?>)</div>
                        <div class="results-list">
                            <?php foreach ($results as $item): ?>
                                <div class="book-card-mini" onclick="window.location.href='book_details.php?id=<?php echo $item['id']; ?>'">
                                    <img src="<?php echo $item['cover_image'] ?: 'assets/img/book-placeholder.jpg'; ?>" class="book-mini-img" alt="Book Cover">
                                    <div class="book-mini-content">
                                        <div>
                                            <div style="font-weight: 700; color: var(--text-main); font-size: 0.95rem; margin-bottom: 2px;"><?php echo htmlspecialchars($item['title']); ?></div>
                                            <div style="font-size: 0.8rem; color: var(--text-muted);">by <?php echo htmlspecialchars($item['author']); ?></div>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <span class="badge badge-<?php echo $item['listing_type']; ?>"><?php echo ucfirst($item['listing_type']); ?></span>
                                            <span style="font-weight: 800; color: var(--primary); font-size: 1rem;">₹<?php echo number_format($item['price'], 2); ?></span>
                                        </div>
                                    </div>
                                    <a href="chat/index.php?user=<?php echo $item['user_id']; ?>" onclick="event.stopPropagation();" class="chat-btn-mini" title="Chat with Owner">
                                        <i class='bx bx-message-square-dots'></i>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div style="position: relative; height: 100%;">
                    <div id="map"></div>
                    <!-- Map Search Overlay -->
                    <div style="position: absolute; top: 10px; right: 10px; z-index: 1000; background: white; padding: 10px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); display: flex; gap: 5px;">
                        <input type="text" id="map-loc-search" placeholder="Go to city..." style="border: 1px solid #ccc; padding: 5px 10px; border-radius: 4px; outline: none;">
                        <button class="btn btn-primary btn-sm" onclick="searchMapLoc()">Go</button>
                        <button class="btn btn-outline btn-sm" onclick="useMyLoc()" title="Use Current Location" style="border: 1px solid #ccc;"><i class='bx bx-current-location'></i></button>
                    </div>
                </div>
            </div>
        </main>
    </div>


    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const map = L.map('map').setView([12.9716, 77.5946], 12); // Default to Bangalore center

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        const markers = <?php echo json_encode($results); ?>;
        const markerGroup = L.featureGroup();

        markers.forEach(m => {
            if (m.latitude && m.longitude) {
                const marker = L.marker([m.latitude, m.longitude])
                    .bindPopup(`
                        <div style="font-family: inherit; padding: 5px; min-width: 180px;">
                            <img src="${m.cover_image || 'https://images.unsplash.com/photo-1543004218-ee141104975a?w=100'}" style="width: 100%; height: 100px; object-fit: cover; border-radius: 8px; margin-bottom: 10px;">
                            <strong style="display:block; font-size: 1.1rem; margin-bottom: 2px; color: var(--text-main);">${m.title}</strong>
                            <span style="display:block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 10px;">by ${m.author}</span>
                            
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                <span class="badge badge-${m.listing_type}" style="font-size: 0.7rem;">${m.listing_type}</span>
                                <strong style="color: var(--primary); font-size: 1.1rem;">₹${m.price}</strong>
                            </div>
                            
                            <div style="display: grid; gap: 0.5rem;">
                                <a href="book_details.php?id=${m.id}" class="btn btn-primary btn-sm" style="text-decoration: none; color: white;">Get Book</a>
                                <a href="chat/index.php?user=${m.user_id}" class="btn btn-outline btn-sm" style="text-decoration: none; color: var(--text-main); border: 1px solid var(--border-color);">
                                    <i class='bx bx-message-square-dots'></i> Chat with Owner
                                </a>
                            </div>
                        </div>
                    `);
                markerGroup.addLayer(marker);
            }
        });

        markerGroup.addTo(map);
        if (markers.length > 0) {
            map.fitBounds(markerGroup.getBounds().pad(0.1));
        }

        function searchMapLoc() {
            const query = document.getElementById('map-loc-search').value;
            if(!query) return;

            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(data => {
                    if(data && data.length > 0) {
                        const lat = data[0].lat;
                        const lon = data[0].lon;
                        map.setView([lat, lon], 13);
                    } else {
                        alert('Location not found');
                    }
                })
                .catch(err => console.error(err));
        }

        function useMyLoc() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(position => {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    map.setView([lat, lng], 13);
                }, () => {
                    alert('Unable to retrieve your location');
                });
            } else {
                alert('Geolocation is not supported by your browser');
            }
        }
    </script>
</body>
</html>
