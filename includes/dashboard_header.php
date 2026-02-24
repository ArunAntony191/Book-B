<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in
if (!isset($_SESSION['user_email'])) {
    // Check for "Remember me" cookie
    if (isset($_COOKIE['remember_me'])) {
        require_once 'db_helper.php';
        $user = getUserByRememberToken($_COOKIE['remember_me']);
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['firstname'] = $user['firstname'];
            $_SESSION['lastname'] = $user['lastname'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['theme_mode'] = $user['theme_mode'] ?? 'light';
            $_SESSION['email_notifications'] = $user['email_notifications'] ?? 1;
            // No redirect needed, continue to page
        } else {
            // Invalid token, clear cookie
            setcookie('remember_me', '', time() - 3600, '/');
            header("Location: login.php");
            exit();
        }
    } else {
        header("Location: login.php");
        exit();
    }
}

// Refresh role from database to ensure it's always up to date (Sync)
$userId = $_SESSION['user_id'] ?? null;
if ($userId) {
    syncSessionRole($userId);
    // Fetch full user data including profile picture
    $userData = getUserById($userId);
    $user = [
        'firstname' => $userData['firstname'] ?? $_SESSION['firstname'] ?? 'User',
        'lastname' => $userData['lastname'] ?? $_SESSION['lastname'] ?? '',
        'role' => $_SESSION['role'] ?? 'user',
        'email' => $_SESSION['user_email'] ?? '',
        'profile_picture' => $userData['profile_picture'] ?? null,
        'favorite_category' => $userData['favorite_category'] ?? null
    ];
} else {
    $user = [
        'firstname' => $_SESSION['firstname'] ?? 'User',
        'lastname' => $_SESSION['lastname'] ?? '',
        'role' => $_SESSION['role'] ?? 'user',
        'email' => $_SESSION['user_email'] ?? '',
        'profile_picture' => null
    ];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $_SESSION['theme_mode'] ?? 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ucfirst($user['role']); ?> Dashboard | BOOK-B</title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css?v=1.3">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/toast.css?v=1.0">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="<?php echo APP_URL; ?>/assets/js/toast.js?v=1.0"></script>
    <style>
        .navbar {
            height: 70px;
            display: flex;
            align-items: center;
            background: var(--bg-card);
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .nav-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            max-width: none !important;
            padding: 0 2rem !important;
        }
        .role-badge {
            font-size: 0.7rem;
            background: var(--bg-body);
            color: var(--text-muted);
            padding: 6px 14px;
            border-radius: 30px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            display: inline-flex;
            align-items: center;
            height: fit-content;
            border: 1px solid var(--border-color);
        }
        .role-badge.badge-admin {
            background: #4f46e5;
            color: #fff;
            border-color: #4f46e5;
            box-shadow: 0 2px 4px rgba(79, 70, 229, 0.3);
        }
        .user-profile-nav {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 4px 16px 4px 4px;
            border-radius: 40px;
            transition: all 0.2s;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            cursor: pointer;
        }
        .user-profile-nav:hover {
            border-color: var(--primary);
            background: var(--bg-body);
        }
        .logout-link {
            color: #ef4444;
            display: flex;
            align-items: center;
            text-decoration: none;
            font-size: 1.4rem;
            transition: all 0.2s;
            padding: 8px;
            border-radius: 50%;
        }
        .logout-link:hover {
            background: rgba(239, 68, 68, 0.1);
            transform: scale(1.1);
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container nav-container">
            <a href="<?php echo APP_URL; ?>/index.php" class="logo">
                <div class="logo-icon"><i class='bx bx-book-bookmark'></i></div>
                BOOK- <span>B</span>
            </a>
            <div style="display: flex; gap: 1rem; align-items: center;">
                <span class="role-badge <?php echo $user['role'] === 'admin' ? 'badge-admin' : ''; ?>"><?php echo $user['role']; ?></span>
                <div class="user-profile-nav" onclick="location.href='profile.php'">
                    <?php if (!empty($user['profile_picture'])): ?>
                        <img src="<?php echo APP_URL . '/' . $user['profile_picture']; ?>" alt="Profile" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;">
                    <?php else: ?>
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['firstname'] . ' ' . $user['lastname']); ?>&background=4f46e5&color=fff" alt="Profile" style="width: 32px; height: 32px; border-radius: 50%;">
                    <?php endif; ?>
                    <span style="font-size: 0.85rem; font-weight: 700; color: var(--text-main);"><?php echo $user['firstname'] . ' ' . $user['lastname']; ?></span>
                </div>
                <a href="<?php echo APP_URL; ?>/actions/logout.php" class="logout-link" title="Logout">
                    <i class='bx bx-power-off'></i>
                </a>
            </div>
        </div>
    </nav>
