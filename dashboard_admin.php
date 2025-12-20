<?php 
require_once 'includes/db_helper.php';
include 'includes/dashboard_header.php'; 

// Ensure only admin can see this
if ($user['role'] !== 'admin') {
    header("Location: dashboard_user.php");
    exit();
}
?>

<div class="dashboard-wrapper">
    <?php include 'includes/dashboard_sidebar.php'; ?>

    <main class="main-content">
        <div class="section-header">
            <h1>System Administration 🛡️</h1>
            <p>Monitor platform growth, manage security, and oversee community moderation.</p>
        </div>

        <!-- Platform Stats -->
        <div class="widgets-grid">
            <div class="widget-card">
                <div class="widget-title"><span><i class='bx bx-user'></i> Total Users</span></div>
                <div style="font-size: 2.5rem; font-weight: 800; color: var(--text-main);">45,231</div>
                <div style="color: var(--success); font-weight: 600;">+145 joining today</div>
            </div>

            <div class="widget-card">
                <div class="widget-title"><span><i class='bx bx-book'></i> Total Books</span></div>
                <div style="font-size: 2.5rem; font-weight: 800; color: var(--primary);">128,400</div>
                <div style="color: var(--text-muted);">Across all categories</div>
            </div>

            <div class="widget-card">
                <div class="widget-title"><span><i class='bx bx-flag'></i> Urgent Reports</span></div>
                <div style="font-size: 2.5rem; font-weight: 800; color: #ef4444;">8</div>
                <div style="color: var(--text-muted);">Flagged by community</div>
            </div>
        </div>

        <!-- Reports Section -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 2rem; margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.25rem; font-weight: 700;">Moderation Queue</h2>
            <button class="btn btn-primary btn-sm">Clear All</button>
        </div>

        <div style="background: white; border-radius: var(--radius-lg); border: 1px solid var(--border-color); overflow: hidden; box-shadow: var(--shadow-sm);">
            <table style="width: 100%; border-collapse: collapse;">
                <thead style="background: #f8fafc; border-bottom: 1px solid var(--border-color);">
                    <tr>
                        <th style="padding: 1rem; text-align: left; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Target Item</th>
                        <th style="padding: 1rem; text-align: left; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Violation</th>
                        <th style="padding: 1rem; text-align: left; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Reporter</th>
                        <th style="padding: 1rem; text-align: right; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Decision</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 1.25rem;">
                            <div style="font-weight: 700; color: var(--text-main);">Comment #8821</div>
                            <div style="font-size: 0.8rem; color: #ef4444;">High Severity</div>
                        </td>
                        <td style="padding: 1.25rem;">Harassment</td>
                        <td style="padding: 1.25rem;">User123</td>
                        <td style="padding: 1.25rem; text-align: right;">
                            <button class="btn btn-outline btn-sm" style="color: #ef4444; border-color: #ef4444;">Delete</button>
                            <button class="btn btn-outline btn-sm">Dismiss</button>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 1.25rem;">
                            <div style="font-weight: 700; color: var(--text-main);">Book: "Fake Guide"</div>
                            <div style="font-size: 0.8rem; color: var(--warning);">Medium Severity</div>
                        </td>
                        <td style="padding: 1.25rem;">Spam Content</td>
                        <td style="padding: 1.25rem;">BookWorm99</td>
                        <td style="padding: 1.25rem; text-align: right;">
                            <button class="btn btn-outline btn-sm" style="color: #ef4444; border-color: #ef4444;">Remove</button>
                            <button class="btn btn-outline btn-sm">Dismiss</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </main>
</div>
</body>
</html>

