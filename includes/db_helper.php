<?php
// Database helper functions for user authentication
require_once __DIR__ . '/../config/database.php';

/**
 * Create a new user
 */
function createUser($email, $password, $firstname, $lastname, $role) {
    try {
        $pdo = getDBConnection();
        
        // Check if user already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            return false; // User already exists
        }
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new user
        $stmt = $pdo->prepare("
            INSERT INTO users (email, password, firstname, lastname, role) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([$email, $hashedPassword, $firstname, $lastname, $role]);
        
    } catch (PDOException $e) {
        error_log("Create user error: " . $e->getMessage());
        return false;
    }
}

/**
 * Authenticate user
 */
function authenticateUser($email, $password) {
    try {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("
            SELECT id, email, password, firstname, lastname, role, reputation_score 
            FROM users 
            WHERE email = ?
        ");
        
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Remove password from returned array
            unset($user['password']);
            return $user;
        }
        
        return false;
        
    } catch (PDOException $e) {
        error_log("Authentication error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user by ID
 */
function getUserById($userId) {
    try {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("
            SELECT id, email, firstname, lastname, role, reputation_score, created_at 
            FROM users 
            WHERE id = ?
        ");
        
        $stmt->execute([$userId]);
        return $stmt->fetch();
        
    } catch (PDOException $e) {
        error_log("Get user error: " . $e->getMessage());
        return false;
    }
}

/**
 * Update user profile
 */
function updateUser($userId, $data) {
    try {
        $pdo = getDBConnection();
        
        $fields = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            if (in_array($key, ['firstname', 'lastname', 'email'])) {
                $fields[] = "$key = ?";
                $values[] = $value;
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $values[] = $userId;
        
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        
        return $stmt->execute($values);
        
    } catch (PDOException $e) {
        error_log("Update user error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all books
 */
function getAllBooks($limit = 10, $offset = 0) {
    try {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("
            SELECT * FROM books 
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?
        ");
        
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Get books error: " . $e->getMessage());
        return [];
    }
}

/**
 * Authenticate user by email only (for OAuth)
 */
function authenticateUserByEmail($email) {
    try {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("
            SELECT id, email, firstname, lastname, role, reputation_score 
            FROM users 
            WHERE email = ?
        ");
        
        $stmt->execute([$email]);
        return $stmt->fetch();
        
    } catch (PDOException $e) {
        error_log("Authentication error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user statistics
 */
function getUserStats($userId) {
    try {
        $pdo = getDBConnection();
        
        // Get total listings
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM listings WHERE user_id = ?");
        $stmt->execute([$userId]);
        $listings = $stmt->fetch()['total'];
        
        // Get active borrows (as borrower)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM transactions 
            WHERE borrower_id = ? AND status = 'active'
        ");
        $stmt->execute([$userId]);
        $active_borrows = $stmt->fetch()['total'];
        
        return [
            'total_listings' => $listings,
            'active_borrows' => $active_borrows
        ];
        
    } catch (PDOException $e) {
        error_log("Get user stats error: " . $e->getMessage());
        return [];
    }
}
?>
