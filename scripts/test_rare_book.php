<?php
require_once __DIR__ . '/../includes/db_helper.php';

try {
    $pdo = getDBConnection();
    
    // 1. Create a test rare book
    $userId = 1; // Assuming there is a user with ID 1
    $title = "The Great Gatsby - Signed First Edition";
    $author = "F. Scott Fitzgerald";
    $description = "A rare signed first edition copy of the masterpiece.";
    $category = "Fiction";
    $isRare = 1;
    $rareDetails = "Signed by the author in 1925. Authenticated by Sotheby's.";
    
    // AddListing: function addListing($userId, $bookTitle, $author, $type, $price, $location, $lat, $lng, $cover = null, $description = '', $category = '', $condition = 'good', $visibility = 'public', $communityId = null, $quantity = 1, $creditCost = 10, $district = null, $city = null, $pincode = null, $landmark = null, $isRare = 0, $rareDetails = null)
    $success = addListing(
        $userId, 
        $title, 
        $author, 
        'sell', 
        5000, 
        'Delhi', 
        28.6139, 
        77.2090, 
        null, 
        $description, 
        $category, 
        'as_new', 
        'public', 
        null, 
        1, 
        100, 
        'New Delhi', 
        'Delhi', 
        '110001', 
        'Near Connaught Place',
        $isRare,
        $rareDetails
    );

    if ($success) {
        echo "Test rare book listing created successfully!\n";
    } else {
        echo "Failed to create test rare book listing.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
