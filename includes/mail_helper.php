<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/mail.php';

function sendResetEmail($toEmail, $firstname, $resetLink) {
    // Check if in Development Mode
    if (defined('MAIL_DEV_MODE') && MAIL_DEV_MODE === true) {
        return false; // Skips sending and triggers the onscreen fallback
    }

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION;
        $mail->Port       = MAIL_PORT;

        // Recipients
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $firstname);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Reset Your BOOK-B Password';
        
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px;'>
                <h2 style='color: #4f46e5; text-align: center;'>Password Reset Request</h2>
                <p>Hi {$firstname},</p>
                <p>We received a request to reset your password for your BOOK-B account. Click the button below to set a new password:</p>
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$resetLink}' style='background-color: #4f46e5; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: bold;'>Reset Password</a>
                </div>
                <p>If you didn't request this, you can safely ignore this email. The link will expire in 1 hour.</p>
                <hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;'>
                <p style='font-size: 0.8rem; color: #64748b; text-align: center;'>&copy; " . date('Y') . " BOOK-B Platform. All rights reserved.</p>
            </div>
        ";
        
        $mail->AltBody = "Hi {$firstname},\n\nReset your password here: {$resetLink}\n\nIf you didn't request this, ignore this email.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mail Error: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Send notification email to user
 * @param int $userId - User ID to send to
 * @param string $type - Notification type
 * @param string $subject - Email subject
 * @param string $message - Email message
 * @param string $actionUrl - Optional action URL
 * @param string $actionText - Optional action button text
 * @return bool
 */
function sendNotificationEmail($userId, $type, $subject, $message, $actionUrl = null, $actionText = null) {
    // Check if in Development Mode
    if (defined('MAIL_DEV_MODE') && MAIL_DEV_MODE === true) {
        return false; // Skip sending in dev mode
    }

    try {
        // Get user details
        require_once __DIR__ . '/db_helper.php';
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT email, firstname, email_notifications FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return false;
        }
        
        // Check if user has email notifications enabled
        if (!$user['email_notifications']) {
            return false; // User has disabled email notifications
        }
        
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION;
        $mail->Port       = MAIL_PORT;

        // Recipients
        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($user['email'], $user['firstname']);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        
        // Build action button HTML if provided
        $actionButton = '';
        if ($actionUrl && $actionText) {
            $actionButton = "
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$actionUrl}' style='background-color: #4f46e5; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block;'>{$actionText}</a>
                </div>
            ";
        }
        
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px; background-color: white;'>
                <div style='text-align: center; margin-bottom: 20px;'>
                    <h2 style='color: #4f46e5; margin: 0;'>BOOK-B</h2>
                </div>
                <h3 style='color: #1e293b; margin-bottom: 15px;'>{$subject}</h3>
                <p style='color: #475569; line-height: 1.6;'>Hi {$user['firstname']},</p>
                <p style='color: #475569; line-height: 1.6;'>{$message}</p>
                {$actionButton}
                <hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;'>
                <p style='font-size: 0.8rem; color: #94a3b8; text-align: center;'>
                    You received this email because you have email notifications enabled.<br>
                    You can manage your notification preferences in your account settings.
                </p>
                <p style='font-size: 0.8rem; color: #64748b; text-align: center; margin-top: 10px;'>&copy; " . date('Y') . " BOOK-B Platform. All rights reserved.</p>
            </div>
        ";
        
        $mail->AltBody = strip_tags($message) . ($actionUrl ? "\n\n{$actionText}: {$actionUrl}" : "");

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Notification Mail Error: {$e->getMessage()}");
        return false;
    }
}
?>
