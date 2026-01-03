<?php 
require_once 'includes/db_helper.php';
require_once 'paths.php';
include 'includes/dashboard_header.php'; 

// Ensure only bookstore users can see this
if ($user['role'] !== 'bookstore' && $user['role'] !== 'admin') {
    header("Location: dashboard_user.php");
    exit();
}
?>

<div class="dashboard-wrapper">
    <?php include 'includes/dashboard_sidebar.php'; ?>

    <main class="main-content">
        <div class="section-header">
            <h1>Store Manager 🏪</h1>
            <p>Track sales, manage orders, and update your professional book inventory.</p>
        </div>

        <!-- Stats Widgets -->
        <div class="widgets-grid">
            <div class="widget-card">
                <div class="widget-title"><span><i class='bx bx-dollar-circle'></i> Total Revenue</span></div>
                <div style="font-size: 2.5rem; font-weight: 800; color: var(--text-main);">$12,450</div>
                <div style="color: var(--success); font-weight: 600;">+12.5% this month</div>
            </div>

            <div class="widget-card">
                <div class="widget-title"><span><i class='bx bx-cart'></i> Pending Orders</span></div>
                <div style="font-size: 2.5rem; font-weight: 800; color: #f59e0b;">3</div>
                <div style="color: var(--text-muted);">Shipment required</div>
            </div>

            <div class="widget-card">
                <div class="widget-title"><span><i class='bx bx-package'></i> Low Stock</span></div>
                <div style="font-size: 2.5rem; font-weight: 800; color: #ef4444;">5</div>
                <div style="color: var(--text-muted);">Items below 5 units</div>
            </div>
        </div>

        <!-- Pending Orders -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 2rem; margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.25rem; font-weight: 700;">Orders to Process</h2>
            <a href="#" style="color: var(--primary); font-size: 0.9rem; text-decoration: none; font-weight: 600;">View All Orders</a>
        </div>

        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <!-- Order Card -->
            <div style="background: white; padding: 1.5rem; border-radius: var(--radius-lg); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; box-shadow: var(--shadow-sm);">
                <div style="display: flex; gap: 1.25rem; align-items: center;">
                    <img src="https://images.unsplash.com/photo-1543002588-bfa74002ed7e?auto=format&fit=crop&q=80&w=800" style="width: 50px; height: 70px; object-fit: cover; border-radius: 6px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <div>
                        <div style="font-weight: 800; color: var(--primary);">ORDER #BO-2024</div>
                        <div style="font-size: 1rem; font-weight: 600; color: var(--text-main);">Educated by Tara Westover</div>
                        <div style="font-size: 0.85rem; color: var(--text-muted);">Customer: <span style="font-weight: 600; color: var(--text-main);">Alice W.</span></div>
                    </div>
                </div>
                <div style="text-align: right;">
                    <div style="font-weight: 800; font-size: 1.25rem; color: var(--text-main); margin-bottom: 0.5rem;">$14.99</div>
                    <button class="btn btn-primary btn-sm" style="background: #f59e0b; border-color: #f59e0b;">Ship Package</button>
                </div>
            </div>
            
            <div style="background: white; padding: 1.5rem; border-radius: var(--radius-lg); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; box-shadow: var(--shadow-sm);">
                <div style="display: flex; gap: 1.25rem; align-items: center;">
                    <img src="https://images.unsplash.com/photo-1512820790803-83ca734da794?auto=format&fit=crop&q=80&w=800" style="width: 50px; height: 70px; object-fit: cover; border-radius: 6px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <div>
                        <div style="font-weight: 800; color: var(--primary);">ORDER #BO-2025</div>
                        <div style="font-size: 1rem; font-weight: 600; color: var(--text-main);">Atomic Habits</div>
                        <div style="font-size: 0.85rem; color: var(--text-muted);">Customer: <span style="font-weight: 600; color: var(--text-main);">Bob K.</span></div>
                    </div>
                </div>
                <div style="text-align: right;">
                    <div style="font-weight: 800; font-size: 1.25rem; color: var(--text-main); margin-bottom: 0.5rem;">$18.50</div>
                    <button class="btn btn-primary btn-sm" style="background: #f59e0b; border-color: #f59e0b;">Ship Package</button>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>

