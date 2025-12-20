<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BOOK-B | Free Book Borrowing Platform</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="container nav-container">
            <a href="index.php" class="logo">
                <div class="logo-icon"><i class='bx bx-book-bookmark'></i></div>
                BOOK-<span>B</span>
            </a>
            <div style="display: flex; gap: 2rem; align-items: center;">
                <a href="#features" style="text-decoration: none; color: var(--text-body); font-weight: 500;">Features</a>
                <a href="#how-it-works" style="text-decoration: none; color: var(--text-body); font-weight: 500;">How it Works</a>
                <a href="login.php" style="text-decoration: none; color: var(--text-body); font-weight: 500;">Login</a>
                <a href="register.php" class="btn btn-primary">Join Free</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="hero-content">
                <h1 class="hero-title">
                    Borrow Books.<br>
                    Read More.<br>
                    Spend less.
                </h1>
                <p class="hero-subtitle">
                    Join <span style="color: var(--primary-color); font-weight: 700;">BOOK-B</span> — the free community where book lovers share locally.
                </p>
                <div style="display: flex; gap: 1rem; justify-content: center;">
                    <a href="register.php" class="btn btn-primary btn-lg">Start Borrowing — It's Free</a>
                    <a href="#how-it-works" class="btn btn-outline btn-lg">See How It Works</a>
                </div>
            </div>
            
            <div class="hero-image-container">
                <img src="https://images.unsplash.com/photo-1507842217121-9d59630c93e1?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80" alt="Library Books">
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features-section">
        <div class="container">
            <h2 class="section-title">Why People Love BOOK-B</h2>
            
            <div class="features-grid">
                <div class="feature-item">
                    <div class="feature-icon-wrapper">
                        <i class='bx bx-book-open'></i>
                    </div>
                    <h3 class="feature-title">Variety Books</h3>
                    <p class="feature-desc">From fiction and textbooks to comics and rare editions — endless choices to discover.</p>
                </div>
                
                <div class="feature-item">
                    <div class="feature-icon-wrapper">
                        <i class='bx bx-group'></i>
                    </div>
                    <h3 class="feature-title">Trusted Community</h3>
                    <p class="feature-desc">Ratings, reviews, and verified profiles keep everything safe.</p>
                </div>
                
                <div class="feature-item">
                    <div class="feature-icon-wrapper">
                        <i class='bx bx-user-voice'></i>
                    </div>
                    <h3 class="feature-title">Join groups</h3>
                    <p class="feature-desc">Connect with local book clubs, neighborhoods, and interest-based circles.</p>
                </div>
                
                <div class="feature-item">
                    <div class="feature-icon-wrapper">
                        <i class='bx bx-leaf'></i>
                    </div>
                    <h3 class="feature-title">Eco-Friendly</h3>
                    <p class="feature-desc">One borrowed book = one less book printed. Save trees!</p>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section id="how-it-works" class="how-it-works-section">
        <div class="container">
            <h2 class="section-title">How BOOK-B Works</h2>
            
            <div class="steps-grid">
                <div class="step-card">
                    <div class="step-number">1</div>
                    <h3 class="feature-title">Sign Up Free</h3>
                    <p class="feature-desc">30 seconds, no credit card</p>
                </div>
                <div class="step-card">
                    <div class="step-number">2</div>
                    <h3 class="feature-title">Find or List Books</h3>
                    <p class="feature-desc">Search nearby or add your own</p>
                </div>
                <div class="step-card">
                    <div class="step-number">3</div>
                    <h3 class="feature-title">Chat & Collect</h3>
                    <p class="feature-desc">Message and arrange pickup</p>
                </div>
                <div class="step-card">
                    <div class="step-number">4</div>
                    <h3 class="feature-title">Read & Return</h3>
                    <p class="feature-desc">Enjoy and give back on time</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer CTA -->
    <section class="footer-cta">
        <div class="container">
            <h2>Start Reading Today</h2>
            <p>Join 10,000+ book lovers already saving money with BOOK-B</p>
            <a href="register.php" class="btn btn-lg">Join BOOK-B — Free Forever</a>
        </div>
    </section>

    <script src="assets/js/script.js"></script>
</body>
</html>
