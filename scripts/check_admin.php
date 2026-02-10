<?php
/**
 * Admin Account Verification and Reset Script
 * This script checks if the admin account exists and can reset it if needed
 */

require_once '../includes/db_helper.php';

echo "=== ADMIN ACCOUNT VERIFICATION ===\n\n";

try {
    // Check if admin account exists
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id, email, firstname, lastname, role, is_banned FROM users WHERE role = 'admin'");
    $stmt->execute();
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($admins)) {
        echo "❌ NO ADMIN ACCOUNT FOUND\n\n";
        echo "Creating default admin account...\n";
        
        // Create admin account with password: admin123
        $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (email, password, firstname, lastname, role, credits) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute(['admin@bookb.com', $hashedPassword, 'Admin', 'User', 'admin', 100]);
        
        echo "✅ Admin account created successfully!\n";
        echo "   Email: admin@bookb.com\n";
        echo "   Password: admin123\n\n";
        
    } else {
        echo "✅ Found " . count($admins) . " admin account(s):\n\n";
        
        foreach ($admins as $admin) {
            echo "   ID: " . $admin['id'] . "\n";
            echo "   Email: " . $admin['email'] . "\n";
            echo "   Name: " . $admin['firstname'] . " " . $admin['lastname'] . "\n";
            echo "   Status: " . ($admin['is_banned'] ? '🚫 BANNED' : '✅ Active') . "\n\n";
        }
        
        // Option to reset password
        echo "To reset admin password to 'admin123', uncomment the line below:\n";
        echo "// RESET PASSWORD BELOW (remove the comment to activate)\n\n";
        
        // Uncomment the next 3 lines to reset the password
        // $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
        // $stmt = $pdo->prepare("UPDATE users SET password = ?, is_banned = 0 WHERE email = 'admin@bookb.com'");
        // $stmt->execute([$hashedPassword]);
        // echo "✅ Password reset to: admin123\n";
    }
    
    echo "\n=== TROUBLESHOOTING TIPS ===\n";
    echo "1. Make sure you're using the correct email (case-sensitive)\n";
    echo "2. Default credentials: admin@bookb.com / admin123\n";
    echo "3. Check if the account is banned (status above)\n";
    echo "4. Clear browser cache and try again\n";
    echo "5. Check browser console for JavaScript errors\n\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
