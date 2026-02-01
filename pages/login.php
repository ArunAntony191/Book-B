<?php require_once '../paths.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | BOOK-B</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
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
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 1rem;
        }
        .auth-logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
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
        .password-wrapper {
            position: relative;
        }
        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            cursor: pointer;
            font-size: 1.25rem;
        }
        .form-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
        }
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: var(--text-body);
        }
        .divider {
            text-align: center;
            margin: 1.5rem 0;
            color: var(--text-muted);
            font-size: 0.9rem;
            position: relative;
        }
        .divider::before,
        .divider::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 45%;
            height: 1px;
            background: var(--border-color);
        }
        .divider::before { left: 0; }
        .divider::after { right: 0; }
        .btn-google {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            width: 100%;
            padding: 0.875rem;
            background: white;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            font-weight: 600;
            color: var(--text-main);
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-google:hover {
            background: var(--bg-body);
            border-color: var(--text-body);
        }
        /* Google Sign-In Button Centering */
        .g_id_signin {
            display: flex !important;
            justify-content: center !important;
            margin-bottom: 1.5rem;
        }
        .auth-link {
            text-align: center;
            margin-top: 1.5rem;
            color: var(--text-body);
            font-size: 0.95rem;
        }
        .auth-link a {
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
        }
        .auth-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo">
                    <div class="auth-logo-icon"><i class='bx bx-book-bookmark'></i></div>
                    BOOK-<span>B</span>
                </div>
                <h1 class="auth-title">Welcome back!</h1>
                <p class="auth-subtitle">Log in to see your borrowed books</p>
            </div>

            <?php if (isset($_GET['reset']) && $_GET['reset'] === 'success'): ?>
                <div style="background: #dcfce7; border: 1px solid #22c55e; color: #166534; padding: 0.875rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; font-size: 0.9rem;">
                    ✅ Password updated successfully. You can now log in.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div style="background: #fee2e2; border: 1px solid #ef4444; color: #991b1b; padding: 0.875rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; font-size: 0.9rem;">
                    <?php
                    switch($_GET['error']) {
                        case 'missing_fields':
                            echo 'Please fill in all fields.';
                            break;
                        case 'invalid_credentials':
                            echo 'Invalid email or password.';
                            break;
                        default:
                            echo 'An error occurred. Please try again.';
                    }
                    ?>
                </div>
            <?php endif; ?>

            <form action="../actions/auth_login.php" method="POST">
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-input" placeholder="you@example.com" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" class="form-input" placeholder="••••••••" required>
                        <i class='bx bx-hide password-toggle' id="togglePassword"></i>
                    </div>
                </div>

                <div class="form-footer">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember">
                        <span>Remember me</span>
                    </label>
                    <a href="forgot_password.php" style="color: var(--primary); font-size: 0.9rem; text-decoration: none;">Forgot password?</a>
                </div>

                <button type="submit" class="btn btn-primary w-full">Sign In</button>

                <div class="divider">Or</div>

                <?php require_once '../config/google.php'; ?>
                <div id="g_id_onload"
                     data-client_id="<?php echo GOOGLE_CLIENT_ID; ?>"
                     data-login_uri="<?php echo APP_URL; ?>/actions/auth_google.php"
                     data-auto_prompt="false"
                     data-auto_select="false"
                     data-itp_support="true">
                </div>
                
                <div class="g_id_signin"
                     data-type="standard"
                     data-size="medium"
                     data-theme="outline"
                     data-text="signin_with"
                     data-shape="rectangular"
                     data-logo_alignment="left"
                     data-width="100%">
                </div>
            </form>

            <div class="auth-link">
                New to BOOK-B? <a href="register.php">Create free account</a>
            </div>
        </div>
    </div>

    <script src="../assets/js/script.js"></script>
</body>
</html>
