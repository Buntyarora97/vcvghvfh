<?php
/**
 * Chatbot Builder System - Main Entry Point
 */

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load configuration
require_once __DIR__ . '/src/Config.php';

use Chatbot\Config;

// API Router
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Remove query string
$requestUri = parse_url($requestUri, PHP_URL_PATH);

// Remove base path
$basePath = dirname($_SERVER['SCRIPT_NAME']);
$requestUri = substr($requestUri, strlen($basePath));

// Route to appropriate API endpoint
$routes = [
    '/api/auth' => 'api/auth.php',
    '/api/chat' => 'api/chat.php',
    '/api/bots' => 'api/bots.php',
    '/api/flows' => 'api/flows.php',
    '/api/upload' => 'api/upload.php',
    '/api/analytics' => 'api/analytics.php',
    '/api/widget-config' => 'api/widget-config.php',
];

// Check if route exists
if (isset($routes[$requestUri])) {
    require_once __DIR__ . '/' . $routes[$requestUri];
    exit;
}

// Serve widget.js
if ($requestUri === '/widget.js') {
    header('Content-Type: application/javascript');
    readfile(__DIR__ . '/widget.js');
    exit;
}

// Serve admin panel
if (strpos($requestUri, '/admin') === 0) {
    $adminFile = __DIR__ . '/admin/dist' . substr($requestUri, 6);
    if (empty(substr($requestUri, 6))) {
        $adminFile = __DIR__ . '/admin/dist/index.html';
    }
    
    if (file_exists($adminFile)) {
        $ext = pathinfo($adminFile, PATHINFO_EXTENSION);
        $contentTypes = [
            'html' => 'text/html',
            'js' => 'application/javascript',
            'css' => 'text/css',
            'json' => 'application/json',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
        ];
        
        if (isset($contentTypes[$ext])) {
            header('Content-Type: ' . $contentTypes[$ext]);
        }
        
        readfile($adminFile);
        exit;
    }
}

// Default response
http_response_code(404);
echo json_encode([
    'success' => false,
    'error' => 'Endpoint not found',
    'available_endpoints' => array_keys($routes)
]);
