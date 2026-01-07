<?php
session_start();
require_once 'includes/db_helper.php';

// Get form data
$firstname = trim($_POST['firstname'] ?? '');
$lastname = trim($_POST['lastname'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$role = $_POST['role'] ?? 'user';

// Security: Prevent registration as admin
if ($role === 'admin') {
    $role = 'user';
}

// Validate inputs
if (empty($firstname) || empty($lastname) || empty($email) || empty($password) || empty($role)) {
    header("Location: register.php?error=missing_fields");
    exit();
}

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: register.php?error=invalid_email");
    exit();
}

// Validate names (Letters, spaces, hyphens, apostrophes only)
if (!preg_match("/^[A-Za-z\s'\-]+$/", $firstname) || !preg_match("/^[A-Za-z\s'\-]+$/", $lastname)) {
    header("Location: register.php?error=invalid_name");
    exit();
}

// Validate password (min 8 chars, at least one uppercase, one lowercase, one number, one special char)
$isStrong = strlen($password) >= 8 && 
            preg_match("/[A-Z]/", $password) && 
            preg_match("/[a-z]/", $password) && 
            preg_match("/[0-9]/", $password) && 
            preg_match("/[^A-Za-z0-9]/", $password);

if (!$isStrong) {
    header("Location: register.php?error=weak_password");
    exit();
}

// Create user
if (createUser($email, $password, $firstname, $lastname, $role)) {
    // Set session
    $_SESSION['user_email'] = $email;
    $_SESSION['firstname'] = $firstname;
    $_SESSION['lastname'] = $lastname;
    $_SESSION['role'] = $role;
    
    // Redirect based on role
    switch ($role) {
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
    // User already exists
    header("Location: register.php?error=user_exists");
}
exit();
?>
