<?php
require_once 'includes/db_helper.php';
$pdo = getDBConnection();
$stmt = $pdo->query("SELECT id, title, cover_image FROM books ORDER BY id DESC LIMIT 20");
echo "ID | Title | Cover Image\n";
echo "---|-------|------------\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$row['id']} | {$row['title']} | {$row['cover_image']}\n";
}
?>
