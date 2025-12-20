<?php 
require_once 'includes/db_helper.php';
include 'includes/dashboard_header.php'; 

// Ensure only library users can see this
if ($user['role'] !== 'library' && $user['role'] !== 'admin') {
    header("Location: dashboard_user.php");
    exit();
}
?>

<div class="dashboard-wrapper">
    <?php include 'includes/dashboard_sidebar.php'; ?>

    <main class="main-content">
        <div class="section-header">
            <h1>Library Management 🏛️</h1>
            <p>Administer your collection, manage members, and track borrowings.</p>
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
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 2rem; margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.25rem; font-weight: 700;">Recent Overdue Items</h2>
            <button class="btn btn-primary btn-sm">Notify All</button>
        </div>
        
        <div style="background: white; border-radius: var(--radius-lg); border: 1px solid var(--border-color); overflow: hidden; box-shadow: var(--shadow-sm);">
            <table style="width: 100%; border-collapse: collapse;">
                <thead style="background: #f8fafc; border-bottom: 1px solid var(--border-color);">
                    <tr>
                        <th style="padding: 1rem; text-align: left; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Book Details</th>
                        <th style="padding: 1rem; text-align: left; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Member</th>
                        <th style="padding: 1rem; text-align: left; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Status</th>
                        <th style="padding: 1rem; text-align: right; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 1.25rem;">
                            <div style="font-weight: 700; color: var(--text-main);">The Great Gatsby</div>
                            <div style="font-size: 0.8rem; color: var(--text-muted);">ID: #LIB-9842</div>
                        </td>
                        <td style="padding: 1.25rem;">John Doe</td>
                        <td style="padding: 1.25rem;"><span style="color: #ef4444; background: #fef2f2; padding: 4px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: 600;">Due Yesterday</span></td>
                        <td style="padding: 1.25rem; text-align: right;"><button class="btn btn-outline btn-sm">Send Alert</button></td>
                    </tr>
                    <tr>
                        <td style="padding: 1.25rem;">
                            <div style="font-weight: 700; color: var(--text-main);">1984</div>
                            <div style="font-size: 0.8rem; color: var(--text-muted);">ID: #LIB-1025</div>
                        </td>
                        <td style="padding: 1.25rem;">Jane Smith</td>
                        <td style="padding: 1.25rem;"><span style="color: #ef4444; background: #fef2f2; padding: 4px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: 600;">2 Days Overdue</span></td>
                        <td style="padding: 1.25rem; text-align: right;"><button class="btn btn-outline btn-sm">Send Alert</button></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </main>
</div>
</body>
</html>

