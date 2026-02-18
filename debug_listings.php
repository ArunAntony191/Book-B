<?php
require_once 'includes/db_helper.php';
$pdo = getDBConnection();
$counts = $pdo->query("SELECT listing_type, COUNT(*) as count FROM listings GROUP BY listing_type")->fetchAll();
echo "Listing counts:\n";
foreach ($counts as $c) {
    echo "{$c['listing_type']}: {$c['count']}\n";
}

$sell_listings = $pdo->query("SELECT l.id, b.title, l.availability_status, l.visibility FROM listings l JOIN books b ON l.book_id = b.id WHERE l.listing_type = 'sell'")->fetchAll();
echo "\nSell listings details:\n";
foreach ($sell_listings as $l) {
    echo "ID: {$l['id']} | Title: {$l['title']} | Status: {$l['availability_status']} | Visibility: {$l['visibility']}\n";
}
?>
