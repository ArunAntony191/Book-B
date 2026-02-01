<?php
require_once '../includes/db_helper.php';
session_start();

if (isset($_GET['delete']) && $_GET['delete'] == 'true' && isset($_SESSION['user_id'])) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    } catch (Exception $e) {
        // Log error or handle
    }
}

if (isset($_SESSION['user_id'])) {
    updateRememberToken($_SESSION['user_id'], null);
}
setcookie('remember_me', '', time() - 3600, '/');

session_destroy();
header("Location: ../pages/login.php");
exit();
?>
