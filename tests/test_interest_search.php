<?php
require_once __DIR__ . '/../includes/db_helper.php';

echo "Testing searchListingsAdvanced with multiple categories...\n";

$testCategories = ['Fiction', 'Mystery'];
echo "Input categories: " . implode(', ', $testCategories) . "\n";

$filters = ['category' => $testCategories];
$results = searchListingsAdvanced($filters, 5);

echo "Found " . count($results) . " results.\n";

foreach ($results as $book) {
    echo "- [" . $book['category'] . "] " . $book['title'] . " by " . $book['author'] . "\n";
}

if (count($results) > 0) {
    echo "\nSUCCESS: Results found for multiple categories.\n";
} else {
    echo "\nNOTE: No results found. Please ensure there are books matching these categories in your local database for a full validation.\n";
}
?>
