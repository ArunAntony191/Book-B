<?php
session_start();
require_once '../includes/db_helper.php';
require_once '../includes/validation_helper.php';

// Get form data (Raw inputs, sanitization happens after check or during use)
$firstnameRaw = $_POST['firstname'] ?? '';
$lastnameRaw = $_POST['lastname'] ?? '';
$emailRaw = $_POST['email'] ?? '';
$passwordRaw = $_POST['password'] ?? '';
$phoneRaw = $_POST['phone'] ?? '';
$roleRaw = $_POST['role'] ?? 'user';

// 1. Check Required Fields
$missing = checkRequiredFields($_POST, ['firstname', 'lastname', 'email', 'password', 'phone', 'role']);
if (!empty($missing)) {
    $msg = "Please fill in all required fields: " . implode(', ', $missing);
    header("Location: ../pages/register.php?error=missing_fields&msg=" . urlencode($msg));
    exit();
}

// 2. Validate Email
$email = validateEmail($emailRaw);
if (!$email) {
    header("Location: ../pages/register.php?error=invalid_email&msg=" . urlencode("The email address provided is invalid."));
    exit();
}

// 3. Validate Phone
$phone = null;
if (!empty($phoneRaw)) {
    $phone = validatePhone($phoneRaw);
    if (!$phone) {
        header("Location: ../pages/register.php?error=invalid_phone&msg=" . urlencode("Phone number must be 10-15 digits."));
        exit();
    }
} else {
    // Phone required
    header("Location: ../pages/register.php?error=missing_fields&msg=" . urlencode("Phone number is required."));
    exit();
}

// 4. Validate Names
$firstname = validateName($firstnameRaw);
if (!$firstname) {
    header("Location: ../pages/register.php?error=invalid_name&msg=" . urlencode("First name contains invalid characters. Only letters, spaces, hyphens, and apostrophes are allowed."));
    exit();
}
$lastname = validateName($lastnameRaw);
if (!$lastname) {
    header("Location: ../pages/register.php?error=invalid_name&msg=" . urlencode("Last name contains invalid characters."));
    exit();
}

// 5. Validate Password Strength
$passCheck = validatePassword($passwordRaw);
if (!$passCheck['valid']) {
    header("Location: ../pages/register.php?error=weak_password&msg=" . urlencode($passCheck['message']));
    exit();
}

// 6. Security: Prevent unauthorized admin registration
// (In a real app, 'admin' role should probably be completely blocked from public registration)
if ($roleRaw === 'admin') {
    $roleRaw = 'user';
}
$role = sanitizeInput($roleRaw);


// Create user
$result = createUser($email, $passwordRaw, $firstname, $lastname, $role, $phone);

if (is_numeric($result)) {
    // Set session
    $_SESSION['user_id'] = $result;
    $_SESSION['user_email'] = $email;
    $_SESSION['firstname'] = $firstname;
    $_SESSION['lastname'] = $lastname;
    $_SESSION['role'] = $role;
    
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
    header("Location: ../pages/register.php?error=user_exists&msg=" . urlencode("An account with this email already exists."));
} elseif ($result === 'phone_exists') {
    header("Location: ../pages/register.php?error=phone_exists&msg=" . urlencode("This phone number is already registered."));
} else {
    header("Location: ../pages/register.php?error=server_error&msg=" . urlencode("A server error occurred. Please try again later."));
}
exit();
?>
