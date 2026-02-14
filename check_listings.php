<?php
require_once 'includes/db_helper.php';
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("DESCRIBE listings");
    $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Listings Schema:\n";
    foreach ($fields as $field) {
        echo $field['Field'] . " (" . $field['Type'] . ")\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
