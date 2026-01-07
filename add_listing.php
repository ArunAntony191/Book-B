<?php
require_once 'includes/db_helper.php';
require_once 'paths.php';
include 'includes/dashboard_header.php';

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
    header("Location: login.php");
    exit();
}

$error = '';
$success = '';

// Edit Mode Logic
$editId = $_GET['edit'] ?? 0;
$editData = null;
if ($editId) {
    $editData = getListingWithQuantity($editId);
    if (!$editData || $editData['user_id'] != $userId) {
        header("Location: dashboard_user.php");
        exit();
    }
}

// Check current credits
$currentCredits = getUserCredits($userId);
// Requirement: If editing, no credit check needed. If new, need 10.
$canList = ($user['role'] === 'admin') || ($currentCredits >= 10) || $editId;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canList) {
        $error = "Insufficient credits. You need at least 10 credits to list a new book.";
    } else {
        $postEditId = $_POST['edit_id'] ?? 0;
        $title = $_POST['title'] ?? '';
        $author = $_POST['author'] ?? '';
        $type = $_POST['listing_type'] ?? 'borrow';
        $price = $_POST['price'] ?? 0;
        $location = $_POST['location_name'] ?? '';
        $lat = $_POST['latitude'] ?? null;
        $lng = $_POST['longitude'] ?? null;
        $description = $_POST['description'] ?? '';
        $categories = isset($_POST['categories']) ? implode(', ', $_POST['categories']) : '';
        $condition = $_POST['condition'] ?? 'good';
        $visibility = $_POST['visibility'] ?? 'public';
        $communityId = !empty($_POST['community_id']) ? $_POST['community_id'] : null;
        
        // Quantity (only for library and bookstore)
        $quantity = 1;
        if ($user['role'] === 'library' || $user['role'] === 'bookstore') {
            $quantity = isset($_POST['quantity']) ? max(1, intval($_POST['quantity'])) : 1;
        }
        
        // Credit cost
        $creditCost = isset($_POST['credit_cost']) ? max(1, intval($_POST['credit_cost'])) : 10;
        
        // Handle Cover Upload (URL or File)
        $cover = $_POST['cover_image'] ?? ''; 
        if ($postEditId && empty($cover)) {
            $cover = $editData['cover_image'] ?? '';
        }
        
        // If a file is uploaded, it takes precedence
        if (isset($_FILES['cover_upload']) && $_FILES['cover_upload']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'images/books/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            
            $fileName = time() . '_' . basename($_FILES['cover_upload']['name']);
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['cover_upload']['tmp_name'], $targetPath)) {
                $cover = $targetPath;
            }
        }

        if ($title && $author && $lat && $lng) {
            if ($price < 0) {
                $error = "Price cannot be less than 0.";
            } else {
                if ($postEditId) {
                    // Update Logic
                    if (updateListing($postEditId, $userId, $title, $author, $type, $price, $location, $lat, $lng, $cover, $description, $categories, $condition, $visibility, $communityId, $quantity, $creditCost)) {
                        $success = "Successfully updated your book!";
                        $editData = getListingWithQuantity($postEditId); // Refresh data
                    } else {
                        $error = "Failed to update listing.";
                    }
                } else {
                    // Create Logic
                    if (addListing($userId, $title, $author, $type, $price, $location, $lat, $lng, $cover, $description, $categories, $condition, $visibility, $communityId, $quantity, $creditCost)) {
                        deductCredits($userId, 10, 'listing_fee', "Book listing fee: {$title}");
                        $success = "Successfully listed your book! 10 credits have been deducted.";
                    } else {
                        $error = "Failed to add listing.";
                    }
                }
            }
        } else {
            $error = "Please fill all required fields and pick a location on the map.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Listing | BOOK-B</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        .add-container {
            max-width: 900px;
            margin: 2rem auto;
            background: white;
            padding: 2.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        #picker-map {
            height: 350px;
            border-radius: var(--radius-md);
            margin-top: 1rem;
            border: 2px solid var(--border-color);
        }
        .step-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary);
        }
        }
        
        /* New Styles */
        .search-hero {
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: var(--radius-md);
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
        }
        .suggestions-list {
            position: absolute;
            top: 100%;
            left: 0; right: 0;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            display: none;
            margin-top: 0.5rem;
        }
        .suggestion-item {
            padding: 1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 1rem;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.2s;
        }
        .suggestion-item:hover { background: #f8fafc; }
        .suggestion-thumb { width: 45px; height: 65px; object-fit: cover; border-radius: 4px; border: 1px solid #e2e8f0; }
        
        .type-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .type-card {
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 1.5rem 1rem;
            cursor: pointer;
            text-align: center;
            transition: all 0.2s;
            position: relative;
        }
        .type-card:hover { border-color: var(--primary-light); background: #f8fafc; }
        .type-card.active { border-color: var(--primary); background: #eff6ff; color: var(--primary); }
        .type-card i { font-size: 1.8rem; margin-bottom: 0.5rem; display: block; }
        .type-input { display: none; }
        
        /* Upload Zone */
        .upload-zone {
            border: 2px dashed #cbd5e1;
            border-radius: var(--radius-md);
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            background: #f8fafc;
            position: relative;
        }
        .upload-zone:hover { border-color: var(--primary); background: #eff6ff; }
        .upload-zone.has-image { border-style: solid; padding: 0; overflow: hidden; height: 300px; display: flex; align-items: center; justify-content: center; }
        .upload-preview { max-width: 100%; max-height: 100%; object-fit: contain; }
        
        /* Category Pills */
        .category-grid { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.5rem; }
        .cat-pill {
            padding: 0.4rem 1rem;
            border-radius: 20px;
            background: #f1f5f9;
            color: var(--text-muted);
            cursor: pointer;
            border: 1px solid transparent;
            font-size: 0.85rem;
            user-select: none;
            transition: all 0.2s;
        }
        .cat-pill:hover { background: #e2e8f0; }
        .cat-pill.selected { background: var(--primary); color: white; border-color: var(--primary); }
        .cat-checkbox { display: none; }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/dashboard_sidebar.php'; ?>

        <main class="main-content">
            <div class="add-container">
                <h1 style="font-size: 1.8rem; font-weight: 800; margin-bottom: 0.5rem;"><?php echo $editId ? 'Edit Book Listing' : 'List a New Book'; ?></h1>
                <p style="color: var(--text-muted); margin-bottom: 2rem;"><?php echo $editId ? 'Update your book details below.' : 'Share your books with the community and start swaps or sales.'; ?></p>

                <?php if ($success): ?>
                    <div style="background: #ecfdf5; color: #059669; padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; border: 1px solid #10b981;">
                        <?php echo $success; ?>
                        <a href="dashboard_user.php" style="margin-left: 1rem; font-weight: 700; text-decoration: underline;">Go to Dashboard</a>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div style="background: #fef2f2; color: #dc2626; padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; border: 1px solid #ef4444;">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <?php if ($editId): ?>
                        <input type="hidden" name="edit_id" value="<?php echo $editId; ?>">
                    <?php endif; ?>
                    
                    <!-- Smart Search Section -->
                    <div class="search-hero">
                        <label class="form-label" style="font-size: 1.1rem; color: var(--primary); margin-bottom: 1rem; display: block;">🚀 Auto-fill details</label>
                        <div style="position: relative;">
                            <i class='bx bx-search' style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); font-size: 1.2rem; color: var(--text-muted);"></i>
                            <input type="text" id="book-search" class="form-input" style="padding-left: 3rem; height: 50px; font-size: 1rem;" placeholder="Type ISBN or Book Title (e.g. 'Atomic Habits')...">
                            <div class="suggestions-list" id="suggestions"></div>
                        </div>
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.5rem; margin-bottom: 0;">We'll fill the title, author, description, and cover for you!</p>
                    </div>

                    
                    <div class="form-grid" style="grid-template-columns: 2fr 1fr; align-items: start;">
                        <!-- Left Column: Inputs -->
                        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Book Title *</label>
                                    <input type="text" name="title" id="input_title" class="form-input" required placeholder="Book Title" value="<?php echo htmlspecialchars($editData['title'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Author Name *</label>
                                    <input type="text" name="author" id="input_author" class="form-input" required placeholder="Author Name" value="<?php echo htmlspecialchars($editData['author'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Category (Select multiple)</label>
                                <div class="category-grid">
                                    <?php 
                                    $categories = ['Authentication', 'Education', 'Fiction', 'Non-Fiction', 'Sci-Fi', 'Romance', 'Mystery', 'Self-Help', 'Business', 'History', 'Other'];
                                    $selectedCats = explode(', ', $editData['category'] ?? '');
                                    foreach ($categories as $cat): 
                                        $selected = in_array($cat, $selectedCats) ? 'selected' : '';
                                        $checked = in_array($cat, $selectedCats) ? 'checked' : '';
                                    ?>
                                        <label class="cat-pill <?php echo $selected; ?>">
                                            <input type="checkbox" name="categories[]" value="<?php echo $cat; ?>" class="cat-checkbox" onchange="toggleCat(this)" <?php echo $checked; ?>>
                                            <?php echo $cat; ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Description</label>
                                <textarea name="description" id="input_description" class="form-input" rows="4" placeholder="Tell us about the book..."><?php echo htmlspecialchars($editData['description'] ?? ''); ?></textarea>
                            </div>
                            <!-- Location Picker -->
                            <div class="form-group">
                                <label class="form-label">Interact Location</label>
                                <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem;">
                                    <input type="text" id="map-search-input" class="form-input" placeholder="Search city or area (e.g. Kottayam)...">
                                    <button type="button" class="btn btn-primary" onclick="searchMapLocation()">Search</button>
                                    <button type="button" class="btn btn-outline" onclick="useMyLocation()" title="Use Current Location"><i class='bx bx-current-location'></i></button>
                                </div>
                                <div id="picker-map"></div>
                                <input type="hidden" name="location_name" id="location_name" value="<?php echo htmlspecialchars($editData['location'] ?? ''); ?>" required>
                                <input type="hidden" name="latitude" id="lat" value="<?php echo htmlspecialchars($editData['latitude'] ?? ''); ?>" required>
                                <input type="hidden" name="longitude" id="lng" value="<?php echo htmlspecialchars($editData['longitude'] ?? ''); ?>" required>
                                <p class="form-hint" style="margin-top: 0.5rem;"><i class='bx bx-map'></i> Search for your city, then click the exact spot.</p>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Listing Type</label>
                                <div class="type-grid">
                                    <?php $listingType = $editData['listing_type'] ?? 'borrow'; ?>
                                    <label class="type-card <?php echo $listingType === 'borrow' ? 'active' : ''; ?>" onclick="selectType('borrow')">
                                        <input type="radio" name="listing_type" value="borrow" class="type-input" <?php echo $listingType === 'borrow' ? 'checked' : ''; ?>>
                                        <i class='bx bx-book-reader'></i>
                                        <div style="font-weight: 700;">Lend</div>
                                        <small style="font-size: 0.75rem;">Free/Deposit</small>
                                    </label>
                                    <label class="type-card <?php echo $listingType === 'sell' ? 'active' : ''; ?>" onclick="selectType('sell')">
                                        <input type="radio" name="listing_type" value="sell" class="type-input" <?php echo $listingType === 'sell' ? 'checked' : ''; ?>>
                                        <i class='bx bx-rupee'></i>
                                        <div style="font-weight: 700;">Sell</div>
                                        <small style="font-size: 0.75rem;">Get Paid</small>
                                    </label>
                                    <label class="type-card <?php echo $listingType === 'exchange' ? 'active' : ''; ?>" onclick="selectType('exchange')">
                                        <input type="radio" name="listing_type" value="exchange" class="type-input" <?php echo $listingType === 'exchange' ? 'checked' : ''; ?>>
                                        <i class='bx bx-refresh'></i>
                                        <div style="font-weight: 700;">Swap</div>
                                        <small style="font-size: 0.75rem;">Book for Book</small>
                                    </label>
                                </div>
                            </div>
                            
                             <div class="form-group" id="price-group" style="display: <?php echo ($listingType === 'sell') ? 'block' : 'none'; ?>;">
                                <label class="form-label">Selling Price (₹)</label>
                                <input type="number" name="price" id="input_price" class="form-input" value="<?php echo htmlspecialchars($editData['price'] ?? 0); ?>" min="0" oninput="validity.valid||(value='');">
                                <p style="color: red; font-size: 0.8rem; display: none;" id="price-error">Price cannot be less than 0</p>
                            </div>

                            <?php if ($user['role'] === 'library' || $user['role'] === 'bookstore'): ?>
                            <!-- Quantity Field (Only for Libraries and Bookstores) -->
                            <div class="form-group">
                                <label class="form-label">
                                    <i class='bx bx-package'></i> Quantity Available
                                </label>
                                <input type="number" name="quantity" class="form-input" value="<?php echo htmlspecialchars($editData['quantity'] ?? 1); ?>" min="1" placeholder="Number of copies">
                                <p class="form-hint" style="margin-top: 0.5rem;">
                                    <i class='bx bx-info-circle'></i> Total number of copies you have in stock
                                </p>
                            </div>
                            <?php endif; ?>

                            <!-- Credit Cost Field -->
                            <div class="form-group">
                                <label class="form-label">
                                    <i class='bx bx-wallet'></i> Credit Cost
                                </label>
                                <input type="number" name="credit_cost" class="form-input" value="<?php echo htmlspecialchars($editData['credit_cost'] ?? 10); ?>" min="1" placeholder="Credits required">
                                <p class="form-hint" style="margin-top: 0.5rem;">
                                    <i class='bx bx-info-circle'></i> Credits borrowers need to pay (default: 10)
                                </p>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Book Condition</label>
                                <select name="condition" class="form-input">
                                    <?php $currentCondition = $editData['condition_status'] ?? 'good'; ?>
                                    <option value="new" <?php echo $currentCondition == 'new' ? 'selected' : ''; ?>>New</option>
                                    <option value="like_new" <?php echo $currentCondition == 'like_new' ? 'selected' : ''; ?>>Like New</option>
                                    <option value="good" <?php echo $currentCondition == 'good' ? 'selected' : ''; ?>>Good</option>
                                    <option value="fair" <?php echo $currentCondition == 'fair' ? 'selected' : ''; ?>>Fair</option>
                                    <option value="poor" <?php echo $currentCondition == 'poor' ? 'selected' : ''; ?>>Poor</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Visibility</label>
                                <div style="display: flex; gap: 1rem;">
                                    <?php $currentVisual = $editData['visibility'] ?? 'public'; ?>
                                    <label class="cat-pill <?php echo $currentVisual == 'public' ? 'selected' : ''; ?>" onclick="toggleVisibility('public', this)">
                                        <input type="radio" name="visibility" value="public" style="display:none;" <?php echo $currentVisual == 'public' ? 'checked' : ''; ?>>
                                        <i class='bx bx-world'></i> Public
                                    </label>
                                    <label class="cat-pill <?php echo $currentVisual == 'community' ? 'selected' : ''; ?>" onclick="toggleVisibility('community', this)">
                                        <input type="radio" name="visibility" value="community" style="display:none;" <?php echo $currentVisual == 'community' ? 'checked' : ''; ?>>
                                        <i class='bx bx-group'></i> Community Only
                                    </label>
                                </div>
                                <div id="community-select" style="display: <?php echo $currentVisual == 'community' ? 'block' : 'none'; ?>; margin-top: 1rem;">
                                    <label class="form-label" style="font-size: 0.9rem;">Select Community</label>
                                    <select name="community_id" class="form-input">
                                        <option value="">-- Choose a Community --</option>
                                        <?php 
                                        $selectedComm = $editData['community_id'] ?? 0;
                                        ?>
                                        <option value="1" <?php echo $selectedComm == 1 ? 'selected' : ''; ?>>Book Lovers Club</option>
                                        <option value="2" <?php echo $selectedComm == 2 ? 'selected' : ''; ?>>Sci-Fi Geeks</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Upload -->
                        <div class="form-group">
                            <label class="form-label">Book Cover</label>
                            <input type="hidden" name="cover_image" id="input_cover_url" value="<?php echo htmlspecialchars($editData['cover_image'] ?? ''); ?>"> <!-- For API URL -->
                            <div class="upload-zone" id="drop-zone" onclick="document.getElementById('file_input').click()">
                                <input type="file" name="cover_upload" id="file_input" style="display: none;" accept="image/*" onchange="previewFile(this)">
                                <div id="upload-content" style="<?php echo ($editData['cover_image'] ?? '') ? 'display: none;' : ''; ?>">
                                    <i class='bx bx-cloud-upload' style="font-size: 3rem; color: #cbd5e1; margin-bottom: 1rem;"></i>
                                    <p style="font-weight: 600; margin-bottom: 0.5rem;">Click to Upload</p>
                                    <p style="font-size: 0.8rem; color: var(--text-muted);">or drag and drop here</p>
                                </div>
                                <img id="cover_preview" class="upload-preview" src="<?php echo htmlspecialchars($editData['cover_image'] ?? ''); ?>" style="<?php echo ($editData['cover_image'] ?? '') ? 'display: block;' : 'display: none;'; ?>">
                            </div>
                        </div>
                    </div>
 
                    
                    <div style="margin-top: 2.5rem; display: flex; gap: 1rem; align-items: center;">
                        <button type="submit" class="btn btn-primary" style="padding: 0.8rem 2.5rem; font-size: 1rem;"><?php echo $editId ? 'Update Book' : 'Publish Book'; ?></button>
                        <a href="dashboard_user.php" class="btn" style="padding: 0.8rem 2rem; background: transparent; color: var(--text-muted);">Cancel</a>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const initialLat = <?php echo ($editData['latitude'] ?? 12.9716); ?>;
        const initialLng = <?php echo ($editData['longitude'] ?? 77.5946); ?>;
        const map = L.map('picker-map').setView([initialLat, initialLng], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

        let marker;
        if (<?php echo $editId ? 'true' : 'false'; ?>) {
            marker = L.marker([initialLat, initialLng]).addTo(map);
        }

        map.on('click', function(e) {
            updateLocation(e.latlng.lat, e.latlng.lng);
        });

        function updateLocation(lat, lng) {
            if (marker) map.removeLayer(marker);
            marker = L.marker([lat, lng]).addTo(map);

            document.getElementById('lat').value = lat;
            document.getElementById('lng').value = lng;

            // Reverse geocoding
            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                .then(res => res.json())
                .then(data => {
                    document.getElementById('location_name').value = data.display_name.split(',')[0] + ', ' + (data.address.city || data.address.state || '');
                });
        }

        // Search Location Logic
        function searchMapLocation() {
            const query = document.getElementById('map-search-input').value;
            if(!query) return;

            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(data => {
                    if(data && data.length > 0) {
                        const lat = data[0].lat;
                        const lon = data[0].lon;
                        map.setView([lat, lon], 13);
                        updateLocation(lat, lon);
                    } else {
                        alert('Location not found');
                    }
                })
                .catch(err => console.error(err));
        }

        function useMyLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(position => {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    map.setView([lat, lng], 15);
                    updateLocation(lat, lng);
                }, () => {
                    alert('Unable to retrieve your location');
                });
            } else {
                alert('Geolocation is not supported by your browser');
            }
        }

        // --- New Logic for Auto-fill & UI ---
        
        // 1. Debounce function to limit API calls
        function debounce(func, wait) {
            let timeout;
            return function(...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        }

        // 2. Book Search Integration
        const searchInput = document.getElementById('book-search');
        const suggestions = document.getElementById('suggestions');

        searchInput.addEventListener('input', debounce(async (e) => {
            const q = e.target.value;
            if (q.length < 3) { suggestions.style.display = 'none'; return; }
            
            try {
                // Using Google Books API (Public)
                const res = await fetch(`https://www.googleapis.com/books/v1/volumes?q=${encodeURIComponent(q)}&maxResults=5`);
                const data = await res.json();
                
                suggestions.innerHTML = '';
                if (data.items) {
                    data.items.forEach(book => {
                        const info = book.volumeInfo;
                        const thumb = info.imageLinks?.thumbnail || 'assets/images/book-placeholder.jpg';
                        const title = info.title;
                        const author = info.authors ? info.authors[0] : 'Unknown Author';
                        
                        const div = document.createElement('div');
                        div.className = 'suggestion-item';
                        div.innerHTML = `
                            <img src="${thumb}" class="suggestion-thumb" onerror="this.src='https://via.placeholder.com/45x65?text=?'">
                            <div>
                                <div style="font-weight: 600; font-size: 0.95rem;">${title}</div>
                                <div style="font-size: 0.8rem; color: var(--text-muted);">${author}</div>
                            </div>
                        `;
                        div.onclick = () => fillBook(info);
                        suggestions.appendChild(div);
                    });
                    suggestions.style.display = 'block';
                }
            } catch (err) {
                console.error(err);
            }
        }, 400));

        // Hide suggestions when clicking outside
        document.addEventListener('click', (e) => {
            if (!searchInput.contains(e.target) && !suggestions.contains(e.target)) {
                suggestions.style.display = 'none';
            }
        });

        function fillBook(info) {
            document.getElementById('input_title').value = info.title;
            document.getElementById('input_author').value = info.authors ? info.authors[0] : '';
            document.getElementById('input_description').value = info.description ? info.description : '';
            
            // Get higher quality image
            let imgUrl = info.imageLinks?.thumbnail || '';
            if (imgUrl) imgUrl = imgUrl.replace('http:', 'https:').replace('&edge=curl', '');
            
            document.getElementById('input_cover_url').value = imgUrl; // Store as backup
            showPreview(imgUrl);
            
            // Try to auto-select categories if available
            if (info.categories) {
                const cats = info.categories.map(c => c.toLowerCase());
                document.querySelectorAll('.cat-checkbox').forEach(box => {
                    if (cats.some(c => c.includes(box.value.toLowerCase()))) {
                        box.checked = true;
                        box.parentElement.classList.add('selected');
                    }
                });
            }

            suggestions.style.display = 'none';
            searchInput.value = ''; 
        }

        // Toggle category style
        function toggleCat(checkbox) {
            if (checkbox.checked) checkbox.parentElement.classList.add('selected');
            else checkbox.parentElement.classList.remove('selected');
        }

        // --- File Upload Logic ---
        function previewFile(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    showPreview(e.target.result);
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function showPreview(src) {
            const zone = document.getElementById('drop-zone');
            const img = document.getElementById('cover_preview');
            const content = document.getElementById('upload-content');
            
            zone.classList.add('has-image');
            content.style.display = 'none';
            img.src = src;
            img.style.display = 'block';
        }

        // Drag & Drop
        const dropZone = document.getElementById('drop-zone');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) { e.preventDefault(); e.stopPropagation(); }

        dropZone.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            document.getElementById('file_input').files = files;
            previewFile(document.getElementById('file_input'));
        }

        function selectType(type) {
            document.querySelectorAll('.type-card').forEach(c => c.classList.remove('active'));
            // Find input with value=type and get its parent label
            const input = document.querySelector(`input[name="listing_type"][value="${type}"]`);
            if (input) {
                input.checked = true;
                input.closest('.type-card').classList.add('active');
            }
            togglePrice(type);
        }

        function togglePrice(val) {
            document.getElementById('price-group').style.display = (val === 'sell') ? 'block' : 'none';
        }

        function toggleVisibility(val, el) {
            // Update UI
            el.parentElement.querySelectorAll('.cat-pill').forEach(p => p.classList.remove('selected'));
            el.classList.add('selected');
            el.querySelector('input').checked = true;

            // Show/Hide Dropdown
            document.getElementById('community-select').style.display = (val === 'community') ? 'block' : 'none';
        }
    </script>
</body>
</html>
