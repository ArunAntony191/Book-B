<?php
require_once '../includes/db_helper.php';
require_once '../paths.php';
include '../includes/dashboard_header.php';

// Ensure only admin can access
if ($user['role'] !== 'admin') {
    header("Location: dashboard_user.php");
    exit();
}

// Get all listings with filters
$search = $_GET['search'] ?? '';

try {
    $pdo = getDBConnection();
    
    $query = "SELECT l.*, b.title, b.author, b.cover_image, u.firstname, u.lastname, u.email 
              FROM listings l
              JOIN books b ON l.book_id = b.id
              JOIN users u ON l.user_id = u.id
              WHERE 1=1";
    $params = [];
    
    if ($search) {
        $query .= " AND (b.title LIKE ? OR b.author LIKE ? OR u.firstname LIKE ? OR u.lastname LIKE ? OR u.email LIKE ?)";
        $searchTerm = "%$search%";
        $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm];
    }
    
    $query .= " ORDER BY l.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $listings = $stmt->fetchAll();
    
} catch (Exception $e) {
    $listings = [];
}
?>
<div class="dashboard-wrapper">
        <?php include '../includes/dashboard_sidebar.php'; ?>

        <main class="main-content">
            <div class="section-header" style="margin-bottom: 2rem;">
                <div>
                    <h1><i class='bx bx-book'></i> Listing Management</h1>
                    <p>Monitor and delete inappropriate or problematic book listings</p>
                </div>
                <a href="dashboard_admin.php" class="btn btn-outline">
                    <i class='bx bx-arrow-back'></i> Back to Dashboard
                </a>
            </div>

            <!-- Search -->
            <div style="background: var(--bg-card); padding: 1.5rem; border-radius: var(--radius-lg); margin-bottom: 2rem; border: 1px solid var(--border-color);">
                <form method="GET" style="display: flex; gap: 0.5rem; max-width: 600px;">
                    <input type="text" name="search" placeholder="Search by title, author, or owner..." value="<?php echo htmlspecialchars($search); ?>" class="form-input">
                    <button type="submit" class="btn btn-primary"><i class='bx bx-search'></i> Search</button>
                    <?php if($search): ?>
                        <a href="admin_listings.php" class="btn btn-outline">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Listings Table -->
            <div style="background: var(--bg-card); border-radius: var(--radius-lg); border: 1px solid var(--border-color); overflow: hidden;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead style="background: var(--bg-body); border-bottom: 1px solid var(--border-color);">
                        <tr>
                            <th style="padding: 1rem; text-align: left; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Book</th>
                            <th style="padding: 1rem; text-align: left; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Owner</th>
                            <th style="padding: 1rem; text-align: center; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Type</th>
                            <th style="padding: 1rem; text-align: center; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Price/Cost</th>
                            <th style="padding: 1rem; text-align: right; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($listings as $l): ?>
                        <tr style="border-bottom: 1px solid var(--border-color); transition: background 0.2s;" onmouseover="this.style.background='var(--bg-body)'" onmouseout="this.style.background='transparent'">
                            <td style="padding: 1.25rem;">
                                <div style="display: flex; align-items: center; gap: 1rem;">
                                    <img src="<?php echo $l['cover_image'] ?: 'https://images.unsplash.com/photo-1543004218-ee141104975a?auto=format&fit=crop&q=80&w=100'; ?>" 
                                         style="width: 40px; height: 60px; object-fit: cover; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                    <div>
                                        <div style="font-weight: 700; color: var(--text-main);"><?php echo htmlspecialchars($l['title']); ?></div>
                                        <div style="font-size: 0.85rem; color: var(--text-muted);">by <?php echo htmlspecialchars($l['author']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td style="padding: 1.25rem;">
                                <div style="font-weight: 600; color: var(--text-main);"><?php echo htmlspecialchars($l['firstname'] . ' ' . $l['lastname']); ?></div>
                                <div style="font-size: 0.8rem; color: var(--text-muted);"><?php echo htmlspecialchars($l['email']); ?></div>
                            </td>
                            <td style="padding: 1.25rem; text-align: center;">
                                <span class="badge badge-<?php echo $l['listing_type']; ?>" style="font-size: 0.75rem; text-transform: uppercase;">
                                    <?php echo $l['listing_type']; ?>
                                </span>
                            </td>
                            <td style="padding: 1.25rem; text-align: center; font-weight: 600;">
                                <?php echo $l['price'] > 0 ? "₹".$l['price'] : ($l['listing_type'] == 'borrow' ? $l['credit_cost']." Tokens" : "Free"); ?>
                            </td>
                            <td style="padding: 1.25rem; text-align: right;">
                                <a href="../pages/book_details.php?id=<?php echo $l['id']; ?>" class="btn btn-outline btn-sm" style="margin-right: 0.5rem;"><i class='bx bx-show'></i> View</a>
                                <button onclick="deleteListing(<?php echo $l['id']; ?>, '<?php echo addslashes($l['title']); ?>')" class="btn btn-sm" style="background: #ef4444; color: white; border: none;">
                                    <i class='bx bx-trash'></i> Delete
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if (empty($listings)): ?>
                <div style="padding: 3rem; text-align: center; color: var(--text-muted);">
                    <i class='bx bx-book-x' style="font-size: 3rem; opacity: 0.3;"></i>
                    <p style="margin-top: 1rem;">No listings found</p>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
    async function deleteListing(listingId, title) {
        if (!confirm('Are you sure you want to PERMANENTLY delete "' + title + '"? This action cannot be undone.')) return;
        
        try {
            const formData = new FormData();
            formData.append('listing_id', listingId);
            
            const response = await fetch('../actions/delete_listing.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                alert(result.message);
                location.reload();
            } else {
                alert(result.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred');
        }
    }
    </script>
