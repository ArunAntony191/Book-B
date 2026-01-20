<?php
session_start();
require_once '../includes/db_helper.php';

// Get form data
$firstname = trim($_POST['firstname'] ?? '');
$lastname = trim($_POST['lastname'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$phone = trim($_POST['phone'] ?? '');
$role = $_POST['role'] ?? 'user';

// Security: Prevent registration as admin (unless authorized - for now just keeping the original check)
if ($role === 'admin') {
    $role = 'user';
}

// Validate inputs
if (empty($firstname) || empty($lastname) || empty($email) || empty($password) || empty($role) || empty($phone)) {
    header("Location: ../pages/register.php?error=missing_fields");
    exit();
}

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: ../pages/register.php?error=invalid_email");
    exit();
}

// Validate names (Letters, spaces, hyphens, apostrophes only)
if (!preg_match("/^[A-Za-z\s'\-]+$/", $firstname) || !preg_match("/^[A-Za-z\s'\-]+$/", $lastname)) {
    header("Location: ../pages/register.php?error=invalid_name");
    exit();
}

// Validate password (min 4 chars for testing)
if (strlen($password) < 4) {
    header("Location: ../pages/register.php?error=weak_password");
    exit();
}

// Create user
$result = createUser($email, $password, $firstname, $lastname, $role, $phone);

if (is_numeric($result)) {
    // Set session
    $_SESSION['user_id'] = $result;
    $_SESSION['user_email'] = $email;
    $_SESSION['firstname'] = $firstname;
    $_SESSION['lastname'] = $lastname;
    $_SESSION['role'] = $role;
    
    // ... (Redirect logic remains same)
    switch ($role) {
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
} elseif ($result === 'email_exists') {
    header("Location: ../pages/register.php?error=user_exists");
} elseif ($result === 'phone_exists') {
    header("Location: ../pages/register.php?error=phone_exists");
} else {
    header("Location: ../pages/register.php?error=server_error");
}
exit();
?>
