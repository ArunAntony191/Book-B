<?php
session_start();
require_once '../includes/db_helper.php';

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// Validate inputs
if (empty($email) || empty($password)) {
    header("Location: ../pages/login.php?error=missing_fields");
    exit();
}

// Authenticate user
$user = authenticateUser($email, $password);

if ($user === 'banned') {
    header("Location: ../pages/login.php?error=account_banned");
    exit();
}

if ($user) {
    // Set session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['firstname'] = $user['firstname'];
    $_SESSION['lastname'] = $user['lastname'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['theme_mode'] = $user['theme_mode'] ?? 'light';
    $_SESSION['email_notifications'] = $user['email_notifications'] ?? 1;
    
    // Redirect based on role
    switch ($user['role']) {
        case 'library':
            header("Location: ../pages/dashboard_library.php");
            break;
        case 'bookstore':
            header("Location: ../pages/dashboard_bookstore.php");
            break;
        case 'admin':
            header("Location: ../admin/dashboard_admin.php");
            break;
        case 'delivery_agent':
            header("Location: ../pages/dashboard_delivery_agent.php");
            break;
        case 'user':
        default:
            header("Location: ../pages/dashboard_user.php");
            break;
    }
} else {
    // Invalid credentials
    header("Location: ../pages/login.php?error=invalid_credentials");
}
exit();
?>
