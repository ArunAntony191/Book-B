<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check Your Email | BOOK-B</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=1.2">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        .auth-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background-color: var(--bg-body);
            padding: 2rem;
        }
        .auth-card {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            padding: 3rem;
            width: 100%;
            max-width: 440px;
            text-align: center;
        }
        .success-icon {
            width: 80px;
            height: 80px;
            background: #f0fdf4;
            color: #22c55e;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            margin: 0 auto 1.5rem;
            border: 4px solid #dcfce7;
        }
        .auth-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 1rem;
        }
        .auth-subtitle {
            color: var(--text-muted);
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 2rem;
        }
        .email-display {
            font-weight: 600;
            color: var(--text-main);
            background: var(--bg-body);
            padding: 0.5rem 1rem;
            border-radius: var(--radius-md);
            display: inline-block;
            margin-bottom: 1rem;
        }
        .resend-text {
            font-size: 0.9rem;
            color: var(--text-muted);
        }
        .resend-link {
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }
        .resend-link:hover {
            text-decoration: underline;
        }
        .back-btn {
            margin-top: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
        }
        .back-btn:hover {
            color: var(--primary);
        }
    </style>
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="success-icon">
                <i class='bx bx-envelope-open'></i>
            </div>
            
            <h1 class="auth-title">Check your email</h1>
            <p class="auth-subtitle">
                We've sent a password reset link to:<br>
                <span class="email-display"><?php echo htmlspecialchars($_GET['email'] ?? 'your email'); ?></span><br>
                Please click the link in the email to reset your password.
            </p>

            <div class="resend-text">
                Didn't receive the email? <a href="forgot_password.php" class="resend-link">Click to resend</a>
            </div>

            <a href="login.php" class="back-btn">
                <i class='bx bx-left-arrow-alt'></i>
                Back to Login
            </a>

            <?php if (isset($_GET['token'])): ?>
                <!-- Simulation Helper: This replaces the real email for your testing -->
                <div style="margin-top: 3rem; padding: 1.25rem; border: 1px dashed #cbd5e1; border-radius: 12px; font-size: 0.85rem; color: #475569; background: #f8fafc; text-align: left;">
                    <p style="margin-bottom: 0.75rem; font-weight: 600; color: var(--primary);">
                        <i class='bx bx-info-circle'></i> Simulation: Click to open reset link
                    </p>
                    <a href="reset_password.php?token=<?php echo htmlspecialchars($_GET['token']); ?>" style="color: var(--text-main); font-weight: 500; word-break: break-all; text-decoration: none; padding: 8px; background: white; border: 1px solid #e2e8f0; border-radius: 6px; display: block;">
                        http://localhost/BOOK-B project/reset_password.php?token=...
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
