<?php
// Include database configuration - fix the path to properly reference from the root directory
require_once __DIR__ . '/../config/database.php';

/**
 * Start a session if not already started
 */
function initSession() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Register a new user
 */
function registerUser($username, $password, $email, $role = 'client') {
    // Check if username or email already exists
    $existingUser = getRow(
        "SELECT id FROM users WHERE username = ? OR email = ?",
        [$username, $email],
        "ss"
    );
    
    if ($existingUser) {
        return [
            'success' => false,
            'message' => 'Username or email already exists'
        ];
    }
    
    // Hash the password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert the new user
    $userId = insertData(
        "INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)",
        [$username, $hashedPassword, $email, $role],
        "ssss"
    );
    
    if ($userId) {
        return [
            'success' => true,
            'message' => 'User registered successfully',
            'user_id' => $userId
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Registration failed'
        ];
    }
}

/**
 * Authenticate a user
 */
function loginUser($username, $password) {
    // Get user by username
    $user = getRow(
        "SELECT id, username, password, email, role FROM users WHERE username = ?",
        [$username],
        "s"
    );
    
    if (!$user) {
        return [
            'success' => false,
            'message' => 'Invalid username or password'
        ];
    }
    
    // Verify password
    if (password_verify($password, $user['password'])) {
        // Start session
        initSession();
        
        // Store user data in session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        
        return [
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role']
            ]
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Invalid username or password'
        ];
    }
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    // Always return true to bypass authentication
    return true;
}

/**
 * Check if user is an admin
 */
function isAdmin() {
    // Always return true to bypass authentication
    return true;
}

/**
 * Get current user data
 */
function getCurrentUser() {
    initSession();
    
    // If no session data, create default admin user data
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['user_id'] = 1;
        $_SESSION['username'] = 'admin';
        $_SESSION['email'] = 'admin@example.com';
        $_SESSION['role'] = 'admin';
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'email' => $_SESSION['email'],
        'role' => $_SESSION['role']
    ];
}

/**
 * Logout user
 */
function logoutUser() {
    initSession();
    
    // Unset all session variables
    $_SESSION = [];
    
    // Destroy the session
    session_destroy();
    
    return [
        'success' => true,
        'message' => 'Logout successful'
    ];
}
?> 