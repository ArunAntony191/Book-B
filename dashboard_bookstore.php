<?php 
$user = [
    'name' => 'Main St. Books',
    'role' => 'Bookstore',
    'revenue' => 1250.00
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bookstore Dashboard | BOOK-B</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>
    <nav class="navbar" style="border-bottom: 2px solid #f59e0b;">
        <div class="container nav-container">
            <a href="index.php" class="logo">
                <div style="background:linear-gradient(135deg, #f59e0b 0%, #d97706 100%);" class="logo-icon"><i class='bx bxs-store'></i></div>
                <span>STORE</span>
            </a>
            <div style="display: flex; gap: 1.5rem; align-items: center;">
                 <span style="font-size:0.8rem; background:#fef3c7; color:#b45309; padding:4px 12px; border-radius:12px; font-weight:700;">BUSINESS</span>
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <img src="https://i.pravatar.cc/150?img=60" alt="Profile" style="width: 36px; height: 36px; border-radius: 50%;">
                    <i class='bx bx-chevron-down'></i>
                </div>
            </div>
        </div>
    </nav>

    <div class="dashboard-wrapper">
        <aside class="sidebar">
            <div class="sidebar-section-title">Store Management</div>
            <a href="#" class="nav-item active"><i class='bx bxs-dashboard'></i> Dashboard</a>
            <a href="#" class="nav-item"><i class='bx bx-shopping-bag'></i> Orders <span style="background:#f59e0b; color:white; border-radius:50%; font-size:0.7rem; padding:2px 6px; margin-left:auto;">3</span></a>
            <a href="#" class="nav-item"><i class='bx bx-list-ul'></i> Inventory</a>
            <a href="#" class="nav-item"><i class='bx bx-line-chart'></i> Sales Reports</a>
            <div class="sidebar-section-title">Promotion</div>
            <a href="#" class="nav-item"><i class='bx bx-purchase-tag-alt'></i> Discounts</a>
            <a href="#" class="nav-item"><i class='bx bx-cog'></i> Store Settings</a>
        </aside>

        <main class="main-content">
            <div class="section-header">
                <h1>Store Dashboard</h1>
                <p>Track sales, manage orders, and update your book listings.</p>
            </div>

            <!-- Stats Widgets -->
            <div class="widgets-grid">
                <div class="widget-card">
                    <div class="widget-title"><span><i class='bx bx-dollar-circle'></i> Total Revenue</span></div>
                    <div style="font-size: 2.5rem; font-weight: 800; color: var(--text-main);">$12,450</div>
                    <div style="color: var(--success);">+12% this month</div>
                </div>

                <div class="widget-card">
                    <div class="widget-title"><span><i class='bx bx-cart'></i> Pending Orders</span></div>
                    <div style="font-size: 2.5rem; font-weight: 800; color: #f59e0b;">3</div>
                    <div style="color: var(--text-muted);">Shipment required</div>
                </div>

                <div class="widget-card">
                    <div class="widget-title"><span><i class='bx bx-package'></i> Low Stock</span></div>
                    <div style="font-size: 2.5rem; font-weight: 800; color: #f59e0b;">5</div>
                    <div style="color: var(--text-muted);">Items below 5 units</div>
                </div>
            </div>

            <!-- Pending Orders -->
            <h2 style="font-size: 1.25rem; margin-bottom: 1rem;">Orders to Ship</h2>
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <div style="background: white; padding: 1.5rem; border-radius: var(--radius-lg); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; gap: 1rem; align-items: center;">
                        <img src="https://images.unsplash.com/photo-1543002588-bfa74002ed7e?auto=format&fit=crop&q=80&w=800" style="width: 50px; height: 70px; object-fit: cover; border-radius: 4px;">
                        <div>
                            <div style="font-weight: 700;">Order #2024</div>
                            <div style="font-size: 0.9rem; color: var(--text-muted);">Educated by Tara Westover</div>
                            <div style="font-size: 0.8rem; color: var(--text-muted);">Buyer: Alice W.</div>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-weight: 700; font-size: 1.1rem; margin-bottom: 0.5rem;">$14.99</div>
                        <button class="btn btn-primary btn-sm" style="background: #f59e0b; border-color: #f59e0b;">Mark Shipped</button>
                    </div>
                </div>
                
                <div style="background: white; padding: 1.5rem; border-radius: var(--radius-lg); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; gap: 1rem; align-items: center;">
                        <img src="https://images.unsplash.com/photo-1512820790803-83ca734da794?auto=format&fit=crop&q=80&w=800" style="width: 50px; height: 70px; object-fit: cover; border-radius: 4px;">
                        <div>
                            <div style="font-weight: 700;">Order #2025</div>
                            <div style="font-size: 0.9rem; color: var(--text-muted);">Atomic Habits</div>
                            <div style="font-size: 0.8rem; color: var(--text-muted);">Buyer: Bob K.</div>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-weight: 700; font-size: 1.1rem; margin-bottom: 0.5rem;">$18.50</div>
                        <button class="btn btn-primary btn-sm" style="background: #f59e0b; border-color: #f59e0b;">Mark Shipped</button>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
