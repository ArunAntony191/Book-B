<?php
require_once 'includes/db_helper.php';
$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT credits, service_start_lat, service_start_lng FROM users WHERE email = ?");
$stmt->execute(['library@test.com']);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if ($user) {
    echo "Credits: " . $user['credits'] . "\n";
    echo "Lat: '" . $user['service_start_lat'] . "'\n";
    echo "Lng: '" . $user['service_start_lng'] . "'\n";
} else {
    echo "User not found\n";
}
?>
