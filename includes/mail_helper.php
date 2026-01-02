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
?>
