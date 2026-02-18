<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BOOK-B Database Setup</title>
    <link rel="stylesheet" href="assets/css/style.css?v=1.2">
    <style>
        .setup-container {
            max-width: 800px;
            margin: 4rem auto;
            padding: 0 2rem;
        }
        .setup-card {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            padding: 3rem;
        }
        .step {
            margin-bottom: 2rem;
            padding: 1.5rem;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
        }
        .step h3 {
            color: var(--primary);
            margin-bottom: 1rem;
        }
        .code-block {
            background: #f8fafc;
            padding: 1rem;
            border-radius: var(--radius-md);
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            overflow-x: auto;
            border: 1px solid var(--border-color);
        }
        .success {
            background: #dcfce7;
            color: #15803d;
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
        }
        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-card">
            <h1 style="text-align: center; margin-bottom: 2rem;">BOOK-B Database Setup</h1>

            <?php
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $host = $_POST['host'] ?? 'localhost';
                $username = $_POST['username'] ?? 'root';
                $password = $_POST['password'] ?? '';
                
                try {
                    // Read SQL file
                    $sql = file_get_contents(__DIR__ . '/database.sql');
                    
                    // Connect to MySQL
                    $pdo = new PDO("mysql:host=$host", $username, $password);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    
                    // Execute SQL statements
                    $pdo->exec($sql);
                    
                    echo '<div class="success">✅ Database created successfully! You can now <a href="index.php" style="color: inherit; text-decoration: underline;">go to the homepage</a>.</div>';
                    
                } catch (PDOException $e) {
                    echo '<div class="error">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            }
            ?>

            <div class="step">
                <h3>Step 1: Requirements</h3>
                <ul style="margin-left: 1.5rem; line-height: 2;">
                    <li>XAMPP installed and running</li>
                    <li>MySQL service started</li>
                    <li>PHP 7.4 or higher</li>
                </ul>
            </div>

            <div class="step">
                <h3>Step 2: Configure Database Connection</h3>
                <p style="margin-bottom: 1rem;">Enter your MySQL credentials below (default XAMPP settings are pre-filled):</p>
                
                <form method="POST">
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-weight: 600; margin-bottom: 0.5rem;">Host:</label>
                        <input type="text" name="host" value="localhost" style="width: 100%; padding: 0.75rem; border: 2px solid var(--border-color); border-radius: var(--radius-md);">
                    </div>
                    
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-weight: 600; margin-bottom: 0.5rem;">Username:</label>
                        <input type="text" name="username" value="root" style="width: 100%; padding: 0.75rem; border: 2px solid var(--border-color); border-radius: var(--radius-md);">
                    </div>
                    
                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; font-weight: 600; margin-bottom: 0.5rem;">Password:</label>
                        <input type="password" name="password" value="" placeholder="Leave empty for default XAMPP" style="width: 100%; padding: 0.75rem; border: 2px solid var(--border-color); border-radius: var(--radius-md);">
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-full">Create Database</button>
                </form>
            </div>

            <div class="step">
                <h3>Step 3: Test Accounts</h3>
                <p style="margin-bottom: 1rem;">After setup, you can login with these test accounts (password for all: <code style="background: #f8fafc; padding: 2px 6px; border-radius: 4px;">admin123</code>):</p>
                
                <div class="code-block">
                    <strong>Admin Account:</strong><br>
                    Email: admin@bookb.com<br><br>
                    
                    <strong>User Account:</strong><br>
                    Email: user@test.com<br><br>
                    
                    <strong>Library Account:</strong><br>
                    Email: library@test.com<br><br>
                    
                    <strong>Bookstore Account:</strong><br>
                    Email: store@test.com
                </div>
            </div>

            <div class="step">
                <h3>Step 4: Manual Setup (Alternative)</h3>
                <p>If automatic setup doesn't work, you can manually import the database:</p>
                <ol style="margin-left: 1.5rem; line-height: 2; margin-top: 1rem;">
                    <li>Open phpMyAdmin (http://localhost/phpmyadmin)</li>
                    <li>Click on "Import" tab</li>
                    <li>Choose the file: <code style="background: #f8fafc; padding: 2px 6px; border-radius: 4px;">database.sql</code></li>
                    <li>Click "Go" to import</li>
                </ol>
            </div>

            <div style="text-align: center; margin-top: 2rem;">
                <a href="index.php" class="btn btn-outline">← Back to Homepage</a>
            </div>
        </div>
    </div>
</body>
</html>
