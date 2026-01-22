<?php
require_once '../includes/db_helper.php';
require_once '../paths.php';
session_start();

$userId = $_SESSION['user_id'] ?? 0;
$user = getUserById($userId);

// Ensure only admin can access
if (!$user || $user['role'] !== 'admin') {
    header("Location: ../pages/dashboard_user.php");
    exit();
}

$success = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = $_POST['request_id'];
    $action = $_POST['action'];
    $adminMessage = trim($_POST['admin_message'] ?? '');
    
    $status = ($action === 'approve') ? 'approved' : 'rejected';
    
    if (updateRoleRequestStatus($requestId, $status, $userId, $adminMessage)) {
        $success = "Request " . ($status === 'approved' ? 'approved' : 'rejected') . " successfully!";
    } else {
        $error = "Failed to update request status.";
    }
}

$pendingRequests = getRoleRequests('pending');
$pastRequests = array_merge(getRoleRequests('approved'), getRoleRequests('rejected'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Role Change Requests | Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=1.1">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        .request-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .user-info h3 { margin: 0; font-size: 1.1rem; }
        .user-info p { margin: 4px 0; color: #64748b; font-size: 0.9rem; }
        .role-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            background: #f1f5f9;
        }
        .requested-role { background: #e0e7ff; color: #4338ca; }
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
        }
        .status-approved { background: #dcfce7; color: #15803d; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
        
        .action-btns { display: flex; gap: 0.5rem; }
        .btn-approve { background: #10b981; color: white; border: none; padding: 8px 16px; border-radius: 8px; cursor: pointer; }
        .btn-reject { background: #ef4444; color: white; border: none; padding: 8px 16px; border-radius: 8px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include '../includes/dashboard_sidebar.php'; ?>
        
        <main class="main-content">
            <div style="max-width: 1000px; margin: 0 auto;">
                <h1>Role Change Requests</h1>
                <p>Manage user requests to change their account type</p>

                <?php if ($success): ?>
                    <div class="alert alert-success" style="padding: 1rem; background: #dcfce7; color: #15803d; border-radius: 8px; margin-bottom: 1rem;">
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <div style="margin-top: 2rem;">
                    <h2>Pending Requests (<?php echo count($pendingRequests); ?>)</h2>
                    <?php if (empty($pendingRequests)): ?>
                        <p style="color: #64748b;">No pending requests at the moment.</p>
                    <?php else: ?>
                        <?php foreach ($pendingRequests as $req): ?>
                            <div class="request-card">
                                <div class="user-info">
                                    <h3><?php echo htmlspecialchars($req['firstname'] . ' ' . $req['lastname']); ?></h3>
                                    <p><?php echo htmlspecialchars($req['email']); ?></p>
                                    <div style="margin-top: 8px;">
                                        <span class="role-badge"><?php echo ucfirst($req['current_role']); ?></span>
                                        <i class='bx bx-right-arrow-alt'></i>
                                        <span class="role-badge requested-role"><?php echo ucfirst($req['requested_role']); ?></span>
                                    </div>
                                </div>
                                <form method="POST" class="action-btns">
                                    <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                    <input type="text" name="admin_message" placeholder="Optional message..." class="form-input" style="width: 200px;">
                                    <button type="submit" name="action" value="approve" class="btn-approve">Approve</button>
                                    <button type="submit" name="action" value="reject" class="btn-reject">Reject</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div style="margin-top: 4rem;">
                    <h2>Recent History</h2>
                    <?php if (empty($pastRequests)): ?>
                        <p style="color: #64748b;">No past requests found.</p>
                    <?php else: ?>
                        <div style="background: white; border-radius: 16px; border: 1px solid #e2e8f0; overflow: hidden;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead style="background: #f8fafc;">
                                    <tr>
                                        <th style="padding: 1rem; text-align: left;">User</th>
                                        <th style="padding: 1rem; text-align: left;">Change</th>
                                        <th style="padding: 1rem; text-align: left;">Status</th>
                                        <th style="padding: 1rem; text-align: left;">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pastRequests as $req): ?>
                                        <tr style="border-top: 1px solid #e2e8f0;">
                                            <td style="padding: 1rem;">
                                                <strong><?php echo htmlspecialchars($req['firstname']); ?></strong><br>
                                                <span style="font-size: 0.8rem; color: #64748b;"><?php echo htmlspecialchars($req['email']); ?></span>
                                            </td>
                                            <td style="padding: 1rem;">
                                                <?php echo ucfirst($req['current_role']); ?> &rarr; <?php echo ucfirst($req['requested_role']); ?>
                                            </td>
                                            <td style="padding: 1rem;">
                                                <span class="status-badge status-<?php echo $req['status']; ?>">
                                                    <?php echo ucfirst($req['status']); ?>
                                                </span>
                                            </td>
                                            <td style="padding: 1rem; color: #64748b; font-size: 0.85rem;">
                                                <?php echo date('M d, Y', strtotime($req['updated_at'])); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
