<?php
session_start();
require_once 'includes/db_helper.php';

$token = $_POST['token'] ?? '';
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

if (empty($token) || empty($password)) {
    header("Location: forgot_password.php");
    exit();
}

if ($password !== $confirm_password) {
    header("Location: reset_password.php?token=$token&error=mismatch");
    exit();
}

try {
    $pdo = getDBConnection();
    
    // Find user by token and check expiry
    $stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if (!$user) {
        header("Location: forgot_password.php?error=expired");
        exit();
    }
    
    // Hash new password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Update password and clear token
    $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
    if ($stmt->execute([$hashedPassword, $user['id']])) {
        // Success
        header("Location: login.php?reset=success");
    } else {
        header("Location: reset_password.php?token=$token&error=failed");
    }
    
} catch (PDOException $e) {
    header("Location: reset_password.php?token=$token&error=failed");
}
exit();
?>
