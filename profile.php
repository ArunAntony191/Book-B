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
        'landmark' => trim($_POST['landmark'] ?? ''),
        'district' => trim($_POST['district'] ?? ''),
        'city' => trim($_POST['city'] ?? ''),
        'pincode' => trim($_POST['pincode'] ?? ''),
        'state' => trim($_POST['state'] ?? 'Kerala'),
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
    <title>My Profile | BOOK-B</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        :root {
            --glass-bg: rgba(255, 255, 255, 0.95);
            --glass-border: rgba(226, 232, 240, 0.8);
            --section-bg: #f8fafc;
        }

        .profile-wrapper {
            max-width: 850px;
            margin: 0 auto;
            padding: 1rem;
        }

        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 2.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
            position: relative;
            overflow: hidden;
        }

        .profile-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            margin-bottom: 3rem;
            position: relative;
        }

        .profile-avatar-wrapper {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, var(--primary), #818cf8);
            color: white;
            border-radius: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3.5rem;
            font-weight: 800;
            box-shadow: 0 15px 35px rgba(99, 102, 241, 0.2);
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .profile-avatar:hover {
            transform: scale(1.05) rotate(2deg);
        }

        .avatar-edit-btn {
            position: absolute;
            bottom: -5px;
            right: -5px;
            background: white;
            width: 38px;
            height: 38px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            border: 1px solid #e2e8f0;
            cursor: pointer;
            transition: all 0.2s;
        }
        .avatar-edit-btn:hover { background: var(--primary); color: white; }

        .profile-info-group {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2.5rem;
        }

        .form-section {
            background: var(--section-bg);
            border-radius: 20px;
            padding: 2rem;
            border: 1px solid #eef2f6;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .section-header i {
            background: white;
            padding: 0.5rem;
            border-radius: 10px;
            color: var(--primary);
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        }

        .section-header h2 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
        }

        .form-label {
            font-size: 0.85rem;
            font-weight: 700;
            color: #64748b;
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-input {
            background: white;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.85rem 1rem;
            transition: all 0.2s;
            font-weight: 500;
            width: 100%;
        }

        .form-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
            outline: none;
        }

        /* Map Styles */
        #address-map {
            height: 350px;
            width: 100%;
            border-radius: 18px;
            margin-top: 1.25rem;
            border: 1.5px solid #e2e8f0;
            z-index: 1;
        }

        .map-search-container {
            position: relative;
            margin-bottom: 1rem;
        }

        .map-search-input {
            padding-left: 2.75rem;
            padding-right: 3rem;
            background: white;
        }

        .map-search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 1.2rem;
        }

        .locate-btn {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary);
            background: #eef2ff;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .locate-btn:hover { background: var(--primary); color: white; }

        .search-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            z-index: 2000;
            margin-top: 0.5rem;
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #eef2f6;
            display: none;
        }

        .suggestion-item {
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.85rem;
            cursor: pointer;
            border-bottom: 1px solid #f8fafc;
        }
        .suggestion-item:hover { background: #f1f5f9; }
        .suggestion-item i { color: #94a3b8; }

        .btn-save {
            background: linear-gradient(135deg, var(--primary), #4f46e5);
            color: white;
            padding: 1.25rem;
            border-radius: 16px;
            font-weight: 800;
            font-size: 1.1rem;
            letter-spacing: 0.5px;
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.2);
            border: none;
            width: 100%;
            margin-top: 2rem;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }

        .btn-save:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(99, 102, 241, 0.3);
        }

        .btn-save:active { transform: translateY(0); }

        /* Status Pills */
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 700;
            background: white;
            border: 1px solid #eef2f6;
        }
        .status-pill.online { color: #10b981; }
        .status-pill.role { color: #6366f1; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .form-section { animation: fadeIn 0.5s ease backwards; }
        .form-section:nth-child(2) { animation-delay: 0.1s; }
        .form-section:nth-child(3) { animation-delay: 0.2s; }

        /* Custom Marker pulse */
        .pulsing-marker { position: relative; }
        .pin { width: 14px; height: 14px; background: var(--primary); border: 2.5px solid white; border-radius: 50%; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .pulse { width: 30px; height: 30px; background: var(--primary); border-radius: 50%; position: absolute; top: -8.5px; left: -8.5px; opacity: 0.3; animation: pulse 2s infinite; }
        @keyframes pulse { 0% { transform: scale(0.5); opacity: 0.6; } 100% { transform: scale(2.5); opacity: 0; } }

        @media (max-width: 640px) {
            .grid-2 { grid-template-columns: 1fr; }
            .glass-card { padding: 1.5rem; }
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/dashboard_sidebar.php'; ?>
        
        <main class="main-content">
            <div class="profile-wrapper">
                <div class="glass-card">
                    <div class="profile-header">
                        <div class="profile-avatar-wrapper">
                            <div class="profile-avatar">
                                <?php echo strtoupper($user['firstname'][0] . $user['lastname'][0]); ?>
                            </div>
                            <div class="avatar-edit-btn" title="Update Profile Picture">
                                <i class='bx bx-camera'></i>
                            </div>
                        </div>
                        <h1 style="margin-bottom: 0.5rem; font-weight: 900;">Profile Settings</h1>
                        <div style="display: flex; gap: 0.75rem; justify-content: center; margin-bottom: 1rem;">
                            <span class="status-pill role"><i class='bx bxs-user-badge'></i> <?php echo ucfirst($user['role']); ?></span>
                            <?php if ($user['role'] === 'delivery_agent'): ?>
                                <span class="status-pill online"><i class='bx bxs-circle'></i> Online</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($success): ?>
                        <div style="background: #f0fdf4; color: #16a34a; padding: 1rem; border-radius: 16px; margin-bottom: 2rem; border: 1px solid #dcfce7; display: flex; align-items: center; gap: 0.75rem; font-weight: 600;">
                            <i class='bx bxs-check-circle' style="font-size: 1.2rem;"></i> <?php echo $success; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="profile-info-group">
                            <!-- Section: Personal Info -->
                            <div class="form-section">
                                <div class="section-header">
                                    <i class='bx bx-user'></i>
                                    <h2>Personal Information</h2>
                                </div>
                                <div class="grid-2">
                                    <div class="form-group">
                                        <label class="form-label">First Name</label>
                                        <input type="text" name="firstname" class="form-input" value="<?php echo htmlspecialchars($user['firstname']); ?>" required pattern="[A-Za-z\s'\-]{2,50}" title="Name should contain only letters and be at least 2 characters long">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Last Name</label>
                                        <input type="text" name="lastname" class="form-input" value="<?php echo htmlspecialchars($user['lastname']); ?>" required pattern="[A-Za-z\s'\-]{1,50}" title="Name should contain only letters">
                                    </div>
                                </div>
                                <div class="form-group" style="margin-top: 1.25rem;">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($user['email']); ?>" required pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$" title="Please enter a valid email address (e.g. user@example.com)">
                                </div>
                                <div class="form-group" style="margin-top: 1.25rem;">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" name="phone" class="form-input" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required
                                           pattern="[\d\s\-\+\(\)]{10,20}" title="Phone number must contain only digits, spaces, and symbols (+, -, parenthesis). Letters are not allowed.">
                                </div>
                            </div>

                            <!-- Section: Delivery Address -->
                            <div class="form-section">
                                <div class="section-header">
                                    <i class='bx bx-map-pin'></i>
                                    <h2>Delivery & Location</h2>
                                </div>
                                
                                <div class="grid-2">
                                    <div class="form-group">
                                        <label class="form-label">District</label>
                                        <select name="district" id="district-select" class="form-input">
                                            <?php 
                                            $districts = ['Alappuzha', 'Ernakulam', 'Idukki', 'Kannur', 'Kasaragod', 'Kollam', 'Kottayam', 'Kozhikode', 'Malappuram', 'Palakkad', 'Pathanamthitta', 'Thiruvananthapuram', 'Thrissur', 'Wayanad'];
                                            foreach($districts as $d): ?>
                                                <option value="<?php echo $d; ?>" <?php echo ($user['district'] ?? '') === $d ? 'selected' : ''; ?>><?php echo $d; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">City / Area</label>
                                        <input type="text" name="city" id="city-input" list="city-suggestions" class="form-input" placeholder="e.g. Aluva" value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>" pattern="[A-Za-z\s\.\-]+" title="City name should contain only letters">
                                        <datalist id="city-suggestions"></datalist>
                                    </div>
                                </div>

                                <div class="grid-2" style="margin-top: 1.25rem;">
                                    <div class="form-group">
                                        <label class="form-label">Pincode</label>
                                        <input type="text" name="pincode" id="pincode-input" class="form-input" placeholder="68xxxx" value="<?php echo htmlspecialchars($user['pincode'] ?? ''); ?>" pattern="\d{6}" maxlength="6" title="Pincode must be exactly 6 digits">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">State</label>
                                        <select name="state" id="state-select" class="form-input">
                                            <option value="Kerala" selected>Kerala</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                </div>

                                <div style="margin-top: 2rem;">
                                    <label class="form-label">Current Pin Location</label>
                                    <div class="map-search-container">
                                        <i class='bx bx-search map-search-icon'></i>
                                        <input type="text" id="address-search" class="form-input map-search-input" placeholder="Search for your building or street...">
                                        <button type="button" class="locate-btn" onclick="getCurrentLocation()" title="Detect My Location">
                                            <i class='bx bx-target-lock'></i>
                                        </button>
                                        <div id="search-suggestions" class="search-suggestions"></div>
                                    </div>
                                    <div id="address-map"></div>
                                    <p style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.75rem; font-weight: 600;">
                                        <i class='bx bxs-info-circle'></i> Accurate GPS location ensures delivery partners find you without calls.
                                    </p>
                                </div>

                                <div class="form-group" style="margin-top: 1.5rem;">
                                    <label class="form-label">Full Address / House No.</label>
                                    <textarea name="address" id="main-address" class="form-input" rows="3" placeholder="Flat No 4B, Blue Apartment, MG Road..."><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                                </div>

                                <div class="form-group" style="margin-top: 1.25rem;">
                                    <label class="form-label">Nearby Reference Point (Landmark) *</label>
                                    <input type="text" name="landmark" class="form-input" placeholder="Near City Mall, Opposite Post Office" value="<?php echo htmlspecialchars($user['landmark'] ?? ''); ?>" required>
                                </div>
                            </div>

                            <?php if ($user['role'] === 'delivery_agent'): ?>
                                <div class="form-section">
                                    <div class="section-header">
                                        <i class='bx bx-rocket'></i>
                                        <h2>Agent Settings</h2>
                                    </div>
                                    <label style="display: flex; align-items: center; gap: 1rem; cursor: pointer; background: white; padding: 1.25rem; border-radius: 12px; border: 1.5px solid #e2e8f0;">
                                        <input type="checkbox" name="is_accepting_deliveries" style="width: 20px; height: 20px;" <?php echo ($user['is_accepting_deliveries'] ?? 0) ? 'checked' : ''; ?>>
                                        <div style="flex: 1;">
                                            <strong style="display: block;">Accepting Deliveries</strong>
                                            <span style="font-size: 0.8rem; color: #64748b;">Turn this off when you are taking a break or offline.</span>
                                        </div>
                                    </label>
                                </div>
                            <?php endif; ?>
                        </div>

                        <input type="hidden" name="service_start_lat" id="lat" value="<?php echo $user['service_start_lat'] ?? ''; ?>">
                        <input type="hidden" name="service_start_lng" id="lng" value="<?php echo $user['service_start_lng'] ?? ''; ?>">

                        <button type="submit" class="btn-save">
                            <i class='bx bx-save'></i> Save Changes
                        </button>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        const cartoTiles = 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png';
        const cartoAttr = '©OpenStreetMap ©CartoDB';

        const addressMap = L.map('address-map', { zoomControl: true }).setView([<?php echo !empty($user['service_start_lat']) ? $user['service_start_lat'] : '9.4124'; ?>, <?php echo !empty($user['service_start_lng']) ? $user['service_start_lng'] : '76.6946'; ?>], 13);
        L.tileLayer(cartoTiles, { attribution: cartoAttr }).addTo(addressMap);
        
        const pulsingIcon = L.divIcon({
            className: 'pulsing-marker',
            html: '<div class="pin"></div><div class="pulse"></div>',
            iconSize: [20, 20],
            iconAnchor: [10, 10]
        });

        let addressMarker = null;
        <?php if (!empty($user['service_start_lat']) && !empty($user['service_start_lng'])): ?>
            addressMarker = L.marker([<?php echo $user['service_start_lat']; ?>, <?php echo $user['service_start_lng']; ?>], { icon: pulsingIcon }).addTo(addressMap);
        <?php endif; ?>

        addressMap.on('click', (e) => updateAddressFromCoords(e.latlng.lat, e.latlng.lng));

        const addrSearch = document.getElementById('address-search');
        const searchSuggestions = document.getElementById('search-suggestions');
        let searchTimeout;

        addrSearch.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            const query = e.target.value.trim();
            if (query.length < 3) { searchSuggestions.style.display = 'none'; return; }

            searchTimeout = setTimeout(async () => {
                const res = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5&countrycodes=in`);
                const data = await res.json();
                searchSuggestions.innerHTML = '';
                if (data.length > 0) {
                    data.forEach(item => {
                        const div = document.createElement('div');
                        div.className = 'suggestion-item';
                        div.innerHTML = `<i class='bx bx-map'></i> <span>${item.display_name}</span>`;
                        div.onclick = () => {
                            addressMap.setView([item.lat, item.lon], 17);
                            updateAddressFromCoords(parseFloat(item.lat), parseFloat(item.lon), item.display_name);
                            searchSuggestions.style.display = 'none';
                            addrSearch.value = item.display_name;
                        };
                        searchSuggestions.appendChild(div);
                    });
                    searchSuggestions.style.display = 'block';
                }
            }, 500);
        });

        addrSearch.addEventListener('keydown', async (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const query = e.target.value.trim();
                if (query.length < 3) return;

                clearTimeout(searchTimeout);
                
                // Show loading state implicitly by not doing anything yet or maybe a spinner could be added later
                const res = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=1&countrycodes=in`);
                const data = await res.json();
                
                if (data.length > 0) {
                    const item = data[0];
                    addressMap.setView([item.lat, item.lon], 17);
                    updateAddressFromCoords(parseFloat(item.lat), parseFloat(item.lon), item.display_name);
                    searchSuggestions.style.display = 'none';
                    // Optional: keep the user's typed query or replace with the formatted one? 
                    // Usually replacing with the official name is better for confirmation
                    addrSearch.value = item.display_name;
                }
            }
        });

        function getCurrentLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition((pos) => {
                    const { latitude, longitude } = pos.coords;
                    addressMap.setView([latitude, longitude], 17);
                    updateAddressFromCoords(latitude, longitude);
                }, () => alert("GPS access denied."), { enableHighAccuracy: true });
            }
        }

        async function updateAddressFromCoords(lat, lng, manualAddress = null) {
            if(addressMarker) addressMap.removeLayer(addressMarker);
            addressMarker = L.marker([lat, lng], { icon: pulsingIcon }).addTo(addressMap);
            document.getElementById('lat').value = lat;
            document.getElementById('lng').value = lng;

            if(!manualAddress) {
                const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`);
                const data = await res.json();
                if(data && data.display_name) {
                    document.getElementById('main-address').value = data.display_name;
                    if (data.address.postcode) document.getElementById('pincode-input').value = data.address.postcode;
                }
            } else {
                document.getElementById('main-address').value = manualAddress;
            }
        }
    </script>
</body>
</html>
