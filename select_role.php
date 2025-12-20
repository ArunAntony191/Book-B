<?php
session_start();

// Check if user came from Google Sign-In
if (!isset($_SESSION['google_user'])) {
    header("Location: register.php");
    exit();
}

$googleUser = $_SESSION['google_user'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'includes/db_helper.php';
    
    $role = $_POST['role'] ?? 'user';
    $email = $googleUser['email'];
    $firstname = $googleUser['firstname'];
    $lastname = $googleUser['lastname'];
    
    // Generate a random password (won't be used since they'll use Google Sign-In)
    $randomPassword = bin2hex(random_bytes(16));
    
    if (createUser($email, $randomPassword, $firstname, $lastname, $role)) {
        // Clear Google user data
        unset($_SESSION['google_user']);
        
        // Set session
        $_SESSION['user_email'] = $email;
        $_SESSION['firstname'] = $firstname;
        $_SESSION['lastname'] = $lastname;
        $_SESSION['role'] = $role;
        
        // Redirect based on role
        switch ($role) {
            case 'library':
                header("Location: dashboard_library.php");
                break;
            case 'bookstore':
                header("Location: dashboard_bookstore.php");
                break;
            case 'admin':
                header("Location: dashboard_admin.php");
                break;
            case 'user':
            default:
                header("Location: dashboard_user.php");
                break;
        }
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Account Type | BOOK-B</title>
    <link rel="stylesheet" href="assets/css/style.css">
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
            max-width: 560px;
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
        .user-info {
            background: var(--bg-body);
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 2rem;
            text-align: center;
        }
        .role-selection {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }
        .role-card {
            position: relative;
            padding: 1.25rem;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all 0.2s;
            background: white;
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
                <h1 class="auth-title">Welcome, <?php echo htmlspecialchars($googleUser['firstname']); ?>!</h1>
                <p class="auth-subtitle">Choose your account type to get started</p>
            </div>

            <div class="user-info">
                <div style="font-size: 0.9rem; color: var(--text-muted);">Signing in as</div>
                <div style="font-weight: 600; color: var(--text-main);"><?php echo htmlspecialchars($googleUser['email']); ?></div>
            </div>

            <form action="select_role.php" method="POST">
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

                    <label class="role-card" data-role="admin">
                        <input type="radio" name="role" value="admin" required>
                        <div class="checkmark"><i class='bx bx-check'></i></div>
                        <div class="role-icon admin">
                            <i class='bx bxs-shield-alt-2'></i>
                        </div>
                        <div class="role-title">Administrator</div>
                        <div class="role-desc">Platform management</div>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary w-full">Continue</button>
            </form>
        </div>
    </div>

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
    </script>
</body>
</html>
