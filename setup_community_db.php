<?php
require_once 'includes/db_helper.php';

try {
    $pdo = getDBConnection();

    // 1. Communities Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS communities (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        created_by INT NOT NULL,
        cover_image VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. Members Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS community_members (
        community_id INT NOT NULL,
        user_id INT NOT NULL,
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (community_id, user_id)
    )");

    // 3. Messages Table (Group Chat)
    $pdo->exec("CREATE TABLE IF NOT EXISTS community_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        community_id INT NOT NULL,
        user_id INT NOT NULL,
        message TEXT,
        attachment_url VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    echo "Community tables created successfully!";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
