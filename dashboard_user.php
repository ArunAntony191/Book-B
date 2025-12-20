<?php 
$user = [
    'name' => 'Alex M.',
    'role' => 'Individual User',
    'reputation' => 95,
    'reputation_status' => 'Excellent'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Dashboard | BOOK-B</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="container nav-container">
            <a href="index.php" class="logo">
                <div class="logo-icon"><i class='bx bx-book-bookmark'></i></div>
                BOOK-<span>B</span>
            </a>
            <div style="display: flex; gap: 1.5rem; align-items: center;">
                <span style="font-size:0.8rem; background:#e0e7ff; color:#4338ca; padding:4px 12px; border-radius:12px; font-weight:700;">USER</span>
                <div style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer;">
                    <img src="https://i.pravatar.cc/150?img=12" alt="Profile" style="width: 36px; height: 36px; border-radius: 50%;">
                    <i class='bx bx-chevron-down'></i>
                </div>
            </div>
        </div>
    </nav>

    <div class="dashboard-wrapper">
        <aside class="sidebar">
            <div class="sidebar-section-title">Menu</div>
            <a href="#" class="nav-item active"><i class='bx bxs-dashboard'></i> Dashboard</a>
            <a href="#" class="nav-item"><i class='bx bx-search-alt'></i> Explore</a>
            <a href="#" class="nav-item"><i class='bx bx-refresh'></i> Exchanges</a>
            <div class="sidebar-section-title">My Activities</div>
            <a href="#" class="nav-item"><i class='bx bx-book-reader'></i> Borrowed Books</a>
            <a href="#" class="nav-item"><i class='bx bx-upload'></i> My Listings</a>
            <a href="#" class="nav-item"><i class='bx bx-history'></i> History</a>
            <div class="sidebar-section-title">Account</div>
            <a href="#" class="nav-item"><i class='bx bx-trophy'></i> Reputation: 95</a>
        </aside>

        <main class="main-content">
            <div class="section-header">
                <h1>Welcome back, Alex!</h1>
                <p>Track your shared books, manage active requests, and discover your next read.</p>
            </div>

            <!-- Widgets -->
            <div class="widgets-grid">
                <div class="widget-card" style="text-align: center;">
                    <div class="widget-title" style="justify-content: center;">
                        <span><i class='bx bx-trophy'></i> Reputation</span>
                    </div>
                    <div style="font-size: 2.5rem; font-weight: 800; color: var(--text-main);">95</div>
                    <div style="color: var(--success); font-weight:700;">Excellent</div>
                </div>

                <div class="widget-card">
                    <div class="widget-title">
                        <span><i class='bx bx-time-five'></i> Active Borrows</span>
                    </div>
                    <div style="display: flex; gap: 1rem; align-items: center;">
                        <img src="https://images.unsplash.com/photo-1544947950-fa07a98d237f?auto=format&fit=crop&w=100&q=80" style="width: 40px; height: 55px; object-fit: cover; border-radius: 4px;" alt="Book">
                        <div>
                            <div style="font-weight: 600; font-size: 0.9rem;">The Midnight Library</div>
                            <div style="font-size: 0.8rem; color: var(--warning);">Return in 2 days</div>
                        </div>
                    </div>
                </div>

                <div class="widget-card">
                    <div class="widget-title"><span><i class='bx bx-message-detail'></i> Inbox</span></div>
                    <div class="chat-item">
                        <div style="font-size: 0.85rem;"><b>Sarah</b> requested an exchange for "Dune"</div>
                    </div>
                </div>
            </div>

             <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2 style="font-size: 1.5rem;">Recommended For You</h2>
            </div>

            <div class="book-grid">
                <!-- Book Card -->
                <div class="book-card">
                    <div class="book-cover">
                        <span style="position: absolute; top: 10px; right: 10px; background:white; padding: 2px 8px; border-radius:10px; font-size:0.7rem; font-weight:700;">Available</span>
                        <img src="https://images.unsplash.com/photo-1541963463532-d68292c34b19?auto=format&fit=crop&q=80&w=800" alt="Book">
                    </div>
                    <div class="book-info">
                        <div class="book-title">Thinking, Fast and Slow</div>
                        <div class="book-author">Daniel Kahneman</div>
                        <button class="btn btn-primary btn-sm w-full mt-4">Borrow Free</button>
                    </div>
                </div>
                 <div class="book-card">
                    <div class="book-cover">
                        <span style="position: absolute; top: 10px; right: 10px; background:white; padding: 2px 8px; border-radius:10px; font-size:0.7rem; font-weight:700;">Available</span>
                        <img src="https://images.unsplash.com/photo-1512820790803-83ca734da794?auto=format&fit=crop&q=80&w=800" alt="Book">
                    </div>
                    <div class="book-info">
                        <div class="book-title">Atomic Habits</div>
                        <div class="book-author">James Clear</div>
                        <button class="btn btn-primary btn-sm w-full mt-4">Borrow Free</button>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
