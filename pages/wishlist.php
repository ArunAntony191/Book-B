<?php
require_once '../includes/db_helper.php';
require_once '../paths.php';
session_start();

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) { header("Location: login.php"); exit(); }

$pdo = getDBConnection();
// Fetch Wishlist Items
$stmt = $pdo->prepare("
    SELECT l.*, b.title, b.author, b.cover_image, b.category, u.firstname, u.lastname
    FROM wishlist w
    JOIN listings l ON w.listing_id = l.id
    JOIN books b ON l.book_id = b.id
    JOIN users u ON l.user_id = u.id
    WHERE w.user_id = ?
    ORDER BY w.created_at DESC
");
$stmt->execute([$userId]);
$wishlist = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Wishlist | BOOK-B</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1.5rem;
        }
        .book-card {
            background: white;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            overflow: hidden;
            transition: all 0.2s;
            cursor: pointer;
        }
        .book-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); border-color: var(--primary); }
        .book-cover {
            width: 100%;
            aspect-ratio: 2/3;
            object-fit: cover;
        }
        .book-info { padding: 1rem; }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include '../includes/dashboard_sidebar.php'; ?>
        
        <main class="main-content">
            <h1 style="font-size: 1.8rem; font-weight: 800; margin-bottom: 2rem;">My Wishlist</h1>
            
            <?php if (count($wishlist) > 0): ?>
                <div class="books-grid">
                    <?php foreach ($wishlist as $item): ?>
                        <div class="book-card" onclick="window.location.href='book_details.php?id=<?php echo $item['id']; ?>'">
                            <img src="<?php echo $item['cover_image'] ?: '../assets/images/book-placeholder.jpg'; ?>" class="book-cover">
                            <div class="book-info">
                                <div style="font-weight: 700; margin-bottom: 0.25rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($item['title']); ?></div>
                                <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.5rem;"><?php echo htmlspecialchars($item['author']); ?></div>
                                <span class="badge badge-<?php echo $item['listing_type']; ?>"><?php echo ucfirst($item['listing_type']); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 4rem; background: white; border-radius: var(--radius-lg); border: 1px solid var(--border-color);">
                    <i class='bx bx-heart' style="font-size: 3rem; color: #cbd5e1; margin-bottom: 1rem;"></i>
                    <h3 style="color: var(--text-muted);">Your wishlist is empty.</h3>
                    <a href="explore.php" class="btn btn-primary" style="margin-top: 1rem;">Explore Books</a>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
