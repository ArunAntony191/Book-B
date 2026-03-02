<?php
require_once '../includes/db_helper.php';
require_once '../paths.php';
include '../includes/dashboard_header.php';

// Ensure only bookstore and library users can see this
if ($user['role'] !== 'bookstore' && $user['role'] !== 'library') {
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
            <div class="alert alert-success" style="padding: 1rem; background: var(--alert-success-bg); color: var(--alert-success-text); border-radius: var(--radius-md); margin-bottom: 1.5rem; border: 1px solid var(--alert-success-border);">
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
            <div style="background: var(--bg-card); padding: 2rem; border-radius: var(--radius-lg); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
                <h3 id="form-title" style="margin-bottom: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem;">
                    <i class='bx bx-pencil' style="color: var(--primary);"></i> Post New Announcement
                </h3>
                <form action="../actions/announcement_action.php" method="POST" id="announcement-form">
                    <input type="hidden" name="action" id="form-action" value="create">
                    <input type="hidden" name="id" id="announcement-id" value="">
                    
                    <div class="form-group" style="margin-bottom: 1.25rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem;">Announcement Heading</label>
                        <input type="text" name="title" id="input-title" placeholder="e.g., Famous Author Visiting Tomorrow!" required 
                               style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-family: inherit; background: var(--bg-body); color: var(--text-main);">
                    </div>

                    <div class="form-group" style="margin-bottom: 1.25rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem;">Message</label>
                        <textarea name="message" id="input-message" rows="4" placeholder="Describe your announcement in detail..." required 
                                  style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-family: inherit; resize: vertical; background: var(--bg-body); color: var(--text-main);"></textarea>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem;">Start Date (Optional)</label>
                            <input type="date" name="start_date" id="input-start-date" 
                                   style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-family: inherit; background: var(--bg-body); color: var(--text-main);">
                        </div>
                        <div class="form-group">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem;">End Date (Auto-expiry)</label>
                            <input type="date" name="end_date" id="input-end-date" title="Announcement will automatically vanish after this date"
                                   style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-family: inherit;">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem;">Action Link (Optional)</label>
                        <input type="url" name="link" id="input-link" placeholder="https://example.com/details" 
                               style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); font-family: inherit; background: var(--bg-body); color: var(--text-main);">
                        <small style="color: var(--text-muted); font-size: 0.75rem;">Link to a website for more details.</small>
                    </div>

                    <div style="display: flex; gap: 1rem;">
                        <button type="submit" id="submit-btn" class="btn btn-primary" style="flex: 1; justify-content: center; padding: 0.8rem;">
                            <i class='bx bx-paper-plane'></i> Publish Announcement
                        </button>
                        <button type="button" id="cancel-btn" onclick="cancelEdit()" class="btn" style="display: none; background: var(--bg-body); color: var(--text-main); border: 1px solid var(--border-color);">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>

            <!-- Existing Announcements List -->
            <div style="background: var(--bg-card); padding: 2rem; border-radius: var(--radius-lg); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
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
                                    <div style="display: flex; gap: 0.5rem;">
                                        <button onclick='editAnnouncement(<?php echo json_encode($a); ?>)' style="background: none; border: none; color: var(--primary); cursor: pointer; font-size: 1.2rem;" title="Edit">
                                            <i class='bx bx-edit'></i>
                                        </button>
                                        <form action="../actions/announcement_action.php" method="POST" id="delete-form-<?php echo $a['id']; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                                            <button type="button" onclick="deleteAnnouncement(<?php echo $a['id']; ?>)" style="background: none; border: none; color: #ef4444; cursor: pointer; font-size: 1.2rem;" title="Delete">
                                                <i class='bx bx-trash'></i>
                                            </button>
                                        </form>
                                    </div>
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
                                        <div style="background: var(--bg-body); padding: 0.5rem; border-radius: 4px; border: 1px dashed var(--border-color); display: flex; align-items: center; gap: 0.5rem;">
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

<script>
function editAnnouncement(data) {
    document.getElementById('form-title').innerHTML = "<i class='bx bx-edit' style='color: var(--primary);'></i> Edit Announcement";
    document.getElementById('form-action').value = "update";
    document.getElementById('announcement-id').value = data.id;
    
    document.getElementById('input-title').value = data.title;
    document.getElementById('input-message').value = data.message;
    document.getElementById('input-start-date').value = data.start_date || '';
    document.getElementById('input-end-date').value = data.end_date || '';
    document.getElementById('input-link').value = data.target_link || '';
    
    document.getElementById('submit-btn').innerHTML = "<i class='bx bx-save'></i> Update Announcement";
    document.getElementById('cancel-btn').style.display = "block";
    
    // Smooth scroll to form
    document.getElementById('announcement-form').scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function cancelEdit() {
    document.getElementById('form-title').innerHTML = "<i class='bx bx-pencil' style='color: var(--primary);'></i> Post New Announcement";
    document.getElementById('form-action').value = "create";
    document.getElementById('announcement-id').value = "";
    
    document.getElementById('announcement-form').reset();
    
    document.getElementById('submit-btn').innerHTML = "<i class='bx bx-paper-plane'></i> Publish Announcement";
    document.getElementById('cancel-btn').style.display = "none";
}

async function deleteAnnouncement(id) {
    const confirmed = await Popup.confirm('Delete Announcement', 'Are you sure you want to delete this announcement?', { confirmText: 'Yes, Delete', confirmStyle: 'danger' });
    if (!confirmed) return;
    document.getElementById('delete-form-' + id).submit();
}
</script>

</body>
</html>
