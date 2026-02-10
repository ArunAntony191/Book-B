<?php

/**
 * Sanitize input data recursively
 * 
 * @param mixed $data Input data (string or array)
 * @return mixed Sanitized data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = sanitizeInput($value);
        }
    } else {
        $data = trim($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
    return $data;
}

/**
 * Validate generic ID (positive integer)
 * 
 * @param mixed $id
 * @return int|false Returns the ID as int if valid, false otherwise
 */
function validateId($id) {
    if (is_numeric($id) && (int)$id > 0) {
        return (int)$id;
    }
    return false;
}

/**
 * Validate email address
 * 
 * @param string $email
 * @return string|false
 */
function validateEmail($email) {
    $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $email;
    }
    return false;
}

/**
 * Validate phone number
 * Supports: +1234567890, 1234567890, 123-456-7890
 * 
 * @param string $phone
 * @return string|false
 */
function validatePhone($phone) {
    // Remove common separators
    $cleanPhone = preg_replace('/[\s\-\(\)\.]/', '', $phone);
    
    // Check for 10-15 digits, optionally starting with +
    if (preg_match('/^\+?[0-9]{10,15}$/', $cleanPhone)) {
        return $cleanPhone; // Return sanitized version for DB
    }
    return false;
}

/**
 * Validate password strength
 * 
 * @param string $password
 * @return array ['valid' => bool, 'message' => string]
 */
function validatePassword($password) {
    if (strlen($password) < 8) {
        return ['valid' => false, 'message' => 'Password must be at least 8 characters long.'];
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return ['valid' => false, 'message' => 'Password must contain at least one uppercase letter.'];
    }
    if (!preg_match('/[a-z]/', $password)) {
        return ['valid' => false, 'message' => 'Password must contain at least one lowercase letter.'];
    }
    if (!preg_match('/[0-9]/', $password)) {
        return ['valid' => false, 'message' => 'Password must contain at least one number.'];
    }
    // Optional: Special chars
    // if (!preg_match('/[^A-Za-z0-9]/', $password)) { ... }
    
    return ['valid' => true, 'message' => 'Valid'];
}

/**
 * Validate Date (Y-m-d)
 * 
 * @param string $date
 * @return string|false
 */
function validateDate($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return ($d && $d->format('Y-m-d') === $date) ? $date : false;
}

/**
 * Validate Name (Letters, spaces, hyphens, apostrophes)
 * 
 * @param string $name
 * @return string|false
 */
function validateName($name) {
    $name = trim($name);
    if (preg_match("/^[A-Za-z\s'\-]{2,50}$/", $name)) {
        return $name;
    }
    return false;
}

/**
 * Check required fields
 * 
 * @param array $data Source array (usually $_POST)
 * @param array $fields List of required field names
 * @return array List of missing field names
 */
function checkRequiredFields($data, $fields) {
    $missing = [];
    foreach ($fields as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            $missing[] = $field;
        }
    }
    return $missing;
}
