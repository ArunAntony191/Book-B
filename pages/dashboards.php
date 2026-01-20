<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboards Hub | BOOK-B</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body style="display: flex; align-items: center; justify-content: center; min-height: 100vh; background-color: var(--bg-body);">
    <div class="container" style="text-align: center; max-width: 800px;">
        <h1 style="font-size: 2.5rem; margin-bottom: 1rem;">Choose a Dashboard</h1>
        <p style="color: var(--text-muted); margin-bottom: 3rem;">Simulate logging in as different user roles to see the tailored interfaces.</p>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem;">
            <!-- User -->
            <a href="dashboard_user.php" style="text-decoration: none; color: inherit;">
                <div class="widget-card" style="padding: 2rem; transition: transform 0.2s; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1rem;">
                    <div style="width: 60px; height: 60px; background: #e0e7ff; color: #4338ca; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem;">
                        <i class='bx bx-user'></i>
                    </div>
                    <h3 style="margin: 0;">Individual User</h3>
                    <p style="font-size: 0.9rem; color: var(--text-muted);">For standard borrowing, swapping, and reputation tracking.</p>
                </div>
            </a>
            
            <!-- Library -->
            <a href="dashboard_library.php" style="text-decoration: none; color: inherit;">
                <div class="widget-card" style="padding: 2rem; transition: transform 0.2s; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1rem;">
                    <div style="width: 60px; height: 60px; background: #dcfce7; color: #15803d; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem;">
                        <i class='bx bxs-institution'></i>
                    </div>
                    <h3 style="margin: 0;">Library</h3>
                    <p style="font-size: 0.9rem; color: var(--text-muted);">For inventory management and member lendings.</p>
                </div>
            </a>
            
            <!-- Bookstore -->
            <a href="dashboard_bookstore.php" style="text-decoration: none; color: inherit;">
                <div class="widget-card" style="padding: 2rem; transition: transform 0.2s; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1rem;">
                    <div style="width: 60px; height: 60px; background: #fef3c7; color: #b45309; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem;">
                        <i class='bx bxs-store'></i>
                    </div>
                    <h3 style="margin: 0;">Bookstore</h3>
                    <p style="font-size: 0.9rem; color: var(--text-muted);">For managing sales, orders, and revenue.</p>
                </div>
            </a>
            
            <!-- Admin -->
            <a href="dashboard_admin.php" style="text-decoration: none; color: inherit;">
                <div class="widget-card" style="padding: 2rem; transition: transform 0.2s; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1rem;">
                    <div style="width: 60px; height: 60px; background: #f1f5f9; color: #475569; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem;">
                        <i class='bx bxs-shield-alt-2'></i>
                    </div>
                    <h3 style="margin: 0;">Admin</h3>
                    <p style="font-size: 0.9rem; color: var(--text-muted);">For platform moderation and system overview.</p>
                </div>
            </a>

            <!-- Delivery Agent -->
            <a href="dashboard_delivery_agent.php" style="text-decoration: none; color: inherit;">
                <div class="widget-card" style="padding: 2rem; transition: transform 0.2s; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1rem;">
                    <div style="width: 60px; height: 60px; background: #e0f2fe; color: #0369a1; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem;">
                        <i class='bx bxs-truck'></i>
                    </div>
                    <h3 style="margin: 0;">Delivery Agent</h3>
                    <p style="font-size: 0.9rem; color: var(--text-muted);">For managing book pickups and deliveries.</p>
                </div>
            </a>
        </div>
        
        <div style="margin-top: 3rem;">
            <a href="index.php" style="color: var(--primary);">Back to Home</a>
        </div>
    </div>
    
    <!-- Link Boxicons just in case script.js doesn't load it here -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</body>
</html>
