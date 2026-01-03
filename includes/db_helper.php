<?php
// Database helper functions for user authentication
require_once __DIR__ . '/../config/database.php';

// Sync timezone for consistent expiration times
date_default_timezone_set('Asia/Kolkata');

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

/**
 * Get all users except the current one
 */
function getAllUsers($excludeId = 0) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id, email, firstname, lastname, role FROM users WHERE id != ? ORDER BY firstname ASC");
        $stmt->execute([$excludeId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get all users error: " . $e->getMessage());
        return [];
    }
}
/**
 * Get all transactions for a user (as borrower or lender)
 */
function getUserDeals($userId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT t.*, b.title, b.author, b.cover_image, 
                   u_borrower.firstname as borrower_name, u_lender.firstname as lender_name,
                   l.listing_type
            FROM transactions t
            JOIN listings l ON t.listing_id = l.id
            JOIN books b ON l.book_id = b.id
            JOIN users u_borrower ON t.borrower_id = u_borrower.id
            JOIN users u_lender ON t.lender_id = u_lender.id
            WHERE t.borrower_id = ? OR t.lender_id = ?
            ORDER BY t.created_at DESC
        ");
        $stmt->execute([$userId, $userId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get deals error: " . $e->getMessage());
        return [];
    }
}

/**
 * Update transaction status
 */
function updateTransactionStatus($transactionId, $status) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("UPDATE transactions SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $transactionId]);
    } catch (PDOException $e) {
        error_log("Update transaction status error: " . $e->getMessage());
        return false;
    }
}

/**
 * Add a new book listing
 */
function addListing($userId, $bookTitle, $author, $type, $price, $location, $lat, $lng, $cover = null) {
    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();

        // 1. Create/Get book
        $stmt = $pdo->prepare("INSERT INTO books (title, author, cover_image) VALUES (?, ?, ?)");
        $stmt->execute([$bookTitle, $author, $cover]);
        $bookId = $pdo->lastInsertId();

        // 2. Create listing
        $stmt = $pdo->prepare("
            INSERT INTO listings (user_id, book_id, listing_type, price, location, latitude, longitude) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $bookId, $type, $price, $location, $lat, $lng]);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Add listing error: " . $e->getMessage());
        return false;
    }
}

/**
 * Advanced search for listings
 */
function searchListingsAdvanced($filters, $limit = 20, $offset = 0) {
    try {
        $pdo = getDBConnection();
        $params = [];
        $sql = "
            SELECT l.*, b.title, b.author, b.cover_image, b.category, 
                   u.firstname, u.lastname, u.role, u.reputation_score
            FROM listings l
            JOIN books b ON l.book_id = b.id
            JOIN users u ON l.user_id = u.id
            WHERE l.availability_status = 'available'
        ";

        if (!empty($filters['query'])) {
            $sql .= " AND (b.title LIKE ? OR b.author LIKE ?)";
            $params[] = "%" . $filters['query'] . "%";
            $params[] = "%" . $filters['query'] . "%";
        }

        if (!empty($filters['role'])) {
            $sql .= " AND u.role = ?";
            $params[] = $filters['role'];
        }

        if (!empty($filters['type'])) {
            $sql .= " AND l.listing_type = ?";
            $params[] = $filters['type'];
        }

        if (isset($filters['min_price']) && $filters['min_price'] !== null) {
            $sql .= " AND l.price >= ?";
            $params[] = $filters['min_price'];
        }
        if (isset($filters['max_price']) && $filters['max_price'] !== null) {
            $sql .= " AND l.price <= ?";
            $params[] = $filters['max_price'];
        }

        if (!empty($filters['has_location'])) {
            $sql .= " AND l.latitude IS NOT NULL AND l.longitude IS NOT NULL";
        }

        $sql .= " ORDER BY l.created_at DESC LIMIT ? OFFSET ?";
        
        // Use bindParam for limit/offset as they must be integers in some SQL dialects
        $stmt = $pdo->prepare($sql);
        $i = 1;
        foreach($params as $p) {
            $stmt->bindValue($i++, $p);
        }
        $stmt->bindValue($i++, (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue($i++, (int)$offset, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll();

    } catch (PDOException $e) {
        error_log("Advanced search error: " . $e->getMessage());
        return [];
    }
}
?>
