<?php
require_once 'includes/db_helper.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    die("Please login first.");
}

$userId = $_SESSION['user_id'];
$pdo = getDBConnection();
$credits = getUserCredits($userId);

// Fetch relevant transactions
$stmt = $pdo->prepare("
    SELECT t.*, b.title 
    FROM transactions t
    JOIN listings l ON t.listing_id = l.id
    JOIN books b ON l.book_id = b.id
    WHERE t.borrower_id = ? AND t.status IN ('delivered', 'returning', 'returned')
    ORDER BY t.created_at DESC
");
$stmt->execute([$userId]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Return Diagnostics</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { padding: 2rem; font-family: sans-serif; background: #f8fafc; }
        .card { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 1rem; }
        .badge { display: inline-block; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.8rem; font-weight: bold; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-fail { background: #fee2e2; color: #991b1b; }
        .badge-warn { background: #fef3c7; color: #92400e; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { text-align: left; padding: 0.75rem; border-bottom: 1px solid #e2e8f0; }
    </style>
</head>
<body>
    <h1>Return System Diagnostics</h1>
    
    <div class="card">
        <h3>User Status</h3>
        <p><strong>User ID:</strong> <?php echo $userId; ?></p>
        <p><strong>Current Credits:</strong> <?php echo $credits; ?> 
            <?php if ($credits < 10) echo '<span class="badge badge-fail">INSUFFICIENT FOR RETURN (Need 10)</span>'; 
                  else echo '<span class="badge badge-success">OK</span>'; ?>
        </p>
    </div>

    <h3>Your Deliveries (Eligible for Return Check)</h3>
    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Trans ID</th>
                    <th>Book</th>
                    <th>Status</th>
                    <th>Return Agent</th>
                    <th>Checks</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $t): ?>
                    <tr>
                        <td>#<?php echo $t['id']; ?></td>
                        <td><?php echo htmlspecialchars($t['title']); ?></td>
                        <td><?php echo $t['status']; ?></td>
                        <td><?php echo $t['return_agent_id'] ? $t['return_agent_id'] : 'None'; ?></td>
                        <td>
                            <?php
                            $checks = [];
                            // Status Check
                            if ($t['status'] === 'delivered') $checks[] = '<span class="badge badge-success">Status OK</span>';
                            elseif ($t['status'] === 'returning') $checks[] = '<span class="badge badge-warn">Already Returning</span>';
                            else $checks[] = '<span class="badge badge-fail">Invalid Status</span>';

                            // Agent Check
                            if (empty($t['return_agent_id'])) $checks[] = '<span class="badge badge-success">No Result Agent OK</span>';
                            else $checks[] = '<span class="badge badge-warn">Agent Assigned</span>';

                            echo implode(' ', $checks);
                            ?>
                        </td>
                        <td>
                            <?php if ($t['status'] === 'delivered' && empty($t['return_agent_id'])): ?>
                                <button onclick="attemptReturn(<?php echo $t['id']; ?>)" style="background: #2563eb; color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer;">
                                    Test Return Request
                                </button>
                            <?php else: ?>
                                <span style="color: #64748b;">Not eligible</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($transactions)) echo "<tr><td colspan='6'>No delivered or returning transactions found.</td></tr>"; ?>
            </tbody>
        </table>
    </div>

    <div id="console" class="card" style="display:none; background: #1e293b; color: #10b981; font-family: monospace;">
        <h4>Test Output:</h4>
        <pre id="console-output"></pre>
    </div>

    <script>
        function attemptReturn(txId) {
            const consoleDiv = document.getElementById('console');
            const output = document.getElementById('console-output');
            consoleDiv.style.display = 'block';
            output.innerText = `Sending request for Transaction #${txId}...\n`;

            const formData = new FormData();
            formData.append('action', 'request_return_delivery');
            formData.append('transaction_id', txId);

            fetch('request_action.php', { method: 'POST', body: formData })
                .then(async response => {
                    const text = await response.text();
                    output.innerText += `\nRaw Server Response:\n${text}\n`;
                    try {
                        const json = JSON.parse(text);
                        if (json.success) {
                            output.innerText += `\n✅ SUCCESS: Return requested successfully!`;
                        } else {
                            output.innerText += `\n❌ ERROR: Server returned error: ${json.message}`;
                        }
                    } catch (e) {
                        output.innerText += `\n❌ PARSE ERROR: Response is not valid JSON. Possible PHP warning/fatal error above.`;
                    }
                })
                .catch(err => {
                    output.innerText += `\n❌ NETWORK ERROR: ${err.message}`;
                });
        }
    </script>
</body>
</html>
