<?php
require_once '../includes/db_helper.php';
require_once '../paths.php';
include '../includes/dashboard_header.php';

// Ensure only admin can access
if ($user['role'] !== 'admin') {
    header("Location: dashboard_user.php");
    exit();
}

// Get all users with filters
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

try {
    $pdo = getDBConnection();
    
    $query = "SELECT id, firstname, lastname, email, role, credits, trust_score, total_lends, total_borrows, late_returns, is_banned, created_at FROM users WHERE 1=1";
    $params = [];
    
    if ($filter === 'low_trust') {
        $query .= " AND trust_score < 30 AND role != 'admin'";
    } elseif ($filter === 'libraries') {
        $query .= " AND role = 'library'";
    } elseif ($filter === 'bookstores') {
        $query .= " AND role = 'bookstore'";
    } elseif ($filter === 'users') {
        $query .= " AND role = 'user'";
    } elseif ($filter === 'agents') {
        $query .= " AND role = 'delivery_agent'";
    }
    
    if ($search) {
        $query .= " AND (firstname LIKE ? OR lastname LIKE ? OR email LIKE ?)";
        $searchTerm = "%$search%";
        $params = [$searchTerm, $searchTerm, $searchTerm];
    }
    
    $query .= " ORDER BY created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
} catch (Exception $e) {
    $users = [];
}
?>
<div class="dashboard-wrapper">
        <?php include '../includes/dashboard_sidebar.php'; ?>

        <main class="main-content">
            <div class="section-header" style="margin-bottom: 2rem;">
                <div>
                    <h1><i class='bx bx-user-circle'></i> User Management</h1>
                    <p>Manage and monitor all platform users</p>
                </div>
                <a href="dashboard_admin.php" class="btn btn-outline">
                    <i class='bx bx-arrow-back'></i> Back to Dashboard
                </a>
            </div>

            <!-- Filters and Search -->
            <div style="background: var(--bg-card); padding: 1.5rem; border-radius: var(--radius-lg); margin-bottom: 2rem; border: 1px solid var(--border-color);">
                <div style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center; justify-content: space-between;">
                    <div style="display: flex; gap: 0.75rem;">
                        <a href="?filter=all" class="btn btn-<?php echo $filter === 'all' ? 'primary' : 'outline'; ?> btn-sm">All Users</a>
                        <a href="?filter=users" class="btn btn-<?php echo $filter === 'users' ? 'primary' : 'outline'; ?> btn-sm">Regular</a>
                        <a href="?filter=libraries" class="btn btn-<?php echo $filter === 'libraries' ? 'primary' : 'outline'; ?> btn-sm">Libraries</a>
                        <a href="?filter=bookstores" class="btn btn-<?php echo $filter === 'bookstores' ? 'primary' : 'outline'; ?> btn-sm">Bookstores</a>
                        <a href="?filter=agents" class="btn btn-<?php echo $filter === 'agents' ? 'primary' : 'outline'; ?> btn-sm">Agents</a>
                        <a href="?filter=low_trust" class="btn btn-<?php echo $filter === 'low_trust' ? 'primary' : 'outline'; ?> btn-sm">Low Trust</a>
                    </div>
                    <form method="GET" style="display: flex; gap: 0.5rem;">
                        <input type="hidden" name="filter" value="<?php echo $filter; ?>">
                        <input type="text" name="search" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>" class="form-input" style="min-width: 300px;">
                        <button type="submit" class="btn btn-primary"><i class='bx bx-search'></i></button>
                    </form>
                </div>
            </div>

            <!-- Users Table -->
            <div style="background: var(--bg-card); border-radius: var(--radius-lg); border: 1px solid var(--border-color); overflow: hidden;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead style="background: var(--bg-body); border-bottom: 1px solid var(--border-color);">
                        <tr>
                            <th style="padding: 1rem; text-align: left; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">User</th>
                            <th style="padding: 1rem; text-align: left; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Role</th>
                            <th style="padding: 1rem; text-align: center; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Credits</th>
                            <th style="padding: 1rem; text-align: center; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Trust</th>
                            <th style="padding: 1rem; text-align: center; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Activity</th>
                            <th style="padding: 1rem; text-align: right; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): 
                            $trustRating = getTrustScoreRating($u['trust_score']);
                        ?>
                        <tr style="border-bottom: 1px solid var(--border-color); transition: background 0.2s;" onmouseover="this.style.background='var(--bg-body)'" onmouseout="this.style.background='transparent'">
                            <td style="padding: 1.25rem;">
                                <div style="font-weight: 700; color: var(--text-main);"><?php echo htmlspecialchars($u['firstname'] . ' ' . $u['lastname']); ?></div>
                                <div style="font-size: 0.85rem; color: var(--text-muted);"><?php echo htmlspecialchars($u['email']); ?></div>
                            </td>
                            <td style="padding: 1.25rem;">
                                <span style="padding: 0.3rem 0.8rem; background: var(--bg-body); color: var(--text-main); border-radius: 12px; font-size: 0.8rem; font-weight: 600; text-transform: capitalize;">
                                    <?php echo $u['role']; ?>
                                </span>
                            </td>
                            <td style="padding: 1.25rem; text-align: center; font-weight: 600;">
                                <?php echo $u['credits']; ?>
                            </td>
                            <td style="padding: 1.25rem; text-align: center;">
                                <span style="padding: 0.3rem 0.8rem; background: <?php echo $trustRating['color']; ?>15; color: <?php echo $trustRating['color']; ?>; border-radius: 12px; font-size: 0.8rem; font-weight: 600;">
                                    <?php echo $u['trust_score']; ?>
                                </span>
                            </td>
                            <td style="padding: 1.25rem; text-align: center; font-size: 0.85rem; color: var(--text-muted);">
                                <?php echo $u['total_lends']; ?> lends / <?php echo $u['total_borrows']; ?> borrows
                                <?php if ($u['late_returns'] > 0): ?>
                                    <br><span style="color: #ef4444; font-weight: 600;"><?php echo $u['late_returns']; ?> late</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 1.25rem; text-align: right;">
                                    <button onclick="adjustTokens(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['firstname'] . ' ' . $u['lastname']); ?>')" class="btn btn-sm" style="background: var(--primary); color: white; border: none; margin-right: 0.5rem;">
                                        <i class='bx bx-coin-stack'></i> Credits
                                    </button>
                                    <?php if ($u['is_banned']): ?>
                                        <button onclick="toggleBan(<?php echo $u['id']; ?>, 'unban')" class="btn btn-sm" style="background: #10b981; color: white; border: none;">Unban</button>
                                    <?php else: ?>
                                        <button onclick="toggleBan(<?php echo $u['id']; ?>, 'ban')" class="btn btn-sm" style="background: #ef4444; color: white; border: none;">Ban</button>
                                    <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if (empty($users)): ?>
                <div style="padding: 3rem; text-align: center; color: var(--text-muted);">
                    <i class='bx bx-user-x' style="font-size: 3rem; opacity: 0.3;"></i>
                    <p style="margin-top: 1rem;">No users found</p>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
    async function toggleBan(userId, action) {
        let reason = null;
        if (action === 'ban') {
            reason = await Popup.prompt('Ban User', 'Enter the reason why you are banning this user:', 'Reason for banning');
            if (reason === null) return; // Cancelled
            if (reason.trim() === '') {
                showToast('A reason is required to ban a user.', 'error');
                return;
            }
        } else {
            const confirmed = await Popup.confirm('Unban User', `Are you sure you want to ${action} this user?`);
            if (!confirmed) return;
        }
        
        try {
            const formData = new FormData();
            formData.append('action', action + '_user');
            formData.append('user_id', userId);
            if (reason) formData.append('reason', reason);
            
            const response = await fetch('../actions/request_action.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                showToast(result.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(result.message, 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showToast('An error occurred', 'error');
        }
    }

    async function adjustTokens(userId, userName) {
        const amount = await Popup.prompt(`Adjust Credits`, `Adjust credits for ${userName}:\nEnter positive number to add, negative to take away (e.g. 50 or -20)`, 'e.g. 50');
        if (amount === null || amount === "" || isNaN(amount) || parseInt(amount) === 0) return;

        const reason = await Popup.prompt("Adjustment Reason", "Enter reason for adjustment:", "Admin adjustment");
        if (reason === null) return;

        try {
            const formData = new FormData();
            formData.append('action', 'adjust_tokens');
            formData.append('user_id', userId);
            formData.append('amount', amount);
            formData.append('reason', reason);

            const response = await fetch('../actions/request_action.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            if (result.success) {
                showToast(result.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(result.message, 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showToast('An error occurred', 'error');
        }
    }
    </script>
