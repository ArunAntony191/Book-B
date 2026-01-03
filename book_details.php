<?php
require_once 'includes/db_helper.php';
require_once 'paths.php';
session_start();

$listingId = $_GET['id'] ?? 0;
// In a real app, we'd fetch the specific listing. For this repro:
$pdo = getDBConnection();
$stmt = $pdo->prepare("
    SELECT l.*, b.title, b.author, b.description, b.cover_image, b.category, 
           u.firstname, u.lastname, u.role, u.reputation_score
    FROM listings l
    JOIN books b ON l.book_id = b.id
    JOIN users u ON l.user_id = u.id
    WHERE l.id = ?
");
$stmt->execute([$listingId]);
$book = $stmt->fetch();

if (!$book) {
    header("Location: explore.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $book['title']; ?> | BOOK-B</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        .details-grid {
            display: grid;
            grid-template-columns: 350px 1fr 300px;
            gap: 2rem;
            padding: 2rem;
        }
        .book-img-large {
            width: 100%;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
        }
        .mini-map {
            height: 200px;
            width: 100%;
            border-radius: var(--radius-md);
            margin-top: 1.5rem;
            border: 1px solid var(--border-color);
        }
        .provider-info {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/dashboard_sidebar.php'; ?>
        
        <div class="content-wrapper">
            <div class="details-grid">
                <div class="column-left">
                    <img src="<?php echo $book['cover_image'] ?: 'https://images.unsplash.com/photo-1543002588-bfa74002ed7e?auto=format&fit=crop&q=80&w=400'; ?>" class="book-img-large" alt="Book Cover">
                </div>
                
                <div class="column-main">
                    <h1 style="font-size: 2.5rem; color: var(--text-main); margin-bottom: 0.5rem;"><?php echo htmlspecialchars($book['title']); ?></h1>
                    <p style="font-size: 1.25rem; color: var(--text-muted); margin-bottom: 2rem;">by <?php echo htmlspecialchars($book['author']); ?></p>
                    
                    <div style="background: white; padding: 2rem; border-radius: var(--radius-lg); border: 1px solid var(--border-color); margin-bottom: 2rem;">
                        <h3 style="margin-bottom: 1rem;">Description</h3>
                        <p style="line-height: 1.6; color: var(--text-muted);"><?php echo nl2br(htmlspecialchars($book['description'] ?: 'No description available for this book.')); ?></p>
                    </div>
                </div>

                <div class="column-right">
                    <div class="provider-info">
                        <div style="font-weight: 700; margin-bottom: 1rem;">Listing Details</div>
                        <div style="display:flex; justify-content: space-between; margin-bottom: 0.5rem;">
                            <span>Price</span>
                            <span style="font-weight: 700; color: var(--primary);">₹<?php echo $book['price']; ?></span>
                        </div>
                        <div style="display:flex; justify-content: space-between; margin-bottom: 1.5rem;">
                            <span>Type</span>
                            <span class="badge badge-<?php echo $book['listing_type']; ?>"><?php echo ucfirst($book['listing_type']); ?></span>
                        </div>

                        <hr style="border:0; border-top:1px solid var(--border-color); margin: 1.5rem 0;">

                        <div style="font-weight: 700; margin-bottom: 1rem;">Provider</div>
                        <div style="display:flex; align-items: center; gap: 0.75rem; margin-bottom: 1.5rem;">
                            <div style="width:40px; height:40px; background: #e2e8f0; border-radius: 50%; display:flex; align-items:center; justify-content:center;">
                                <i class='bx bx-user' style="font-size: 1.25rem;"></i>
                            </div>
                            <div>
                                <div style="font-weight: 600;"><?php echo $book['firstname'] . ' ' . $book['lastname']; ?></div>
                                <div style="font-size: 0.8rem; color: var(--text-muted);"><?php echo ucfirst($book['role']); ?></div>
                            </div>
                        </div>

                        <a href="chat/index.php?user=<?php echo $book['user_id']; ?>" class="btn btn-primary w-full" style="text-align: center; text-decoration: none; display: block; margin-bottom: 1rem;">
                            <i class='bx bx-message-rounded-dots'></i> Chat with Owner
                        </a>

                        <?php if ($book['latitude'] && $book['longitude']): ?>
                            <div style="font-weight: 600; font-size: 0.85rem; color: var(--text-muted); margin-top: 1.5rem;">Pickup Location</div>
                            <div id="mini-map" class="mini-map"></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        <?php if ($book['latitude'] && $book['longitude']): ?>
            const map = L.map('mini-map', {
                zoomControl: false,
                attributionControl: false,
                dragging: false,
                touchZoom: false,
                scrollWheelZoom: false,
                doubleClickZoom: false
            }).setView([<?php echo $book['latitude']; ?>, <?php echo $book['longitude']; ?>], 14);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
            L.marker([<?php echo $book['latitude']; ?>, <?php echo $book['longitude']; ?>]).addTo(map);
        <?php endif; ?>
    </script>
</body>
</html>
