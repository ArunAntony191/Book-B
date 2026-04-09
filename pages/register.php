<?php require_once '../paths.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | BOOK-B</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=1.2">
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
        /* Hide native password reveal on Edge/IE */
        input[type="password"]::-ms-reveal,
        input[type="password"]::-ms-clear {
            display: none;
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
                    BOOK- <span>B</span>
                </div>
                <h1 class="auth-title">Join BOOK-B Today</h1>
                <p class="auth-subtitle">Start borrowing books for free in seconds</p>
            </div>

            <style>
                .error-text {
                    color: #dc2626;
                    font-size: 0.8rem;
                    margin-top: 0.25rem;
                    display: none;
                }
                .form-input.invalid {
                    border-color: #dc2626;
                }
                .form-input.valid {
                    border-color: #16a34a;
                }
            </style>

            <?php if (isset($_GET['error'])): ?>
                <div style="background: #fee2e2; border: 1px solid #ef4444; color: #991b1b; padding: 0.875rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; font-size: 0.9rem;">
                    <?php 
                    if (isset($_GET['msg'])) {
                        echo htmlspecialchars($_GET['msg']); 
                    } else {
                        switch($_GET['error']) {
                            case 'missing_fields': echo 'Please fill in all required fields.'; break;
                            case 'invalid_email': echo 'Please enter a valid email address.'; break;
                            case 'weak_password': echo 'Password is too weak.'; break;
                            case 'user_exists': echo 'User already exists.'; break;
                            default: echo 'An error occurred.';
                        }
                    }
                    ?>
                </div>
            <?php endif; ?>

            <form action="../actions/auth_register.php" method="POST" id="registerForm" novalidate>
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
                            <div class="role-icon admin">
                                <i class='bx bxs-truck'></i>
                            </div>
                            <div class="role-title">Delivery Agent</div>
                            <div class="role-desc">Deliver books & earn</div>
                        </label>

                    </div>
                    <div class="error-text" id="roleError">Please select an account type.</div>
                </div>

                <!-- Name Fields -->
                <div class="form-group">
                    <div class="form-row">
                        <div>
                            <label class="form-label">First Name</label>
                            <input type="text" id="firstname" name="firstname" class="form-input" placeholder="First Name" required>
                            <div class="error-text" id="firstnameError"></div>
                        </div>
                        <div>
                            <label class="form-label">Last Name</label>
                            <input type="text" id="lastname" name="lastname" class="form-input" placeholder="Last Name" required>
                            <div class="error-text" id="lastnameError"></div>
                        </div>
                    </div>
                </div>

                <!-- Email & Phone -->
                <div class="form-group">
                    <div class="form-row">
                        <div>
                            <label class="form-label">Email</label>
                            <input type="email" id="email" name="email" class="form-input" placeholder="you@example.com" required>
                            <div class="error-text" id="emailError"></div>
                        </div>
                        <div>
                            <label class="form-label">Phone Number</label>
                            <input type="tel" id="phone" name="phone" class="form-input" placeholder="1234567890" required>
                            <div class="error-text" id="phoneError"></div>
                        </div>
                    </div>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label class="form-label">Create Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" class="form-input" placeholder="••••••••" required>
                        <i class='bx bx-hide password-toggle' id="togglePassword"></i>
                    </div>
                    <div class="error-text" id="passwordError"></div>
                    <p class="form-hint" style="font-size: 0.75rem; margin-top: 0.25rem; color: #64748b;">
                        Min 8 chars, 1 uppercase, 1 lowercase, 1 number.
                    </p>
                </div>

                <!-- Terms Checkbox -->
                <label class="checkbox-label">
                    <input type="checkbox" id="terms" required>
                    <span>I agree to the <a href="#">Terms</a> and <a href="#">Privacy Policy</a></span>
                </label>
                <div class="error-text" id="termsError">You must agree to the terms.</div>

                <!-- Submit Button -->
                <button type="submit" class="btn btn-primary w-full" id="submitBtn">Create Free Account</button>

                <!-- Divider -->
                <div class="divider">Or</div>

                <?php require_once '../config/google.php'; ?>
                <!-- Google Sign-In Button -->
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

    <script src="../assets/js/script.js"></script>
    <script>
        // --- UI Logic: Role Selection ---
        document.querySelectorAll('.role-card').forEach(card => {
            card.addEventListener('click', function() {
                document.querySelectorAll('.role-card').forEach(c => c.classList.remove('selected'));
                this.classList.add('selected');
                this.querySelector('input[type="radio"]').checked = true;
                hideError('roleError');
            });
        });



        // --- Validation Logic ---
        const form = document.getElementById('registerForm');
        const firstname = document.getElementById('firstname');
        const lastname = document.getElementById('lastname');
        const email = document.getElementById('email');
        const phone = document.getElementById('phone');
        const password = document.getElementById('password');
        const terms = document.getElementById('terms');

        function showError(elementId, message) {
            const errorEl = document.getElementById(elementId);
            const inputEl = document.getElementById(elementId.replace('Error', '')); // Derive input ID
            if(errorEl) {
                errorEl.innerText = message;
                errorEl.style.display = 'block';
            }
            if(inputEl) {
                inputEl.classList.add('invalid');
                inputEl.classList.remove('valid');
            }
        }

        function hideError(elementId) {
            const errorEl = document.getElementById(elementId);
            const inputEl = document.getElementById(elementId.replace('Error', ''));
            if(errorEl) {
                errorEl.style.display = 'none';
            }
            if(inputEl) {
                inputEl.classList.remove('invalid');
                inputEl.classList.add('valid'); // Mark as valid
            }
        }

        // Validators
        function validateName(input, errorId) {
            const val = input.value.trim();
            if (val.length < 2) {
                showError(errorId, "Must be at least 2 characters.");
                return false;
            }
            if (!/^[A-Za-z\s]+$/.test(val)) {
                showError(errorId, "Only letters and spaces allowed.");
                return false;
            }
            hideError(errorId);
            return true;
        }

        function validateEmail() {
            const val = email.value.trim();
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!re.test(val)) {
                showError('emailError', "Please enter a valid email address.");
                return false;
            }
            hideError('emailError');
            return true;
        }

        function validatePhone() {
            const val = phone.value.replace(/[\s\-\(\)\.]/g, ''); // Strip chars to check numeric length
            // Regex: Allow + at start, then digits.
            if (!/^\+?\d+$/.test(val)) {
                showError('phoneError', "Phone number must only contain digits.");
                return false;
            }
            if (val.length < 10 || val.length > 15) {
                showError('phoneError', "Phone number must be between 10 and 15 digits.");
                return false;
            }
            hideError('phoneError');
            return true;
        }

        function validatePassword() {
            const val = password.value;
            if (val.length < 8) {
                showError('passwordError', "Password must be at least 8 characters.");
                return false;
            }
            if (!/[A-Z]/.test(val)) {
                showError('passwordError', "Must contain at least one uppercase letter.");
                return false;
            }
            if (!/[a-z]/.test(val)) {
                showError('passwordError', "Must contain at least one lowercase letter.");
                return false;
            }
            if (!/[0-9]/.test(val)) {
                showError('passwordError', "Must contain at least one number.");
                return false;
            }
            hideError('passwordError');
            return true;
        }

        function validateTerms() {
            if (!terms.checked) {
                document.getElementById('termsError').style.display = 'block';
                return false;
            }
            document.getElementById('termsError').style.display = 'none';
            return true;
        }

        function validateRole() {
            const role = document.querySelector('input[name="role"]:checked');
            if (!role) {
                document.getElementById('roleError').style.display = 'block';
                return false;
            }
            document.getElementById('roleError').style.display = 'none';
            return true;
        }

        // Event Listeners (Live Validation)
        firstname.addEventListener('input', () => validateName(firstname, 'firstnameError'));
        lastname.addEventListener('input', () => validateName(lastname, 'lastnameError'));
        email.addEventListener('input', validateEmail);
        phone.addEventListener('input', validatePhone);
        password.addEventListener('input', validatePassword);
        terms.addEventListener('change', validateTerms);

        // Form Submission
        form.addEventListener('submit', function(e) {
            let isValid = true;
            if (!validateName(firstname, 'firstnameError')) isValid = false;
            if (!validateName(lastname, 'lastnameError')) isValid = false;
            if (!validateEmail()) isValid = false;
            if (!validatePhone()) isValid = false;
            if (!validatePassword()) isValid = false;
            if (!validateRole()) isValid = false;
            if (!validateTerms()) isValid = false;

            if (!isValid) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
