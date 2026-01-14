<?php
require_once 'includes/db_helper.php';
require_once 'paths.php';
session_start();

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
    header("Location: login.php");
    exit();
}

$user = getUserById($userId);
$success = '';
$error = '';

// Handle Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_settings'])) {
        $emailNotifs = isset($_POST['email_notifications']) ? 1 : 0;
        $privacyMode = $_POST['privacy_mode'] ?? 'public';
        
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("UPDATE users SET email_notifications = ?, privacy_mode = ? WHERE id = ?");
            if ($stmt->execute([$emailNotifs, $privacyMode, $userId])) {
                $success = "Settings updated successfully!";
                $user = getUserById($userId); // Refresh
            }
        } catch (Exception $e) {
            $error = "Failed to update settings.";
        }
    }

    if (isset($_POST['change_password'])) {
        $oldPass = $_POST['old_password'];
        $newPass = $_POST['new_password'];
        $confirmPass = $_POST['confirm_password'];

        if ($newPass !== $confirmPass) {
            $error = "New passwords do not match.";
        } else {
            try {
                $pdo = getDBConnection();
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $hash = $stmt->fetchColumn();

                if (password_verify($oldPass, $hash)) {
                    $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$newHash, $userId]);
                    $success = "Password changed successfully!";
                } else {
                    $error = "Current password is incorrect.";
                }
            } catch (Exception $e) {
                $error = "Failed to change password.";
            }
        }
    }

    if (isset($_POST['contact_admin'])) {
        $message = trim($_POST['admin_message']);
        if (empty($message)) {
            $error = "Please enter a message.";
        } else {
            try {
                $pdo = getDBConnection();
                // Get all admins
                $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin'");
                $stmt->execute();
                $admins = $stmt->fetchAll(PDO::FETCH_COLUMN);

                $displayName = htmlspecialchars($user['firstname'] . ' ' . $user['lastname']);
                $notifMsg = "Support Request from {$displayName}: " . (strlen($message) > 100 ? substr($message, 0, 97) . '...' : $message);

                foreach ($admins as $adminId) {
                    $pdo->prepare("INSERT INTO notifications (user_id, type, message, reference_id) VALUES (?, 'system', ?, ?)")
                        ->execute([$adminId, $notifMsg, $userId]);
                }
                $success = "Your message has been sent to the administrators.";
            } catch (Exception $e) {
                $error = "Failed to send message.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | BOOK-B</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        .settings-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }
        .settings-card {
            background: white;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .settings-section-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--text-main);
        }
        .settings-section-title i {
            color: var(--primary);
        }
        .settings-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem 0;
            border-bottom: 1px solid var(--border-color);
        }
        .settings-row:last-child {
            border-bottom: none;
        }
        .settings-info {
            flex: 1;
        }
        .settings-label {
            font-weight: 600;
            display: block;
            margin-bottom: 0.25rem;
        }
        .settings-desc {
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 26px;
        }
        .toggle-switch input {
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
            background-color: #e2e8f0;
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
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.95rem;
        }
        .btn-danger-outline {
            color: #ef4444;
            border: 1px solid #ef4444;
            background: transparent;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-danger-outline:hover {
            background: #fef2f2;
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/dashboard_sidebar.php'; ?>
        
        <main class="main-content">
            <div class="settings-container">
                <h1 style="margin-bottom: 2rem;">Settings</h1>

                <?php if ($success): ?>
                    <div class="alert alert-success" style="background: #f0fdf4; color: #16a34a; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; border: 1px solid #dcfce7;">
                        <i class='bx bxs-check-circle'></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger" style="background: #fef2f2; color: #dc2626; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; border: 1px solid #fee2e2;">
                        <i class='bx bxs-error-circle'></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <!-- General Preferences -->
                <form method="POST">
                    <div class="settings-card">
                        <h2 class="settings-section-title"><i class='bx bx-slider-alt'></i> Preferences</h2>
                        
                        <div class="settings-row">
                            <div class="settings-info">
                                <span class="settings-label">Email Notifications</span>
                                <span class="settings-desc">Receive updates about requests and deliveries via email.</span>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="email_notifications" <?php echo ($user['email_notifications'] ?? 1) ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <div class="settings-row">
                            <div class="settings-info">
                                <span class="settings-label">Privacy Mode</span>
                                <span class="settings-desc">Hide your profile details from non-community members.</span>
                            </div>
                            <select name="privacy_mode" class="form-control" style="width: auto;">
                                <option value="public" <?php echo ($user['privacy_mode'] ?? 'public') === 'public' ? 'selected' : ''; ?>>Public</option>
                                <option value="private" <?php echo ($user['privacy_mode'] ?? 'public') === 'private' ? 'selected' : ''; ?>>Private</option>
                            </select>
                        </div>

                        <div style="margin-top: 1.5rem; text-align: right;">
                            <button type="submit" name="update_settings" class="btn btn-primary">Save Preferences</button>
                        </div>
                    </div>
                </form>

                <!-- Security Settings -->
                <form method="POST">
                    <div class="settings-card">
                        <h2 class="settings-section-title"><i class='bx bx-lock-alt'></i> Security</h2>
                        
                        <div class="form-group">
                            <label>Current Password</label>
                            <input type="password" name="old_password" class="form-control" placeholder="••••••••" required>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password" name="new_password" class="form-control" placeholder="••••••••" required minlength="6">
                            </div>
                            <div class="form-group">
                                <label>Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control" placeholder="••••••••" required minlength="6">
                            </div>
                        </div>

                        <div style="margin-top: 1rem; text-align: right;">
                            <button type="submit" name="change_password" class="btn btn-primary">Update Password</button>
                        </div>
                    </div>
                </form>

                <!-- Contact Admin Section -->
                <form method="POST">
                    <div class="settings-card">
                        <h2 class="settings-section-title"><i class='bx bx-support'></i> Contact Admin</h2>
                        <p class="settings-desc" style="margin-bottom: 1rem;">Need help or have a suggestion? Send a message to our administrators.</p>
                        
                        <div class="form-group">
                            <label>Your Message</label>
                            <textarea name="admin_message" class="form-control" rows="4" placeholder="How can we help you today?" required></textarea>
                        </div>

                        <div style="margin-top: 1rem; text-align: right;">
                            <button type="submit" name="contact_admin" class="btn btn-primary">
                                <i class='bx bx-send'></i> Send Message
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Danger Zone -->
                <div class="settings-card" style="border-color: #fee2e2;">
                    <h2 class="settings-section-title" style="color: #dc2626;"><i class='bx bx-trash'></i> Danger Zone</h2>
                    <p class="settings-desc" style="margin-bottom: 1.5rem;">Once you delete your account, there is no going back. Please be certain.</p>
                    <button class="btn-danger-outline" onclick="if(confirm('Are you absolutely sure you want to delete your account? This cannot be undone.')) window.location.href='logout.php?delete=true';">
                        Delete Account
                    </button>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
