<?php
require_once '../includes/db_helper.php';
require_once '../paths.php';
include '../includes/dashboard_header.php';

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
    header("Location: login.php");
    exit();
}

$creditHistory = getCreditHistory($userId, 50);
$currentCredits = getUserCredits($userId);
?>

<div class="dashboard-wrapper">
    <?php include '../includes/dashboard_sidebar.php'; ?>

    <main class="main-content">
        <div class="section-header">
            <div>
                <h1><i class='bx bx-wallet'></i> Credit History</h1>
                <p>Track your credit earnings and spending over time.</p>
            </div>
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1rem 2rem; border-radius: var(--radius-lg); display: flex; flex-direction: column; align-items: center;">
                <div style="font-size: 0.85rem; opacity: 0.9;">Current Balance</div>
                <div style="font-size: 2.5rem; font-weight: 900;"><?php echo $currentCredits; ?></div>
                <div style="font-size: 0.8rem; opacity: 0.9;">credits</div>
            </div>
        </div>

        <?php if (count($creditHistory) > 0): ?>
        <div style="background: white; border-radius: var(--radius-lg); border: 1px solid var(--border-color); overflow: hidden; box-shadow: var(--shadow-md); margin-top: 2rem;">
            <div style="background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); padding: 1.5rem; border-bottom: 1px solid var(--border-color);">
                <h2 style="font-size: 1.2rem; font-weight: 700; margin: 0;">Transaction History</h2>
            </div>
            
            <table style="width: 100%; border-collapse: collapse;">
                <thead style="background: #f8fafc; border-bottom: 1px solid var(--border-color);">
                    <tr>
                        <th style="padding: 1rem; text-align: left; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Type</th>
                        <th style="padding: 1rem; text-align: left; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Description</th>
                        <th style="padding: 1rem; text-align: center; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Amount</th>
                        <th style="padding: 1rem; text-align: center; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Balance After</th>
                        <th style="padding: 1rem; text-align: right; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($creditHistory as $transaction): 
                        $typeIcons = [
                            'earn' => ['icon' => 'bx-trending-up', 'color' => '#10b981', 'bg' => '#d1fae5', 'label' => 'Earned'],
                            'spend' => ['icon' => 'bx-trending-down', 'color' => '#3b82f6', 'bg' => '#dbeafe', 'label' => 'Spent'],
                            'penalty' => ['icon' => 'bx-error-circle', 'color' => '#ef4444', 'bg' => '#fee2e2', 'label' => 'Penalty'],
                            'bonus' => ['icon' => 'bx-gift', 'color' => '#8b5cf6', 'bg' => '#ede9fe', 'label' => 'Bonus'],
                            'rating_bonus' => ['icon' => 'bx-star', 'color' => '#fbbf24', 'bg' => '#fef3c7', 'label' => 'Rating Bonus']
                        ];
                        $typeInfo = $typeIcons[$transaction['type']] ?? ['icon' => 'bx-transfer', 'color' => '#6b7280', 'bg' => '#f3f4f6', 'label' => 'Other'];
                    ?>
                    <tr style="border-bottom: 1px solid var(--border-color); transition: background 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='white'">
                        <td style="padding: 1.25rem;">
                            <span style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.4rem 1rem; background: <?php echo $typeInfo['bg']; ?>; color: <?php echo $typeInfo['color']; ?>; border-radius: 12px; font-size: 0.85rem; font-weight: 600;">
                                <i class='bx <?php echo $typeInfo['icon']; ?>'></i>
                                <?php echo $typeInfo['label']; ?>
                            </span>
                        </td>
                        <td style="padding: 1.25rem; color: var(--text-main);">
                            <?php echo htmlspecialchars($transaction['description']); ?>
                        </td>
                        <td style="padding: 1.25rem; text-align: center; font-weight: 700; font-size: 1.1rem; 
                            color: <?php echo $transaction['amount'] >= 0 ? '#10b981' : '#ef4444'; ?>;">
                            <?php echo $transaction['amount'] >= 0 ? '+' : ''; ?><?php echo $transaction['amount']; ?>
                        </td>
                        <td style="padding: 1.25rem; text-align: center; font-weight: 600; color: var(--text-muted);">
                            <?php echo $transaction['balance_after']; ?>
                        </td>
                        <td style="padding: 1.25rem; text-align: right; font-size: 0.85rem; color: var(--text-muted);">
                            <?php echo date('M d, Y', strtotime($transaction['created_at'])); ?><br>
                            <small style="font-size: 0.75rem;"><?php echo date('h:i A', strtotime($transaction['created_at'])); ?></small>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div style="background: white; border-radius: var(--radius-lg); border: 1px solid var(--border-color); padding: 4rem; text-align: center; margin-top: 2rem;">
            <i class='bx bx-wallet' style="font-size: 5rem; color: var(--text-muted); opacity: 0.3;"></i>
            <h3 style="color: var(--text-main); margin-top: 1rem; font-size: 1.3rem;">No Credit Activity Yet</h3>
            <p style="color: var(--text-muted); margin-top: 0.5rem; font-size: 1rem;">Your credit transactions will appear here once you start borrowing or lending books.</p>
            <a href="explore.php" class="btn btn-primary" style="margin-top: 1.5rem;">
                <i class='bx bx-book'></i> Explore Books
            </a>
        </div>
        <?php endif; ?>

        <!-- Credit System Info -->
        <div style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); padding: 2rem; border-radius: var(--radius-lg); margin-top: 2rem; border: 1px solid #3b82f6;">
            <h3 style="font-size: 1.2rem; font-weight: 700; color: #1e40af; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class='bx bx-info-circle'></i> How Credits Work
            </h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                <div>
                    <div style="font-weight: 700; color: #10b981; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class='bx bx-trending-up' style="font-size: 1.5rem;"></i> Earn Credits
                    </div>
                    <ul style="color: var(--text-muted); font-size: 0.9rem; margin: 0; padding-left: 1.5rem;">
                        <li>Book Sold: Credits from sale (Lenders)</li>
                        <li>Lend books: +10 credits (Borrow transactions)</li>
                        <li style="color: #059669; font-weight: 700;">Deliver Books: +10 credits (Agents)</li>
                        <li>5-star rating: +5 credits bonus</li>
                    </ul>
                </div>
                <div>
                    <div style="font-weight: 700; color: #3b82f6; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class='bx bx-trending-down' style="font-size: 1.5rem;"></i> Spend Credits
                    </div>
                    <ul style="color: var(--text-muted); font-size: 0.9rem; margin: 0; padding-left: 1.5rem;">
                        <li>Borrow books: -10 credits base</li>
                        <li>Buy books: Sale price in credits</li>
                        <li style="color: #1d4ed8; font-weight: 700;">Delivery Fee: -10 credits (if door-step)</li>
                        <li>Credits deducted on lender acceptance</li>
                    </ul>
                </div>
                <div>
                    <div style="font-weight: 700; color: #ef4444; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class='bx bx-error-circle' style="font-size: 1.5rem;"></i> Penalties
                    </div>
                    <ul style="color: var(--text-muted); font-size: 0.9rem; margin: 0; padding-left: 1.5rem;">
                        <li>Late returns: -5 credits/day</li>
                        <li style="color: #b91c1c; font-weight: 700;">Job Abandon: -5 credits (Agents)</li>
                        <li>Damage/Loss: Variable credit deduction</li>
                    </ul>
                </div>
            </div>
        </div>
    </main>
</div>

</body>
</html>
