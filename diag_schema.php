<?php
require_once 'includes/db_helper.php';
$pdo = getDBConnection();
$res = $pdo->query("DESCRIBE transactions");
while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
?>
