<?php
require_once '../includes/db_helper.php';
require_once '../paths.php';
include '../includes/dashboard_header.php';

$idsParam = $_GET['ids'] ?? '';
$ids = array_filter(explode(',', $idsParam), 'is_numeric');

if (empty($ids)) {
    header("Location: wishlist.php");
    exit();
}

// Limit to 3 items
$ids = array_slice($ids, 0, 3);

$pdo = getDBConnection();
$placeholders = str_repeat('?,', count($ids) - 1) . '?';
$stmt = $pdo->prepare("
    SELECT l.*, b.title, b.author, b.isbn, b.cover_image, b.category, b.condition_status, b.description,
           u.firstname, u.lastname, u.trust_score
    FROM listings l
    JOIN books b ON l.book_id = b.id
    JOIN users u ON l.user_id = u.id
    WHERE l.id IN ($placeholders)
");
$stmt->execute($ids);
$books = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Sort books to match input order if needed, or just display as is.
?>
<style>
        .compare-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
            overflow-x: auto;
        }
        .compare-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: white;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            table-layout: fixed; /* Ensures equal column widths */
        }
        .compare-table th, .compare-table td {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            border-right: 1px solid var(--border-color);
            vertical-align: top;
        }
        .compare-table tr:last-child td { border-bottom: none; }
        .compare-table th:last-child, .compare-table td:last-child { border-right: none; }
        
        .compare-table th {
            background-color: var(--bg-body);

            font-weight: 600;
            color: var(--text-main);

            width: 150px;
            position: sticky;
            left: 0;
            z-index: 10;
        }
        
        /* Specific widths for book columns - distribute remaining space */
        .compare-table td {
            width: calc((100% - 150px) / <?php echo count($books); ?>);
            min-width: 250px; /* Minimum width for readability */
        }

        .book-preview-img {
            width: 100%;
            height: 250px;
            object-fit: cover;
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
        }
        .attr-label {
            font-weight: 600;
            color: var(--text-muted);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .btn-action { width: 100%; margin-top: 1rem; }
</style>
    <div class="dashboard-wrapper">
        <?php include '../includes/dashboard_sidebar.php'; ?>
        
        <main class="main-content">
            <div style="display: flex; align-items: center; margin-bottom: 2rem;">
                <a href="wishlist.php" class="btn btn-outline" style="margin-right: 1rem;"><i class='bx bx-arrow-back'></i> Back</a>
                <h1 style="font-size: 1.8rem; font-weight: 800; margin: 0;">Compare Books</h1>
            </div>

            <?php if (count($books) > 0): ?>
                <div class="compare-container">
                    <table class="compare-table">
                        <!-- Header Row with Images -->
                        <tr>
                            <th class="attr-label">Book Details</th>
                            <?php foreach ($books as $book): ?>
                                <td>
                                    <?php 
                                        $cover = $book['cover_image'];
                                        // If it's a relative path (doesn't start with http), it's likely already relative to the pages/ directory
                                        // We avoid prepending ../ because images/books/ is inside the pages/ folder.
                                        $fallback = 'https://images.unsplash.com/photo-1543004218-ee141104975a?w=400';
                                        $cover = $cover ?: $fallback;
                                    ?>
                                    <img src="<?php echo htmlspecialchars(html_entity_decode($cover), ENT_QUOTES, 'UTF-8'); ?>" 
                                         class="book-cover" 
                                         onerror="this.onerror=null; this.src='<?php echo $fallback; ?>';">
                                    <h3 style="font-size: 1.2rem; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($book['title']); ?></h3>
                                    <p style="color: var(--text-muted);"><?php echo htmlspecialchars($book['author']); ?></p>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        
                        <!-- Cost/Price -->
                        <tr>
                            <th class="attr-label">Price / Tokens</th>
                            <?php foreach ($books as $book): ?>
                                <td>
                                    <div style="font-size: 1.1rem; font-weight: 700; color: var(--primary);">
                                        <?php if ($book['listing_type'] == 'sell'): ?>
                                            ₹<?php echo number_format($book['price'], 2); ?>
                                        <?php else: ?>
                                            <i class='bx bxs-coin-stack'></i> <?php echo $book['credit_cost'] ?? 0; ?> Credits

                                        <?php endif; ?>
                                    </div>
                                    <span class="badge badge-<?php echo $book['listing_type']; ?>" style="margin-top: 0.5rem; display: inline-block;">
                                        <?php echo ucfirst($book['listing_type']); ?>
                                    </span>
                                </td>
                            <?php endforeach; ?>
                        </tr>

                        <!-- Condition -->
                        <tr>
                            <th class="attr-label">Condition</th>
                            <?php foreach ($books as $book): ?>
                                <td>
                                    <?php 
                                        $condColor = 'var(--text-main)';

                                        if($book['condition_status'] === 'new') $condColor = '#10b981';
                                        elseif($book['condition_status'] === 'like_new') $condColor = '#3b82f6';
                                    ?>
                                    <span style="font-weight: 600; color: <?php echo $condColor; ?>">
                                        <?php echo ucwords(str_replace('_', ' ', $book['condition_status'])); ?>
                                    </span>
                                </td>
                            <?php endforeach; ?>
                        </tr>

                        <!-- Category -->
                        <tr>
                            <th class="attr-label">Category</th>
                            <?php foreach ($books as $book): ?>
                                <td><?php echo htmlspecialchars($book['category']); ?></td>
                            <?php endforeach; ?>
                        </tr>
                        
                        <!-- Owner -->
                        <tr>
                            <th class="attr-label">Lender / Seller</th>
                            <?php foreach ($books as $book): ?>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($book['firstname'] . ' ' . $book['lastname']); ?></div>
                                        <?php if ($book['trust_score'] > 80): ?>
                                            <i class='bx bxs-check-shield' style='color: var(--primary);' title='High Trust Score'></i>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">
                                        Trust Score: <?php echo $book['trust_score']; ?>
                                    </div>
                                </td>
                            <?php endforeach; ?>
                        </tr>

                        <!-- Location -->
                        <tr>
                            <th class="attr-label">Location</th>
                            <?php foreach ($books as $book): ?>
                                <td>
                                    <i class='bx bx-map' style="color: var(--text-muted);"></i>
                                    <?php echo htmlspecialchars($book['location'] ?: 'Not specified'); ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>

                        <!-- Description -->
                         <tr>
                            <th class="attr-label">Description</th>
                            <?php foreach ($books as $book): ?>
                                <td style="font-size: 0.9rem; line-height: 1.5; color: var(--text-secondary);">
                                    <?php echo htmlspecialchars(substr($book['description'], 0, 150)) . (strlen($book['description']) > 150 ? '...' : ''); ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>

                        <!-- Actions -->
                        <tr>
                            <th></th>
                            <?php foreach ($books as $book): ?>
                                <td>
                                    <a href="book_details.php?id=<?php echo $book['id']; ?>" class="btn btn-primary btn-action">View Details</a>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 4rem;">
                    <h3>No books found.</h3>
                    <a href="wishlist.php" class="btn btn-primary">Go back to Wishlist</a>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
