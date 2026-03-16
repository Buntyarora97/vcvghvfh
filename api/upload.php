<?php
/**
 * Chatbot Builder System - File Upload API
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/FileManager.php';

use Chatbot\Config;
use Chatbot\Database;
use Chatbot\FileManager;

try {
    if (empty($_FILES['file'])) {
        throw new Exception('No file uploaded');
    }
    
    $botId = $_POST['bot_id'] ?? 0;
    $conversationId = $_POST['conversation_id'] ?? null;
    
    $db = Database::getInstance();
    $fileManager = new FileManager($db);
    
    $file = $fileManager->upload($_FILES['file'], (int) $botId, $conversationId ? (int) $conversationId : null);
    
    echo json_encode([
        'success' => true,
        'file' => [
            'id' => $file['id'],
            'name' => $file['original_name'],
            'url' => $fileManager->getFileUrl($file),
            'thumbnail' => $fileManager->getThumbnailUrl($file),
            'size' => $file['file_size'],
            'type' => $file['file_type'],
            'size_formatted' => $fileManager->formatBytes($file['file_size'])
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
