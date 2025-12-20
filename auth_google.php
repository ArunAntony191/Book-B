<?php
session_start();
require_once 'config/google.php';

// Check if database exists, if not redirect to setup
try {
    require_once 'includes/db_helper.php';
    getDBConnection(); // Test connection
} catch (Exception $e) {
    header("Location: setup.php?error=database_not_setup");
    exit();
}

// Get the credential from POST
$credential = $_POST['credential'] ?? '';

if (empty($credential)) {
    header("Location: login.php?error=google_auth_failed");
    exit();
}

// Verify the JWT token with Google
function verifyGoogleToken($credential) {
    $clientId = GOOGLE_CLIENT_ID;
    
    // Call Google's tokeninfo endpoint
    $url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . urlencode($credential);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return false;
    }
    
    $data = json_decode($response, true);
    
    // Verify the token is for our app
    if (!isset($data['aud']) || $data['aud'] !== $clientId) {
        return false;
    }
    
    return $data;
}

// Verify the token
$userData = verifyGoogleToken($credential);

if (!$userData) {
    header("Location: login.php?error=google_auth_failed");
    exit();
}

// Extract user information
$email = $userData['email'] ?? '';
$firstname = $userData['given_name'] ?? '';
$lastname = $userData['family_name'] ?? '';
$googleId = $userData['sub'] ?? '';

if (empty($email)) {
    header("Location: login.php?error=google_auth_failed");
    exit();
}

// Check if user exists
$user = authenticateUserByEmail($email);

if ($user) {
    // User exists - log them in
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['firstname'] = $user['firstname'];
    $_SESSION['lastname'] = $user['lastname'];
    $_SESSION['role'] = $user['role'];
    
    // Redirect based on role
    switch ($user['role']) {
        case 'library':
            header("Location: dashboard_library.php");
            break;
        case 'bookstore':
            header("Location: dashboard_bookstore.php");
            break;
        case 'admin':
            header("Location: dashboard_admin.php");
            break;
        case 'user':
        default:
            header("Location: dashboard_user.php");
            break;
    }
} else {
    // New user - store in session and redirect to role selection
    $_SESSION['google_user'] = [
        'email' => $email,
        'firstname' => $firstname,
        'lastname' => $lastname
    ];
    
    header("Location: select_role.php");
}
exit();
?>
