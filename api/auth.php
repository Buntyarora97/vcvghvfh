<?php
/**
 * Chatbot Builder System - Authentication API
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/JWT.php';

use Chatbot\Config;
use Chatbot\Database;
use Chatbot\JWT;

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? $_GET['action'] ?? 'login';

try {
    $db = Database::getInstance();
    
    $response = match($action) {
        'login' => handleLogin($db, $input),
        'register' => handleRegister($db, $input),
        'logout' => handleLogout($db, $input),
        'refresh' => handleRefresh($db, $input),
        'me' => handleMe($db, $input),
        'forgot_password' => handleForgotPassword($db, $input),
        'reset_password' => handleResetPassword($db, $input),
        default => throw new Exception('Invalid action')
    };
    
    echo json_encode([
        'success' => true,
        'data' => $response
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Handle login
 */
function handleLogin(Database $db, array $input): array {
    $email = $input['email'] ?? '';
    $password = $input['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        throw new Exception('Email and password are required');
    }
    
    $user = $db->fetchOne("SELECT * FROM users WHERE email = ? AND status = 'active'", [$email]);
    
    if (!$user || !password_verify($password, $user['password'])) {
        throw new Exception('Invalid credentials');
    }
    
    // Update last login
    $db->update('users', ['last_login' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);
    
    // Generate JWT token
    $token = JWT::generate([
        'user_id' => $user['id'],
        'email' => $user['email'],
        'name' => $user['name'],
        'role' => $user['role']
    ]);
    
    return [
        'token' => $token,
        'user' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'role' => $user['role'],
            'avatar' => $user['avatar'],
            'timezone' => $user['timezone'],
            'language' => $user['language']
        ]
    ];
}

/**
 * Handle registration
 */
function handleRegister(Database $db, array $input): array {
    $email = $input['email'] ?? '';
    $password = $input['password'] ?? '';
    $name = $input['name'] ?? '';
    
    if (empty($email) || empty($password) || empty($name)) {
        throw new Exception('All fields are required');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address');
    }
    
    if (strlen($password) < 8) {
        throw new Exception('Password must be at least 8 characters');
    }
    
    // Check if email exists
    if ($db->exists('users', 'email = ?', [$email])) {
        throw new Exception('Email already registered');
    }
    
    // Create user
    $userData = [
        'email' => $email,
        'password' => password_hash($password, PASSWORD_BCRYPT),
        'name' => $name,
        'role' => 'owner',
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $userId = $db->insert('users', $userData);
    
    // Generate token
    $token = JWT::generate([
        'user_id' => $userId,
        'email' => $email,
        'name' => $name,
        'role' => 'owner'
    ]);
    
    return [
        'token' => $token,
        'user' => [
            'id' => $userId,
            'email' => $email,
            'name' => $name,
            'role' => 'owner'
        ]
    ];
}

/**
 * Handle logout
 */
function handleLogout(Database $db, array $input): array {
    // In a more complex system, you might invalidate the token
    return ['status' => 'logged_out'];
}

/**
 * Handle token refresh
 */
function handleRefresh(Database $db, array $input): array {
    $token = $input['token'] ?? '';
    
    if (empty($token)) {
        throw new Exception('Token is required');
    }
    
    $newToken = JWT::refresh($token);
    
    if (!$newToken) {
        throw new Exception('Invalid or expired token');
    }
    
    return ['token' => $newToken];
}

/**
 * Handle get current user
 */
function handleMe(Database $db, array $input): array {
    $userId = JWT::getUserId();
    
    if (!$userId) {
        throw new Exception('Not authenticated');
    }
    
    $user = $db->fetchOne("SELECT id, email, name, role, avatar, timezone, language, status FROM users WHERE id = ?", [$userId]);
    
    if (!$user) {
        throw new Exception('User not found');
    }
    
    return ['user' => $user];
}

/**
 * Handle forgot password
 */
function handleForgotPassword(Database $db, array $input): array {
    $email = $input['email'] ?? '';
    
    if (empty($email)) {
        throw new Exception('Email is required');
    }
    
    $user = $db->fetchOne("SELECT * FROM users WHERE email = ?", [$email]);
    
    if (!$user) {
        // Don't reveal if email exists
        return ['status' => 'email_sent'];
    }
    
    // Generate reset token
    $resetToken = bin2hex(random_bytes(32));
    $resetExpiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // Store reset token (would need a password_resets table in production)
    // For now, just return success
    
    // Send email (would integrate with email service)
    // mail($email, 'Password Reset', 'Your reset link: ...');
    
    return ['status' => 'email_sent'];
}

/**
 * Handle reset password
 */
function handleResetPassword(Database $db, array $input): array {
    $token = $input['token'] ?? '';
    $password = $input['password'] ?? '';
    
    if (empty($token) || empty($password)) {
        throw new Exception('Token and password are required');
    }
    
    if (strlen($password) < 8) {
        throw new Exception('Password must be at least 8 characters');
    }
    
    // Verify token and update password
    // Would need to check password_resets table
    
    return ['status' => 'password_reset'];
}
