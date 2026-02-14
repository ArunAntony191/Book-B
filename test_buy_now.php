<?php
session_start();

// Simulate a logged-in user (ID 5 based on the previous transaction)
$_SESSION['user_id'] = 5;
$_SESSION['role'] = 'user';
$_SESSION['firstname'] = 'Test';
$_SESSION['lastname'] = 'User';

// Simulate the POST data from the frontend
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
    'book_title' => 'Test Book',
    'payment_method' => 'cod'
];

// Include the request action file
ob_start();
include 'actions/request_action.php';
$response = ob_get_clean();

echo "Response from server:\n";
echo $response . "\n\n";

// Decode and display the JSON response
$data = json_decode($response, true);
if ($data) {
    echo "Parsed Response:\n";
    echo "Success: " . ($data['success'] ? 'YES' : 'NO') . "\n";
    echo "Message: " . ($data['message'] ?? 'N/A') . "\n";
    if (isset($data['transaction_id'])) {
        echo "Transaction ID: " . $data['transaction_id'] . "\n";
    }
} else {
    echo "Failed to parse JSON response\n";
}
?>
