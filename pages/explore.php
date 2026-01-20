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
        'has_location' => true
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
    'has_location' => true
];

$results = searchListingsAdvanced($filters);
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $_SESSION['theme_mode'] ?? 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Explore Books | BOOK-B</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=1.1">
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

        /* Toggle Switch */
        .switch { position: relative; display: inline-block; width: 44px; height: 22px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: var(--primary); }
        input:checked + .slider:before { transform: translateX(22px); }
        
        .distance-label {
            font-size: 0.7rem; color: var(--primary); font-weight: 700;
            display: flex; align-items: center; gap: 2px;
        }

        /* Accuracy Circle */
        .accuracy-circle {
            border: 2px solid var(--primary);
            background: rgba(var(--primary-rgb), 0.1);
            border-radius: 50%;
            pointer-events: none;
        }

        /* Pulsing Pin Marker */
        .pulsing-marker { position: relative; }
        .pulsing-marker .pin {
            width: 14px; height: 14px; background: var(--primary);
            border: 2px solid white; border-radius: 50%;
            position: absolute; z-index: 10;
        }
        .pulsing-marker .pulse {
            width: 30px; height: 30px; background: var(--primary);
            border-radius: 50%; position: absolute;
            top: -8px; left: -8px; opacity: 0.4;
            animation: pin-pulse 1.5s infinite;
        }
        @keyframes pin-pulse {
            0% { transform: scale(0.5); opacity: 0.5; }
            100% { transform: scale(2.5); opacity: 0; }
        }
    </style>
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" />
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include '../includes/dashboard_sidebar.php'; ?>
        
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
                            
                            <div style="margin-top: 1rem; padding: 0.75rem; background: white; border-radius: 8px; border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between;">
                                <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-main);">Search as I move</div>
                                <label class="switch">
                                    <input type="checkbox" id="live-search-toggle" checked>
                                    <span class="slider round"></span>
                                </label>
                            </div>
                        </form>
                    </div>

                    <div>
                        <div class="filter-section-title">Results (<?php echo count($results); ?>)</div>
                        <div class="results-list">
                            <?php foreach ($results as $item): ?>
                                <div class="book-card-mini" onclick="window.location.href='book_details.php?id=<?php echo $item['id']; ?>'">
                                    <img src="<?php echo $item['cover_image'] ?: 'https://images.unsplash.com/photo-1543004218-ee141104975a?auto=format&fit=crop&q=80&w=800'; ?>" 
                                         class="book-mini-img" alt="Book Cover"
                                         onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1543004218-ee141104975a?auto=format&fit=crop&q=80&w=800';">
                                    <div class="book-mini-content">
                                        <div>
                                            <div style="font-weight: 700; color: var(--text-main); font-size: 0.95rem; margin-bottom: 2px;"><?php echo htmlspecialchars($item['title']); ?></div>
                                            <div style="font-size: 0.8rem; color: var(--text-muted);">by <?php echo htmlspecialchars($item['author']); ?></div>
                                                <a href="user_profile.php?id=<?php echo $item['user_id']; ?>" onclick="event.stopPropagation();" style="display: flex; flex-direction: column; gap: 2px; text-decoration: none;">
                                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                        <div style="display: flex; align-items: center; color: #f59e0b; font-size: 0.8rem; font-weight: 700;">
                                                            <i class='bx bxs-star'></i> <?php echo number_format($item['average_rating'], 1); ?>
                                                        </div>
                                                        <div style="font-size: 0.75rem; color: var(--text-muted); background: #f1f5f9; padding: 1px 6px; border-radius: 4px;">
                                                            <i class='bx bxs-shield-check'></i> <?php echo $item['trust_score']; ?>
                                                        </div>
                                                    </div>
                                                    <span style="font-size: 0.65rem; color: var(--primary); font-weight: 600;">View Feedback</span>
                                                </a>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <div style="display: flex; flex-direction: column; gap: 2px;">
                                                <span class="badge badge-<?php echo $item['listing_type']; ?>"><?php echo ucfirst($item['listing_type']); ?></span>
                                                <?php if (isset($item['distance'])): ?>
                                                    <span style="font-size: 0.7rem; color: var(--primary); font-weight: 700;">
                                                        <i class='bx bx-navigation'></i> <?php echo number_format($item['distance'], 1); ?> km away
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div style="text-align: right;">
                                                <div style="font-size: 0.75rem; color: var(--text-muted);"><i class='bx bx-layer'></i> Qty: <?php echo $item['quantity']; ?></div>
                                                <span style="font-weight: 800; color: var(--primary); font-size: 1rem;">₹<?php echo number_format($item['price'], 2); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php if ($item['quantity'] <= 0): ?>
                                        <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.6); display: flex; align-items: center; justify-content: center; z-index: 5; border-radius: var(--radius-md);">
                                            <span class="badge" style="background: #ef4444; color: white;">Sold Out</span>
                                        </div>
                                    <?php endif; ?>
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
    <script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>
    <script>
        const initialLat = <?php echo ($user['service_start_lat'] ?? 9.4124); ?>;
        const initialLng = <?php echo ($user['service_start_lng'] ?? 76.6946); ?>;
        const map = L.map('map').setView([initialLat, initialLng], 12);

        L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
            attribution: '©OpenStreetMap ©CartoDB'
        }).addTo(map);

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
                document.querySelector('.filter-section-title').innerText = 'Results (0)';
                return;
            }

            listings.forEach(m => {
                if (m.latitude && m.longitude) {
                    const marker = L.marker([m.latitude, m.longitude])
                        .bindPopup(`
                            <div style="font-family: inherit; padding: 5px; min-width: 180px;">
                                <img src="${m.cover_image || 'https://images.unsplash.com/photo-1543004218-ee141104975a?auto=format&fit=crop&q=80&w=800'}" 
                                     style="width: 100%; height: 100px; object-fit: cover; border-radius: 8px; margin-bottom: 10px;"
                                     onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1543004218-ee141104975a?auto=format&fit=crop&q=80&w=800';">
                                <strong style="display:block; font-size: 1.1rem; margin-bottom: 2px; color: var(--text-main);">${m.title}</strong>
                                <span style="display:block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 10px;">by ${m.author}</span>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                    <span class="badge badge-${m.listing_type}" style="font-size: 0.7rem;">${m.listing_type}</span>
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <span style="color: #f59e0b; font-size: 0.85rem; font-weight: 700;"><i class='bx bxs-star'></i> ${m.average_rating}</span>
                                    </div>
                                </div>
                                <div style="display: grid; gap: 0.5rem;">
                                    <a href="book_details.php?id=${m.id}" class="btn btn-primary btn-sm" style="text-decoration: none; color: white;">Get Book</a>
                                    <a href="user_profile.php?id=${m.user_id}" class="btn btn-outline btn-sm" style="text-decoration: none; font-size: 0.75rem;">View User Reviews</a>
                                </div>
                            </div>
                        `);
                    markerCluster.addLayer(marker);
                }

                // Sidebar Card
                const card = document.createElement('div');
                card.className = 'book-card-mini';
                card.onclick = () => window.location.href = `book_details.php?id=${m.id}`;
                card.innerHTML = `
                    <img src="${m.cover_image || 'https://images.unsplash.com/photo-1543004218-ee141104975a?auto=format&fit=crop&q=80&w=800'}" 
                         class="book-mini-img" alt="Book Cover"
                         onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1543004218-ee141104975a?auto=format&fit=crop&q=80&w=800';">
                    <div class="book-mini-content">
                        <div>
                            <div style="font-weight: 700; color: var(--text-main); font-size: 0.95rem; margin-bottom: 2px;">${m.title}</div>
                            <div style="font-size: 0.8rem; color: var(--text-muted);">by ${m.author}</div>
                                <a href="user_profile.php?id=${m.user_id}" style="display: flex; flex-direction: column; gap: 2px; text-decoration: none;" onclick="event.stopPropagation();">
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <div style="display: flex; align-items: center; color: #f59e0b; font-size: 0.8rem; font-weight: 700;">
                                            <i class='bx bxs-star'></i> ${m.average_rating}
                                        </div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted); background: #f1f5f9; padding: 1px 6px; border-radius: 4px;">
                                            <i class='bx bxs-shield-check'></i> ${m.trust_score}
                                        </div>
                                    </div>
                                    <div style="font-size: 0.65rem; color: var(--primary); font-weight: 600;">View Reviews</div>
                                </a>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div style="display: flex; flex-direction: column; gap: 2px;">
                                <span class="badge badge-${m.listing_type}">${m.listing_type}</span>
                                ${m.distance ? `
                                    <span class="distance-label">
                                        <i class='bx bx-navigation'></i> ${parseFloat(m.distance).toFixed(1)} km
                                    </span>
                                ` : ''}
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 0.75rem; color: var(--text-muted);"><i class='bx bx-layer'></i> Qty: ${m.quantity}</div>
                                <span style="font-weight: 800; color: var(--primary); font-size: 1rem;">₹${parseFloat(m.price).toFixed(2)}</span>
                            </div>
                        </div>
                    </div>
                `;
                resultsList.appendChild(card);
            });
            
            document.querySelector('.filter-section-title').innerText = `Results (${listings.length})`;
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
                query: '<?php echo $filters['query']; ?>',
                role: '<?php echo $filters['role']; ?>',
                type: '<?php echo $filters['type']; ?>',
                category: '<?php echo $filters['category']; ?>'
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
                        alert('Location not found in India');
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
                    alert(msg);
                }, {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                });
            } else {
                alert('Geolocation is not supported by your browser');
            }
        }
    </script>
</body>
</html>
