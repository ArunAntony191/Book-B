<?php
require_once '../includes/db_helper.php';

try {
    $pdo = getDBConnection();
    echo "Updating schema for reverse delivery...\n";

    // Update status enum if needed (mysql requires full definition)
    // Note: status is already ENUM('requested', 'approved', 'active', 'delivered', 'cancelled')
    // Let's add 'returning' and 'returned' if not there (returned is already there)
    
    // First, check if 'returning' is in the enum
    $stmt = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'status'");
    $col = $stmt->fetch();
    $type = $col['Type']; // e.g. enum('requested','approved','active','delivered','cancelled')
    
    if (strpos($type, "'returning'") === false) {
        echo "Updating status ENUM to include 'returning'...\n";
        $newType = str_replace(")", ",'returning')", $type);
        $pdo->exec("ALTER TABLE transactions MODIFY COLUMN status $newType");
    }

    $cols = [
        'return_delivery_method' => "ALTER TABLE transactions ADD COLUMN return_delivery_method ENUM('pickup', 'delivery') DEFAULT 'pickup' AFTER borrower_confirm_at",
        'return_agent_id' => "ALTER TABLE transactions ADD COLUMN return_agent_id INT DEFAULT NULL AFTER return_delivery_method",
        'return_picked_up_at' => "ALTER TABLE transactions ADD COLUMN return_picked_up_at DATETIME DEFAULT NULL AFTER return_agent_id",
        'return_agent_confirm_at' => "ALTER TABLE transactions ADD COLUMN return_agent_confirm_at DATETIME DEFAULT NULL AFTER return_picked_up_at",
        'return_lender_confirm_at' => "ALTER TABLE transactions ADD COLUMN return_lender_confirm_at DATETIME DEFAULT NULL AFTER return_agent_confirm_at",
        'return_borrower_confirm_at' => "ALTER TABLE transactions ADD COLUMN return_borrower_confirm_at DATETIME DEFAULT NULL AFTER return_lender_confirm_at",
        'return_delivered_at' => "ALTER TABLE transactions ADD COLUMN return_delivered_at DATETIME DEFAULT NULL AFTER return_borrower_confirm_at"
    ];

    foreach ($cols as $name => $sql) {
        try {
            $pdo->query("SELECT $name FROM transactions LIMIT 1");
            echo "Column '$name' already exists.\n";
        } catch (PDOException $e) {
            $pdo->exec($sql);
            echo "Added column '$name'.\n";
        }
    }
    echo "Database update completed successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
