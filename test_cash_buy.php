<?php
// Start session and simulate logged-in user
session_start();
$_SESSION['user_id'] = 5;  // Your user ID
$_SESSION['role'] = 'user';
$_SESSION['firstname'] = 'Test';
$_SESSION['lastname'] = 'User';

// Simulate the exact POST request from the browser
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'action' => 'create_request',
    'type' => 'sell',
    'listing_id' => '28',
    'owner_id' => '42',
    'delivery' => '0',
    'address' => '',
    'landmark' => '',
    'lat' => '',
    'lng' => '',
    'book_title' => 'uuuu',
    'payment_method' => 'cod'
];

echo "=== TESTING BUY NOW WITH CASH PAYMENT ===\n\n";
echo "Request Data:\n";
print_r($_POST);
echo "\n";

// Capture the response
ob_start();

// Change to the actions directory context
chdir(__DIR__ . '/actions');
require_once 'request_action.php';

$response = ob_get_clean();

echo "=== SERVER RESPONSE ===\n";
echo $response . "\n\n";

// Parse JSON
$json = json_decode($response, true);
if ($json) {
    echo "=== PARSED RESPONSE ===\n";
    echo "Success: " . ($json['success'] ? 'YES ✅' : 'NO ❌') . "\n";
    echo "Message: " . ($json['message'] ?? 'N/A') . "\n";
    if (isset($json['transaction_id'])) {
        echo "Transaction ID: " . $json['transaction_id'] . " ✅\n";
        echo "\nRedirect URL: delivery_details.php?id=" . $json['transaction_id'] . "\n";
    }
} else {
    echo "❌ Failed to parse JSON response\n";
    echo "Raw response: " . substr($response, 0, 200) . "\n";
}
?>
