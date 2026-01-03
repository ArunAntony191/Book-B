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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $author = $_POST['author'] ?? '';
    $type = $_POST['listing_type'] ?? 'borrow';
    $price = $_POST['price'] ?? 0;
    $location = $_POST['location_name'] ?? '';
    $lat = $_POST['latitude'] ?? null;
    $lng = $_POST['longitude'] ?? null;
    $cover = $_POST['cover_image'] ?? '';

    if ($title && $author && $lat && $lng) {
        if (addListing($userId, $title, $author, $type, $price, $location, $lat, $lng, $cover)) {
            $success = "Successfully listed your book!";
        } else {
            $error = "Failed to add listing. Please try again.";
        }
    } else {
        $error = "Please fill all required fields and pick a location on the map.";
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
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/dashboard_sidebar.php'; ?>

        <main class="main-content">
            <div class="add-container">
                <h1 style="font-size: 1.8rem; font-weight: 800; margin-bottom: 0.5rem;">List a New Book</h1>
                <p style="color: var(--text-muted); margin-bottom: 2rem;">Share your books with the community and start swaps or sales.</p>

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

                <form method="POST">
                    <div class="step-title"><i class='bx bx-book'></i> 1. Book Information</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Book Title *</label>
                            <input type="text" name="title" class="form-input" required placeholder="e.g. Atomic Habits">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Author Name *</label>
                            <input type="text" name="author" class="form-input" required placeholder="e.g. James Clear">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Listing Type</label>
                            <select name="listing_type" class="form-input" onchange="togglePrice(this.value)">
                                <option value="borrow">Lend (Free/Deposit)</option>
                                <option value="sell">Sell</option>
                                <option value="exchange">Exchange</option>
                            </select>
                        </div>
                        <div class="form-group" id="price-group" style="display: none;">
                            <label class="form-label">Price (₹)</label>
                            <input type="number" name="price" class="form-input" value="0">
                        </div>
                        <div class="form-group" style="grid-column: span 2;">
                            <label class="form-label">Cover Image URL (Optional)</label>
                            <input type="url" name="cover_image" class="form-input" placeholder="https://...">
                        </div>
                    </div>

                    <div class="step-title" style="margin-top: 2rem;"><i class='bx bx-map-pin'></i> 2. Location</div>
                    <p style="font-size: 0.9rem; color: var(--text-muted);">Click on the map to mark where the book is available.</p>
                    
                    <input type="hidden" name="latitude" id="lat">
                    <input type="hidden" name="longitude" id="lng">
                    <div class="form-group">
                        <label class="form-label">Location Name (e.g. Koramangala, Bangalore)</label>
                        <input type="text" name="location_name" id="location_name" class="form-input" placeholder="Searching for location...">
                    </div>
                    
                    <div id="picker-map"></div>

                    <div style="margin-top: 2.5rem; display: flex; gap: 1rem;">
                        <button type="submit" class="btn btn-primary" style="padding: 0.8rem 2.5rem;">Publish Listing</button>
                        <a href="dashboard_user.php" class="btn" style="padding: 0.8rem 2rem; background: #f1f5f9; color: var(--text-main);">Cancel</a>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const map = L.map('picker-map').setView([12.9716, 77.5946], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

        let marker;

        map.on('click', function(e) {
            const lat = e.latlng.lat;
            const lng = e.latlng.lng;

            if (marker) map.removeLayer(marker);
            marker = L.marker([lat, lng]).addTo(map);

            document.getElementById('lat').value = lat;
            document.getElementById('lng').value = lng;

            // Reverse geocoding (simplified)
            fetch(`https://nominatim.openstreetmap.org/reverse?format=json\u0026lat=${lat}\u0026lon=${lng}`)
                .then(res =\u003e res.json())
                .then(data =\u003e {
                    document.getElementById('location_name').value = data.display_name;
                });
        });

        function togglePrice(val) {
            document.getElementById('price-group').style.display = (val === 'sell') ? 'block' : 'none';
        }
    </script>
</body>
</html>
