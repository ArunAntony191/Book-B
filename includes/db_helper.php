<?php
// Database helper functions for user authentication
require_once __DIR__ . '/../config/database.php';

// Sync timezone for consistent expiration times
date_default_timezone_set('Asia/Kolkata');

/**
 * Create a new user
 */
function createUser($email, $password, $firstname, $lastname, $role, $phone = null) {
    try {
        $pdo = getDBConnection();
        
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return 'email_exists';
        }
        
        // Check if phone already exists
        if ($phone) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
            $stmt->execute([$phone]);
            if ($stmt->fetch()) {
                return 'phone_exists';
            }
        }
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new user with initial credits and trust
        $stmt = $pdo->prepare("
            INSERT INTO users (email, password, firstname, lastname, phone, role, credits, trust_score) 
            VALUES (?, ?, ?, ?, ?, ?, 100, 100)
        ");
        
        if ($stmt->execute([$email, $hashedPassword, $firstname, $lastname, $phone, $role])) {
            return $pdo->lastInsertId();
        }
        return false;
        
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
            SELECT id, email, password, firstname, lastname, role, reputation_score, is_accepting_deliveries, is_banned 
            FROM users 
            WHERE email = ?
        ");
        
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            if ($user['is_banned']) {
                return 'banned';
            }
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
            SELECT id, email, firstname, lastname, phone, role, reputation_score, created_at,
                   address, landmark, service_start_lat, service_start_lng, service_end_lat, service_end_lng, is_accepting_deliveries
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
            if (in_array($key, [
                'firstname', 'lastname', 'email', 'phone', 'address', 'landmark', 'district', 'city', 'pincode', 'state',
                'service_start_lat', 'service_start_lng', 
                'service_end_lat', 'service_end_lng', 
                'is_accepting_deliveries'
            ])) {
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
            SELECT id, email, firstname, lastname, role, reputation_score, is_banned 
            FROM users 
            WHERE email = ?
        ");
        
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && $user['is_banned']) {
            return 'banned';
        }
        return $user;
        
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
        $sql = "SELECT u.id, u.email, u.firstname, u.lastname, u.role,
                (SELECT COUNT(*) FROM messages m WHERE m.sender_id = u.id AND m.receiver_id = ? AND m.is_read = 0) as unread_count,
                (SELECT MAX(created_at) FROM messages WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id)) as last_activity
                FROM users u WHERE u.id != ? 
                ORDER BY last_activity DESC, firstname ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$excludeId, $excludeId, $excludeId, $excludeId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get all users error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get users that have had a conversation with the current user
 */
function getRecentChats($userId) {
    try {
        $pdo = getDBConnection();
        $sql = "SELECT DISTINCT u.id, u.email, u.firstname, u.lastname, u.role,
                (SELECT message FROM messages WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id) ORDER BY created_at DESC LIMIT 1) as last_message,
                (SELECT created_at FROM messages WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id) ORDER BY created_at DESC LIMIT 1) as last_time,
                (SELECT COUNT(*) FROM messages m WHERE m.sender_id = u.id AND m.receiver_id = ? AND m.is_read = 0) as unread_count
                FROM users u
                INNER JOIN messages m ON (m.sender_id = u.id AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = u.id)
                WHERE u.id != ?
                ORDER BY last_time DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get recent chats error: " . $e->getMessage());
        return [];
    }
}

/**
 * Search users by name or email
 */
function searchUsers($query, $excludeId = 0) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id, email, firstname, lastname, role FROM users 
                              WHERE id != ? AND (firstname LIKE ? OR lastname LIKE ? OR email LIKE ?)
                              LIMIT 10");
        $q = "%$query%";
        $stmt->execute([$excludeId, $q, $q, $q]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Search users error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get total unread messages for a user
 */
function getTotalUnreadCount($userId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Get unread notification count for a user
 */
function getUnreadNotificationsCount($userId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
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
 * Get all deliveries for a user (as borrower or lender)
 */
function getUserDeliveries($userId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT t.*, b.title, b.author, b.cover_image, 
                   u_borrower.firstname as borrower_name, u_lender.firstname as lender_name,
                   l.listing_type, l.location as pickup_location, l.landmark as pickup_landmark,
                   l.latitude as pickup_lat, l.longitude as pickup_lng,
                   u_agent.firstname as agent_name, u_agent.phone as agent_phone
            FROM transactions t
            JOIN listings l ON t.listing_id = l.id
            JOIN books b ON l.book_id = b.id
            JOIN users u_borrower ON t.borrower_id = u_borrower.id
            JOIN users u_lender ON t.lender_id = u_lender.id
            LEFT JOIN users u_agent ON t.delivery_agent_id = u_agent.id
            WHERE (t.borrower_id = ? OR t.lender_id = ?) 
            AND t.delivery_method = 'delivery'
            ORDER BY t.created_at DESC
        ");
        $stmt->execute([$userId, $userId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get deliveries error: " . $e->getMessage());
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
function addListing($userId, $bookTitle, $author, $type, $price, $location, $lat, $lng, $cover = null, $description = '', $category = '', $condition = 'good', $visibility = 'public', $communityId = null, $quantity = 1, $creditCost = 10, $district = null, $city = null, $pincode = null, $landmark = null) {
    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();

        // 1. Create/Get book
        $stmt = $pdo->prepare("INSERT INTO books (title, author, cover_image, description, category, condition_status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$bookTitle, $author, $cover, $description, $category, $condition]);
        $bookId = $pdo->lastInsertId();

        // 2. Create listing with quantity and credit_cost
        $stmt = $pdo->prepare("
            INSERT INTO listings (user_id, book_id, listing_type, price, location, landmark, district, city, pincode, latitude, longitude, visibility, community_id, quantity, credit_cost) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $bookId, $type, $price, $location, $landmark, $district, $city, $pincode, $lat, $lng, $visibility, $communityId, $quantity, $creditCost]);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Add listing error: " . $e->getMessage());
        return false;
    }
}

/**
 * Update an existing book listing
 */
function updateListing($listingId, $userId, $bookTitle, $author, $type, $price, $location, $lat, $lng, $cover = null, $description = '', $category = '', $condition = 'good', $visibility = 'public', $communityId = null, $quantity = 1, $creditCost = 10, $district = null, $city = null, $pincode = null, $landmark = null) {
    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();

        // 1. Get book_id from listing
        $stmt = $pdo->prepare("SELECT book_id FROM listings WHERE id = ? AND user_id = ?");
        $stmt->execute([$listingId, $userId]);
        $listing = $stmt->fetch();
        if (!$listing) throw new Exception("Listing not found or unauthorized");
        $bookId = $listing['book_id'];

        // 2. Update book
        $stmt = $pdo->prepare("UPDATE books SET title = ?, author = ?, cover_image = ?, description = ?, category = ?, condition_status = ? WHERE id = ?");
        $stmt->execute([$bookTitle, $author, $cover, $description, $category, $condition, $bookId]);

        // 3. Update listing
        $stmt = $pdo->prepare("
            UPDATE listings 
            SET listing_type = ?, price = ?, location = ?, landmark = ?, district = ?, city = ?, pincode = ?, latitude = ?, longitude = ?, visibility = ?, community_id = ?, quantity = ?, credit_cost = ?
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$type, $price, $location, $landmark, $district, $city, $pincode, $lat, $lng, $visibility, $communityId, $quantity, $creditCost, $listingId, $userId]);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        if (isset($pdo)) $pdo->rollBack();
        error_log("Update listing error: " . $e->getMessage());
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
        
        $select = "l.*, b.title, b.author, b.cover_image, b.category, 
                   u.firstname, u.lastname, u.role, u.reputation_score, u.trust_score, u.average_rating";
        
        // Dynamic Distance Calculation if center provided
        if (!empty($filters['center_lat']) && !empty($filters['center_lng'])) {
            $select .= ", (6371 * acos(cos(radians(" . (float)$filters['center_lat'] . ")) 
                        * cos(radians(l.latitude)) 
                        * cos(radians(l.longitude) - radians(" . (float)$filters['center_lng'] . ")) 
                        + sin(radians(" . (float)$filters['center_lat'] . ")) 
                        * sin(radians(l.latitude)))) AS distance";
        } else {
            $select .= ", NULL as distance";
        }

        $sql = "
            SELECT $select
            FROM listings l
            JOIN books b ON l.book_id = b.id
            JOIN users u ON l.user_id = u.id
            WHERE l.availability_status = 'available' AND l.visibility = 'public'
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

        if (!empty($filters['category'])) {
            $sql .= " AND b.category LIKE ?";
            $params[] = "%" . $filters['category'] . "%";
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

        // ONLY show available quantities by default if not specified
        if (!isset($filters['show_all'])) {
            $sql .= " AND l.quantity > 0";
        }

        if (!empty($filters['has_location'])) {
            $sql .= " AND l.latitude IS NOT NULL AND l.longitude IS NOT NULL";
        }

        // Bounding Box Filter
        if (!empty($filters['bounds'])) {
            $sql .= " AND l.latitude BETWEEN ? AND ? AND l.longitude BETWEEN ? AND ?";
            $params[] = (float)$filters['bounds']['sw_lat'];
            $params[] = (float)$filters['bounds']['ne_lat'];
            $params[] = (float)$filters['bounds']['sw_lng'];
            $params[] = (float)$filters['bounds']['ne_lng'];
        }

        // Sorting
        if (!empty($filters['center_lat']) && !empty($filters['center_lng'])) {
            $sql .= " ORDER BY distance ASC";
        } else {
            $sql .= " ORDER BY l.created_at DESC";
        }

        $sql .= " LIMIT ? OFFSET ?";
        
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

// ==================== CREDIT MANAGEMENT FUNCTIONS ====================

/**
 * Get user's current credit balance
 */
function getUserCredits($userId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT credits FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        return $result ? (int)$result['credits'] : 0;
    } catch (PDOException $e) {
        error_log("Get credits error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Add credits to user account
 */
function addCredits($userId, $amount, $type, $description, $transactionId = null) {
    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();
        
        // Get current balance
        $stmt = $pdo->prepare("SELECT credits FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $currentBalance = (int)$stmt->fetchColumn();
        
        $newBalance = $currentBalance + $amount;
        
        // Update user credits
        $stmt = $pdo->prepare("UPDATE users SET credits = ? WHERE id = ?");
        $stmt->execute([$newBalance, $userId]);
        
        // Log transaction
        $stmt = $pdo->prepare("
            INSERT INTO credit_transactions (user_id, transaction_id, amount, balance_after, type, description)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $transactionId, $amount, $newBalance, $type, $description]);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Add credits error: " . $e->getMessage());
        return false;
    }
}

/**
 * Deduct credits from user account
 */
function deductCredits($userId, $amount, $type, $description, $transactionId = null) {
    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();
        
        // Get current balance
        $stmt = $pdo->prepare("SELECT credits FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $currentBalance = (int)$stmt->fetchColumn();
        
        $newBalance = $currentBalance - $amount;
        
        // Update user credits (allow negative balance for penalties)
        $stmt = $pdo->prepare("UPDATE users SET credits = ? WHERE id = ?");
        $stmt->execute([$newBalance, $userId]);
        
        // Log transaction
        $stmt = $pdo->prepare("
            INSERT INTO credit_transactions (user_id, transaction_id, amount, balance_after, type, description)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $transactionId, -$amount, $newBalance, $type, $description]);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Deduct credits error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if user has sufficient credits
 */
function checkSufficientCredits($userId, $amount) {
    $balance = getUserCredits($userId);
    return $balance >= $amount;
}

/**
 * Get credit transaction history
 */
function getCreditHistory($userId, $limit = 20) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT * FROM credit_transactions
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get credit history error: " . $e->getMessage());
        return [];
    }
}

// ==================== TRUST SCORE FUNCTIONS ====================

/**
 * Update user trust score
 */
function updateTrustScore($userId, $change, $reason) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            UPDATE users 
            SET trust_score = GREATEST(0, LEAST(100, trust_score + ?))
            WHERE id = ?
        ");
        return $stmt->execute([$change, $userId]);
    } catch (PDOException $e) {
        error_log("Update trust score error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get trust score rating label
 */
function getTrustScoreRating($score) {
    if ($score >= 80) return ['label' => 'Excellent', 'color' => '#10b981'];
    if ($score >= 60) return ['label' => 'Good', 'color' => '#3b82f6'];
    if ($score >= 40) return ['label' => 'Fair', 'color' => '#f59e0b'];
    return ['label' => 'Poor', 'color' => '#ef4444'];
}

/**
 * Calculate penalty for late return
 */
function calculatePenalty($transactionId) {
    try {
        $pdo = getDBConnection();
        
        // Get transaction details
        $stmt = $pdo->prepare("
            SELECT borrower_id, due_date, return_date 
            FROM transactions 
            WHERE id = ?
        ");
        $stmt->execute([$transactionId]);
        $transaction = $stmt->fetch();
        
        if (!$transaction || !$transaction['due_date']) {
            return ['days' => 0, 'credit_penalty' => 0, 'trust_penalty' => 0];
        }
        
        $dueDate = new DateTime($transaction['due_date']);
        $returnDate = $transaction['return_date'] ? new DateTime($transaction['return_date']) : new DateTime();
        
        if ($returnDate <= $dueDate) {
            return ['days' => 0, 'credit_penalty' => 0, 'trust_penalty' => 0];
        }
        
        $daysOverdue = $dueDate->diff($returnDate)->days;
        $creditPenalty = $daysOverdue * 5; // 5 credits per day
        $trustPenalty = min($daysOverdue * 2, 20); // 2 points per day, max 20
        
        return [
            'days' => $daysOverdue,
            'credit_penalty' => $creditPenalty,
            'trust_penalty' => $trustPenalty
        ];
        
    } catch (Exception $e) {
        error_log("Calculate penalty error: " . $e->getMessage());
        return ['days' => 0, 'credit_penalty' => 0, 'trust_penalty' => 0];
    }
}

/**
 * Apply penalty for late return
 */
function applyPenalty($transactionId, $userId) {
    try {
        $pdo = getDBConnection();
        $penalty = calculatePenalty($transactionId);
        
        if ($penalty['days'] > 0) {
            $pdo->beginTransaction();
            
            // Record penalty
            $stmt = $pdo->prepare("
                INSERT INTO penalties (transaction_id, user_id, days_overdue, credit_penalty, trust_penalty)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $transactionId,
                $userId,
                $penalty['days'],
                $penalty['credit_penalty'],
                $penalty['trust_penalty']
            ]);
            
            // Deduct credits
            deductCredits($userId, $penalty['credit_penalty'], 'penalty', 
                "Late return penalty: {$penalty['days']} days overdue", $transactionId);
            
            // Update trust score
            updateTrustScore($userId, -$penalty['trust_penalty'], 'late_return');
            
            // Update late returns count
            $stmt = $pdo->prepare("UPDATE users SET late_returns = late_returns + 1 WHERE id = ?");
            $stmt->execute([$userId]);
            
            $pdo->commit();
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        if (isset($pdo)) $pdo->rollBack();
        error_log("Apply penalty error: " . $e->getMessage());
        return false;
    }
}

// ==================== RATING SYSTEM FUNCTIONS ====================

/**
 * Add a review/rating for a user
 */
function addReview($transactionId, $reviewerId, $revieweeId, $rating, $comment = '') {
    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();
        
        // Calculate trust impact based on rating (1-5 stars)
        // 5 stars: +10 trust, 4 stars: +5, 3 stars: 0, 2 stars: -5, 1 star: -10
        $trustImpact = ($rating - 3) * 5;
        
        // Insert review
        $stmt = $pdo->prepare("
            INSERT INTO reviews (transaction_id, reviewer_id, reviewee_id, rating, comment, trust_impact)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$transactionId, $reviewerId, $revieweeId, $rating, $comment, $trustImpact]);
        
        // Update reviewee's average rating and total ratings
        $stmt = $pdo->prepare("
            UPDATE users 
            SET total_ratings = total_ratings + 1,
                average_rating = (
                    SELECT AVG(rating) FROM reviews WHERE reviewee_id = ?
                )
            WHERE id = ?
        ");
        $stmt->execute([$revieweeId, $revieweeId]);
        
        // Update trust score
        updateTrustScore($revieweeId, $trustImpact, 'rating_received');
        
        // Bonus credits for 5-star ratings
        if ($rating == 5) {
            addCredits($revieweeId, 5, 'rating_bonus', 'Earned 5-star rating bonus', $transactionId);
        }
        
        $pdo->commit();
        return true;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Add review error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if user has already reviewed a transaction
 */
function hasUserReviewed($transactionId, $userId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM reviews 
            WHERE transaction_id = ? AND reviewer_id = ?
        ");
        $stmt->execute([$transactionId, $userId]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Get reviews for a user
 */
function getUserReviews($userId, $limit = 10) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT r.*, u.firstname, u.lastname, t.id as trans_id
            FROM reviews r
            JOIN users u ON r.reviewer_id = u.id
            JOIN transactions t ON r.transaction_id = t.id
            WHERE r.reviewee_id = ?
            ORDER BY r.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get user reviews error: " . $e->getMessage());
        return [];
    }
}

// ==================== ADMIN SAFETY FUNCTIONS ====================

/**
 * Ban a user
 */
function banUser($userId) {
    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();

        // 1. Set is_banned flag
        $stmt = $pdo->prepare("UPDATE users SET is_banned = 1 WHERE id = ?");
        $stmt->execute([$userId]);

        // 2. Clear any active sessions (handled by login check usually, but good to flag)
        
        // 3. Send notification
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type, message, is_read) 
            VALUES (?, 'system', 'Your account has been suspended due to policy violations. Contact admin for appeal.', 0)
        ");
        $stmt->execute([$userId]);

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Ban user error: " . $e->getMessage());
        return false;
    }
}

/**
 * Unban a user
 */
function unbanUser($userId) {
    try {
        $pdo = getDBConnection();
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE users SET is_banned = 0 WHERE id = ?");
        $stmt->execute([$userId]);

        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type, message, is_read) 
            VALUES (?, 'system', 'Your account suspension has been lifted. Welcome back.', 0)
        ");
        $stmt->execute([$userId]);

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Unban user error: " . $e->getMessage());
        return false;
    }
}

/**
 * Create a user report
 */
function createReport($reporterId, $reportedId, $reason, $description) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            INSERT INTO reports (reporter_id, reported_id, reason, description, status) 
            VALUES (?, ?, ?, ?, 'pending')
        ");
        return $stmt->execute([$reporterId, $reportedId, $reason, $description]);
    } catch (PDOException $e) {
        error_log("Create report error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get reports
 */
function getReports($status = 'pending') {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT r.*, 
                   u_reporter.firstname as reporter_fname, u_reporter.lastname as reporter_lname,
                   u_reported.firstname as reported_fname, u_reported.lastname as reported_lname, u_reported.id as reported_uid
            FROM reports r
            JOIN users u_reporter ON r.reporter_id = u_reporter.id
            JOIN users u_reported ON r.reported_id = u_reported.id
            WHERE r.status = ?
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([$status]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get reports error: " . $e->getMessage());
        return [];
    }
}

/**
 * Resolve report
 */
function resolveReport($reportId, $status) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("UPDATE reports SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $reportId]);
    } catch (PDOException $e) {
        error_log("Resolve report error: " . $e->getMessage());
        return false;
    }
}

// ==================== QUANTITY MANAGEMENT FUNCTIONS ====================

/**
 * Check available quantity for a listing
 */
function checkAvailableQuantity($listingId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT quantity FROM listings WHERE id = ?");
        $stmt->execute([$listingId]);
        $result = $stmt->fetch();
        return $result ? (int)$result['quantity'] : 0;
    } catch (PDOException $e) {
        error_log("Check quantity error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Update listing quantity
 */
function updateListingQuantity($listingId, $change) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            UPDATE listings 
            SET quantity = GREATEST(0, quantity + ?),
                availability_status = CASE 
                    WHEN (quantity + ?) <= 0 THEN 'unavailable'
                    ELSE 'available'
                END
            WHERE id = ?
        ");
        return $stmt->execute([$change, $change, $listingId]);
    } catch (PDOException $e) {
        error_log("Update quantity error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get listing with full details including quantity
 */
function getListingWithQuantity($listingId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT l.*, b.title, b.author, b.cover_image, b.description, b.category,
                   u.firstname, u.lastname, u.role, u.trust_score, u.average_rating
            FROM listings l
            JOIN books b ON l.book_id = b.id
            JOIN users u ON l.user_id = u.id
            WHERE l.id = ?
        ");
        $stmt->execute([$listingId]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Get listing error: " . $e->getMessage());
        return null;
    }
}

// ==================== ENHANCED USER STATS ====================

/**
 * Get enhanced user statistics with credits and trust
 */
function getUserStatsEnhanced($userId) {
    try {
        $pdo = getDBConnection();
        
        // Get user data
        $stmt = $pdo->prepare("
            SELECT credits, trust_score, average_rating, total_ratings, 
                   total_lends, total_borrows, late_returns
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        // Get total listings
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM listings WHERE user_id = ?");
        $stmt->execute([$userId]);
        $listings = $stmt->fetch()['total'];
        
        // Get active borrows
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM transactions 
            WHERE borrower_id = ? AND status IN ('active', 'approved')
        ");
        $stmt->execute([$userId]);
        $activeBorrows = $stmt->fetch()['total'];
        
        // Get pending requests (as lender)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM transactions 
            WHERE lender_id = ? AND status = 'requested'
        ");
        $stmt->execute([$userId]);
        $pendingRequests = $stmt->fetch()['total'];
        
        return array_merge($user ?: [], [
            'total_listings' => $listings,
            'active_borrows' => $activeBorrows,
            'pending_requests' => $pendingRequests,
            'trust_rating' => getTrustScoreRating($user['trust_score'] ?? 50)
        ]);
        
    } catch (PDOException $e) {
        error_log("Get enhanced stats error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get library/bookstore specific stats
 */
function getStoreStats($userId) {
    try {
        $pdo = getDBConnection();
        
        // Total inventory (sum of all quantities)
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(quantity), 0) as total_inventory,
                   COUNT(*) as unique_titles
            FROM listings 
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $inventory = $stmt->fetch();
        
        // Currently lent books
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as lent 
            FROM transactions t
            JOIN listings l ON t.listing_id = l.id
            WHERE l.user_id = ? AND t.status IN ('active', 'approved')
        ");
        $stmt->execute([$userId]);
        $lent = $stmt->fetch()['lent'];
        
        // Low stock items (quantity < 3)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as low_stock
            FROM listings 
            WHERE user_id = ? AND quantity < 3 AND quantity > 0
        ");
        $stmt->execute([$userId]);
        $lowStock = $stmt->fetch()['low_stock'];
        
        // Out of stock items
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as out_stock
            FROM listings 
            WHERE user_id = ? AND quantity = 0
        ");
        $stmt->execute([$userId]);
        $outStock = $stmt->fetch()['out_stock'];
        
        return [
            'total_inventory' => $inventory['total_inventory'],
            'unique_titles' => $inventory['unique_titles'],
            'currently_lent' => $lent,
            'low_stock_items' => $lowStock,
            'out_of_stock_items' => $outStock
        ];
        
    } catch (PDOException $e) {
        error_log("Get store stats error: " . $e->getMessage());
        return [];
    }
}

/**
 * Mark notification as read
 */
function markNotificationAsRead($notificationId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
        return $stmt->execute([$notificationId]);
    } catch (Exception $e) {
        error_log("Mark notification read error: " . $e->getMessage());
        return false;
    }
}

/**
 * Mark all notifications as read for a user
 */
function markAllNotificationsAsRead($userId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        return $stmt->execute([$userId]);
    } catch (Exception $e) {
        error_log("Mark all notifications read error: " . $e->getMessage());
        return false;
    }
}

// ==================== DELIVERY & SMART ASSIGN FUNCTIONS ====================

/**
 * Calculate distance between two points in KM
 */
function getDistanceKM($lat1, $lng1, $lat2, $lng2) {
    if (!$lat1 || !$lng1 || !$lat2 || !$lng2) return 9999;
    $earth_radius = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2) * sin($dLng/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earth_radius * $c;
}

/**
 * Check if any delivery agent is available for a route
 */
function checkDeliveryAvailability($pickupLat, $pickupLng, $dropoffLat, $dropoffLng) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT id, service_start_lat, service_start_lng, service_end_lat, service_end_lng 
            FROM users 
            WHERE role = 'delivery_agent' AND is_accepting_deliveries = 1 AND is_banned = 0
        ");
        $stmt->execute();
        $agents = $stmt->fetchAll();

        foreach ($agents as $agent) {
            // Check if both pickup and dropoff are within 10km of either endpoint of agent's route
            $distP1 = getDistanceKM($pickupLat, $pickupLng, $agent['service_start_lat'], $agent['service_start_lng']);
            $distP2 = getDistanceKM($pickupLat, $pickupLng, $agent['service_end_lat'], $agent['service_end_lng']);
            
            $distD1 = getDistanceKM($dropoffLat, $dropoffLng, $agent['service_start_lat'], $agent['service_start_lng']);
            $distD2 = getDistanceKM($dropoffLat, $dropoffLng, $agent['service_end_lat'], $agent['service_end_lng']);

            $canPickup = ($distP1 < 10 || $distP2 < 10);
            $canDropoff = ($distD1 < 10 || $distD2 < 10);

            if ($canPickup && $canDropoff) return true;
        }
        return false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Smart Assign an agent to a transaction
 */
function assignDeliveryAgent($transactionId) {
    try {
        $pdo = getDBConnection();
        
        // 1. Get transaction info
        $stmt = $pdo->prepare("
            SELECT t.*, l.lat as pickup_lat, l.lng as pickup_lng 
            FROM transactions t
            JOIN listings l ON t.listing_id = l.id
            WHERE t.id = ?
        ");
        $stmt->execute([$transactionId]);
        $t = $stmt->fetch();

        if (!$t || $t['delivery_method'] !== 'delivery') return false;

        // 2. Find best agent
        $stmt = $pdo->prepare("
            SELECT id, service_start_lat, service_start_lng, service_end_lat, service_end_lng, firstname
            FROM users 
            WHERE role = 'delivery_agent' AND is_accepting_deliveries = 1 AND is_banned = 0
        ");
        $stmt->execute();
        $agents = $stmt->fetchAll();

        $bestAgentId = null;
        $minDist = 9999;

        foreach ($agents as $agent) {
            $distP = min(
                getDistanceKM($t['pickup_lat'], $t['pickup_lng'], $agent['service_start_lat'], $agent['service_start_lng']),
                getDistanceKM($t['pickup_lat'], $t['pickup_lng'], $agent['service_end_lat'], $agent['service_end_lng'])
            );
            $distD = min(
                getDistanceKM($t['order_lat'], $t['order_lng'], $agent['service_start_lat'], $agent['service_start_lng']),
                getDistanceKM($t['order_lat'], $t['order_lng'], $agent['service_end_lat'], $agent['service_end_lng'])
            );

            if ($distP < 15 && $distD < 15) { // Threshold for candidate
                $totalDist = $distP + $distD;
                if ($totalDist < $minDist) {
                    $minDist = $totalDist;
                    $bestAgentId = $agent['id'];
                }
            }
        }

        if ($bestAgentId) {
            $pdo->prepare("UPDATE transactions SET delivery_agent_id = ?, status = 'assigned' WHERE id = ?")
                ->execute([$bestAgentId, $transactionId]);
            
            // Notify agent
            $pdo->prepare("INSERT INTO notifications (user_id, type, message, reference_id) VALUES (?, 'delivery_assigned', ?, ?)")
                ->execute([$bestAgentId, "New delivery task assigned! Travel to pickup location.", $transactionId]);
            
            return true;
        }

        return false;
    } catch (PDOException $e) {
        error_log("Assign agent error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if delivery is available for a given pickup location
 * Returns true if any active delivery agent can cover this area
 */
/**
 * Check if delivery is available for a given pickup location
 * Returns true if any active delivery agent can cover this area
 */
function checkDeliveryServiceAvailability($pickupLat, $pickupLng, $district = null) {
    try {
        $pdo = getDBConnection();
        
        // Get all active delivery agents
        $stmt = $pdo->prepare("
            SELECT id, service_start_lat, service_start_lng, service_end_lat, service_end_lng, district
            FROM users 
            WHERE role = 'delivery_agent' 
            AND is_accepting_deliveries = 1
        ");
        $stmt->execute();
        $agents = $stmt->fetchAll();
        
        foreach ($agents as $agent) {
            // Check if agent's district matches (if both have district set)
            if ($district && $agent['district'] && $agent['district'] === $district) {
                return true;
            }
            
            // Check distance from pickup to agent's service start point
            if ($agent['service_start_lat'] && $agent['service_start_lng']) {
                $distStart = getDistanceKM(
                    $pickupLat, 
                    $pickupLng, 
                    $agent['service_start_lat'], 
                    $agent['service_start_lng']
                );
                
                if ($distStart < 25) {
                    return true;
                }
            }
            
            // Check distance from pickup to agent's service end point
            if ($agent['service_end_lat'] && $agent['service_end_lng']) {
                $distEnd = getDistanceKM(
                    $pickupLat, 
                    $pickupLng, 
                    $agent['service_end_lat'], 
                    $agent['service_end_lng']
                );
                
                if ($distEnd < 25) {
                    return true;
                }
            }
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Check delivery availability error: " . $e->getMessage());
        return false;
    }
}
?>