<?php
require_once 'includes/db_helper.php';
require_once 'paths.php';
session_start();

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
    header("Location: login.php");
    exit();
}

$user = getUserById($userId);
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'firstname' => trim($_POST['firstname']),
        'lastname' => trim($_POST['lastname']),
        'email' => trim($_POST['email']),
        'phone' => trim($_POST['phone']),
        'address' => trim($_POST['address'] ?? ''),
        'service_start_lat' => !empty($_POST['service_start_lat']) ? $_POST['service_start_lat'] : null,
        'service_start_lng' => !empty($_POST['service_start_lng']) ? $_POST['service_start_lng'] : null,
        'service_end_lat' => !empty($_POST['service_end_lat']) ? $_POST['service_end_lat'] : null,
        'service_end_lng' => !empty($_POST['service_end_lng']) ? $_POST['service_end_lng'] : null,
        'is_accepting_deliveries' => isset($_POST['is_accepting_deliveries']) ? 1 : 0
    ];

    if (updateUser($userId, $data)) {
        $success = "Profile updated successfully!";
        // Refresh user data
        $user = getUserById($userId);
        $_SESSION['firstname'] = $user['firstname'];
        $_SESSION['lastname'] = $user['lastname'];
    } else {
        $error = "Failed to update profile. Email might already be in use.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile | BOOK-B</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        .profile-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 3rem;
            max-width: 600px;
            margin: 0 auto;
            box-shadow: var(--shadow-md);
        }
        .profile-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }
        .profile-avatar {
            width: 100px;
            height: 100px;
            background: var(--primary-light);
            color: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            margin: 0 auto 1rem;
            font-weight: 700;
        }
        }
        #route-map {
            height: 300px;
            width: 100%;
            border-radius: var(--radius-md);
            margin-top: 1rem;
            border: 2px solid var(--border-color);
        }
        .map-instruction {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/dashboard_sidebar.php'; ?>
        
        <main class="main-content">
            <div class="profile-card">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?php echo strtoupper($user['firstname'][0] . $user['lastname'][0]); ?>
                    </div>
                    <h1>Edit Your Profile</h1>
                    <p style="color: var(--text-muted);">Keep your contact information up to date</p>
                </div>

                <?php if ($success): ?>
                    <div style="background: #dcfce7; color: #15803d; padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1.5rem;">
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div style="background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1.5rem;">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                        <div class="form-group">
                            <label class="form-label">First Name</label>
                            <input type="text" name="firstname" class="form-input" value="<?php echo htmlspecialchars($user['firstname']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="lastname" class="form-input" value="<?php echo htmlspecialchars($user['lastname']); ?>" required>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>

                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" name="phone" class="form-input" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required
                               pattern="^\+?[0-9]{10,15}$" title="Enter a valid phone number (10-15 digits)">
                    </div>

                    <div class="form-group" style="margin-bottom: 2rem;">
                        <label class="form-label">Delivery Address</label>
                        <textarea name="address" class="form-input" rows="3" placeholder="Enter your full delivery address..."><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                    </div>

                    <?php if ($user['role'] === 'delivery_agent'): ?>
                        <div style="border-top: 1px solid var(--border-color); padding-top: 2rem; margin-top: 2rem;">
                            <h3 style="margin-bottom: 1rem;">Service Route Configuration</h3>
                            
                            <label class="checkbox-label" style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                                <input type="checkbox" name="is_accepting_deliveries" <?php echo ($user['is_accepting_deliveries'] ?? 0) ? 'checked' : ''; ?>>
                                <span>Accepting new delivery tasks</span>
                            </label>

                            <div class="form-group">
                                <label class="form-label">Set Your Service Route (Start & End)</label>
                                <div id="route-map"></div>
                                <div class="map-instruction">
                                    <i class='bx bx-info-circle'></i>
                                    Click once for Start, again for End point.
                                </div>
                            </div>
                            
                            <input type="hidden" name="service_start_lat" id="start_lat" value="<?php echo $user['service_start_lat']; ?>">
                            <input type="hidden" name="service_start_lng" id="start_lng" value="<?php echo $user['service_start_lng']; ?>">
                            <input type="hidden" name="service_end_lat" id="end_lat" value="<?php echo $user['service_end_lat']; ?>">
                            <input type="hidden" name="service_end_lng" id="end_lng" value="<?php echo $user['service_end_lng']; ?>">
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-primary w-full">Save Changes</button>
                </form>
            </div>
        </main>
    </div>
    <script>
        <?php if ($user['role'] === 'delivery_agent'): ?>
        const map = L.map('route-map').setView([<?php echo !empty($user['service_start_lat']) ? $user['service_start_lat'] : '9.4124'; ?>, <?php echo !empty($user['service_start_lng']) ? $user['service_start_lng'] : '76.6946'; ?>], 13);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        let startMarker = null;
        let endMarker = null;
        let routeLine = null;

        // Load existing route if available
        const startLat = document.getElementById('start_lat').value;
        const startLng = document.getElementById('start_lng').value;
        const endLat = document.getElementById('end_lat').value;
        const endLng = document.getElementById('end_lng').value;

        if (startLat && startLng) {
            startMarker = L.marker([startLat, startLng], {draggable: false, icon: L.icon({
                iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
                iconSize: [25, 41], iconAnchor: [12, 41]
            })}).addTo(map).bindPopup('Route Start');
        }
        if (endLat && endLng) {
            endMarker = L.marker([endLat, endLng], {draggable: false, icon: L.icon({
                iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
                iconSize: [25, 41], iconAnchor: [12, 41]
            })}).addTo(map).bindPopup('Route End');
        }
        if (startMarker && endMarker) {
            routeLine = L.polyline([startMarker.getLatLng(), endMarker.getLatLng()], {color: 'blue', weight: 4, dashArray: '10, 10'}).addTo(map);
            map.fitBounds(routeLine.getBounds(), {padding: [30, 30]});
        }

        map.on('click', function(e) {
            if (!startMarker || (startMarker && endMarker)) {
                // Reset/Start new
                if (startMarker) map.removeLayer(startMarker);
                if (endMarker) map.removeLayer(endMarker);
                if (routeLine) map.removeLayer(routeLine);
                
                startMarker = L.marker(e.latlng, {icon: L.icon({
                    iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
                    iconSize: [25, 41], iconAnchor: [12, 41]
                })}).addTo(map).bindPopup('Route Start');
                endMarker = null;
                
                document.getElementById('start_lat').value = e.latlng.lat;
                document.getElementById('start_lng').value = e.latlng.lng;
                document.getElementById('end_lat').value = '';
                document.getElementById('end_lng').value = '';
            } else {
                // Set end point
                endMarker = L.marker(e.latlng, {icon: L.icon({
                    iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
                    iconSize: [25, 41], iconAnchor: [12, 41]
                })}).addTo(map).bindPopup('Route End');
                
                document.getElementById('end_lat').value = e.latlng.lat;
                document.getElementById('end_lng').value = e.latlng.lng;
                
                routeLine = L.polyline([startMarker.getLatLng(), endMarker.getLatLng()], {color: 'blue', weight: 4, dashArray: '10, 10'}).addTo(map);
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>
