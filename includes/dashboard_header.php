<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in
if (!isset($_SESSION['user_email'])) {
    header("Location: login.php");
    exit();
}

$user = [
    'firstname' => $_SESSION['firstname'] ?? 'User',
    'lastname' => $_SESSION['lastname'] ?? '',
    'role' => $_SESSION['role'] ?? 'user',
    'email' => $_SESSION['user_email'] ?? ''
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ucfirst($user['role']); ?> Dashboard | BOOK-B</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        .role-badge {
            font-size: 0.75rem;
            background: var(--primary-light);
            color: var(--primary-dark);
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .user-profile-nav {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 6px 12px;
            border-radius: 12px;
            transition: all 0.2s;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }
        .user-profile-nav:hover {
            background: #f1f5f9;
        }
        .logout-link {
            color: #ef4444;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .logout-link:hover {
            background: #fef2f2;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container nav-container">
            <a href="index.php" class="logo">
                <div class="logo-icon"><i class='bx bx-book-bookmark'></i></div>
                BOOK-<span>B</span>
            </a>
            <div style="display: flex; gap: 1.5rem; align-items: center;">
                <span class="role-badge"><?php echo $user['role']; ?></span>
                <div class="user-profile-nav">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['firstname'] . ' ' . $user['lastname']); ?>&background=4f46e5&color=fff" alt="Profile" style="width: 32px; height: 32px; border-radius: 50%;">
                    <div style="display: flex; flex-direction: column;">
                        <span style="font-size: 0.85rem; font-weight: 700; color: var(--text-main);"><?php echo $user['firstname']; ?></span>
                    </div>
                </div>
                <a href="logout.php" class="logout-link"><i class='bx bx-log-out'></i></a>
            </div>
        </div>
    </nav>
