<?php
/**
 * Chatbot Builder System - Bot Management API
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Chatbot.php';
require_once __DIR__ . '/../src/JWT.php';

use Chatbot\Config;
use Chatbot\Database;
use Chatbot\Chatbot;
use Chatbot\JWT;

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$method = $_SERVER['REQUEST_METHOD'];

// Get action from URL or method
$id = $_GET['id'] ?? null;
$action = $_GET['action'] ?? null;

try {
    $db = Database::getInstance();
    
    // Verify authentication
    $userId = JWT::getUserId();
    if (!$userId) {
        throw new Exception('Not authenticated');
    }
    
    $response = match($method) {
        'GET' => $id ? handleGetOne($db, $id, $userId) : handleGetAll($db, $userId, $_GET),
        'POST' => handleCreate($db, $input, $userId),
        'PUT' => handleUpdate($db, $id, $input, $userId),
        'DELETE' => handleDelete($db, $id, $userId),
        default => throw new Exception('Invalid method')
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
 * Get all bots for user
 */
function handleGetAll(Database $db, int $userId, array $params): array {
    $page = (int) ($params['page'] ?? 1);
    $perPage = (int) ($params['per_page'] ?? 20);
    $status = $params['status'] ?? null;
    
    $sql = "SELECT id, name, description, status, unique_id, primary_color, 
                   welcome_message, created_at, updated_at 
            FROM chatbots WHERE user_id = ?";
    $queryParams = [$userId];
    
    if ($status) {
        $sql .= " AND status = ?";
        $queryParams[] = $status;
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    $result = $db->paginate($sql, $queryParams, $page, $perPage);
    
    // Add stats for each bot
    foreach ($result['data'] as &$bot) {
        $bot['stats'] = [
            'total_chats' => $db->fetchColumn(
                "SELECT COUNT(*) FROM conversations WHERE bot_id = ?",
                [$bot['id']]
            ),
            'total_leads' => $db->fetchColumn(
                "SELECT COUNT(*) FROM leads WHERE bot_id = ?",
                [$bot['id']]
            ),
            'active_chats' => $db->fetchColumn(
                "SELECT COUNT(*) FROM conversations WHERE bot_id = ? AND status = 'active'",
                [$bot['id']]
            )
        ];
    }
    
    return $result;
}

/**
 * Get single bot
 */
function handleGetOne(Database $db, $id, int $userId): array {
    $chatbot = new Chatbot($db);
    
    if (is_numeric($id)) {
        $chatbot->loadById((int) $id);
    } else {
        $chatbot->load($id);
    }
    
    $botData = $chatbot->getData();
    
    if (!$botData || $botData['user_id'] != $userId) {
        throw new Exception('Bot not found');
    }
    
    // Get flows
    $flows = $db->fetchAll(
        "SELECT id, name, trigger_type, is_active, is_default, priority, created_at 
         FROM flows WHERE bot_id = ? ORDER BY priority DESC",
        [$botData['id']]
    );
    
    return [
        'bot' => $botData,
        'settings' => $chatbot->getSettings(),
        'theme' => $chatbot->getThemeConfig(),
        'flows' => $flows,
        'embed_code' => $chatbot->getEmbedCode()
    ];
}

/**
 * Create new bot
 */
function handleCreate(Database $db, array $input, int $userId): array {
    $chatbot = new Chatbot($db);
    
    $data = [
        'name' => $input['name'] ?? 'New Chatbot',
        'description' => $input['description'] ?? null,
        'welcome_message' => $input['welcome_message'] ?? 'Hi there! How can I help you today?',
        'fallback_message' => $input['fallback_message'] ?? "I didn't understand that. Could you rephrase?",
        'position' => $input['position'] ?? 'bottom-right',
        'primary_color' => $input['primary_color'] ?? '#6366f1',
        'secondary_color' => $input['secondary_color'] ?? '#8b5cf6',
        'font_family' => $input['font_family'] ?? 'Inter',
        'border_radius' => $input['border_radius'] ?? 20,
        'settings' => $input['settings'] ?? [],
        'theme' => $input['theme'] ?? []
    ];
    
    $bot = $chatbot->create($data, $userId);
    
    // Create default welcome flow
    $db->insert('flows', [
        'bot_id' => $bot['id'],
        'name' => 'Welcome Flow',
        'description' => 'Default welcome conversation',
        'nodes_json' => json_encode([
            [
                'id' => 'welcome',
                'type' => 'message',
                'data' => [
                    'message' => $data['welcome_message'],
                    'is_start' => true
                ],
                'position' => ['x' => 100, 'y' => 100]
            ]
        ]),
        'connections_json' => json_encode([]),
        'trigger_type' => 'welcome',
        'is_active' => true,
        'is_default' => true,
        'priority' => 1,
        'created_by' => $userId,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    return $bot;
}

/**
 * Update bot
 */
function handleUpdate(Database $db, $id, array $input, int $userId): array {
    $chatbot = new Chatbot($db);
    
    if (is_numeric($id)) {
        $chatbot->loadById((int) $id);
    } else {
        $chatbot->load($id);
    }
    
    $botData = $chatbot->getData();
    
    if (!$botData || $botData['user_id'] != $userId) {
        throw new Exception('Bot not found');
    }
    
    $updateData = [];
    
    $fields = [
        'name', 'description', 'welcome_message', 'fallback_message', 'offline_message',
        'position', 'primary_color', 'secondary_color', 'font_family', 'border_radius',
        'shadow_intensity', 'background_blur', 'custom_css', 'sound_enabled',
        'typing_indicator_enabled', 'file_upload_enabled', 'max_file_size',
        'allowed_file_types', 'ai_enabled', 'webhook_url', 'zapier_enabled', 'status'
    ];
    
    foreach ($fields as $field) {
        if (isset($input[$field])) {
            $updateData[$field] = $input[$field];
        }
    }
    
    if (isset($input['settings'])) {
        $updateData['settings'] = $input['settings'];
    }
    
    if (isset($input['theme'])) {
        $updateData['theme'] = $input['theme'];
    }
    
    if (isset($input['business_hours'])) {
        $updateData['business_hours'] = $input['business_hours'];
    }
    
    if (isset($input['ai_config'])) {
        $updateData['ai_config'] = $input['ai_config'];
    }
    
    $chatbot->update($botData['id'], $updateData);
    
    return $chatbot->getData();
}

/**
 * Delete bot
 */
function handleDelete(Database $db, $id, int $userId): array {
    $chatbot = new Chatbot($db);
    
    if (is_numeric($id)) {
        $chatbot->loadById((int) $id);
    } else {
        $chatbot->load($id);
    }
    
    $botData = $chatbot->getData();
    
    if (!$botData || $botData['user_id'] != $userId) {
        throw new Exception('Bot not found');
    }
    
    $chatbot->delete($botData['id']);
    
    return ['deleted' => true];
}
