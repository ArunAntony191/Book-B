<?php
require_once 'includes/db_helper.php';

try {
    $pdo = getDBConnection();
    echo "Checking 'communities' table...\n";

    // Check if column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM communities LIKE 'cover_image'");
    if ($stmt->rowCount() == 0) {
        echo "Column 'cover_image' missing. Adding it...\n";
        $pdo->exec("ALTER TABLE communities ADD COLUMN cover_image VARCHAR(255) AFTER description");
        echo "✅ Column 'cover_image' added successfully.\n";
    } else {
        echo "✅ Column 'cover_image' already exists.\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
