<?php
/**
 * Due Date Reminder Component
 * Displays a warning banner for books due within the next 3 days.
 */

// Function to calculate days remaining
function getDaysRemaining($dueDate) {
    if (empty($dueDate) || $dueDate === '0000-00-00') return 999;
    $now = time();
    $due = strtotime($dueDate);
    if ($due === false) return 999;
    $diff = $due - $now;
    return round($diff / (60 * 60 * 24));
}

$dueBooks = getDueBooks($userId, 3); // Check for books due in 3 days

if (!empty($dueBooks)): 
    foreach ($dueBooks as $book):
        $daysLeft = getDaysRemaining($book['due_date']);
        $alertId = 'due_alert_' . $book['id'];
        
        // Skip if dismissed for this session (optional, maybe better to always show for urgency)
        if (isset($_COOKIE[$alertId])) continue;
        
        $isOverdue = $daysLeft < 0;
        $isDueToday = $daysLeft == 0;
?>
<div id="<?php echo $alertId; ?>" class="announcement-modern due-date-alert">
    <div class="ann-accent-bar" style="background: <?php echo $isOverdue ? '#ef4444' : '#f59e0b'; ?>;"></div>
    <div class="ann-content">
        <div class="ann-badge-container">
            <span class="ann-badge" style="background: <?php echo $isOverdue ? '#ef4444' : '#f59e0b'; ?>;">
                <i class='bx bx-time-five'></i> 
                <?php if ($isOverdue): ?>
                    Overdue
                <?php elseif ($isDueToday): ?>
                    Due Today
                <?php else: ?>
                    Due Soon
                <?php endif; ?>
            </span>
        </div>
        <div class="ann-text">
            <span class="ann-store-name">Reminder:</span>
            <span class="ann-headline">"<?php echo htmlspecialchars($book['title']); ?>"</span>
            <span class="ann-description">
                is due 
                <?php if ($isOverdue): ?>
                    <strong><?php echo abs($daysLeft); ?> days ago</strong>.
                <?php elseif ($isDueToday): ?>
                    <strong>today</strong>.
                <?php else: ?>
                    in <strong><?php echo $daysLeft; ?> days</strong>.
                <?php endif; ?>
                Please return it or request an extension.
            </span>
            <a href="transactions.php?filter=active" class="ann-action-link" style="color: <?php echo $isOverdue ? '#ef4444' : '#f59e0b'; ?>;">
                View Transaction <i class='bx bx-right-arrow-alt'></i>
            </a>
        </div>
        <button class="ann-dismiss" onclick="dismissDueAlert('<?php echo $alertId; ?>')" title="Dismiss">
            <i class='bx bx-x'></i>
        </button>
    </div>
</div>
<?php endforeach; ?>

<style>
/* Extend existing announcement styles for due date alert */
.due-date-alert {
    border-color: rgba(245, 158, 11, 0.3);
    background: rgba(255, 251, 235, 0.95); /* Light yellow tint */
}
.due-date-alert .ann-dismiss:hover {
    background: #fef3c7;
    color: #d97706;
}
</style>

<script>
function dismissDueAlert(id) {
    const el = document.getElementById(id);
    if (!el) return;
    
    // Animate out
    el.style.opacity = '0';
    el.style.transform = 'translateY(-10px) scale(0.95)';
    el.style.transition = 'all 0.4s cubic-bezier(0.16, 1, 0.3, 1)';
    
    setTimeout(() => {
        el.style.display = 'none';
        // Persist dismissal for session
        document.cookie = id + "=dismissed; path=/; max-age=3600"; // 1 hour
    }, 400);
}
</script>
<?php endif; ?>
