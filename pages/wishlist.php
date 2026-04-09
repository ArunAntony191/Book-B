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
    <link rel="stylesheet" href="../assets/css/style.css?v=1.2">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1.5rem;
        }
        .book-card {
            background: var(--bg-card);
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            overflow: hidden;
            transition: all 0.2s;
            cursor: pointer;
            position: relative;
        }
        .book-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); border-color: var(--primary); }
        .book-cover {
            width: 100%;
            aspect-ratio: 2/3;
            object-fit: cover;
        }
        .book-info { padding: 1rem; }
        
        .compare-btn-toggle {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 10;
            background: rgba(var(--bg-card-rgb), 0.9);
            border: 1px solid var(--border-color);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 4px;
            color: var(--text-muted);
        }
        .compare-btn-toggle:hover {
            background: var(--bg-card);
            border-color: var(--primary);
            color: var(--primary);
        }
        .compare-btn-toggle.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        .book-card.selected {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px var(--primary-light);
        }
    </style>
</head>
<body>
    <?php include '../includes/dashboard_header.php'; ?>
    <div class="dashboard-wrapper">
        <?php include '../includes/dashboard_sidebar.php'; ?>
        
        <main class="main-content">
            <h1 style="font-size: 1.8rem; font-weight: 800; margin-bottom: 2rem; color: var(--text-main);">My Wishlist</h1>
            
            <?php if (count($wishlist) > 0): ?>
                <div class="books-grid">
                    <?php foreach ($wishlist as $item): ?>
                        <div class="book-card" onclick="window.location.href='book_details.php?id=<?php echo $item['id']; ?>'">
                            <button class="compare-btn-toggle" data-id="<?php echo $item['id']; ?>" onclick="toggleCompare(this, event)">
                                <i class='bx bx-plus'></i> Compare
                            </button>
                            <?php 
                                $cover = $item['cover_image'];
                                $fallback = 'https://images.unsplash.com/photo-1543004218-ee141104975a?auto=format&fit=crop&q=80&w=400';
                                $cover = $cover ?: $fallback;
                            ?>
                            <img src="<?php echo htmlspecialchars(html_entity_decode($cover), ENT_QUOTES, 'UTF-8'); ?>" 
                                 class="book-cover" 
                                 onerror="this.onerror=null; this.src='<?php echo $fallback; ?>';">
                            <div class="book-info">
                                <div style="font-weight: 700; margin-bottom: 0.25rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($item['title']); ?></div>
                                <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.5rem;"><?php echo htmlspecialchars($item['author']); ?></div>
                                <span class="badge badge-<?php echo $item['listing_type']; ?>"><?php echo ucfirst($item['listing_type']); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 4rem; background: var(--bg-card); border-radius: var(--radius-lg); border: 1px solid var(--border-color);">
                    <i class='bx bx-heart' style="font-size: 3rem; color: #cbd5e1; margin-bottom: 1rem;"></i>
                    <h3 style="color: var(--text-muted);">Your wishlist is empty.</h3>
                    <a href="explore.php" class="btn btn-primary" style="margin-top: 1rem;">Explore Books</a>
                </div>
            <?php endif; ?>
        </main>
        
        <!-- Comparison Action Bar -->
        <div id="compare-bar" style="position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: var(--text-main); color: white; padding: 1rem 2rem; border-radius: 50px; display: none; align-items: center; gap: 1.5rem; box-shadow: 0 10px 25px rgba(0,0,0,0.2); z-index: 1000;">
            <div style="font-weight: 600;">
                <span id="selected-count">0</span>/3 Selected
            </div>
            <button onclick="goToCompare()" class="btn btn-primary" style="padding: 0.5rem 1.5rem; border-radius: 25px;">Ready to Compare</button>
            <button onclick="clearSelection()" style="background: none; border: none; color: var(--text-muted); cursor: pointer;"><i class='bx bx-x' style="font-size: 1.5rem;"></i></button>
        </div>

        <script>
            let selectedIds = [];
            const compareBar = document.getElementById('compare-bar');
            const selectedCountSpan = document.getElementById('selected-count');
            
            function toggleCompare(btn, event) {
                event.stopPropagation();
                const id = btn.getAttribute('data-id');
                const card = btn.closest('.book-card');
                
                if (selectedIds.includes(id)) {
                    // Deselect
                    selectedIds = selectedIds.filter(i => i !== id);
                    btn.classList.remove('active');
                    btn.innerHTML = "<i class='bx bx-plus'></i> Compare";
                    card.classList.remove('selected');
                } else {
                    // Select
                    if (selectedIds.length >= 3) {
                        showToast('You can only compare up to 3 books at a time.', 'warning');
                        return;
                    }
                    selectedIds.push(id);
                    btn.classList.add('active');
                    btn.innerHTML = "<i class='bx bx-check'></i> Selected";
                    card.classList.add('selected');
                }
                
                updateSelectionUI();
            }

            function updateSelectionUI() {
                if (selectedIds.length > 0) {
                    compareBar.style.display = 'flex';
                } else {
                    compareBar.style.display = 'none';
                }
                selectedCountSpan.textContent = selectedIds.length;
            }

            function clearSelection() {
                selectedIds = [];
                document.querySelectorAll('.compare-btn-toggle').forEach(btn => {
                    btn.classList.remove('active');
                    btn.innerHTML = "<i class='bx bx-plus'></i> Compare";
                });
                document.querySelectorAll('.book-card').forEach(card => card.classList.remove('selected'));
                updateSelectionUI();
            }

            function goToCompare() {
                if (selectedIds.length < 2) {
                    showToast('Please select at least 2 books to compare.', 'info');
                    return;
                }
                window.location.href = `compare_books.php?ids=${selectedIds.join(',')}`;
            }
        </script>
    </div>
</body>
</html>
