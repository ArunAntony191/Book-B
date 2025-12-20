<?php 
$user = [
    'name' => 'City Central Library',
    'role' => 'Library',
    'catalog_size' => 12500,
    'members' => 3400
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Library Dashboard | BOOK-B</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>
    <nav class="navbar" style="border-bottom: 2px solid #10b981;">
        <div class="container nav-container">
            <a href="index.php" class="logo">
                <div style="background:linear-gradient(135deg, #10b981 0%, #059669 100%);" class="logo-icon"><i class='bx bxs-institution'></i></div>
                <span>LIBRARY</span>
            </a>
            <div style="display: flex; gap: 1.5rem; align-items: center;">
                 <span style="font-size:0.8rem; background:#dcfce7; color:#15803d; padding:4px 12px; border-radius:12px; font-weight:700;">INSTITUTION</span>
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <img src="https://i.pravatar.cc/150?img=50" alt="Profile" style="width: 36px; height: 36px; border-radius: 50%;">
                    <i class='bx bx-chevron-down'></i>
                </div>
            </div>
        </div>
    </nav>

    <div class="dashboard-wrapper">
        <aside class="sidebar">
            <div class="sidebar-section-title">Library Management</div>
            <a href="#" class="nav-item active"><i class='bx bxs-dashboard'></i> Overview</a>
            <a href="#" class="nav-item"><i class='bx bx-book-bookmark'></i> Catalog Manager</a>
            <a href="#" class="nav-item"><i class='bx bx-user-pin'></i> Member Requests</a>
            <a href="#" class="nav-item"><i class='bx bx-calendar-event'></i> Due Returns <span style="background:#ef4444; color:white; border-radius:50%; font-size:0.7rem; padding:2px 6px; margin-left:auto;">5</span></a>
            <div class="sidebar-section-title">Community</div>
            <a href="#" class="nav-item"><i class='bx bx-news'></i> Announcements</a>
            <a href="#" class="nav-item"><i class='bx bx-cog'></i> Settings</a>
        </aside>

        <main class="main-content">
            <div class="section-header">
                <h1>Library Overview</h1>
                <p>Manage inventory, lendings, and community engagement.</p>
            </div>

            <!-- Library Stats Widgets -->
            <div class="widgets-grid">
                <div class="widget-card">
                    <div class="widget-title"><span><i class='bx bx-library'></i> Total Inventory</span></div>
                    <div style="font-size: 2.5rem; font-weight: 800; color: var(--text-main);">12,543</div>
                    <div style="color: var(--text-muted);">Books available</div>
                </div>

                <div class="widget-card">
                    <div class="widget-title"><span><i class='bx bx-export'></i> Currently Lent</span></div>
                    <div style="font-size: 2.5rem; font-weight: 800; color: #10b981;">842</div>
                    <div style="color: var(--text-muted);">Books out with members</div>
                </div>

                <div class="widget-card">
                    <div class="widget-title"><span><i class='bx bx-error-circle'></i> Overdue</span></div>
                    <div style="font-size: 2.5rem; font-weight: 800; color: #ef4444;">12</div>
                    <div style="color: var(--text-muted);">Items need attention</div>
                </div>
            </div>

            <!-- Overdue List -->
            <h2 style="font-size: 1.25rem; margin-bottom: 1rem;">Recent Overdue Items</h2>
            <div style="background: white; border-radius: var(--radius-lg); border: 1px solid var(--border-color); overflow: hidden;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead style="background: #f8fafc; border-bottom: 1px solid var(--border-color);">
                        <tr>
                            <th style="padding: 1rem; text-align: left; font-size: 0.85rem; color: var(--text-muted);">Book Title</th>
                            <th style="padding: 1rem; text-align: left; font-size: 0.85rem; color: var(--text-muted);">Borrowed By</th>
                            <th style="padding: 1rem; text-align: left; font-size: 0.85rem; color: var(--text-muted);">Due Date</th>
                            <th style="padding: 1rem; text-align: right; font-size: 0.85rem; color: var(--text-muted);">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 1rem; font-weight: 600;">The Great Gatsby</td>
                            <td style="padding: 1rem;">John Doe</td>
                            <td style="padding: 1rem; color: #ef4444;">Yesterday</td>
                            <td style="padding: 1rem; text-align: right;"><button class="btn btn-outline btn-sm">Remind</button></td>
                        </tr>
                        <tr>
                            <td style="padding: 1rem; font-weight: 600;">1984</td>
                            <td style="padding: 1rem;">Jane Smith</td>
                            <td style="padding: 1rem; color: #ef4444;">2 Days Ago</td>
                            <td style="padding: 1rem; text-align: right;"><button class="btn btn-outline btn-sm">Remind</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
