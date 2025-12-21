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
