<?php
require_once 'includes/db_helper.php';

echo "Starting image URL repair...\n";

try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT id, cover_image FROM books WHERE cover_image LIKE '%&amp;%'");
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($books) . " potentially corrupted book images.\n";

    $updateStmt = $pdo->prepare("UPDATE books SET cover_image = ? WHERE id = ?");

    foreach ($books as $book) {
        $original = $book['cover_image'];
        // Decode until no more &amp; exists (handles double/triple encoding)
        $decoded = $original;
        while (strpos($decoded, '&amp;') !== false) {
            $decoded = htmlspecialchars_decode($decoded, ENT_QUOTES);
        }

        if ($decoded !== $original) {
            if ($updateStmt->execute([$decoded, $book['id']])) {
                echo "Fixed ID {$book['id']}: Decoded URL.\n";
            } else {
                echo "Failed to update ID {$book['id']}.\n";
            }
        }
    }

    echo "Repair completed.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
