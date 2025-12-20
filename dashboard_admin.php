<?php 
$user = [
    'name' => 'Administrator',
    'role' => 'Admin'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard | BOOK-B</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>
    <nav class="navbar" style="background: #0f172a; border-bottom: none;">
        <div class="container nav-container">
            <a href="index.php" class="logo" style="color: white;">
                <div style="background: white; color: #0f172a;" class="logo-icon"><i class='bx bxs-shield-alt-2'></i></div>
                <span>ADMIN</span>
            </a>
            <div style="display: flex; gap: 1.5rem; align-items: center;">
                 <span style="font-size:0.8rem; background:#334155; color:#94a3b8; padding:4px 12px; border-radius:12px; font-weight:700;">SYSTEM</span>
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <img src="https://i.pravatar.cc/150?img=1" alt="Profile" style="width: 36px; height: 36px; border-radius: 50%;">
                </div>
            </div>
        </div>
    </nav>

    <div class="dashboard-wrapper">
        <aside class="sidebar" style="background: #f8fafc;">
            <div class="sidebar-section-title">System Administration</div>
            <a href="#" class="nav-item active"><i class='bx bxs-dashboard'></i> Overview</a>
            <a href="#" class="nav-item"><i class='bx bx-user-circle'></i> User Management</a>
            <a href="#" class="nav-item"><i class='bx bx-book-content'></i> Content Moderation</a>
            <a href="#" class="nav-item"><i class='bx bx-bar-chart-alt-2'></i> Platform Stats</a>
            <div class="sidebar-section-title">Technical</div>
            <a href="#" class="nav-item"><i class='bx bx-server'></i> System Health</a>
            <a href="#" class="nav-item"><i class='bx bx-file'></i> Logs</a>
        </aside>

        <main class="main-content">
            <div class="section-header">
                <h1>Platform Overview</h1>
                <p>Monitor system growth, user activity, and reported content.</p>
            </div>

            <!-- Platform Stats -->
            <div class="widgets-grid">
                <div class="widget-card">
                    <div class="widget-title"><span><i class='bx bx-user'></i> Total Users</span></div>
                    <div style="font-size: 2.5rem; font-weight: 800; color: var(--text-main);">45,231</div>
                    <div style="color: var(--success);">+145 today</div>
                </div>

                <div class="widget-card">
                    <div class="widget-title"><span><i class='bx bx-book'></i> Total Books</span></div>
                    <div style="font-size: 2.5rem; font-weight: 800; color: var(--primary);">128,400</div>
                    <div style="color: var(--text-muted);">Across all categories</div>
                </div>

                <div class="widget-card">
                    <div class="widget-title"><span><i class='bx bx-flag'></i> Reports</span></div>
                    <div style="font-size: 2.5rem; font-weight: 800; color: #ef4444;">8</div>
                    <div style="color: var(--text-muted);">Content flagged by users</div>
                </div>
            </div>

            <!-- Reports Section -->
            <h2 style="font-size: 1.25rem; margin-bottom: 1rem;">Recent Reports</h2>
             <div style="background: white; border-radius: var(--radius-lg); border: 1px solid var(--border-color); overflow: hidden;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead style="background: #f8fafc; border-bottom: 1px solid var(--border-color);">
                        <tr>
                            <th style="padding: 1rem; text-align: left; font-size: 0.85rem; color: var(--text-muted);">Reported Item</th>
                            <th style="padding: 1rem; text-align: left; font-size: 0.85rem; color: var(--text-muted);">Reason</th>
                            <th style="padding: 1rem; text-align: left; font-size: 0.85rem; color: var(--text-muted);">Reporter</th>
                            <th style="padding: 1rem; text-align: right; font-size: 0.85rem; color: var(--text-muted);">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 1rem; font-weight: 600;">Comment #8821</td>
                            <td style="padding: 1rem;">Harassment</td>
                            <td style="padding: 1rem;">User123</td>
                            <td style="padding: 1rem; text-align: right;">
                                <button class="btn btn-outline btn-sm" style="color: #ef4444; border-color: #ef4444;">Delete</button>
                                <button class="btn btn-outline btn-sm">Ignore</button>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 1rem; font-weight: 600;">Book: "Fake Guide"</td>
                            <td style="padding: 1rem;">Misleading Content</td>
                            <td style="padding: 1rem;">BookWorm99</td>
                            <td style="padding: 1rem; text-align: right;">
                                <button class="btn btn-outline btn-sm" style="color: #ef4444; border-color: #ef4444;">Remove</button>
                                <button class="btn btn-outline btn-sm">Ignore</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
