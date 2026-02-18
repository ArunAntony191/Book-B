<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | BOOK-B</title>
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
        }
        .auth-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .auth-logo {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.75rem;
            font-weight: 900;
            color: #0f172a;
            margin-bottom: 1rem;
        }
        .auth-logo-icon {
            width: 40px;
            height: 40px;
            background: var(--primary);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
        }
        .auth-logo span {
            color: var(--primary);
        }
        .auth-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 0.5rem;
        }
        .auth-subtitle {
            color: var(--text-muted);
            font-size: 0.95rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-label {
            display: block;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--text-main);
            font-size: 0.9rem;
        }
        .form-input {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            transition: all 0.2s;
        }
        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        .alert {
            padding: 0.875rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        .alert-error {
            background: #fee2e2;
            border: 1px solid #ef4444;
            color: #991b1b;
        }
    </style>
</head>
<body>
    <?php
    require_once '../includes/db_helper.php';
    $token = $_GET['token'] ?? '';
    $validToken = false;
    
    $debugInfo = "";
    if (!empty($token)) {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT id, reset_expires FROM users WHERE reset_token = ?");
            $stmt->execute([$token]);
            $user = $stmt->fetch();
            
            if ($user) {
                $expiry = strtotime($user['reset_expires']);
                $now = time();
                
                if ($expiry > ($now - 300)) {
                    $validToken = true;
                }
            }
        } catch (PDOException $e) {
            $debugInfo = "Database Error: " . $e->getMessage();
        }
    } else {
        $debugInfo = "Reason: No token provided in URL.";
    }
    ?>

    <div class="auth-wrapper">
        <div class="auth-card">
            <?php if (!$validToken): ?>
                <div class="auth-header">
                    <div class="auth-logo">
                        <div class="auth-logo-icon"><i class='bx bx-book-bookmark'></i></div>
                        BOOK- <span>B</span>
                    </div>
                    <h1 class="auth-title">Invalid Link</h1>
                    <p class="auth-subtitle">This password reset link is invalid or has expired.</p>
                </div>
                <a href="forgot_password.php" class="btn btn-primary w-full" style="text-align: center; display: block; text-decoration: none;">Request New Link</a>
            <?php else: ?>
                <div class="auth-header">
                    <div class="auth-logo">
                        <div class="auth-logo-icon"><i class='bx bx-book-bookmark'></i></div>
                        BOOK- <span>B</span>
                    </div>
                    <h1 class="auth-title">New Password</h1>
                    <p class="auth-subtitle">Create a secure new password for your account</p>
                </div>

                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-error">Passwords do not match. Please try again.</div>
                <?php endif; ?>

                <form action="../actions/auth_reset_password.php" method="POST">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <input type="password" name="password" class="form-input" placeholder="••••••••" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-input" placeholder="••••••••" required>
                    </div>

                    <button type="submit" class="btn btn-primary w-full">Update Password</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
