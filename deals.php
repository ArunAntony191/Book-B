<?php
require_once 'includes/db_helper.php';
require_once 'paths.php';
include 'includes/dashboard_header.php';

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
    header("Location: login.php");
    exit();
}

// Handle status updates
if (isset($_POST['action']) && isset($_POST['transaction_id'])) {
    $tid = $_POST['transaction_id'];
    $action = $_POST['action'];
    
    if ($action === 'approve') updateTransactionStatus($tid, 'approved');
    if ($action === 'cancel') updateTransactionStatus($tid, 'cancelled');
    if ($action === 'complete') updateTransactionStatus($tid, 'returned');
}

$deals = getUserDeals($userId);

$incoming = array_filter($deals, fn($d) => $d['lender_id'] == $userId);
$outgoing = array_filter($deals, fn($d) => $d['borrower_id'] == $userId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Deals | BOOK-B</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        .deals-wrapper {
            max-width: 1100px;
            margin: 0 auto;
        }
        .page-header {
            margin-bottom: 2.5rem;
            animation: fadeInUp 0.5s ease-out;
        }
        .tabs-header {
            display: flex;
            gap: 2.5rem;
            margin-bottom: 2.5rem;
            border-bottom: 1px solid var(--border-color);
            position: relative;
        }
        .tab-btn {
            padding: 1rem 0;
            font-weight: 700;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            background: none;
            border: none;
            font-size: 1rem;
        }
        .tab-btn.active {
            color: var(--primary);
        }
        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--primary);
            border-radius: 3px;
        }
        .tab-count {
            background: #f1f5f9;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            margin-left: 6px;
        }
        .tab-btn.active .tab-count {
            background: var(--primary-light);
            color: white;
        }

        .deal-card {
            background: white;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 2rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            animation: fadeInUp 0.6s ease-out;
        }
        .deal-card:hover {
            transform: scale(1.02);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-light);
        }

        .deal-visual {
            position: relative;
            width: 100px;
            height: 140px;
            flex-shrink: 0;
        }
        .deal-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
        }
        .deal-type-tag {
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--primary);
            color: white;
            padding: 2px 10px;
            border-radius: 10px;
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .deal-main {
            flex: 1;
        }
        .deal-title {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 0.5rem;
        }
        .deal-meta {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .meta-item i { font-size: 1.1rem; color: var(--primary); }

        .deal-actions {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 1rem;
            min-width: 180px;
        }

        .status-pill {
            padding: 0.5rem 1.25rem;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .pill-requested { background: #fef3c7; color: #d97706; }
        .pill-approved { background: #dcfce7; color: #16a34a; }
        .pill-cancelled { background: #fee2e2; color: #dc2626; }
        .pill-returned { background: #f1f5f9; color: #475569; }

        .btn-group {
            display: flex;
            gap: 0.75rem;
        }

        .empty-deals {
            text-align: center;
            padding: 5rem 2rem;
            color: var(--text-muted);
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/dashboard_sidebar.php'; ?>

        <main class="main-content">
            <div class="deals-wrapper">
                <div class="page-header">
                    <h1>Deals & Transactions</h1>
                    <p>Track your book requests, exchanges, and sales</p>
                </div>

                <div class="tabs-header">
                    <button class="tab-btn active" onclick="switchTab('incoming', this)">
                        Incoming Offers <span class="tab-count"><?php echo count($incoming); ?></span>
                    </button>
                    <button class="tab-btn" onclick="switchTab('outgoing', this)">
                        My Requests <span class="tab-count"><?php echo count($outgoing); ?></span>
                    </button>
                </div>

                <div id="incoming-list">
                    <?php if (empty($incoming)): ?>
                        <div class="empty-deals">
                            <i class='bx bx-mail-send' style="font-size: 4rem; margin-bottom: 1.5rem; display: block; opacity: 0.3;"></i>
                            <p style="font-size: 1.1rem; font-weight: 500;">No one has requested your books yet.</p>
                        </div>
                    <?php endif; ?>
                    <?php foreach ($incoming as $deal): ?>
                        <div class="deal-card">
                            <div class="deal-visual">
                                <img src="<?php echo $deal['cover_image'] ?: 'https://images.unsplash.com/photo-1543004218-ee141104975a?w=200'; ?>" class="deal-img">
                                <span class="deal-type-tag"><?php echo $deal['listing_type']; ?></span>
                            </div>
                            <div class="deal-main">
                                <div class="deal-title"><?php echo htmlspecialchars($deal['title']); ?></div>
                                <div class="deal-meta">
                                    <div class="meta-item"><i class='bx bx-user'></i> From: <strong><?php echo htmlspecialchars($deal['borrower_name']); ?></strong></div>
                                    <div class="meta-item"><i class='bx bx-calendar'></i> <?php echo date('M d, Y', strtotime($deal['created_at'])); ?></div>
                                </div>
                            </div>
                            <div class="deal-actions">
                                <span class="status-pill pill-<?php echo $deal['status']; ?>"><?php echo $deal['status']; ?></span>
                                <?php if ($deal['status'] === 'requested'): ?>
                                    <form method="POST" class="btn-group">
                                        <input type="hidden" name="transaction_id" value="<?php echo $deal['id']; ?>">
                                        <button name="action" value="approve" class="btn btn-primary btn-sm">Accept</button>
                                        <button name="action" value="cancel" class="btn btn-sm" style="background: #fee2e2; color: #dc2626; border: none;">Decline</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div id="outgoing-list" style="display: none;">
                    <?php if (empty($outgoing)): ?>
                        <div class="empty-deals">
                            <i class='bx bx-paper-plane' style="font-size: 4rem; margin-bottom: 1.5rem; display: block; opacity: 0.3;"></i>
                            <p style="font-size: 1.1rem; font-weight: 500;">You haven't requested any books yet.</p>
                        </div>
                    <?php endif; ?>
                    <?php foreach ($outgoing as $deal): ?>
                        <div class="deal-card">
                            <div class="deal-visual">
                                <img src="<?php echo $deal['cover_image'] ?: 'https://images.unsplash.com/photo-1543004218-ee141104975a?w=200'; ?>" class="deal-img">
                                <span class="deal-type-tag"><?php echo $deal['listing_type']; ?></span>
                            </div>
                            <div class="deal-main">
                                <div class="deal-title"><?php echo htmlspecialchars($deal['title']); ?></div>
                                <div class="deal-meta">
                                    <div class="meta-item"><i class='bx bx-store-alt'></i> Owner: <strong><?php echo htmlspecialchars($deal['lender_name']); ?></strong></div>
                                    <div class="meta-item"><i class='bx bx-calendar'></i> <?php echo date('M d, Y', strtotime($deal['created_at'])); ?></div>
                                </div>
                            </div>
                            <div class="deal-actions">
                                <span class="status-pill pill-<?php echo $deal['status']; ?>"><?php echo $deal['status']; ?></span>
                                <a href="chat/index.php?user=<?php echo $deal['lender_id']; ?>" class="btn btn-outline btn-sm">
                                    <i class='bx bx-message-square-dots'></i> Chat
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        function switchTab(tab, el) {
            document.querySelectorAll('.tab-btn').forEach(t => t.classList.remove('active'));
            el.classList.add('active');
            
            if (tab === 'incoming') {
                document.getElementById('incoming-list').style.display = 'block';
                document.getElementById('outgoing-list').style.display = 'none';
            } else {
                document.getElementById('incoming-list').style.display = 'none';
                document.getElementById('outgoing-list').style.display = 'block';
            }
        }
    </script>
</body>
</html>
