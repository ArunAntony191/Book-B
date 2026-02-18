<?php
require_once '../includes/db_helper.php';
require_once '../paths.php';
session_start();

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
    header("Location: login.php");
    exit();
}

$user = getUserById($userId);
$pendingRoleRequest = getPendingRoleRequest($userId);
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_password'])) {
        $oldPass = $_POST['old_password'];
        $newPass = $_POST['new_password'];
        $confirmPass = $_POST['confirm_password'];
        
        // Verify old password (simplification: assuming we have password in $user)
        // Note: getUserById doesn't return password by default for security, 
        // but for this flow we need it. Let's fetch it specifically.
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $storedPass = $stmt->fetchColumn();
        
        if (password_verify($oldPass, $storedPass)) {
            if ($newPass === $confirmPass) {
                if (updateUserPassword($userId, $newPass)) {
                    $success = "Password updated successfully!";
                } else {
                    $error = "Failed to update password.";
                }
            } else {
                $error = "New passwords do not match.";
            }
        } else {
            $error = "Current password is incorrect.";
        }
    } 
    elseif (isset($_POST['update_preferences'])) {
        $settings = [
            'email_notifications' => isset($_POST['email_notifications']) ? 1 : 0,
            'notify_new_listings' => isset($_POST['notify_new_listings']) ? 1 : 0,
            'theme_mode' => $_POST['theme_mode'] ?? 'light'
        ];
        
        $prefsUpdated = updateUserSettings($userId, $settings);
        
        // Handle Role Change Request
        if (isset($_POST['role']) && $_POST['role'] !== $user['role'] && $user['role'] !== 'admin') {
            if ($pendingRoleRequest) {
                $error = "You already have a pending role change request.";
            } else {
                if (createRoleChangeRequest($userId, $user['role'], $_POST['role'])) {
                    $success = "Role change request submitted to admin!";
                    $pendingRoleRequest = getPendingRoleRequest($userId); // Refresh
                } else {
                    $error = "Failed to submit role change request.";
                }
            }
        }

        if ($prefsUpdated && !$error) {
            $success = $success ?: "Preferences updated!";
            $user = getUserById($userId);
            $_SESSION['theme_mode'] = $user['theme_mode'];
        } elseif (!$prefsUpdated && !$error) {
            $error = "Failed to update preferences.";
        }
    }
    elseif (isset($_POST['contact_admin'])) {
        $message = trim($_POST['admin_message']);
        if (!empty($message)) {
            // Find admin ID (defaulting to 1 as per current logic, or searching for admin role)
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
            $stmt->execute();
            $adminId = $stmt->fetchColumn() ?: 1;
            
            // Add notification for admin instead of chat message to prevent spam
            $userName = $user['firstname'] . ' ' . $user['lastname'];
            $msgSnippet = mb_strimwidth($message, 0, 100, "...");
            if (createNotification($adminId, 'support', "Support request from $userName: \"$msgSnippet\"", $userId)) {
                $success = "Your message has been sent to the admin team.";
            } else {
                $error = "Failed to send message.";
            }
        }
    }
    elseif (isset($_POST['delete_account'])) {
        if ($_POST['confirm_delete'] === 'DELETE') {
            if (deleteUserAccount($userId)) {
                session_destroy();
                header("Location: login.php?deleted=1");
                exit();
            } else {
                $error = "Failed to delete account.";
            }
        } else {
            $error = "Please type 'DELETE' to confirm account closure.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $user['theme_mode'] ?? 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | BOOK-B</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=1.2">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        .settings-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }

        .settings-card {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 2.5rem;
            box-shadow: var(--shadow-md);
            margin-bottom: 2rem;
        }

        .settings-section {
            margin-bottom: 3rem;
        }

        .settings-section:last-child {
            margin-bottom: 0;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border-color);
        }

        .section-header i {
            font-size: 1.5rem;
            color: var(--primary);
        }

        .section-header h2 {
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0;
        }

        .setting-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color, #eef2f6);
        }

        .setting-row:last-child {
            border-bottom: none;
        }

        .setting-info h3 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .setting-info p {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .btn-danger {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .btn-danger:hover {
            background: #ef4444;
            color: white;
        }

        /* Toggle Switch */
        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 26px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: var(--primary);
        }

        input:checked + .slider:before {
            transform: translateX(24px);
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-muted);
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            border: 1.5px solid var(--border-color);
            background: transparent;
            color: var(--text-main);
        }
    </style>
</head>
<body>
    <?php include '../includes/dashboard_header.php'; ?>
    <div class="dashboard-wrapper">
        <?php include '../includes/dashboard_sidebar.php'; ?>
        
        <main class="main-content">
            <div class="settings-container">
                <div class="page-header" style="margin-bottom: 2rem;">
                    <h1>Settings</h1>
                    <p>Customize your experience and manage account security</p>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class='bx bxs-check-circle'></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class='bx bxs-error-circle'></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <div class="settings-card">
                    <!-- Display & Appearance -->
                    <div class="settings-section">
                        <div class="section-header">
                            <i class='bx bx-palette'></i>
                            <h2>Appearance</h2>
                        </div>
                        <form method="POST">
                            <div class="setting-row">
                                <div class="setting-info">
                                    <h3>Dark Mode</h3>
                                    <p>Switch between light and dark themes</p>
                                </div>
                                <div style="display: flex; align-items: center; gap: 1rem;">
                                    <select name="theme_mode" class="form-input" style="width: auto;" onchange="this.form.submit()">
                                        <option value="light" <?php echo ($user['theme_mode'] ?? 'light') === 'light' ? 'selected' : ''; ?>>Light</option>
                                        <option value="dark" <?php echo ($user['theme_mode'] ?? 'light') === 'dark' ? 'selected' : ''; ?>>Dark</option>
                                    </select>
                                    <input type="hidden" name="update_preferences" value="1">
                                </div>
                            </div>
                            <div class="setting-row">
                                <div class="setting-info">
                                    <h3>Email Notifications</h3>
                                    <p>Receive updates about your requests and deliveries</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="email_notifications" <?php echo ($user['email_notifications'] ?? 1) ? 'checked' : ''; ?> onchange="this.form.submit()">
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <div class="setting-row">
                                <div class="setting-info">
                                    <h3>New Listing Alerts</h3>
                                    <p>Get notified when new books are listed on the platform</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" name="notify_new_listings" <?php echo ($user['notify_new_listings'] ?? 0) ? 'checked' : ''; ?> onchange="this.form.submit()">
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <div class="setting-row">
                                <div class="setting-info">
                                    <h3>Account Role</h3>
                                    <p>
                                        <?php if ($pendingRoleRequest): ?>
                                            <span style="color: var(--primary); font-weight: 600;">
                                                <i class='bx bx-time-five'></i> Request Pending: 
                                                Change from <?php echo ucfirst($pendingRoleRequest['current_role']); ?> 
                                                to <?php echo ucfirst($pendingRoleRequest['requested_role']); ?>
                                            </span>
                                        <?php else: ?>
                                            Change your account type (requires Admin approval)
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div style="display: flex; align-items: center; gap: 1rem;">
                                    <?php if ($user['role'] === 'admin'): ?>
                                        <span class="status-pill admin"><i class='bx bxs-shield'></i> Administrator</span>
                                    <?php else: ?>
                                        <select name="role" class="form-input" style="width: auto;" <?php echo ($pendingRoleRequest || $user['role'] === 'admin') ? 'disabled' : ''; ?>>
                                            <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>Individual User</option>
                                            <option value="library" <?php echo $user['role'] === 'library' ? 'selected' : ''; ?>>Library</option>
                                            <option value="bookstore" <?php echo $user['role'] === 'bookstore' ? 'selected' : ''; ?>>Bookstore</option>
                                            <option value="delivery_agent" <?php echo $user['role'] === 'delivery_agent' ? 'selected' : ''; ?>>Delivery Agent</option>
                                        </select>
                                        <?php if (!$pendingRoleRequest): ?>
                                            <button type="submit" name="update_preferences" class="btn btn-primary btn-sm">Send Request</button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Security -->
                    <div class="settings-section">
                        <div class="section-header">
                            <i class='bx bx-shield-lock'></i>
                            <h2>Security</h2>
                        </div>
                        <form method="POST">
                            <div class="grid-2">
                                <div class="form-group">
                                    <label class="form-label">Current Password</label>
                                    <input type="password" name="old_password" class="form-input" required>
                                </div>
                            </div>
                            <div class="grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div class="form-group">
                                    <label class="form-label">New Password</label>
                                    <input type="password" name="new_password" class="form-input" required minlength="6">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Confirm New Password</label>
                                    <input type="password" name="confirm_password" class="form-input" required minlength="6">
                                </div>
                            </div>
                            <button type="submit" name="update_password" class="btn btn-primary">Update Password</button>
                        </form>
                    </div>

                    <!-- Support -->
                    <?php if ($user['role'] !== 'admin'): ?>
                    <div class="settings-section">
                        <div class="section-header">
                            <i class='bx bx-support'></i>
                            <h2>Support & Feedback</h2>
                        </div>
                        <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1rem;">Have an issue or suggestion? Send a message directly to the admin team.</p>
                        <form method="POST">
                            <div class="form-group">
                                <textarea name="admin_message" class="form-input" rows="3" placeholder="How can we help you?" required></textarea>
                            </div>
                            <button type="submit" name="contact_admin" class="btn btn-outline">Send Message to Admin</button>
                        </form>
                    </div>
                    <?php endif; ?>

                    <!-- Danger Zone -->
                    <div class="settings-section" style="margin-top: 4rem; padding-top: 2rem; border-top: 2px dashed #fee2e2;">
                        <div class="section-header" style="border-bottom-color: #fee2e2;">
                            <i class='bx bx-error' style="color: #ef4444;"></i>
                            <h2 style="color: #ef4444;">Danger Zone</h2>
                        </div>
                        <div class="setting-row" style="border-bottom: none;">
                            <div class="setting-info">
                                <h3 style="color: #ef4444;">Delete Account</h3>
                                <p>Permanently remove your account and all associated data. This cannot be undone.</p>
                            </div>
                            <button type="button" class="btn btn-danger" onclick="document.getElementById('delete-confirm').style.display='block'">Delete Account</button>
                        </div>
                        
                        <div id="delete-confirm" style="display: none; margin-top: 1.5rem; background: #fff1f2; padding: 1.5rem; border-radius: 16px; border: 1px solid #fecaca;">
                            <p style="color: #991b1b; font-weight: 600; font-size: 0.9rem; margin-bottom: 1rem;">
                                Are you absolutely sure? This will delete all your listings, history, and ratings.
                                <br>Please type <strong>DELETE</strong> below to confirm.
                            </p>
                            <form method="POST">
                                <input type="text" name="confirm_delete" class="form-input" placeholder="Type DELETE" required style="border-color: #fecaca; margin-bottom: 1rem;">
                                <div style="display: flex; gap: 1rem;">
                                    <button type="submit" name="delete_account" class="btn btn-danger">Yes, Delete Everything</button>
                                    <button type="button" class="btn btn-outline" onclick="document.getElementById('delete-confirm').style.display='none'">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Optional: Theme live preview
        document.querySelector('select[name="theme_mode"]').addEventListener('change', function(e) {
            document.documentElement.setAttribute('data-theme', e.target.value);
        });
    </script>
</body>
</html>
