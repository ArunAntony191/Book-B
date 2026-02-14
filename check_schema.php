<?php
require_once 'includes/db_helper.php';
$pdo = getDBConnection();
echo "COLUMNS IN transactions TABLE:\n";
$cols = $pdo->query("DESCRIBE transactions")->fetchAll(PDO::FETCH_COLUMN);
print_r($cols);
?>
