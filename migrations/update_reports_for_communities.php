<?php
require_once __DIR__ . '/../includes/db_helper.php';

try {
    $pdo = getDBConnection();
    
    // 1. Add reported_community_id column and type column
    // First check if they exist to make it idempotent
    $cols = $pdo->query("SHOW COLUMNS FROM reports")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('reported_community_id', $cols)) {
        $pdo->exec("ALTER TABLE reports ADD COLUMN reported_community_id INT DEFAULT NULL AFTER reported_id");
    }
    
    if (!in_array('type', $cols)) {
        $pdo->exec("ALTER TABLE reports ADD COLUMN type ENUM('user', 'community') DEFAULT 'user' AFTER reason");
    }

    // 2. Make reported_id nullable
    $pdo->exec("ALTER TABLE reports MODIFY reported_id INT NULL");

    // 3. Add Foreign Key for reported_community_id
    try {
        $pdo->exec("ALTER TABLE reports ADD CONSTRAINT fk_reported_community FOREIGN KEY (reported_community_id) REFERENCES communities(id) ON DELETE CASCADE");
    } catch (Exception $e) {
        // FK might already exist
    }

    echo "Reports table updated successfully for communities!";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
