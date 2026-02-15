<?php
require_once '../includes/db_helper.php';
require_once '../paths.php';
include '../includes/dashboard_header.php';

// Ensure only bookstore users can see this
if ($user['role'] !== 'bookstore') {
    header("Location: dashboard_user.php");
    exit();
}

$user_id = $_SESSION['user_id'] ?? 0;
$my_announcements = getAnnouncementsByUser($user_id);
?>

<div class="dashboard-wrapper">
    <?php include '../includes/dashboard_sidebar.php'; ?>

    <main class="main-content">
        <div class="section-header">
            <div>
                <h1>Manage Announcements 📢</h1>
                <p>Post messages to all users about physical store events, author visits, or special offers.</p>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success" style="padding: 1rem; background: #d1fae5; color: #065f46; border-radius: var(--radius-md); margin-bottom: 1.5rem; border: 1px solid #10b981;">
                <i class='bx bx-check-circle'></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error" style="padding: 1rem; background: #fee2e2; color: #991b1b; border-radius: var(--radius-md); margin-bottom: 1.5rem; border: 1px solid #ef4444;">
                <i class='bx bx-error-circle'></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 2rem; align-items: start;">
            <!-- Post Announcement Form -->
            <div style="background: white; padding: 2rem; border-radius: var(--radius-lg); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
                <h3 style="margin-bottom: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem;">
                    <i class='bx bx-pencil' style="color: var(--primary);"></i> Post New Announcement
                </h3>
                <form action="../actions/announcement_action.php" method="POST">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="form-group" style="margin-bottom: 1.25rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem;">Announcement Heading</label>
                        <input type="text" name="title" placeholder="e.g., Famous Author Visiting Tomorrow!" required 
                               style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-family: inherit;">
                    </div>

                    <div class="form-group" style="margin-bottom: 1.25rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem;">Message</label>
                        <textarea name="message" rows="4" placeholder="Describe your announcement in detail..." required 
                                  style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-family: inherit; resize: vertical;"></textarea>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem;">Start Date (Optional)</label>
                            <input type="date" name="start_date" 
                                   style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-family: inherit;">
                        </div>
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem;">End Date (Optional)</label>
                            <input type="date" name="end_date" 
                                   style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-family: inherit;">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem;">Action Link (Optional)</label>
                        <input type="url" name="link" placeholder="https://example.com/details" 
                               style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-family: inherit;">
                        <small style="color: var(--text-muted); font-size: 0.75rem;">Link to a website for more details.</small>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 0.8rem;">
                        <i class='bx bx-paper-plane'></i> Publish Announcement
                    </button>
                </form>
            </div>

            <!-- Existing Announcements List -->
            <div style="background: white; padding: 2rem; border-radius: var(--radius-lg); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
                <h3 style="margin-bottom: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem;">
                    <i class='bx bx-list-ul' style="color: var(--primary);"></i> Your Previous Announcements
                </h3>
                
                <?php if (empty($my_announcements)): ?>
                    <div style="text-align: center; padding: 3rem; color: var(--text-muted);">
                        <i class='bx bx-megaphone' style="font-size: 3rem; opacity: 0.3;"></i>
                        <p style="margin-top: 1rem;">No announcements yet.</p>
                    </div>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <?php foreach ($my_announcements as $a): ?>
                            <div style="padding: 1.25rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); position: relative;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                                    <h4 style="font-weight: 700; margin: 0; color: var(--text-main);"><?php echo htmlspecialchars($a['title']); ?></h4>
                                    <form action="../actions/announcement_action.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this announcement?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                                        <button type="submit" style="background: none; border: none; color: #ef4444; cursor: pointer; font-size: 1.2rem;">
                                            <i class='bx bx-trash'></i>
                                        </button>
                                    </form>
                                </div>
                                <p style="font-size: 0.9rem; color: var(--text-body); margin-bottom: 0.75rem; line-height: 1.5;">
                                    <?php echo nl2br(htmlspecialchars($a['message'])); ?>
                                </p>
                                <div style="display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.75rem; color: var(--text-muted);">
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <span><i class='bx bx-calendar-plus'></i> Created: <?php echo date('M d, Y', strtotime($a['created_at'])); ?></span>
                                        <?php if ($a['target_link']): ?>
                                            <a href="<?php echo htmlspecialchars($a['target_link']); ?>" target="_blank" style="color: var(--primary); font-weight: 600;">
                                                <i class='bx bx-link-external'></i> View Link
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($a['start_date'] || $a['end_date']): ?>
                                        <div style="background: #f8fafc; padding: 0.5rem; border-radius: 4px; border: 1px dashed var(--border-color); display: flex; align-items: center; gap: 0.5rem;">
                                            <i class='bx bx-time-five'></i>
                                            <span>Active: <strong><?php echo $a['start_date'] ? date('M d, Y', strtotime($a['start_date'])) : 'Infinity'; ?></strong> to <strong><?php echo $a['end_date'] ? date('M d, Y', strtotime($a['end_date'])) : 'Infinity'; ?></strong></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

</body>
</html>
