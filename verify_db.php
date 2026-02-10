<?php
require_once 'includes/db_helper.php';
$pdo = getDBConnection();
$stmt = $pdo->query("SELECT id, name, cover_image FROM communities LIMIT 5");
$communities = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($communities, JSON_PRETTY_PRINT);
?>
