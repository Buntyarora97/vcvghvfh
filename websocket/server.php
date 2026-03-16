<?php
/**
 * Chatbot Builder System - WebSocket Server
 * Real-time communication using Ratchet
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Database.php';

use Chatbot\Config;
use Chatbot\Database;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

class ChatbotWebSocket implements MessageComponentInterface {
    protected $clients;
    protected $conversations;
    protected $agents;
    protected $db;
    
    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->conversations = [];
        $this->agents = [];
        $this->db = Database::getInstance();
        
        echo "WebSocket Server started on " . Config::WS_HOST . ":" . Config::WS_PORT . "\n";
    }
    
    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        $conn->authenticated = false;
        $conn->userId = null;
        $conn->visitorId = null;
        $conn->conversationId = null;
        $conn->isAgent = false;
        
        echo "New connection! ({$conn->resourceId})\n";
    }
    
    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        
        if (!$data || !isset($data['action'])) {
            $from->send(json_encode(['error' => 'Invalid message format']));
            return;
        }
        
        $action = $data['action'];
        
        try {
            switch ($action) {
                case 'auth':
                    $this->handleAuth($from, $data);
                    break;
                case 'join_conversation':
                    $this->handleJoinConversation($from, $data);
                    break;
                case 'leave_conversation':
                    $this->handleLeaveConversation($from, $data);
                    break;
                case 'send_message':
                    $this->handleSendMessage($from, $data);
                    break;
                case 'typing':
                    $this->handleTyping($from, $data);
                    break;
                case 'agent_status':
                    $this->handleAgentStatus($from, $data);
                    break;
                case 'request_human':
                    $this->handleRequestHuman($from, $data);
                    break;
                case 'ping':
                    $from->send(json_encode(['action' => 'pong']));
                    break;
                default:
                    $from->send(json_encode(['error' => 'Unknown action: ' . $action]));
            }
        } catch (Exception $e) {
            error_log("WebSocket error: " . $e->getMessage());
            $from->send(json_encode(['error' => 'Server error']));
        }
    }
    
    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        
        // Remove from conversations
        if ($conn->conversationId) {
            unset($this->conversations[$conn->conversationId][$conn->resourceId]);
        }
        
        // Update agent status
        if ($conn->isAgent && $conn->userId) {
            unset($this->agents[$conn->userId]);
            $this->updateAgentStatus($conn->userId, 'offline');
        }
        
        echo "Connection {$conn->resourceId} has disconnected\n";
    }
    
    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }
    
    /**
     * Handle authentication
     */
    protected function handleAuth(ConnectionInterface $conn, $data) {
        $token = $data['token'] ?? '';
        $type = $data['user_type'] ?? 'visitor'; // 'visitor' or 'agent'
        
        if ($type === 'agent') {
            // Verify JWT token for agents
            $payload = $this->verifyToken($token);
            
            if (!$payload) {
                $conn->send(json_encode(['error' => 'Invalid token']));
                return;
            }
            
            $conn->authenticated = true;
            $conn->userId = $payload['user_id'];
            $conn->isAgent = true;
            $conn->name = $payload['name'] ?? 'Agent';
            
            $this->agents[$payload['user_id']] = $conn;
            $this->updateAgentStatus($payload['user_id'], 'online');
            
            // Send active conversations
            $activeChats = $this->getActiveConversations();
            $conn->send(json_encode([
                'action' => 'auth_success',
                'user_type' => 'agent',
                'active_conversations' => $activeChats
            ]));
            
        } else {
            // Visitor authentication
            $visitorId = $data['visitor_id'] ?? null;
            $conversationId = $data['conversation_id'] ?? null;
            
            $conn->authenticated = true;
            $conn->visitorId = $visitorId;
            $conn->conversationId = $conversationId;
            $conn->isAgent = false;
            
            if ($conversationId) {
                $this->conversations[$conversationId][$conn->resourceId] = $conn;
            }
            
            $conn->send(json_encode([
                'action' => 'auth_success',
                'user_type' => 'visitor'
            ]));
        }
        
        echo "User authenticated: {$conn->resourceId} ({$type})\n";
    }
    
    /**
     * Handle join conversation
     */
    protected function handleJoinConversation(ConnectionInterface $conn, $data) {
        if (!$conn->authenticated) {
            $conn->send(json_encode(['error' => 'Not authenticated']));
            return;
        }
        
        $conversationId = $data['conversation_id'] ?? null;
        
        if (!$conversationId) {
            $conn->send(json_encode(['error' => 'Conversation ID required']));
            return;
        }
        
        // Leave previous conversation
        if ($conn->conversationId) {
            unset($this->conversations[$conn->conversationId][$conn->resourceId]);
        }
        
        // Join new conversation
        $conn->conversationId = $conversationId;
        $this->conversations[$conversationId][$conn->resourceId] = $conn;
        
        // If agent joined, update conversation
        if ($conn->isAgent) {
            $this->db->update('conversations', [
                'assigned_agent_id' => $conn->userId,
                'is_live_chat' => true,
                'agent_joined_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$conversationId]);
            
            // Notify visitor
            $this->broadcastToConversation($conversationId, [
                'action' => 'agent_joined',
                'agent_name' => $conn->name,
                'message' => $conn->name . ' has joined the conversation'
            ], $conn->resourceId);
        }
        
        $conn->send(json_encode([
            'action' => 'joined_conversation',
            'conversation_id' => $conversationId
        ]));
        
        echo "User {$conn->resourceId} joined conversation {$conversationId}\n";
    }
    
    /**
     * Handle leave conversation
     */
    protected function handleLeaveConversation(ConnectionInterface $conn, $data) {
        if ($conn->conversationId) {
            unset($this->conversations[$conn->conversationId][$conn->resourceId]);
            
            if ($conn->isAgent) {
                // Notify visitor
                $this->broadcastToConversation($conn->conversationId, [
                    'action' => 'agent_left',
                    'agent_name' => $conn->name,
                    'message' => $conn->name . ' has left the conversation'
                ]);
            }
            
            $conn->conversationId = null;
        }
        
        $conn->send(json_encode(['action' => 'left_conversation']));
    }
    
    /**
     * Handle send message
     */
    protected function handleSendMessage(ConnectionInterface $conn, $data) {
        if (!$conn->authenticated) {
            $conn->send(json_encode(['error' => 'Not authenticated']));
            return;
        }
        
        $conversationId = $data['conversation_id'] ?? $conn->conversationId;
        $message = $data['message'] ?? '';
        $messageType = $data['message_type'] ?? 'text';
        
        if (!$conversationId || !$message) {
            $conn->send(json_encode(['error' => 'Conversation ID and message required']));
            return;
        }
        
        // Save message to database
        $messageData = [
            'conversation_id' => $conversationId,
            'type' => $messageType,
            'content' => $message,
            'sender_type' => $conn->isAgent ? 'agent' : 'user',
            'sender_id' => $conn->isAgent ? $conn->userId : $conn->visitorId,
            'sender_name' => $conn->isAgent ? $conn->name : null,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $messageId = $this->db->insert('messages', $messageData);
        
        // Broadcast to conversation
        $broadcastData = [
            'action' => 'new_message',
            'message_id' => $messageId,
            'conversation_id' => $conversationId,
            'message' => $message,
            'message_type' => $messageType,
            'sender_type' => $messageData['sender_type'],
            'sender_name' => $messageData['sender_name'],
            'created_at' => $messageData['created_at']
        ];
        
        $this->broadcastToConversation($conversationId, $broadcastData);
        
        echo "Message sent in conversation {$conversationId}\n";
    }
    
    /**
     * Handle typing indicator
     */
    protected function handleTyping(ConnectionInterface $conn, $data) {
        if (!$conn->authenticated || !$conn->conversationId) {
            return;
        }
        
        $isTyping = $data['is_typing'] ?? false;
        
        $this->broadcastToConversation($conn->conversationId, [
            'action' => 'typing',
            'sender_type' => $conn->isAgent ? 'agent' : 'user',
            'sender_name' => $conn->name,
            'is_typing' => $isTyping
        ], $conn->resourceId);
    }
    
    /**
     * Handle agent status change
     */
    protected function handleAgentStatus(ConnectionInterface $conn, $data) {
        if (!$conn->authenticated || !$conn->isAgent) {
            return;
        }
        
        $status = $data['status'] ?? 'online';
        $this->updateAgentStatus($conn->userId, $status);
        
        // Broadcast to all agents
        foreach ($this->agents as $agent) {
            $agent->send(json_encode([
                'action' => 'agent_status_changed',
                'agent_id' => $conn->userId,
                'status' => $status
            ]));
        }
    }
    
    /**
     * Handle human takeover request
     */
    protected function handleRequestHuman(ConnectionInterface $conn, $data) {
        if (!$conn->authenticated) {
            return;
        }
        
        $conversationId = $data['conversation_id'] ?? $conn->conversationId;
        
        // Update conversation
        $this->db->update('conversations', [
            'is_live_chat' => true,
            'status' => 'active'
        ], 'id = ?', [$conversationId]);
        
        // Notify all agents
        foreach ($this->agents as $agent) {
            $agent->send(json_encode([
                'action' => 'human_requested',
                'conversation_id' => $conversationId,
                'visitor_id' => $conn->visitorId
            ]));
        }
        
        // Send acknowledgment to visitor
        $conn->send(json_encode([
            'action' => 'human_request_sent',
            'message' => 'A human agent will be with you shortly'
        ]));
    }
    
    /**
     * Broadcast message to all clients in a conversation
     */
    protected function broadcastToConversation($conversationId, $data, $excludeResourceId = null) {
        if (!isset($this->conversations[$conversationId])) {
            return;
        }
        
        $message = json_encode($data);
        
        foreach ($this->conversations[$conversationId] as $resourceId => $client) {
            if ($excludeResourceId && $resourceId == $excludeResourceId) {
                continue;
            }
            $client->send($message);
        }
    }
    
    /**
     * Verify JWT token
     */
    protected function verifyToken($token) {
        // Simple JWT verification - in production, use proper JWT library
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        
        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);
        
        if (!$payload || !isset($payload['exp']) || $payload['exp'] < time()) {
            return null;
        }
        
        return $payload;
    }
    
    /**
     * Update agent status in database
     */
    protected function updateAgentStatus($userId, $status) {
        $this->db->update('agents', [
            'status' => $status,
            'last_active' => date('Y-m-d H:i:s')
        ], 'user_id = ?', [$userId]);
    }
    
    /**
     * Get active conversations
     */
    protected function getActiveConversations() {
        return $this->db->fetchAll(
            "SELECT c.*, v.name as visitor_name, m.content as last_message 
             FROM conversations c 
             LEFT JOIN visitors v ON c.visitor_id = v.id 
             LEFT JOIN messages m ON c.id = m.conversation_id 
             WHERE c.status = 'active' 
             AND (c.is_live_chat = 1 OR c.last_message_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE))
             GROUP BY c.id 
             ORDER BY c.last_message_at DESC 
             LIMIT 50"
        );
    }
}

// Start server
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new ChatbotWebSocket()
        )
    ),
    Config::WS_PORT,
    Config::WS_HOST
);

$server->run();
