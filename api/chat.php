<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Chatbot.php';
require_once __DIR__ . '/../src/FlowEngine.php';
require_once __DIR__ . '/../src/AIHandler.php';
require_once __DIR__ . '/../src/FileManager.php';

use Chatbot\Config;
use Chatbot\Database;
use Chatbot\Chatbot;
use Chatbot\FlowEngine;
use Chatbot\AIHandler;
use Chatbot\FileManager;

// Rate limiting check
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateKey = 'rate_limit_' . md5($ip);

// Initialize database
$db = Database::getInstance();

// Get request data
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST ?? [];
$action = $input['action'] ?? $_GET['action'] ?? 'init';
$botId = $input['bot_id'] ?? $_GET['bot_id'] ?? '';

try {
    // Load chatbot
    $chatbot = new Chatbot($db);
    
    if (!$chatbot->load($botId)) {
        throw new Exception('Chatbot not found or inactive');
    }
    
    $response = match($action) {
        'init' => handleInit($chatbot, $input, $db),
        'send_message' => handleSendMessage($chatbot, $input, $db),
        'get_messages' => handleGetMessages($chatbot, $input, $db),
        'upload_file' => handleUploadFile($chatbot, $input, $db),
        'typing' => handleTyping($chatbot, $input),
        'close_chat' => handleCloseChat($chatbot, $input, $db),
        'submit_rating' => handleSubmitRating($chatbot, $input, $db),
        'save_lead' => handleSaveLead($chatbot, $input, $db),
        'quick_reply' => handleQuickReply($chatbot, $input, $db),
        'request_human' => handleRequestHuman($chatbot, $input, $db),
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
 * Handle init action - Initialize chat session
 */
function handleInit(Chatbot $chatbot, array $input, Database $db): array {
    $visitorHash = $input['visitor_hash'] ?? md5($_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT']);
    
    // Get or create visitor
    $visitorData = [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'browser' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'device_type' => detectDeviceType(),
        'referrer' => $input['referrer'] ?? null,
        'country' => $input['country'] ?? null
    ];
    
    $visitor = $chatbot->getOrCreateVisitor($visitorHash, $visitorData);
    
    // Check for active conversation
    $conversation = $chatbot->getActiveConversation($visitor['id']);
    
    // Get welcome message
    $welcomeMessage = $chatbot->getData()['welcome_message'];
    $isReturnVisitor = $visitor['total_visits'] > 1;
    
    if ($isReturnVisitor) {
        $welcomeMessage = "Welcome back" . ($visitor['name'] ? ", {$visitor['name']}" : "") . "! " . $welcomeMessage;
    }
    
    // If no active conversation, start new one
    if (!$conversation) {
        $conversation = $chatbot->startConversation($visitor['id'], [
            'source_url' => $input['source_url'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'device' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        
        // Add welcome message
        $chatbot->addMessage($conversation['id'], 'text', $welcomeMessage, [
            'sender_type' => 'bot',
            'sender_name' => $chatbot->getData()['name']
        ]);
        
        // Execute welcome flow if exists
        $flowEngine = new FlowEngine($db, $chatbot);
        if ($flowEngine->loadFlowByTrigger('welcome')) {
            $flowResponse = $flowEngine->start(['visitor_name' => $visitor['name']]);
            
            if ($flowResponse['type'] === 'message' && !empty($flowResponse['message'])) {
                $chatbot->addMessage($conversation['id'], 'text', $flowResponse['message'], [
                    'sender_type' => 'bot',
                    'sender_name' => $chatbot->getData()['name'],
                    'quick_replies' => $flowResponse['quick_replies'] ?? null
                ]);
            }
        }
    }
    
    // Get recent messages
    $messages = $chatbot->getMessages($conversation['id'], 50);
    $messages = array_reverse($messages);
    
    // Get bot settings
    $settings = $chatbot->getSettings();
    $theme = $chatbot->getThemeConfig();
    
    return [
        'conversation_id' => $conversation['id'],
        'visitor_id' => $visitor['id'],
        'visitor_name' => $visitor['name'],
        'visitor_email' => $visitor['email'],
        'is_return_visitor' => $isReturnVisitor,
        'messages' => $messages,
        'settings' => $settings,
        'theme' => $theme,
        'bot_name' => $chatbot->getData()['name'],
        'bot_avatar' => $chatbot->getData()['avatar'],
        'business_hours' => $chatbot->isBusinessHours(),
        'welcome_message' => $welcomeMessage
    ];
}

/**
 * Handle send message action
 */
function handleSendMessage(Chatbot $chatbot, array $input, Database $db): array {
    $conversationId = $input['conversation_id'] ?? 0;
    $message = trim($input['message'] ?? '');
    $visitorId = $input['visitor_id'] ?? 0;
    
    if (empty($message)) {
        throw new Exception('Message is required');
    }
    
    // Verify conversation
    $conversation = $chatbot->getConversation($conversationId);
    if (!$conversation || $conversation['visitor_id'] != $visitorId) {
        throw new Exception('Invalid conversation');
    }
    
    // Add user message
    $chatbot->addMessage($conversationId, 'text', $message, [
        'sender_type' => 'user'
    ]);
    
    // Check if AI is enabled
    if ($chatbot->getData()['ai_enabled']) {
        $aiHandler = new AIHandler($db);
        $aiHandler->loadKnowledgeBase($chatbot->getId());
        
        // Get conversation history for context
        $history = $chatbot->getMessages($conversationId, 10);
        
        $aiResponse = $aiHandler->generateResponse($message, [
            'conversation' => $history,
            'system_message' => 'You are a helpful customer support assistant for ' . $chatbot->getData()['name']
        ]);
        
        // Add AI response
        $chatbot->addMessage($conversationId, 'text', $aiResponse, [
            'sender_type' => 'bot',
            'sender_name' => $chatbot->getData()['name']
        ]);
        
        return [
            'message_id' => $conversationId,
            'response' => $aiResponse,
            'type' => 'ai'
        ];
    }
    
    // Check for keyword triggers
    $flowEngine = new FlowEngine($db, $chatbot);
    if ($flowEngine->loadFlowByTrigger('keyword', $message)) {
        $flowResponse = $flowEngine->start();
        
        if ($flowResponse['type'] === 'message') {
            $chatbot->addMessage($conversationId, 'text', $flowResponse['message'], [
                'sender_type' => 'bot',
                'sender_name' => $chatbot->getData()['name'],
                'quick_replies' => $flowResponse['quick_replies'] ?? null
            ]);
        }
        
        return [
            'message_id' => $conversationId,
            'response' => $flowResponse['message'],
            'quick_replies' => $flowResponse['quick_replies'] ?? null,
            'type' => 'flow'
        ];
    }
    
    // Fallback message
    $fallbackMessage = $chatbot->getData()['fallback_message'];
    $chatbot->addMessage($conversationId, 'text', $fallbackMessage, [
        'sender_type' => 'bot',
        'sender_name' => $chatbot->getData()['name']
    ]);
    
    return [
        'message_id' => $conversationId,
        'response' => $fallbackMessage,
        'type' => 'fallback'
    ];
}

/**
 * Handle get messages action
 */
function handleGetMessages(Chatbot $chatbot, array $input, Database $db): array {
    $conversationId = $input['conversation_id'] ?? 0;
    $lastId = $input['last_id'] ?? 0;
    
    $sql = "SELECT * FROM messages WHERE conversation_id = ? AND id > ? ORDER BY id ASC";
    $messages = $db->fetchAll($sql, [$conversationId, $lastId]);
    
    // Mark as read
    $chatbot->markAsRead($conversationId, 'user');
    
    return [
        'messages' => $messages,
        'unread_count' => 0
    ];
}

/**
 * Handle file upload
 */
function handleUploadFile(Chatbot $chatbot, array $input, Database $db): array {
    if (empty($_FILES['file'])) {
        throw new Exception('No file uploaded');
    }
    
    $conversationId = $input['conversation_id'] ?? 0;
    $fileManager = new FileManager($db);
    
    $file = $fileManager->upload($_FILES['file'], $chatbot->getId(), $conversationId);
    
    // Add message with file
    $chatbot->addMessage($conversationId, 'file', 'File uploaded: ' . $file['original_name'], [
        'sender_type' => 'user',
        'file_url' => $fileManager->getFileUrl($file),
        'file_name' => $file['original_name'],
        'file_size' => $file['file_size'],
        'file_type' => $file['file_type']
    ]);
    
    // Acknowledge file
    $chatbot->addMessage($conversationId, 'text', 'Thank you for sharing the file. Our team will review it.', [
        'sender_type' => 'bot',
        'sender_name' => $chatbot->getData()['name']
    ]);
    
    return [
        'file' => $file,
        'file_url' => $fileManager->getFileUrl($file)
    ];
}

/**
 * Handle typing indicator
 */
function handleTyping(Chatbot $chatbot, array $input): array {
    // This would trigger WebSocket event in real implementation
    return ['status' => 'ok'];
}

/**
 * Handle close chat
 */
function handleCloseChat(Chatbot $chatbot, array $input, Database $db): array {
    $conversationId = $input['conversation_id'] ?? 0;
    $rating = $input['rating'] ?? null;
    $ratingComment = $input['rating_comment'] ?? null;
    
    $chatbot->closeConversation($conversationId, [
        'rating' => $rating,
        'rating_comment' => $ratingComment
    ]);
    
    return ['status' => 'closed'];
}

/**
 * Handle submit rating
 */
function handleSubmitRating(Chatbot $chatbot, array $input, Database $db): array {
    $conversationId = $input['conversation_id'] ?? 0;
    $rating = $input['rating'] ?? 0;
    $comment = $input['comment'] ?? '';
    
    $db->update('conversations', [
        'rating' => $rating,
        'rating_comment' => $comment
    ], 'id = ?', [$conversationId]);
    
    return ['status' => 'rated'];
}

/**
 * Handle save lead
 */
function handleSaveLead(Chatbot $chatbot, array $input, Database $db): array {
    $visitorId = $input['visitor_id'] ?? 0;
    $conversationId = $input['conversation_id'] ?? null;
    
    $leadData = [
        'name' => $input['name'] ?? null,
        'email' => $input['email'] ?? null,
        'phone' => $input['phone'] ?? null,
        'company' => $input['company'] ?? null,
        'message' => $input['message'] ?? null
    ];
    
    $lead = $chatbot->saveLead($visitorId, $leadData, $conversationId);
    
    return [
        'lead_id' => $lead['id'],
        'score' => $lead['score'],
        'status' => 'saved'
    ];
}

/**
 * Handle quick reply
 */
function handleQuickReply(Chatbot $chatbot, array $input, Database $db): array {
    $conversationId = $input['conversation_id'] ?? 0;
    $reply = $input['reply'] ?? '';
    
    // Add user selection as message
    $chatbot->addMessage($conversationId, 'text', $reply, [
        'sender_type' => 'user'
    ]);
    
    // Check for flow trigger
    $flowEngine = new FlowEngine($db, $chatbot);
    if ($flowEngine->loadFlowByTrigger('keyword', $reply)) {
        $flowResponse = $flowEngine->start();
        
        if ($flowResponse['type'] === 'message') {
            $chatbot->addMessage($conversationId, 'text', $flowResponse['message'], [
                'sender_type' => 'bot',
                'sender_name' => $chatbot->getData()['name'],
                'quick_replies' => $flowResponse['quick_replies'] ?? null
            ]);
        }
        
        return [
            'response' => $flowResponse['message'],
            'quick_replies' => $flowResponse['quick_replies'] ?? null
        ];
    }
    
    return ['status' => 'processed'];
}

/**
 * Handle request human
 */
function handleRequestHuman(Chatbot $chatbot, array $input, Database $db): array {
    $conversationId = $input['conversation_id'] ?? 0;
    
    // Update conversation for live chat
    $db->update('conversations', [
        'is_live_chat' => true,
        'status' => 'active'
    ], 'id = ?', [$conversationId]);
    
    // Add system message
    $chatbot->addMessage($conversationId, 'system', 'A human agent will join shortly. Please wait...', [
        'sender_type' => 'system'
    ]);
    
    // Notify agents (would use WebSocket in real implementation)
    
    return [
        'status' => 'requested',
        'message' => 'Connecting you to a human agent...'
    ];
}

/**
 * Detect device type
 */
function detectDeviceType(): string {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if (preg_match('/mobile|android|iphone|ipad|ipod/i', $userAgent)) {
        return 'mobile';
    }
    if (preg_match('/tablet|ipad/i', $userAgent)) {
        return 'tablet';
    }
    return 'desktop';
}
