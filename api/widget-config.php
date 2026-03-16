<?php
/**
 * Chatbot Builder System - Widget Configuration API
 * Returns widget settings for embed
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Chatbot.php';

use Chatbot\Config;
use Chatbot\Database;
use Chatbot\Chatbot;

$botId = $_GET['id'] ?? '';

if (empty($botId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Bot ID is required']);
    exit;
}

try {
    $db = Database::getInstance();
    $chatbot = new Chatbot($db);
    
    if (!$chatbot->load($botId)) {
        throw new Exception('Chatbot not found');
    }
    
    $botData = $chatbot->getData();
    $settings = $chatbot->getSettings();
    $theme = $chatbot->getThemeConfig();
    
    // Build widget configuration
    $config = [
        'bot_id' => $botData['unique_id'],
        'bot_name' => $botData['name'],
        'welcome_message' => $botData['welcome_message'],
        'fallback_message' => $botData['fallback_message'],
        'offline_message' => $botData['offline_message'],
        'position' => $botData['position'],
        'auto_popup_delay' => (int) $botData['auto_popup_delay'],
        'primary_color' => $botData['primary_color'],
        'secondary_color' => $botData['secondary_color'],
        'font_family' => $botData['font_family'],
        'border_radius' => (int) $botData['border_radius'],
        'shadow_intensity' => (int) $botData['shadow_intensity'],
        'background_blur' => (bool) $botData['background_blur'],
        'sound_enabled' => (bool) $botData['sound_enabled'],
        'typing_indicator' => (bool) $botData['typing_indicator_enabled'],
        'file_upload' => (bool) $botData['file_upload_enabled'],
        'max_file_size' => (int) $botData['max_file_size'],
        'allowed_file_types' => explode(',', $botData['allowed_file_types']),
        'ai_enabled' => (bool) $botData['ai_enabled'],
        'theme' => $theme,
        'settings' => $settings,
        'api_url' => Config::getUrl('api/'),
        'websocket_url' => 'wss://' . $_SERVER['HTTP_HOST'] . ':8080'
    ];
    
    echo json_encode([
        'success' => true,
        'config' => $config
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
