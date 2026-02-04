<?php
require_once __DIR__ . '/../includes/db_helper.php';

try {
    $pdo = getDBConnection();
    
    // Drop existing FK if it exists
    try {
        $pdo->exec("ALTER TABLE reports DROP FOREIGN KEY fk_reported_community");
    } catch (Exception $e) {}

    // Add new FK with ON DELETE SET NULL
    $pdo->exec("ALTER TABLE reports ADD CONSTRAINT fk_reported_community FOREIGN KEY (reported_community_id) REFERENCES communities(id) ON DELETE SET NULL");

    echo "FK updated to SET NULL successfully!";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
