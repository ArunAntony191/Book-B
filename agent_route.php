<?php
require_once 'includes/db_helper.php';
require_once 'paths.php';
session_start();

$userId = $_SESSION['user_id'] ?? 0;
$userRole = $_SESSION['role'] ?? 'user';

if (!$userId || $userRole !== 'delivery_agent') {
    header("Location: login.php");
    exit();
}

$pdo = getDBConnection();
$user = getUserById($userId);

// Fetch current coordinates
$startLat = $user['service_start_lat'] ?? '';
$startLng = $user['service_start_lng'] ?? '';
$endLat = $user['service_end_lat'] ?? '';
$endLng = $user['service_end_lng'] ?? '';

// Handle form submission
$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sLat = $_POST['service_start_lat'];
    $sLng = $_POST['service_start_lng'];
    $eLat = $_POST['service_end_lat'];
    $eLng = $_POST['service_end_lng'];
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET service_start_lat=?, service_start_lng=?, service_end_lat=?, service_end_lng=? WHERE id=?");
        $stmt->execute([$sLat, $sLng, $eLat, $eLng, $userId]);
        
        // Refresh data
        $startLat = $sLat; $startLng = $sLng;
        $endLat = $eLat; $endLng = $eLng;
        $successMsg = "Service route updated successfully!";
    } catch (Exception $e) {
        $errorMsg = "Failed to update route.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Assign | BOOK-B</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        #map { height: 500px; width: 100%; border-radius: var(--radius-lg); border: 2px solid var(--border-color); }
        .instruction-card {
            background: #f0f9ff; color: #0369a1; padding: 1.5rem;
            border-radius: 0.75rem; margin-bottom: 2rem;
            border: 1px solid #bae6fd;
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/dashboard_sidebar.php'; ?>
        
        <main class="main-content">
            <div style="margin-bottom: 2rem;">
                <h1 style="color: #1e293b; font-size: 2rem; font-weight: 800;">Smart Assign</h1>
                <p style="color: #64748b; font-size: 1rem;">Define the area where you want to receive delivery requests.</p>
            </div>

            <?php if ($successMsg): ?>
                <div class="alert alert-success" style="background:#dcfce7; color:#166534; padding:1rem; border-radius:var(--radius-md); margin-bottom: 1.5rem;"><?php echo $successMsg; ?></div>
            <?php endif; ?>

            <div class="instruction-card">
                <i class='bx bx-info-circle'></i> 
                <strong>How to set your route:</strong>
                <ul style="margin: 0.5rem 0 0 1.5rem;">
                    <li>Use the search boxes below or click on the map to set your locations.</li>
                    <li>Set your <strong>Start Location</strong> (e.g., Home).</li>
                    <li>Set your <strong>End Location</strong> (e.g., Work or frequent area).</li>
                </ul>
            </div>

            <form method="POST">
                <input type="hidden" id="start_lat" name="service_start_lat" value="<?php echo htmlspecialchars($startLat); ?>">
                <input type="hidden" id="start_lng" name="service_start_lng" value="<?php echo htmlspecialchars($startLng); ?>">
                <input type="hidden" id="end_lat" name="service_end_lat" value="<?php echo htmlspecialchars($endLat); ?>">
                <input type="hidden" id="end_lng" name="service_end_lng" value="<?php echo htmlspecialchars($endLng); ?>">

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div style="position: relative;">
                        <label style="font-weight: 600; font-size: 0.9rem; margin-bottom: 0.5rem; display: block;">Start Location</label>
                        <div style="display: flex; gap: 0.5rem;">
                            <input type="text" id="start_search" class="form-control" placeholder="City, Area, or Pin..." style="width: 100%;" onkeydown="if(event.key === 'Enter'){event.preventDefault(); searchLocation('start');}">
                            <button type="button" class="btn btn-outline" onclick="searchLocation('start')" title="Search" style="background: #f1f5f9; border-color: #e2e8f0;"><i class='bx bx-search'></i></button>
                            <button type="button" class="btn btn-outline" onclick="useCurrentLocation()" title="Use Current Location" style="background: #f3f0ff; color: #7c3aed; border-color: #e2e8f0;"><i class='bx bx-target-lock' style="font-size: 1.2rem;"></i></button>
                        </div>
                    </div>
                    <div style="position: relative;">
                        <label style="font-weight: 600; font-size: 0.9rem; margin-bottom: 0.5rem; display: block;">End Location</label>
                        <div style="display: flex; gap: 0.5rem;">
                            <input type="text" id="end_search" class="form-control" placeholder="City, Area, or Pin..." style="width: 100%;" onkeydown="if(event.key === 'Enter'){event.preventDefault(); searchLocation('end');}">
                            <button type="button" class="btn btn-outline" onclick="searchLocation('end')" title="Search"><i class='bx bx-search'></i></button>
                        </div>
                    </div>
                </div>

                <div id="map"></div>
                
                <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end; gap: 1rem;">
                    <a href="dashboard_delivery_agent.php" class="btn btn-outline">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Route</button>
                </div>
            </form>
        </main>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        var map = L.map('map').setView([9.9312, 76.2673], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        var startMarker = null;
        var endMarker = null;
        var routeLine = null;

        var startIcon = L.icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
            iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41]
        });

        var endIcon = L.icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
            iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41]
        });

        // Initialize existing points
        var initStartLat = "<?php echo $startLat; ?>";
        var initStartLng = "<?php echo $startLng; ?>";
        var initEndLat = "<?php echo $endLat; ?>";
        var initEndLng = "<?php echo $endLng; ?>";

        if (initStartLat && initStartLng) {
            startMarker = L.marker([initStartLat, initStartLng], {icon: startIcon}).addTo(map).bindPopup("Start Location").openPopup();
        }
        if (initEndLat && initEndLng) {
            endMarker = L.marker([initEndLat, initEndLng], {icon: endIcon}).addTo(map).bindPopup("End Location").openPopup();
        }
        if (startMarker && endMarker) {
            drawRoute();
            var group = new L.featureGroup([startMarker, endMarker]);
            map.fitBounds(group.getBounds().pad(0.1));
        }

        function drawRoute() {
            if (routeLine) map.removeLayer(routeLine);
            if (startMarker && endMarker) {
                routeLine = L.polyline([startMarker.getLatLng(), endMarker.getLatLng()], {color: 'blue'}).addTo(map);
            }
        }

        function useCurrentLocation() {
            if (navigator.geolocation) {
                // Show loading state if desired, or simpler alert
                navigator.geolocation.getCurrentPosition(function(position) {
                    const lat = position.coords.latitude;
                    const lon = position.coords.longitude;
                    const latlng = [lat, lon];
                    
                    if (startMarker) map.removeLayer(startMarker);
                    startMarker = L.marker(latlng, {icon: startIcon}).addTo(map).bindPopup("Current Location").openPopup();
                    
                    document.getElementById('start_lat').value = lat;
                    document.getElementById('start_lng').value = lon;
                    document.getElementById('start_search').value = "Current Location"; // Visual feedback
                    
                    map.setView(latlng, 15);
                    
                    if (startMarker && endMarker) {
                        drawRoute();
                        var group = new L.featureGroup([startMarker, endMarker]);
                        map.fitBounds(group.getBounds().pad(0.1));
                    }
                }, function(error) {
                    alert("Error getting location: " + error.message);
                });
            } else {
                alert("Geolocation is not supported by this browser.");
            }
        }

        async function searchLocation(type) {
            const inputId = type === 'start' ? 'start_search' : 'end_search';
            const query = document.getElementById(inputId).value;
            
            if (!query) return;

            // Simple visual feedback could be added here (e.g. changing button icon)

            try {
                const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}`);
                const data = await response.json();

                if (data && data.length > 0) {
                    const lat = data[0].lat;
                    const lon = data[0].lon;
                    const latlng = [lat, lon];

                    if (type === 'start') {
                        if (startMarker) map.removeLayer(startMarker);
                        startMarker = L.marker(latlng, {icon: startIcon}).addTo(map).bindPopup("Start: " + data[0].display_name).openPopup();
                        document.getElementById('start_lat').value = lat;
                        document.getElementById('start_lng').value = lon;
                    } else {
                        if (endMarker) map.removeLayer(endMarker);
                        endMarker = L.marker(latlng, {icon: endIcon}).addTo(map).bindPopup("End: " + data[0].display_name).openPopup();
                        document.getElementById('end_lat').value = lat;
                        document.getElementById('end_lng').value = lon;
                    }

                    map.setView(latlng, 13);
                    
                    if (startMarker && endMarker) {
                        drawRoute();
                        var group = new L.featureGroup([startMarker, endMarker]);
                        map.fitBounds(group.getBounds().pad(0.1));
                    }
                } else {
                    alert('Location not found. Try adding a city name.');
                }
            } catch (error) {
                console.error('Search error:', error);
                alert('Error searching for location');
            }
        }

        map.on('click', function(e) {
            if (!startMarker) {
                startMarker = L.marker(e.latlng, {icon: startIcon}).addTo(map).bindPopup("Start Location").openPopup();
                document.getElementById('start_lat').value = e.latlng.lat;
                document.getElementById('start_lng').value = e.latlng.lng;
            } else if (!endMarker) {
                endMarker = L.marker(e.latlng, {icon: endIcon}).addTo(map).bindPopup("End Location").openPopup();
                document.getElementById('end_lat').value = e.latlng.lat;
                document.getElementById('end_lng').value = e.latlng.lng;
                drawRoute();
            } else {
                // If both exist, replace the nearest one or cycle through?
                // Logic: If clicking near start, replace start. If near end, replace end.
                // Simple version: Reset end if clicked again, or reset all if both present?
                // Let's stick to the current cycle logic for map clicks, OR intelligent replacement.
                
                // Let's modify behavior: If both set, clicking map updates the END location by default, 
                // OR we can add a 'Reset' button. 
                // For now, adhering to previous 'cycle' logic effectively resets the sequence.
                
                // Resetting sequence to start over
                map.removeLayer(startMarker);
                map.removeLayer(endMarker);
                if (routeLine) map.removeLayer(routeLine);
                
                startMarker = L.marker(e.latlng, {icon: startIcon}).addTo(map).bindPopup("Start Location").openPopup();
                endMarker = null; // Clear end
                
                document.getElementById('start_lat').value = e.latlng.lat;
                document.getElementById('start_lng').value = e.latlng.lng;
                document.getElementById('end_lat').value = '';
                document.getElementById('end_lng').value = '';
                
                // Clear search inputs visually
                document.getElementById('start_search').value = '';
                document.getElementById('end_search').value = '';
            }
        });
    </script>
</body>
</html>
