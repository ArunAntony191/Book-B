<?php
session_start();
require_once 'includes/db_helper.php';

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// Validate inputs
if (empty($email) || empty($password)) {
    header("Location: login.php?error=missing_fields");
    exit();
}

// Authenticate user
$user = authenticateUser($email, $password);

if ($user) {
    // Set session
    $_SESSION['user_id'] = $user['id'];
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
    // Invalid credentials
    header("Location: login.php?error=invalid_credentials");
}
exit();
?>
