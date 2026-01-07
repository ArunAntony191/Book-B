<?php
require_once 'includes/db_helper.php';
$pdo = getDBConnection();

echo "--- Schema Check ---\n";
try {
    $stmt = $pdo->query("DESCRIBE messages");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "Error describing table: " . $e->getMessage() . "\n";
}

echo "\n--- Unread Messages Check ---\n";
try {
    $stmt = $pdo->query("SELECT sender_id, receiver_id, is_read, message FROM messages WHERE is_read = 0");
    $unreads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($unreads)) {
        echo "No unread messages found in database.\n";
    } else {
        echo "Found " . count($unreads) . " unread messages:\n";
        print_r($unreads);
        
        echo "\nRefining: Cleaning up self-messages...\n";
        $pdo->exec("UPDATE messages SET is_read = 1 WHERE sender_id = receiver_id AND is_read = 0");
        echo "Cleaned self-unread messages.\n";
    }
} catch (Exception $e) {
    echo "Error reading/updating messages: " . $e->getMessage() . "\n";
}
?>
