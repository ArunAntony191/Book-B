<?php
require_once 'includes/db_helper.php';
$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = 27");
$stmt->execute();
$tx = $stmt->fetch(PDO::FETCH_ASSOC);
echo "TRANSACTION 27:\n";
print_r($tx);
?>
