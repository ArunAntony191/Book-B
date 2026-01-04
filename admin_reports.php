<?php
require_once 'includes/db_helper.php';
require_once 'paths.php';
include 'includes/dashboard_header.php';

if ($user['role'] !== 'admin') {
    header("Location: dashboard_user.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Platform Reports | Admin</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/dashboard_sidebar.php'; ?>
        <main class="main-content">
            <div class="section-header">
                <div>
                    <h1><i class='bx bx-flag'></i> Platform Reports</h1>
                    <p>Track user reports and system alerts</p>
                </div>
            </div>

            <div style="background: white; border-radius: var(--radius-lg); border: 1px solid var(--border-color); margin-top: 2rem; padding: 5rem; text-align: center;">
                <i class='bx bx-check-shield' style="font-size: 5rem; color: #10b981; opacity: 0.3;"></i>
                <h3 style="margin-top: 2rem; color: var(--text-muted);">No active reports or disputes</h3>
                <p style="color: var(--text-muted); margin-top: 0.5rem;">All systems are nominal.</p>
            </div>
        </main>
    </div>
</body>
</html>
