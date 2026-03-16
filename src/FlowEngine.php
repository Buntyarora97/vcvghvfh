<?php
/**
 * Chatbot Builder System - Flow Engine
 * Executes conversation flows and manages node logic
 */

namespace Chatbot;

class FlowEngine {
    private Database $db;
    private Chatbot $chatbot;
    private ?array $flow = null;
    private array $nodes = [];
    private array $connections = [];
    private array $variables = [];
    private ?array $currentNode = null;
    
    public function __construct(Database $db, Chatbot $chatbot) {
        $this->db = $db;
        $this->chatbot = $chatbot;
    }
    
    /**
     * Load flow by ID
     */
    public function loadFlow(int $flowId): bool {
        $sql = "SELECT * FROM flows WHERE id = ? AND bot_id = ?";
        $this->flow = $this->db->fetchOne($sql, [$flowId, $this->chatbot->getId()]);
        
        if (!$this->flow) {
            return false;
        }
        
        $this->nodes = json_decode($this->flow['nodes_json'] ?? '[]', true);
        $this->connections = json_decode($this->flow['connections_json'] ?? '[]', true);
        $this->variables = json_decode($this->flow['variables'] ?? '{}', true) ?: [];
        
        return true;
    }
    
    /**
     * Load flow by trigger type
     */
    public function loadFlowByTrigger(string $triggerType, string $triggerValue = null): bool {
        $sql = "SELECT * FROM flows 
                WHERE bot_id = ? AND trigger_type = ? AND is_active = 1";
        $params = [$this->chatbot->getId(), $triggerType];
        
        if ($triggerValue) {
            $sql .= " AND (trigger_value = ? OR trigger_value IS NULL)";
            $params[] = $triggerValue;
        }
        
        $sql .= " ORDER BY priority DESC, id ASC LIMIT 1";
        
        $this->flow = $this->db->fetchOne($sql, $params);
        
        if (!$this->flow) {
            return false;
        }
        
        $this->nodes = json_decode($this->flow['nodes_json'] ?? '[]', true);
        $this->connections = json_decode($this->flow['connections_json'] ?? '[]', true);
        $this->variables = json_decode($this->flow['variables'] ?? '{}', true) ?: [];
        
        return true;
    }
    
    /**
     * Start flow execution
     */
    public function start(array $context = []): array {
        if (empty($this->nodes)) {
            return $this->createResponse('error', 'No nodes in flow');
        }
        
        // Merge context variables
        $this->variables = array_merge($this->variables, $context);
        
        // Find start node
        $startNode = $this->findStartNode();
        
        if (!$startNode) {
            $startNode = $this->nodes[0];
        }
        
        $this->currentNode = $startNode;
        
        return $this->executeNode($startNode);
    }
    
    /**
     * Process user input and continue flow
     */
    public function processInput(string $input, array $context = []): array {
        $this->variables = array_merge($this->variables, $context);
        
        // Store user input in variable if current node expects input
        if ($this->currentNode && $this->currentNode['type'] === 'input') {
            $variableName = $this->currentNode['data']['variable'] ?? 'user_input';
            $this->variables[$variableName] = $input;
            
            // Validate input
            $validation = $this->currentNode['data']['validation'] ?? 'text';
            if (!$this->validateInput($input, $validation)) {
                return $this->createResponse('validation_error', 
                    $this->currentNode['data']['error_message'] ?? 'Invalid input. Please try again.');
            }
        }
        
        // Find next node
        $nextNode = $this->findNextNode($this->currentNode);
        
        if (!$nextNode) {
            return $this->createResponse('end', 'Conversation ended');
        }
        
        $this->currentNode = $nextNode;
        
        return $this->executeNode($nextNode);
    }
    
    /**
     * Execute a node
     */
    private function executeNode(array $node): array {
        $type = $node['type'];
        $data = $node['data'] ?? [];
        
        return match($type) {
            'message' => $this->executeMessageNode($data),
            'input' => $this->executeInputNode($data),
            'image' => $this->executeImageNode($data),
            'file' => $this->executeFileNode($data),
            'quick_reply' => $this->executeQuickReplyNode($data),
            'condition' => $this->executeConditionNode($data),
            'delay' => $this->executeDelayNode($data),
            'api' => $this->executeApiNode($data),
            'ai' => $this->executeAiNode($data),
            'form' => $this->executeFormNode($data),
            'rating' => $this->executeRatingNode($data),
            'transfer' => $this->executeTransferNode($data),
            default => $this->createResponse('unknown', 'Unknown node type: ' . $type)
        };
    }
    
    /**
     * Execute message node
     */
    private function executeMessageNode(array $data): array {
        $message = $this->replaceVariables($data['message'] ?? 'Hello!');
        
        $response = $this->createResponse('message', $message);
        
        // Add quick replies if present
        if (!empty($data['quick_replies'])) {
            $response['quick_replies'] = array_map(function($reply) {
                return $this->replaceVariables($reply);
            }, $data['quick_replies']);
        }
        
        // Add typing indicator
        $response['typing'] = true;
        $response['typing_duration'] = $data['typing_duration'] ?? 1000;
        
        return $response;
    }
    
    /**
     * Execute input node
     */
    private function executeInputNode(array $data): array {
        return $this->createResponse('input', $data['placeholder'] ?? 'Type your message...', [
            'variable' => $data['variable'] ?? 'user_input',
            'validation' => $data['validation'] ?? 'text',
            'validation_message' => $data['validation_message'] ?? null
        ]);
    }
    
    /**
     * Execute image node
     */
    private function executeImageNode(array $data): array {
        return $this->createResponse('image', '', [
            'image_url' => $this->replaceVariables($data['image_url'] ?? ''),
            'caption' => $this->replaceVariables($data['caption'] ?? '')
        ]);
    }
    
    /**
     * Execute file node
     */
    private function executeFileNode(array $data): array {
        return $this->createResponse('file', '', [
            'file_url' => $this->replaceVariables($data['file_url'] ?? ''),
            'file_name' => $this->replaceVariables($data['file_name'] ?? ''),
            'file_type' => $data['file_type'] ?? 'document'
        ]);
    }
    
    /**
     * Execute quick reply node
     */
    private function executeQuickReplyNode(array $data): array {
        $message = $this->replaceVariables($data['message'] ?? 'Please select an option:');
        
        return $this->createResponse('quick_reply', $message, [
            'options' => $data['options'] ?? [],
            'allow_multiple' => $data['allow_multiple'] ?? false
        ]);
    }
    
    /**
     * Execute condition node
     */
    private function executeConditionNode(array $data): array {
        $condition = $data['condition'] ?? '';
        $variable = $data['variable'] ?? '';
        $value = $data['value'] ?? '';
        
        $actualValue = $this->variables[$variable] ?? '';
        
        $result = match($condition) {
            'equals' => $actualValue == $value,
            'not_equals' => $actualValue != $value,
            'contains' => str_contains($actualValue, $value),
            'starts_with' => str_starts_with($actualValue, $value),
            'ends_with' => str_ends_with($actualValue, $value),
            'greater_than' => $actualValue > $value,
            'less_than' => $actualValue < $value,
            'exists' => !empty($actualValue),
            'empty' => empty($actualValue),
            default => false
        };
        
        // Find next node based on condition result
        $nextNodeId = $result ? ($data['true_node'] ?? null) : ($data['false_node'] ?? null);
        
        if ($nextNodeId) {
            $nextNode = $this->findNodeById($nextNodeId);
            if ($nextNode) {
                $this->currentNode = $nextNode;
                return $this->executeNode($nextNode);
            }
        }
        
        return $this->createResponse('condition_result', '', ['result' => $result]);
    }
    
    /**
     * Execute delay node
     */
    private function executeDelayNode(array $data): array {
        $duration = $data['duration'] ?? 1000;
        
        return $this->createResponse('delay', '', [
            'duration' => $duration
        ]);
    }
    
    /**
     * Execute API node
     */
    private function executeApiNode(array $data): array {
        $url = $this->replaceVariables($data['url'] ?? '');
        $method = $data['method'] ?? 'GET';
        $headers = $data['headers'] ?? [];
        $body = $data['body'] ?? null;
        
        // Replace variables in body
        if ($body) {
            $body = $this->replaceVariables($body);
        }
        
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            
            if ($headers) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            }
            
            if ($body && in_array($method, ['POST', 'PUT', 'PATCH'])) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            // Store response in variable
            $responseVariable = $data['response_variable'] ?? 'api_response';
            $this->variables[$responseVariable] = $response;
            
            return $this->createResponse('api_response', '', [
                'status_code' => $httpCode,
                'response' => $response
            ]);
            
        } catch (\Exception $e) {
            return $this->createResponse('api_error', 'API call failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Execute AI node
     */
    private function executeAiNode(array $data): array {
        $prompt = $this->replaceVariables($data['prompt'] ?? '');
        $systemMessage = $data['system_message'] ?? 'You are a helpful assistant.';
        
        // Check if AI is enabled for this bot
        if (!$this->chatbot->getData()['ai_enabled']) {
            return $this->createResponse('message', 
                $data['fallback_message'] ?? 'AI is currently unavailable. Please try again later.');
        }
        
        // Call AI handler
        $aiHandler = new AIHandler($this->db);
        $response = $aiHandler->generateResponse($prompt, [
            'system_message' => $systemMessage,
            'max_tokens' => $data['max_tokens'] ?? Config::AI_MAX_TOKENS,
            'temperature' => $data['temperature'] ?? Config::AI_TEMPERATURE
        ]);
        
        return $this->createResponse('message', $response);
    }
    
    /**
     * Execute form node
     */
    private function executeFormNode(array $data): array {
        return $this->createResponse('form', '', [
            'title' => $data['title'] ?? 'Please fill out this form',
            'fields' => $data['fields'] ?? [],
            'submit_text' => $data['submit_text'] ?? 'Submit'
        ]);
    }
    
    /**
     * Execute rating node
     */
    private function executeRatingNode(array $data): array {
        return $this->createResponse('rating', $data['message'] ?? 'How would you rate this conversation?', [
            'max_rating' => $data['max_rating'] ?? 5,
            'allow_comment' => $data['allow_comment'] ?? true
        ]);
    }
    
    /**
     * Execute transfer node
     */
    private function executeTransferNode(array $data): array {
        return $this->createResponse('transfer', $data['message'] ?? 'Connecting you to a human agent...', [
            'department' => $data['department'] ?? null,
            'priority' => $data['priority'] ?? 'normal'
        ]);
    }
    
    /**
     * Find start node
     */
    private function findStartNode(): ?array {
        foreach ($this->nodes as $node) {
            if (($node['data']['is_start'] ?? false) || $node['id'] === 'start') {
                return $node;
            }
        }
        return null;
    }
    
    /**
     * Find node by ID
     */
    private function findNodeById(string $id): ?array {
        foreach ($this->nodes as $node) {
            if ($node['id'] === $id) {
                return $node;
            }
        }
        return null;
    }
    
    /**
     * Find next node
     */
    private function findNextNode(?array $currentNode): ?array {
        if (!$currentNode) {
            return null;
        }
        
        $currentId = $currentNode['id'];
        
        foreach ($this->connections as $connection) {
            if ($connection['from'] === $currentId) {
                return $this->findNodeById($connection['to']);
            }
        }
        
        return null;
    }
    
    /**
     * Replace variables in text
     */
    private function replaceVariables(string $text): string {
        return preg_replace_callback('/\{\{(\w+)\}\}/', function($matches) {
            $varName = $matches[1];
            return $this->variables[$varName] ?? $matches[0];
        }, $text);
    }
    
    /**
     * Validate input
     */
    private function validateInput(string $input, string $type): bool {
        return match($type) {
            'text' => strlen($input) > 0,
            'email' => filter_var($input, FILTER_VALIDATE_EMAIL) !== false,
            'phone' => preg_match('/^[\d\s\-\+\(\)]+$/', $input) && strlen($input) >= 10,
            'number' => is_numeric($input),
            'url' => filter_var($input, FILTER_VALIDATE_URL) !== false,
            'date' => strtotime($input) !== false,
            default => true
        };
    }
    
    /**
     * Create response array
     */
    private function createResponse(string $type, string $message, array $data = []): array {
        return array_merge([
            'type' => $type,
            'message' => $message,
            'node_id' => $this->currentNode['id'] ?? null,
            'variables' => $this->variables
        ], $data);
    }
    
    /**
     * Get current node
     */
    public function getCurrentNode(): ?array {
        return $this->currentNode;
    }
    
    /**
     * Get variables
     */
    public function getVariables(): array {
        return $this->variables;
    }
    
    /**
     * Set variable
     */
    public function setVariable(string $name, $value): void {
        $this->variables[$name] = $value;
    }
    
    /**
     * Get variable
     */
    public function getVariable(string $name): mixed {
        return $this->variables[$name] ?? null;
    }
    
    /**
     * Save flow
     */
    public function saveFlow(int $botId, string $name, array $nodes, array $connections, array $data = [], int $userId): array {
        $flowData = [
            'bot_id' => $botId,
            'name' => $name,
            'description' => $data['description'] ?? null,
            'nodes_json' => json_encode($nodes),
            'connections_json' => json_encode($connections),
            'trigger_type' => $data['trigger_type'] ?? 'welcome',
            'trigger_value' => $data['trigger_value'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'is_default' => $data['is_default'] ?? false,
            'priority' => $data['priority'] ?? 0,
            'variables' => json_encode($data['variables'] ?? []),
            'conditions' => json_encode($data['conditions'] ?? []),
            'created_by' => $userId,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        if (isset($data['id'])) {
            $this->db->update('flows', $flowData, 'id = ?', [$data['id']]);
            return $this->db->fetchOne("SELECT * FROM flows WHERE id = ?", [$data['id']]);
        }
        
        $flowId = $this->db->insert('flows', $flowData);
        return $this->db->fetchOne("SELECT * FROM flows WHERE id = ?", [$flowId]);
    }
    
    /**
     * Delete flow
     */
    public function deleteFlow(int $flowId): bool {
        return $this->db->delete('flows', 'id = ? AND bot_id = ?', [$flowId, $this->chatbot->getId()]) > 0;
    }
    
    /**
     * Get all flows for bot
     */
    public function getFlows(): array {
        $sql = "SELECT * FROM flows WHERE bot_id = ? ORDER BY priority DESC, created_at DESC";
        return $this->db->fetchAll($sql, [$this->chatbot->getId()]);
    }
}
