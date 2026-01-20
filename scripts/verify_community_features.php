<?php
require_once '../includes/db_helper.php';

echo "--- Verifying Database Tables ---\n";
try {
    $pdo = getDBConnection();
    
    $tables = ['communities', 'community_members', 'community_messages', 'listings'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "✅ Table '$table' exists.\n";
            
            // Check columns for listings
            if ($table === 'listings') {
                $stmt = $pdo->query("SHOW COLUMNS FROM listings LIKE 'visibility'");
                echo ($stmt->rowCount() > 0 ? "   - Column 'visibility' found.\n" : "   ❌ Column 'visibility' MISSING.\n");
                
                $stmt = $pdo->query("SHOW COLUMNS FROM listings LIKE 'community_id'");
                echo ($stmt->rowCount() > 0 ? "   - Column 'community_id' found.\n" : "   ❌ Column 'community_id' MISSING.\n");
            }
        } else {
            echo "❌ Table '$table' MISSING.\n";
        }
    }
} catch (Exception $e) {
    echo "Error connecting to DB: " . $e->getMessage() . "\n";
}

echo "\n--- Verifying API Logic (Simulation) ---\n";
// We can't easily test full HTTP/POST flow from CLI without valid session/files, 
// but we can check if the file syntax is valid by including it (it will exit due to no session, which is fine)
// or just checking if file exists.
if (file_exists('community/api.php')) {
    echo "✅ 'community/api.php' file exists.\n";
    // Syntax check
    exec('php -l community/api.php', $output, $returnVar);
    if ($returnVar === 0) {
        echo "✅ 'community/api.php' has valid PHP syntax.\n";
    } else {
        echo "❌ 'community/api.php' has syntax errors!\n";
        print_r($output);
    }
} else {
    echo "❌ 'community/api.php' file logic MISSING.\n";
}

if (file_exists('community.php')) {
    echo "✅ 'community.php' file exists.\n";
} else {
    echo "❌ 'community.php' file MISSING.\n";
}

?>
