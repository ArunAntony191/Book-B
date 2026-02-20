<?php
/**
 * Announcements Component
 * Centrally manages fetching and displaying announcement banners across dashboards.
 */

require_once __DIR__ . '/db_helper.php';

$announcements = getActiveAnnouncements();

if (empty($announcements)) return;

foreach ($announcements as $ann):
    $announcementId = 'ann_' . $ann['id'];
    
    // Check if user has dismissed this announcement
    if (isset($_COOKIE[$announcementId])) continue;

    // Secondary check: Hide if end date has passed (fallback for DB time discrepancies)
    if (!empty($ann['end_date']) && $ann['end_date'] < date('Y-m-d')) continue;
?>
<div id="<?php echo $announcementId; ?>" class="announcement-modern">
    <div class="ann-accent-bar"></div>
    <div class="ann-content">
        <div class="ann-badge-container">
            <span class="ann-badge">
                <i class='bx bxs-megaphone'></i> Store Event
            </span>
        </div>
        <div class="ann-text">
            <span class="ann-store-name"><?php echo htmlspecialchars($ann['firstname'] . ' ' . $ann['lastname']); ?>:</span>
            <span class="ann-headline"><?php echo htmlspecialchars($ann['title']); ?></span>
            <span class="ann-description"><?php echo htmlspecialchars($ann['message']); ?></span>
            <?php if ($ann['target_link']): ?>
                <a href="<?php echo htmlspecialchars($ann['target_link']); ?>" target="_blank" class="ann-action-link">
                    Explore Details <i class='bx bx-right-arrow-alt'></i>
                </a>
            <?php endif; ?>
        </div>
        <button class="ann-dismiss" onclick="dismissAnnouncement('<?php echo $announcementId; ?>')" title="Dismiss">
            <i class='bx bx-x'></i>
        </button>
    </div>
</div>
<?php endforeach; ?>

<style>
/* Modern Announcement Banner Styles */
.announcement-modern {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(124, 58, 237, 0.2);
    border-radius: 16px;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    display: flex;
    box-shadow: 0 10px 25px -5px rgba(124, 58, 237, 0.1), 
                0 4px 10px -5px rgba(124, 58, 237, 0.04);
    animation: ann-fade-in 0.6s cubic-bezier(0.16, 1, 0.3, 1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.announcement-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 20px 30px -10px rgba(124, 58, 237, 0.15);
}

.ann-accent-bar {
    width: 6px;
    background: linear-gradient(180deg, #7c3aed 0%, #4f46e5 100%);
}

.ann-content {
    display: flex;
    align-items: center;
    padding: 1.25rem 1.5rem;
    flex-grow: 1;
    gap: 1.5rem;
}

.ann-badge {
    background: linear-gradient(135deg, #7c3aed 0%, #6366f1 100%);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 10px;
    font-size: 0.75rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    white-space: nowrap;
    box-shadow: 0 4px 12px rgba(124, 58, 237, 0.3);
}

.ann-text {
    flex-grow: 1;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.95rem;
    line-height: 1.5;
}

.ann-store-name {
    font-weight: 800;
    color: #1e1b4b;
}

.ann-headline {
    font-weight: 800;
    color: #4338ca;
}

.ann-description {
    color: #475569;
}

.ann-action-link {
    color: #7c3aed;
    font-weight: 700;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    margin-left: 0.5rem;
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    transition: all 0.2s;
}

.ann-action-link:hover {
    background: rgba(124, 58, 237, 0.1);
    text-decoration: underline;
}

.ann-dismiss {
    background: #f1f5f9;
    border: none;
    color: #64748b;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 1.25rem;
}

.ann-dismiss:hover {
    background: #fee2e2;
    color: #ef4444;
    transform: rotate(90deg);
}

@keyframes ann-fade-in {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsiveness */
@media (max-width: 768px) {
    .ann-content {
        flex-direction: column;
        align-items: flex-start;
        padding-right: 3rem;
    }
    .ann-dismiss {
        position: absolute;
        top: 1rem;
        right: 1rem;
    }
}
</style>

<script>
function dismissAnnouncement(id) {
    const el = document.getElementById(id);
    if (!el) return;
    
    // Animate out
    el.style.opacity = '0';
    el.style.transform = 'translateY(-10px) scale(0.95)';
    el.style.transition = 'all 0.4s cubic-bezier(0.16, 1, 0.3, 1)';
    
    setTimeout(() => {
        el.style.display = 'none';
        
        // Persist via cookie (Session BASED - clears on logout or browser close)
        document.cookie = id + "=dismissed; path=/";
    }, 400);
}
</script>
