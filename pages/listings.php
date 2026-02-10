<?php
require_once '../includes/db_helper.php';
require_once '../paths.php';
session_start();

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
    header("Location: login.php");
    exit();
}

$pdo = getDBConnection();

// Get filter/sort parameters
$search = $_GET['search'] ?? '';
$type = $_GET['type'] ?? 'all';
$status = $_GET['status'] ?? 'all';
$sort = $_GET['sort'] ?? 'newest';

// Build Query
$query = "
    SELECT l.*, b.title, b.author, b.cover_image 
    FROM listings l
    JOIN books b ON l.book_id = b.id
    WHERE l.user_id = ?
";
$params = [$userId];

if ($search) {
    $query .= " AND (b.title LIKE ? OR b.author LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($type !== 'all') {
    $query .= " AND l.listing_type = ?";
    $params[] = $type;
}

if ($status === 'in_stock') {
    $query .= " AND l.quantity > 0";
} elseif ($status === 'out_of_stock') {
    $query .= " AND l.quantity <= 0";
}

// Sorting logic
$orderBy = "l.created_at DESC";
switch ($sort) {
    case 'title_asc': $orderBy = "b.title ASC"; break;
    case 'title_desc': $orderBy = "b.title DESC"; break;
    case 'price_asc': $orderBy = "l.price ASC"; break;
    case 'price_desc': $orderBy = "l.price DESC"; break;
    case 'stock_asc': $orderBy = "l.quantity ASC"; break;
    case 'stock_desc': $orderBy = "l.quantity DESC"; break;
    case 'newest': $orderBy = "l.created_at DESC"; break;
}
$query .= " ORDER BY $orderBy";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$myListings = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Listings | BOOK-B</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2.5rem;
            animation: fadeInUp 0.5s ease-out;
        }
        .listing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
            animation: fadeInUp 0.7s ease-out;
        }
        .listing-card {
            background: white;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            display: flex;
            flex-direction: column;
        }
        .listing-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-light);
        }
        .listing-img-wrapper {
            position: relative;
            height: 220px;
            overflow: hidden;
        }
        .listing-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s ease;
        }
        .listing-card:hover .listing-img {
            transform: scale(1.1);
        }
        .type-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            padding: 0.5rem 1rem;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: white;
            z-index: 2;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }
        .badge-borrow { background: var(--info); }
        .badge-sell { background: var(--success); }
        .badge-exchange { background: #8b5cf6; }

        .listing-content {
            padding: 1.5rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .listing-title {
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 0.35rem;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .listing-author {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        .listing-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: auto;
            padding-top: 1rem;
            border-top: 1px solid #f1f5f9;
        }
        .listing-price {
            font-weight: 800;
            color: var(--primary);
            font-size: 1.25rem;
        }
        .empty-state {
            text-align: center;
            padding: 6rem 2rem;
            background: white;
            border-radius: var(--radius-lg);
            border: 2px dashed var(--border-color);
        }

        /* Filter Bar Styles */
        .filter-bar {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
            display: flex;
            gap: 1.5rem;
            align-items: flex-end;
            flex-wrap: wrap;
            animation: fadeInDown 0.5s ease-out;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            flex: 1;
            min-width: 150px;
        }
        .filter-label {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .filter-select, .filter-input {
            padding: 0.75rem 1rem;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            background: #f8fafc;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-main);
            transition: all 0.2s;
            width: 100%;
        }
        .filter-select:focus, .filter-input:focus {
            border-color: var(--primary);
            background: white;
            outline: none;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }
        .search-group {
            flex: 2;
            min-width: 250px;
        }
        .filter-btn-group {
            display: flex;
            gap: 0.75rem;
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include '../includes/dashboard_sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1>My Book Listings</h1>
                    <p>Collections you've shared with the community</p>
                </div>
                <div style="display: flex; gap: 1rem;">
                    <a href="add_listing.php" class="btn btn-primary">
                        <i class='bx bx-plus-circle'></i> Add New Listing
                    </a>
                </div>
            </div>

            <!-- Filter Bar -->
            <form method="GET" class="filter-bar">
                <div class="filter-group search-group">
                    <label class="filter-label">Search</label>
                    <div style="position: relative;">
                        <i class='bx bx-search' style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                        <input type="text" name="search" class="filter-input" style="padding-left: 2.75rem;" placeholder="Search by title or author..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Listing Type</label>
                    <select name="type" class="filter-select">
                        <option value="all" <?php echo $type === 'all' ? 'selected' : ''; ?>>All Types</option>
                        <option value="borrow" <?php echo $type === 'borrow' ? 'selected' : ''; ?>>Borrow</option>
                        <option value="sell" <?php echo $type === 'sell' ? 'selected' : ''; ?>>Sell</option>
                        <option value="exchange" <?php echo $type === 'exchange' ? 'selected' : ''; ?>>Exchange</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Availability</label>
                    <select name="status" class="filter-select">
                        <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="in_stock" <?php echo $status === 'in_stock' ? 'selected' : ''; ?>>In Stock</option>
                        <option value="out_of_stock" <?php echo $status === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Sort By</label>
                    <select name="sort" class="filter-select">
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="title_asc" <?php echo $sort === 'title_asc' ? 'selected' : ''; ?>>Title (A-Z)</option>
                        <option value="title_desc" <?php echo $sort === 'title_desc' ? 'selected' : ''; ?>>Title (Z-A)</option>
                        <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Price (Low to High)</option>
                        <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Price (High to Low)</option>
                        <option value="stock_asc" <?php echo $sort === 'stock_asc' ? 'selected' : ''; ?>>Stock (Low to High)</option>
                        <option value="stock_desc" <?php echo $sort === 'stock_desc' ? 'selected' : ''; ?>>Stock (High to Low)</option>
                    </select>
                </div>

                <div class="filter-btn-group">
                    <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.5rem;">Apply</button>
                    <?php if ($search || $type !== 'all' || $status !== 'all' || $sort !== 'newest'): ?>
                        <a href="listings.php" class="btn btn-outline" style="padding: 0.75rem 1.25rem;">Reset</a>
                    <?php endif; ?>
                </div>
            </form>

            <?php if (empty($myListings)): ?>
                <div class="empty-state">
                    <i class='bx bx-book-add' style="font-size: 4.5rem; color: var(--border-color); margin-bottom: 1.5rem; display: block;"></i>
                    <h2 style="font-weight: 800; margin-bottom: 0.5rem; color: var(--text-main);">No Listings Yet</h2>
                    <p style="color: var(--text-muted); margin-bottom: 2rem; max-width: 400px; margin-left: auto; margin-right: auto;">Start sharing your library with others to earn reputation and help out the community.</p>
                    <a href="add_listing.php" class="btn btn-primary">Create Your First Listing</a>
                </div>
            <?php else: ?>
                <div class="listing-grid">
                    <?php foreach ($myListings as $listing): ?>
                        <div class="listing-card">
                            <span class="type-badge badge-<?php echo $listing['listing_type']; ?>">
                                <?php echo $listing['listing_type']; ?>
                            </span>
                            <?php if ($listing['quantity'] <= 0): ?>
                                <span class="type-badge" style="background: var(--text-muted); left: auto; right: 15px;">Out of Stock</span>
                            <?php endif; ?>
                            <div class="listing-img-wrapper">
                                <?php 
                                    $cover = $listing['cover_image'];
                                    $fallback = 'https://images.unsplash.com/photo-1543002588-bfa74002ed7e?w=600';
                                    $cover = $cover ?: $fallback;
                                ?>
                                <img src="<?php echo htmlspecialchars($cover, ENT_QUOTES, 'UTF-8', false); ?>" 
                                     class="listing-img" alt="Book" 
                                     onerror="this.onerror=null; this.src='<?php echo $fallback; ?>';">
                            </div>
                            <div class="listing-content">
                                <div class="listing-title"><?php echo htmlspecialchars($listing['title']); ?></div>
                                <div class="listing-author">by <?php echo htmlspecialchars($listing['author']); ?></div>
                                <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">
                                    <i class='bx bx-layer'></i> Quantity: <strong><?php echo $listing['quantity']; ?></strong>
                                </div>
                                
                                <div class="listing-footer">
                                    <div class="listing-price">
                                        <?php echo ($listing['listing_type'] === 'sell') ? '₹'.$listing['price'] : ucfirst($listing['listing_type']); ?>
                                    </div>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <a href="add_listing.php?edit=<?php echo $listing['id']; ?>" class="btn btn-outline btn-sm" style="padding: 0.4rem; border:1px solid var(--border-color); color: var(--text-main);"><i class='bx bx-edit-alt'></i></a>
                                        <button onclick="confirmDelete(<?php echo $listing['id']; ?>, '<?php echo addslashes($listing['title']); ?>')" class="btn btn-sm" style="padding: 0.4rem; border:1px solid #fee2e2; color: #ef4444; background: white;"><i class='bx bx-trash'></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
    <script>
        async function confirmDelete(id, title) {
            if (!confirm(`Are you sure you want to delete "${title}"? This cannot be undone.`)) return;

            try {
                const formData = new FormData();
                formData.append('listing_id', id);

                const response = await fetch('../actions/delete_listing.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                if (result.success) {
                    showToast(result.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast('Error: ' + result.message, 'error');
                }
            } catch (err) {
                console.error(err);
                showToast('An error occurred. Check console.', 'error');
            }
        }
    </script>
</body>
</html>
