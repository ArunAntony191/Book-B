<?php
require_once '../includes/db_helper.php';
require_once '../paths.php';
include '../includes/dashboard_header.php';

$userId = $_SESSION['user_id'] ?? 0;
$userRole = $_SESSION['role'] ?? '';

if (!$userId || $userRole !== 'library') {
    header("Location: dashboard_user.php");
    exit();
}

// Fetch all pending damage fines applied by this library (as lender)
try {
    $pdo = getDBConnection();
    // We want to see penalties where the current library is the lender of the associated transaction
    $stmt = $pdo->prepare("
        SELECT p.*, b.title as book_title, u.firstname, u.lastname, u.email, t.id as tx_id
        FROM penalties p
        JOIN transactions t ON p.transaction_id = t.id
        JOIN listings l ON t.listing_id = l.id
        JOIN books b ON l.book_id = b.id
        JOIN users u ON p.user_id = u.id
        WHERE t.lender_id = ? AND p.penalty_type = 'damage_fine' AND p.status = 'pending'
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$userId]);
    $pendingFines = $stmt->fetchAll();

    // Fetch settled fines for history
    $stmt = $pdo->prepare("
        SELECT p.*, b.title as book_title, u.firstname, u.lastname, t.id as tx_id
        FROM penalties p
        JOIN transactions t ON p.transaction_id = t.id
        JOIN listings l ON t.listing_id = l.id
        JOIN books b ON l.book_id = b.id
        JOIN users u ON p.user_id = u.id
        WHERE t.lender_id = ? AND p.penalty_type = 'damage_fine' AND p.status = 'applied'
        ORDER BY p.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$userId]);
    $historyFines = $stmt->fetchAll();
} catch (Exception $e) {
    $pendingFines = [];
    $historyFines = [];
}
?>
    <style>
        .fines-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-top: 2rem;
        }
        @media (max-width: 1024px) {
            .fines-grid { grid-template-columns: 1fr; }
        }
        .fine-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .fine-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary);
        }
        .fine-info {
            display: flex;
            gap: 1.25rem;
            align-items: center;
        }
        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            font-weight: 700;
        }
        .fine-details p { margin: 0; }
        .fine-amount {
            font-size: 1.5rem;
            font-weight: 900;
            color: #ef4444;
        }
        .history-card {
            font-size: 0.9rem;
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
        }
        .history-card:last-child { border-bottom: none; }
        .settle-btn {
            background: #10b981;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }
        .settle-btn:hover {
            background: #059669;
            transform: scale(1.05);
        }
        .settle-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .cancel-btn {
            background: transparent;
            color: #ef4444;
            border: 1px solid #fee2e2;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        .cancel-btn:hover {
            background: #fff1f2;
            border-color: #fecdd3;
        }
    </style>

    <div class="dashboard-wrapper">
        <?php include '../includes/dashboard_sidebar.php'; ?>
        
        <main class="main-content">
            <header style="margin-bottom: 2.5rem;">
                <h1 style="font-weight: 900; font-size: 2rem; color: var(--text-main);">Manage Damage Fines</h1>
                <p style="color: var(--text-muted);">Track and settle book damage dues from your borrowers.</p>
            </header>

            <div class="fines-grid">
                <!-- Active Pending Fines -->
                <section>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <h2 style="font-size: 1.25rem; font-weight: 800;"><i class='bx bx-time-five' style="color: #ef4444;"></i> Pending Collections</h2>
                        <span style="background: #fee2e2; color: #dc2626; padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 700;">
                            <?php echo count($pendingFines); ?> Actions Needed
                        </span>
                    </div>

                    <?php if (empty($pendingFines)): ?>
                        <div style="text-align: center; padding: 4rem; background: var(--bg-card); border-radius: 20px; border: 2px dashed var(--border-color);">
                            <i class='bx bx-check-double' style="font-size: 4rem; color: #10b981; opacity: 0.4;"></i>
                            <p style="margin-top: 1rem; font-weight: 600; color: var(--text-muted);">All clear! No pending fines to collect.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($pendingFines as $fine): ?>
                            <div class="fine-card">
                                <div class="fine-info">
                                    <div class="user-avatar">
                                        <?php echo substr($fine['firstname'], 0, 1); ?>
                                    </div>
                                    <div class="fine-details">
                                        <p style="font-weight: 800; font-size: 1.1rem; color: var(--text-main);"><?php echo htmlspecialchars($fine['firstname'] . ' ' . $fine['lastname']); ?></p>
                                        <p style="font-size: 0.85rem; color: var(--text-muted);"><i class='bx bx-book'></i> <?php echo htmlspecialchars($fine['book_title']); ?></p>
                                        <p style="font-size: 0.75rem; color: #ef4444; font-weight: 600; margin-top: 4px;">Reason: <?php echo htmlspecialchars($fine['reason']); ?></p>
                                    </div>
                                </div>
                                <div style="text-align: right; display: flex; flex-direction: column; gap: 0.75rem; align-items: flex-end;">
                                    <div class="fine-amount">₹<?php echo number_format($fine['monetary_penalty'], 2); ?></div>
                                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                                        <button onclick="cancelFine(<?php echo $fine['tx_id']; ?>, this)" class="cancel-btn">
                                            <i class='bx bx-x-circle'></i> Cancel
                                        </button>
                                        <button onclick="settleFine(<?php echo $fine['tx_id']; ?>, this)" class="settle-btn">
                                            <i class='bx bx-check-circle'></i> Settle Cash
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </section>

                <!-- History Section -->
                <aside>
                    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem; position: sticky; top: 100px;">
                        <h2 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class='bx bx-history'></i> Recent Activity
                        </h2>
                        
                        <?php if (empty($historyFines)): ?>
                            <p style="text-align: center; color: var(--text-muted); font-size: 0.85rem; padding: 2rem 0;">No recent history.</p>
                        <?php else: ?>
                            <?php foreach ($historyFines as $h): ?>
                                <div class="history-card">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                        <span style="font-weight: 700; color: var(--text-main);"><?php echo htmlspecialchars($h['firstname']); ?></span>
                                        <span style="color: #10b981; font-weight: 800;">+ ₹<?php echo number_format($h['monetary_penalty'], 0); ?></span>
                                    </div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted); display: flex; justify-content: space-between;">
                                        <span><?php echo htmlspecialchars(substr($h['book_title'], 0, 20)) . '...'; ?></span>
                                         <span><?php echo date('M d', strtotime($h['created_at'])); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </aside>
            </div>
        </main>
    </div>

    <script>
        async function cancelFine(txId, btn) {
            const confirmed = await Popup.confirm(
                'Cancel Fine',
                "Are you sure you want to cancel this fine? This should only be done if the fine was applied due to a misunderstanding. Borrower's balance will be reverted.",
                { confirmText: 'Yes, Cancel Fine' }
            );
            if (!confirmed) return;

            try {
                btn.disabled = true;
                const originalContent = btn.innerHTML;
                btn.innerHTML = "<i class='bx bx-loader-alt bx-spin'></i>...";

                const formData = new FormData();
                formData.append('action', 'cancel_transaction_fine');
                formData.append('transaction_id', txId);

                const response = await fetch('../actions/request_action.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    showToast('✅ Fine cancelled successfully!', 'success', 3000);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(result.message || 'Failed to cancel fine.', 'error', 5000);
                    btn.disabled = false;
                    btn.innerHTML = originalContent;
                }
            } catch (err) {
                showToast('Network error. Please try again.', 'error', 4000);
                btn.disabled = false;
                btn.innerHTML = "<i class='bx bx-x-circle'></i> Cancel";
            }
        }

        async function settleFine(txId, btn) {
            const confirmed = await Popup.confirm(
                'Settle Fine (Cash)',
                'Confirm that you have received the cash payment for this damage fine?',
                { confirmText: 'Yes, Confirm Payment' }
            );
            if (!confirmed) return;

            try {
                btn.disabled = true;
                btn.innerHTML = "<i class='bx bx-loader-alt bx-spin'></i> Processing...";

                const formData = new FormData();
                formData.append('action', 'settle_transaction_fine');
                formData.append('transaction_id', txId);

                const response = await fetch('../actions/request_action.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    showToast('💰 Fine settled successfully!', 'success', 3000);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(result.message || 'Failed to settle fine.', 'error', 5000);
                    btn.disabled = false;
                    btn.innerHTML = "<i class='bx bx-check-circle'></i> Settle Cash";
                }
            } catch (err) {
                showToast('Network error. Please try again.', 'error', 4000);
                btn.disabled = false;
                btn.innerHTML = "<i class='bx bx-check-circle'></i> Settle Cash";
            }
        }
    </script>
