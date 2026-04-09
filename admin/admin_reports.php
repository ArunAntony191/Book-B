<?php
require_once '../includes/db_helper.php';
require_once '../paths.php';
include '../includes/dashboard_header.php';

if ($user['role'] !== 'admin') {
    header("Location: dashboard_user.php");
    exit();
}

$reports = getReports('pending');
?>
<div class="dashboard-wrapper">
        <?php include '../includes/dashboard_sidebar.php'; ?>
        <main class="main-content">
            <div class="section-header">
                <div>
                    <h1><i class='bx bx-flag'></i> Platform Reports</h1>
                    <p>Track user reports and system alerts</p>
                </div>
            </div>

            <?php if (empty($reports)): ?>
            <div style="background: var(--bg-card); border-radius: var(--radius-lg); border: 1px solid var(--border-color); margin-top: 2rem; padding: 5rem; text-align: center;">
                <i class='bx bx-check-shield' style="font-size: 5rem; color: #10b981; opacity: 0.3;"></i>
                <h3 style="margin-top: 2rem; color: var(--text-muted);">No active reports</h3>
                <p style="color: var(--text-muted); margin-top: 0.5rem;">All systems are nominal.</p>
            </div>
            <?php else: ?>
            <div style="display: grid; gap: 1.5rem; margin-top: 2rem;">
                <?php foreach ($reports as $r): ?>
                <div style="background: var(--bg-card); padding: 1.5rem; border-radius: var(--radius-lg); border: 1px solid var(--border-color);">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                        <div>
                            <span style="font-size: 0.8rem; font-weight: 700; color: var(--danger); background: var(--alert-danger-bg); padding: 0.25rem 0.5rem; border-radius: 4px; text-transform: uppercase;">
                                <?php echo htmlspecialchars($r['reason']); ?>
                            </span>
                            <div style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.5rem;">
                                Reported on <?php echo date('M d, Y H:i', strtotime($r['created_at'])); ?>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <?php if ($r['type'] === 'community'): ?>
                                <div style="font-weight: 600;">Community: <?php echo htmlspecialchars($r['community_name'] ?? 'Deleted Community'); ?></div>
                            <?php else: ?>
                                <div style="font-weight: 600;">Reported: <?php echo htmlspecialchars($r['reported_fname'] . ' ' . $r['reported_lname']); ?></div>
                            <?php endif; ?>
                            <div style="font-size: 0.85rem; color: var(--text-muted);">by <?php echo htmlspecialchars($r['reporter_fname'] . ' ' . $r['reporter_lname']); ?></div>
                        </div>
                    </div>
                    
                    <div style="background: var(--bg-body); padding: 1rem; border-radius: var(--radius-md); font-size: 0.95rem; color: var(--text-main); margin-bottom: 1.5rem; border: 1px solid var(--border-color);">
                        "<?php echo nl2br(htmlspecialchars($r['description'])); ?>"
                    </div>

                    <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                        <button onclick="resolveReport(<?php echo $r['id']; ?>, 'dismissed')" class="btn btn-outline btn-sm">Dismiss Report</button>
                        <?php if ($r['type'] === 'community'): ?>
                            <button onclick="warnCommunity(<?php echo $r['id']; ?>, <?php echo $r['reported_community_id']; ?>, '<?php echo addslashes($r['reason']); ?>')" class="btn btn-sm" style="background: #f59e0b; color: white; border: none;">Warn Community</button>
                            <button onclick="deleteCommunityAndResolve(<?php echo $r['id']; ?>, <?php echo $r['reported_community_id']; ?>, '<?php echo addslashes($r['reason']); ?>')" class="btn btn-danger btn-sm" style="background: #ef4444; color: white; border: none;">Delete Community & Resolve</button>
                        <?php else: ?>
                            <button onclick="banAndResolve(<?php echo $r['id']; ?>, <?php echo $r['reported_uid']; ?>)" class="btn btn-danger btn-sm" style="background: #ef4444; color: white; border: none;">Ban User & Resolve</button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
    async function resolveReport(reportId, status, silent = false) {
        if (!silent) {
            const confirmed = await Popup.confirm('Resolve Report', 'Mark this report as ' + status + '?');
            if (!confirmed) return;
        }
        
        try {
            const formData = new FormData();
            formData.append('action', 'resolve_report');
            formData.append('report_id', reportId);
            formData.append('status', status);
            
            const response = await fetch('../actions/request_action.php', { method: 'POST', body: formData });
            const result = await response.json();
            
            if (result.success) {
                showToast(`Report ${status}`, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(result.message, 'error');
            }
        } catch (error) {
            showToast('Error resolving report', 'error');
        }
    }

    async function banAndResolve(reportId, userId) {
        const confirmed = await Popup.confirm('Ban & Resolve', 'This will BAN the user and resolve the report. Continue?');
        if (!confirmed) return;
        
        try {
            const banData = new FormData();
            banData.append('action', 'ban_user');
            banData.append('user_id', userId);
            
            const banResp = await fetch('../actions/request_action.php', { method: 'POST', body: banData });
            const banResult = await banResp.json();
            
            if (!banResult.success) {
                throw new Error(banResult.message);
            }
            
            await resolveReport(reportId, 'resolved', true);
            
        } catch (error) {
            showToast('Error: ' + error.message, 'error');
        }
    }

    async function deleteCommunityAndResolve(reportId, communityId, reason) {
        const confirmed = await Popup.confirm('Delete Community', 'This will DELETE the community group and resolve the report. Continue?');
        if (!confirmed) return;
        
        try {
            const formData = new FormData();
            formData.append('action', 'delete_community');
            formData.append('community_id', communityId);
            formData.append('reason', reason);
            
            const response = await fetch('../actions/request_action.php', { method: 'POST', body: formData });
            const result = await response.json();
            
            if (result.success) {
                await resolveReport(reportId, 'resolved', true);
            } else {
                showToast(result.message, 'error');
            }
        } catch (error) {
            showToast('Error deleting community', 'error');
        }
    }

    async function warnCommunity(reportId, communityId, reason) {
        const confirmed = await Popup.confirm('Warn Community', 'Send an official warning to this community group for: ' + reason + '?');
        if (!confirmed) return;
        
        try {
            const formData = new FormData();
            formData.append('action', 'warn_community');
            formData.append('community_id', communityId);
            formData.append('reason', reason);
            
            const response = await fetch('../actions/request_action.php', { method: 'POST', body: formData });
            const result = await response.json();
            
            if (result.success) {
                await resolveReport(reportId, 'resolved', true);
            } else {
                showToast(result.message, 'error');
            }
        } catch (error) {
            showToast('Error sending warning', 'error');
        }
    }
    </script>
