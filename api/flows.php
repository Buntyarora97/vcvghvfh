<?php
/**
 * Chatbot Builder System - Flows API
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
require_once __DIR__ . '/../src/FlowEngine.php';
require_once __DIR__ . '/../src/JWT.php';

use Chatbot\Config;
use Chatbot\Database;
use Chatbot\Chatbot;
use Chatbot\FlowEngine;
use Chatbot\JWT;

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$method = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null;

try {
    $db = Database::getInstance();
    
    // Verify authentication
    $userId = JWT::getUserId();
    if (!$userId) {
        throw new Exception('Not authenticated');
    }
    
    $response = match($method) {
        'GET' => $id ? handleGetOne($db, $id, $userId) : handleGetAll($db, $_GET, $userId),
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
 * Get all flows
 */
function handleGetAll(Database $db, array $params, int $userId): array {
    $botId = $params['bot_id'] ?? 0;
    
    // Verify bot ownership
    $bot = $db->fetchOne("SELECT * FROM chatbots WHERE id = ? AND user_id = ?", [$botId, $userId]);
    if (!$bot) {
        throw new Exception('Bot not found');
    }
    
    $flows = $db->fetchAll(
        "SELECT id, name, description, trigger_type, trigger_value, is_active, 
                is_default, priority, created_at, updated_at 
         FROM flows WHERE bot_id = ? ORDER BY priority DESC, created_at DESC",
        [$botId]
    );
    
    return ['flows' => $flows];
}

/**
 * Get single flow
 */
function handleGetOne(Database $db, int $id, int $userId): array {
    $flow = $db->fetchOne(
        "SELECT f.*, b.user_id as bot_owner_id 
         FROM flows f 
         JOIN chatbots b ON f.bot_id = b.id 
         WHERE f.id = ?",
        [$id]
    );
    
    if (!$flow || $flow['bot_owner_id'] != $userId) {
        throw new Exception('Flow not found');
    }
    
    // Decode JSON fields
    $flow['nodes'] = json_decode($flow['nodes_json'], true);
    $flow['connections'] = json_decode($flow['connections_json'], true);
    $flow['variables'] = json_decode($flow['variables'], true);
    $flow['conditions'] = json_decode($flow['conditions'], true);
    
    unset($flow['nodes_json'], $flow['connections_json']);
    
    return $flow;
}

/**
 * Create new flow
 */
function handleCreate(Database $db, array $input, int $userId): array {
    $botId = $input['bot_id'] ?? 0;
    
    // Verify bot ownership
    $bot = $db->fetchOne("SELECT * FROM chatbots WHERE id = ? AND user_id = ?", [$botId, $userId]);
    if (!$bot) {
        throw new Exception('Bot not found');
    }
    
    $chatbot = new Chatbot($db);
    $chatbot->loadById($botId);
    
    $flowEngine = new FlowEngine($db, $chatbot);
    
    $flow = $flowEngine->saveFlow(
        $botId,
        $input['name'] ?? 'New Flow',
        $input['nodes'] ?? [],
        $input['connections'] ?? [],
        [
            'description' => $input['description'] ?? null,
            'trigger_type' => $input['trigger_type'] ?? 'welcome',
            'trigger_value' => $input['trigger_value'] ?? null,
            'is_active' => $input['is_active'] ?? true,
            'is_default' => $input['is_default'] ?? false,
            'priority' => $input['priority'] ?? 0,
            'variables' => $input['variables'] ?? [],
            'conditions' => $input['conditions'] ?? []
        ],
        $userId
    );
    
    return $flow;
}

/**
 * Update flow
 */
function handleUpdate(Database $db, int $id, array $input, int $userId): array {
    $flow = $db->fetchOne(
        "SELECT f.*, b.user_id as bot_owner_id 
         FROM flows f 
         JOIN chatbots b ON f.bot_id = b.id 
         WHERE f.id = ?",
        [$id]
    );
    
    if (!$flow || $flow['bot_owner_id'] != $userId) {
        throw new Exception('Flow not found');
    }
    
    $chatbot = new Chatbot($db);
    $chatbot->loadById($flow['bot_id']);
    
    $flowEngine = new FlowEngine($db, $chatbot);
    
    $flow = $flowEngine->saveFlow(
        $flow['bot_id'],
        $input['name'] ?? $flow['name'],
        $input['nodes'] ?? json_decode($flow['nodes_json'], true),
        $input['connections'] ?? json_decode($flow['connections_json'], true),
        [
            'id' => $id,
            'description' => $input['description'] ?? $flow['description'],
            'trigger_type' => $input['trigger_type'] ?? $flow['trigger_type'],
            'trigger_value' => $input['trigger_value'] ?? $flow['trigger_value'],
            'is_active' => $input['is_active'] ?? $flow['is_active'],
            'is_default' => $input['is_default'] ?? $flow['is_default'],
            'priority' => $input['priority'] ?? $flow['priority'],
            'variables' => $input['variables'] ?? json_decode($flow['variables'], true),
            'conditions' => $input['conditions'] ?? json_decode($flow['conditions'], true)
        ],
        $userId
    );
    
    return $flow;
}

/**
 * Delete flow
 */
function handleDelete(Database $db, int $id, int $userId): array {
    $flow = $db->fetchOne(
        "SELECT f.*, b.user_id as bot_owner_id 
         FROM flows f 
         JOIN chatbots b ON f.bot_id = b.id 
         WHERE f.id = ?",
        [$id]
    );
    
    if (!$flow || $flow['bot_owner_id'] != $userId) {
        throw new Exception('Flow not found');
    }
    
    $chatbot = new Chatbot($db);
    $chatbot->loadById($flow['bot_id']);
    
    $flowEngine = new FlowEngine($db, $chatbot);
    $flowEngine->deleteFlow($id);
    
    return ['deleted' => true];
}
