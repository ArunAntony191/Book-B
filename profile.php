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
        #address-map {
            height: 300px;
            width: 100%;
            border-radius: var(--radius-md);
            margin-top: 1rem;
            border: 1px solid var(--border-color);
        }
        .map-search-container {
            position: relative;
            margin-top: 1rem;
            z-index: 1001;
        }
        .map-search-input {
            width: 100%;
            padding: 0.8rem 1rem 0.8rem 2.5rem;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            font-size: 0.9rem;
            box-shadow: var(--shadow-sm);
        }
        .search-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid var(--border-color);
            border-top: none;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            max-height: 250px;
            overflow-y: auto;
            display: none;
            z-index: 2000;
        }
        .suggestion-item {
            padding: 0.8rem 1rem;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .suggestion-item:last-child { border-bottom: none; }
        .suggestion-item:hover { background: #f8fafc; color: var(--primary); }
        .suggestion-item i { color: var(--text-muted); font-size: 1.1rem; }

        .accuracy-circle {
            pointer-events: none;
        }
        .map-search-icon {
            position: absolute;
            left: 0.8rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }
        .locate-btn {
            position: absolute;
            right: 0.8rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--primary);
            cursor: pointer;
            display: flex;
            align-items: center;
        }
        .geocoding-loader {
            display: none;
            font-size: 0.75rem;
            color: var(--primary);
            margin-top: 0.25rem;
        }
        .map-instruction {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        /* Pulsing Pin Marker */
        .pulsing-marker {
            position: relative;
        }
        .pulsing-marker .pin {
            width: 14px;
            height: 14px;
            background: var(--primary);
            border: 2px solid white;
            border-radius: 50%;
            position: absolute;
            z-index: 10;
        }
        .pulsing-marker .pulse {
            width: 30px;
            height: 30px;
            background: var(--primary);
            border-radius: 50%;
            position: absolute;
            top: -8px;
            left: -8px;
            opacity: 0.4;
            animation: pin-pulse 1.5s infinite;
        }
        @keyframes pin-pulse {
            0% { transform: scale(0.5); opacity: 0.5; }
            100% { transform: scale(2.5); opacity: 0; }
        }

        /* Accuracy Circle */
        .accuracy-circle {
            border: 2px solid var(--primary);
            background: rgba(var(--primary-rgb), 0.1);
            border-radius: 50%;
            pointer-events: none;
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
                        <label class="form-label">Delivery Address & Location</label>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                            <div class="form-group">
                                <label class="form-label" style="font-size: 0.8rem;">District</label>
                                <select name="district" id="district-select" class="form-input">
                                    <option value="">Select District</option>
                                    <?php 
                                    $districts = ['Alappuzha', 'Ernakulam', 'Idukki', 'Kannur', 'Kasaragod', 'Kollam', 'Kottayam', 'Kozhikode', 'Malappuram', 'Palakkad', 'Pathanamthitta', 'Thiruvananthapuram', 'Thrissur', 'Wayanad'];
                                    foreach($districts as $d): ?>
                                        <option value="<?php echo $d; ?>" <?php echo ($user['district'] ?? '') === $d ? 'selected' : ''; ?>><?php echo $d; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label" style="font-size: 0.8rem;">City / Area</label>
                                <input type="text" name="city" id="city-input" list="city-suggestions" class="form-input" placeholder="Enter City/Area" value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>">
                                <datalist id="city-suggestions">
                                    <!-- Suggestions will be populated via JS -->
                                </datalist>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                            <div class="form-group">
                                <label class="form-label" style="font-size: 0.8rem;">Pincode</label>
                                <input type="text" name="pincode" id="pincode-input" class="form-input" placeholder="6xxxxx" value="<?php echo htmlspecialchars($user['pincode'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label" style="font-size: 0.8rem;">State</label>
                                <select name="state" id="state-select" class="form-input">
                                    <option value="Kerala" <?php echo ($user['state'] ?? 'Kerala') === 'Kerala' ? 'selected' : ''; ?>>Kerala</option>
                                    <option value="Other" <?php echo ($user['state'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>

                        <div class="map-search-container">
                            <i class='bx bx-search map-search-icon'></i>
                            <input type="text" id="address-search" class="map-search-input" placeholder="Search for your area, street, or building...">
                            <button type="button" class="locate-btn" onclick="getCurrentLocation()" title="Use my current location">
                                <i class='bx bx-target-lock' style="font-size: 1.2rem;"></i>
                            </button>
                            <div id="search-suggestions" class="search-suggestions"></div>
                        </div>
                        <div id="address-map"></div>
                        <div id="geocoding-status" class="geocoding-loader">
                            <i class='bx bx-loader-alt bx-spin'></i> Updating address...
                        </div>
                        <div class="map-instruction">
                            <i class='bx bx-map-pin'></i> Click on map to set your exact location for deliveries
                        </div>
                        <textarea name="address" id="main-address" class="form-input" rows="3" style="margin-top:1rem;" placeholder="Enter your full delivery address..."><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                        
                        <div class="form-group" style="margin-top: 1rem;">
                            <label class="form-label" style="font-size: 0.8rem;">Nearby Reference Point *</label>
                            <input type="text" name="landmark" class="form-input" placeholder="e.g. Near City Hospital, Opposite SBI Bank" value="<?php echo htmlspecialchars($user['landmark'] ?? ''); ?>" required>
                            <p class="form-hint" style="font-size: 0.75rem; margin-top: 0.25rem;"><i class='bx bx-info-circle'></i> This helps delivery agents find your location faster.</p>
                        </div>
                        
                        <input type="hidden" name="service_start_lat" id="lat" value="<?php echo $user['service_start_lat'] ?? ''; ?>">
                        <input type="hidden" name="service_start_lng" id="lng" value="<?php echo $user['service_start_lng'] ?? ''; ?>">
                    </div>

                    <?php if ($user['role'] === 'delivery_agent'): ?>
                        <div style="border-top: 1px solid var(--border-color); padding-top: 2rem; margin-top: 2rem;">
                            <h3 style="margin-bottom: 1rem;">Delivery Availability</h3>
                            
                            <label class="checkbox-label" style="margin-bottom: 2rem; display: flex; align-items: center; gap: 0.5rem;">
                                <input type="checkbox" name="is_accepting_deliveries" <?php echo ($user['is_accepting_deliveries'] ?? 0) ? 'checked' : ''; ?>>
                                <span>Currently on-duty and accepting tasks</span>
                            </label>
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-primary w-full">Save Changes</button>
                </form>
            </div>
        </main>
    </div>
    <script>
        const cartoTiles = 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png';
        const cartoAttr = '©OpenStreetMap ©CartoDB';

        const addressMap = L.map('address-map').setView([<?php echo !empty($user['service_start_lat']) ? $user['service_start_lat'] : '9.4124'; ?>, <?php echo !empty($user['service_start_lng']) ? $user['service_start_lng'] : '76.6946'; ?>], 13);
        L.tileLayer(cartoTiles, { attribution: cartoAttr }).addTo(addressMap);
        
        // Custom Pulsing Pin Icon
        const pulsingIcon = L.divIcon({
            className: 'pulsing-marker',
            html: '<div class="pin"></div><div class="pulse"></div>',
            iconSize: [20, 20],
            iconAnchor: [10, 10]
        });

        let addressMarker = null;
        let accuracyCircle = null;

        <?php if (!empty($user['service_start_lat']) && !empty($user['service_start_lng'])): ?>
            addressMarker = L.marker([<?php echo $user['service_start_lat']; ?>, <?php echo $user['service_start_lng']; ?>], { icon: pulsingIcon }).addTo(addressMap);
        <?php endif; ?>

        addressMap.on('click', function(e) {
            updateAddressFromCoords(e.latlng.lat, e.latlng.lng);
        });

        // Autocomplete Logic
        const addrSearch = document.getElementById('address-search');
        const searchSuggestions = document.getElementById('search-suggestions');
        let searchTimeout;

        addrSearch.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            const query = e.target.value.trim();
            if (query.length < 3) {
                searchSuggestions.style.display = 'none';
                return;
            }

            searchTimeout = setTimeout(async () => {
                try {
                    const res = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5&countrycodes=in`);
                    const data = await res.json();
                    
                    searchSuggestions.innerHTML = '';
                    if (data && data.length > 0) {
                        data.forEach(item => {
                            const div = document.createElement('div');
                            div.className = 'suggestion-item';
                            div.innerHTML = `<i class='bx bx-map-pin'></i> <span>${item.display_name}</span>`;
                            div.onclick = () => {
                                addressMap.setView([item.lat, item.lon], 17);
                                updateAddressFromCoords(parseFloat(item.lat), parseFloat(item.lon), item.display_name);
                                searchSuggestions.style.display = 'none';
                                addrSearch.value = item.display_name;
                            };
                            searchSuggestions.appendChild(div);
                        });
                        searchSuggestions.style.display = 'block';
                    } else {
                        searchSuggestions.style.display = 'none';
                    }
                } catch (e) {
                    console.error("Search failed", e);
                }
            }, 500);
        });

        // Close suggestions on outside click
        document.addEventListener('click', (e) => {
            if (!addrSearch.contains(e.target) && !searchSuggestions.contains(e.target)) {
                searchSuggestions.style.display = 'none';
            }
        });

        async function searchLocation(query) {
            try {
                const res = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=1&countrycodes=in`);
                const data = await res.json();
                if(data && data.length > 0) {
                    const { lat, lon, display_name } = data[0];
                    addressMap.setView([lat, lon], 16);
                    updateAddressFromCoords(parseFloat(lat), parseFloat(lon), display_name);
                }
            } catch(e) {}
        }

        // District and City Mapping (Major Cities/Towns)
        const cityData = {
            'Alappuzha': ['Alappuzha', 'Cherthala', 'Kayamkulam', 'Mavelikkara', 'Chengannur', 'Haripad'],
            'Ernakulam': ['Kochi', 'Aluva', 'Angamaly', 'North Paravur', 'Perumbavoor', 'Kothamangalam', 'Muvattupuzha', 'Tripunithura', 'Kalamassery', 'Thrikkakara', 'Kakkanad'],
            'Idukki': ['Thodupuzha', 'Kattappana', 'Adimali', 'Munnar', 'Nedumkandam', 'Painavu'],
            'Kannur': ['Kannur', 'Thalassery', 'Payyanur', 'Taliparamba', 'Iritty', 'Mattannur'],
            'Kasaragod': ['Kasaragod', 'Kanhangad', 'Nileshwaram', 'Manjeshwar', 'Uppala'],
            'Kollam': ['Kollam', 'Punalur', 'Karunagappally', 'Paravur', 'Kottarakkara', 'Chathannoor'],
            'Kottayam': ['Kottayam', 'Changanassery', 'Pala', 'Ettumanoor', 'Kanjirappally', 'Vaikom'],
            'Kozhikode': ['Kozhikode', 'Vatakara', 'Quilandy', 'Feroke', 'Ramanattukara', 'Kunnamangalam'],
            'Malappuram': ['Malappuram', 'Manjeri', 'Kottakkal', 'Tirur', 'Ponnani', 'Perinthalmanna', 'Nilambur'],
            'Palakkad': ['Palakkad', 'Ottapalam', 'Shoranur', 'Chittur', 'Mannarkkad', 'Pattambi', 'Alathur'],
            'Pathanamthitta': ['Pathanamthitta', 'Adoor', 'Thiruvalla', 'Pandalam', 'Ranni', 'Konni'],
            'Thiruvananthapuram': ['Thiruvananthapuram', 'Neyyattinkara', 'Varkala', 'Nedumangad', 'Attingal', 'Kizhakke Kotta', 'Kazhakkoottam'],
            'Thrissur': ['Thrissur', 'Guruvayur', 'Chalakudy', 'Kodungallur', 'Kunnamkulam', 'Irinjalakuda', 'Chavakkad'],
            'Wayanad': ['Kalpetta', 'Mananthavady', 'Sulthan Bathery', 'Meenangadi', 'Panamaram']
        };

        const districtSelect = document.getElementById('district-select');
        const cityInput = document.getElementById('city-input');
        const citySuggestions = document.getElementById('city-suggestions');

        const pincodeInput = document.getElementById('pincode-input');
        const stateSelect = document.getElementById('state-select');

        districtSelect.addEventListener('change', function() {
            const district = this.value;
            citySuggestions.innerHTML = '';
            if (cityData[district]) {
                cityData[district].forEach(city => {
                    const option = document.createElement('option');
                    option.value = city;
                    citySuggestions.appendChild(option);
                });
                searchLocation(district + ', Kerala');
            }
        });

        if (districtSelect.value && cityData[districtSelect.value]) {
            cityData[districtSelect.value].forEach(city => {
                const option = document.createElement('option');
                option.value = city;
                citySuggestions.appendChild(option);
            });
        }

        function getCurrentLocation() {
            if (navigator.geolocation) {
                const loader = document.getElementById('geocoding-status');
                if(loader) loader.style.display = 'block';
                
                navigator.geolocation.getCurrentPosition((position) => {
                    const { latitude, longitude, accuracy } = position.coords;
                    
                    if (accuracyCircle) addressMap.removeLayer(accuracyCircle);
                    accuracyCircle = L.circle([latitude, longitude], {
                        radius: accuracy,
                        color: 'var(--primary)',
                        fillColor: 'var(--primary)',
                        fillOpacity: 0.15,
                        weight: 1,
                        className: 'accuracy-circle'
                    }).addTo(addressMap);

                    addressMap.setView([latitude, longitude], 17);
                    updateAddressFromCoords(latitude, longitude);
                }, () => {
                    if(loader) loader.style.display = 'none';
                    alert("Unable to retrieve your location");
                }, { enableHighAccuracy: true });
            }
        }

        async function updateAddressFromCoords(lat, lng, manualAddress = null) {
            if(addressMarker) addressMap.removeLayer(addressMarker);
            addressMarker = L.marker([lat, lng], { icon: pulsingIcon }).addTo(addressMap);
            
            if(document.getElementById('lat')) {
                document.getElementById('lat').value = lat;
                document.getElementById('lng').value = lng;
            }

            if(manualAddress) {
                document.getElementById('main-address').value = manualAddress;
            } else {
                document.getElementById('geocoding-status').style.display = 'block';
                try {
                    const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`);
                    const data = await res.json();
                    if(data && data.display_name) {
                        const addr = data.address;
                        
                        // Deep Address Parsing for extra depth
                        const house = addr.house_number || '';
                        const road = addr.road || addr.pedestrian || '';
                        const suburb = addr.suburb || addr.neighbourhood || addr.residential || '';
                        const city = addr.city || addr.town || addr.village || '';
                        const district = addr.state_district || addr.county || '';
                        const pincode = addr.postcode || '';
                        
                        // Construct a more accurate address string
                        let parts = [];
                        if (house) parts.push(house);
                        if (road) parts.push(road);
                        if (suburb) parts.push(suburb);
                        if (city) parts.push(city);
                        
                        // Fallback for very rural areas
                        const rural = addr.village || addr.hamlet || addr.isolated_dwelling || '';
                        if (!city && rural) parts.push(rural);

                        const baseAddr = parts.join(', ');
                        const fullAddress = baseAddr + (baseAddr ? ', ' : '') + data.display_name;
                        document.getElementById('main-address').value = manualAddress || fullAddress;
                        
                        if (district) {
                            const cleanDist = district.replace(' District', '').replace(' district', '');
                            for (let opt of districtSelect.options) {
                                if (opt.value.toLowerCase() === cleanDist.toLowerCase()) {
                                    districtSelect.value = opt.value;
                                    districtSelect.dispatchEvent(new Event('change'));
                                    break;
                                }
                            }
                        }
                        if (city && !cityInput.value) {
                            cityInput.value = city;
                        }
                        if (pincode) {
                            pincodeInput.value = pincode;
                        }
                        if (addr.state === 'Kerala') {
                            stateSelect.value = 'Kerala';
                        }
                    }
                } catch(e) {}
                document.getElementById('geocoding-status').style.display = 'none';
            }
        }
    </script>
</body>
</html>
