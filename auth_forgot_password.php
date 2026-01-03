<?php
session_start();
require_once 'includes/db_helper.php';
require_once 'includes/mail_helper.php';

$email = trim($_POST['email'] ?? '');

if (empty($email)) {
    header("Location: forgot_password.php?error=not_found");
    exit();
}

try {
    $pdo = getDBConnection();
    
    // Proactive fix: Ensure reset columns exist
    $columnsToAdd = [
        'reset_token' => "ALTER TABLE users ADD COLUMN reset_token VARCHAR(255) DEFAULT NULL AFTER reputation_score",
        'reset_expires' => "ALTER TABLE users ADD COLUMN reset_expires DATETIME DEFAULT NULL AFTER reset_token"
    ];
    
    foreach ($columnsToAdd as $col => $sql) {
        try {
            $pdo->query("SELECT $col FROM users LIMIT 1");
        } catch (PDOException $e) {
            $pdo->exec($sql);
        }
    }
    
    // Check if user exists and get firstname
    $stmt = $pdo->prepare("SELECT id, firstname FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        header("Location: forgot_password.php?error=not_found");
        exit();
    }
    
    // Generate Token
    $token = bin2hex(random_bytes(32));
    $expiry = date('Y-m-d H:i:s', strtotime('+2 hours'));
    
    // Save token to DB
    $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE email = ?");
    if ($stmt->execute([$token, $expiry, $email])) {
        // Send actual email
        $resetLink = "http://localhost/BOOK-B project/reset_password.php?token=" . $token;
        $mailSent = sendResetEmail($email, $user['firstname'], $resetLink);
        
        // Redirection to the professional success page
        header("Location: verify_email.php?email=" . urlencode($email) . ($mailSent ? "" : "&token=" . $token . "&not_sent=1"));
    } else {
        header("Location: forgot_password.php?error=failed");
    }
    
} catch (PDOException $e) {
    // Show readable error for debugging if needed
    $errorMsg = urlencode($e->getMessage());
    header("Location: forgot_password.php?error=failed&debug=" . $errorMsg);
}
exit();
?>
