<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | BOOK-B</title>
    <link rel="stylesheet" href="assets/css/style.css">
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
            max-width: 520px;
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
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
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
        .checkbox-label {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            font-size: 0.85rem;
            color: var(--text-body);
            margin-bottom: 1.5rem;
        }
        .checkbox-label a {
            color: var(--primary);
            text-decoration: none;
        }
        .divider {
            text-align: center;
            margin: 1.5rem 0;
            color: var(--text-muted);
            font-size: 0.9rem;
            position: relative;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border-color);
        }
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
            margin-bottom: 1.5rem;
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
        .role-selection {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }
        @media (max-width: 480px) {
            .role-selection {
                grid-template-columns: 1fr;
            }
            .auth-card {
                padding: 2rem 1.5rem;
            }
        }
        .role-card {
            position: relative;
            padding: 1.5rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-lg);
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: white;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .role-card:hover {
            border-color: var(--primary);
            background: #f5f3ff;
            transform: translateY(-2px);
        }
        .role-card.selected {
            border-color: var(--primary);
            background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        .role-card input[type="radio"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }
        .role-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 0.75rem;
            transition: all 0.2s;
        }
        .role-card.selected .role-icon {
            transform: scale(1.1);
        }
        .role-icon.user { background: #e0e7ff; color: #4338ca; }
        .role-icon.library { background: #dcfce7; color: #15803d; }
        .role-icon.bookstore { background: #fef3c7; color: #b45309; }
        .role-icon.admin { background: #f1f5f9; color: #475569; }
        .role-title {
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--text-main);
            margin-bottom: 0.25rem;
        }
        .role-desc {
            font-size: 0.8rem;
            color: var(--text-muted);
            line-height: 1.4;
        }
        .checkmark {
            position: absolute;
            top: 0.75rem;
            right: 0.75rem;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
        }
        .role-card.selected .checkmark {
            display: flex;
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
                <h1 class="auth-title">Join BOOK-B Today</h1>
                <p class="auth-subtitle">Start borrowing books for free in seconds</p>
            </div>

            <?php if (isset($_GET['error'])): ?>
                <div style="background: #fee2e2; border: 1px solid #ef4444; color: #991b1b; padding: 0.875rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; font-size: 0.9rem;">
                    <?php
                    switch($_GET['error']) {
                        case 'missing_fields':
                            echo 'Please fill in all required fields.';
                            break;
                        case 'invalid_email':
                            echo 'Please enter a valid email address.';
                            break;
                        case 'invalid_name':
                            echo 'Names can only contain letters, spaces, hyphens and apostrophes.';
                            break;
                        case 'weak_password':
                            echo 'Password must be at least 8 characters long and include uppercase, lowercase, numbers, and symbols.';
                            break;
                        case 'user_exists':
                            echo 'An account with this email already exists. Please <a href="login.php" style="color: inherit; text-decoration: underline;">login instead</a>.';
                            break;
                        case 'phone_exists':
                            echo 'This phone number is already registered to another account. Please use a different number.';
                            break;
                        case 'server_error':
                            echo 'A server error occurred. Please try again later.';
                            break;
                        default:
                            echo 'An error occurred. Please try again.';
                    }
                    ?>
                </div>
            <?php endif; ?>

            <form action="auth_register.php" method="POST">
                <!-- Account Type Selection - Now First -->
                <div class="form-group">
                    <label class="form-label">Choose Your Account Type</label>
                    <div class="role-selection">
                        <label class="role-card" data-role="user">
                            <input type="radio" name="role" value="user" required>
                            <div class="checkmark"><i class='bx bx-check'></i></div>
                            <div class="role-icon user">
                                <i class='bx bx-user'></i>
                            </div>
                            <div class="role-title">Individual User</div>
                            <div class="role-desc">Borrow & share books</div>
                        </label>

                        <label class="role-card" data-role="library">
                            <input type="radio" name="role" value="library" required>
                            <div class="checkmark"><i class='bx bx-check'></i></div>
                            <div class="role-icon library">
                                <i class='bx bxs-institution'></i>
                            </div>
                            <div class="role-title">Library</div>
                            <div class="role-desc">Manage inventory</div>
                        </label>

                        <label class="role-card" data-role="bookstore">
                            <input type="radio" name="role" value="bookstore" required>
                            <div class="checkmark"><i class='bx bx-check'></i></div>
                            <div class="role-icon bookstore">
                                <i class='bx bxs-store'></i>
                            </div>
                            <div class="role-title">Bookstore</div>
                            <div class="role-desc">Sell & track orders</div>
                        </label>

                        <label class="role-card" data-role="delivery_agent">
                            <input type="radio" name="role" value="delivery_agent" required>
                            <div class="checkmark"><i class='bx bx-check'></i></div>
                            <div class="role-icon admin"> <!-- Using admin icon color for delivery agent -->
                                <i class='bx bxs-truck'></i>
                            </div>
                            <div class="role-title">Delivery Agent</div>
                            <div class="role-desc">Deliver books & earn</div>
                        </label>

                    </div>
                </div>

                <!-- Name Fields -->
                <div class="form-group">
                    <div class="form-row">
                        <div>
                            <label class="form-label">First Name</label>
                            <input type="text" name="firstname" class="form-input" placeholder="First Name" required 
                                   pattern="[A-Za-z\s'\-]+" title="Only letters, spaces, hyphens and apostrophes allowed">
                        </div>
                        <div>
                            <label class="form-label">Last Name</label>
                            <input type="text" name="lastname" class="form-input" placeholder="Last Name" required
                                   pattern="[A-Za-z\s'\-]+" title="Only letters, spaces, hyphens and apostrophes allowed">
                        </div>
                    </div>
                </div>

                <!-- Email & Phone -->
                <div class="form-group">
                    <div class="form-row">
                        <div>
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-input" placeholder="you@example.com" required>
                        </div>
                        <div>
                            <label class="form-label">Phone Number</label>
                            <input type="tel" name="phone" class="form-input" placeholder="+1234567890" required
                                   pattern="^\+?[0-9]{10,15}$" title="Enter a valid phone number (10-15 digits)">
                        </div>
                    </div>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label class="form-label">Create Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" class="form-input" placeholder="••••••••" required
                               minlength="4">
                        <i class='bx bx-hide password-toggle' id="togglePassword"></i>
                    </div>
                    <p class="form-hint" style="font-size: 0.75rem; margin-top: 0.25rem; color: #64748b;">Minimal 4 characters for testing</p>
                </div>

                <!-- Terms Checkbox -->
                <label class="checkbox-label">
                    <input type="checkbox" required>
                    <span>I agree to the <a href="#">Terms</a> and <a href="#">Privacy Policy</a></span>
                </label>

                <!-- Submit Button -->
                <button type="submit" class="btn btn-primary w-full">Create Free Account</button>

                <!-- Divider -->
                <div class="divider">Or</div>

                <?php require_once 'config/google.php'; ?>
                <!-- Google Sign-In Button -->
                <div id="g_id_onload"
                     data-client_id="<?php echo GOOGLE_CLIENT_ID; ?>"
                     data-login_uri="http://localhost/BOOK-B project/auth_google.php"
                     data-auto_prompt="false"
                     data-auto_select="false"
                     data-itp_support="true">
                </div>
                
                <div class="g_id_signin"
                     data-type="standard"
                     data-size="medium"
                     data-theme="outline"
                     data-text="signup_with"
                     data-shape="rectangular"
                     data-logo_alignment="left"
                     data-width="100%">
                </div>
            </form>

            <div class="auth-link">
                Already have an account? <a href="login.php">Log in</a>
            </div>
        </div>
    </div>

    <script src="assets/js/script.js"></script>
    <script>
        // Handle role card selection
        document.querySelectorAll('.role-card').forEach(card => {
            card.addEventListener('click', function() {
                // Remove selected class from all cards
                document.querySelectorAll('.role-card').forEach(c => c.classList.remove('selected'));
                
                // Add selected class to clicked card
                this.classList.add('selected');
                
                // Check the radio button
                this.querySelector('input[type="radio"]').checked = true;
            });
        });
        // Password Strength Logic
        // Password toggle logic only
        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');
        togglePassword.addEventListener('click', function (e) {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('bx-show');
            this.classList.toggle('bx-hide');
        });
    </script>
</body>
</html>
