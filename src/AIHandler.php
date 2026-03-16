<?php
/**
 * Chatbot Builder System - AI Handler
 * OpenAI GPT-4 Integration
 */

namespace Chatbot;

class AIHandler {
    private Database $db;
    private string $apiKey;
    private string $model;
    private array $knowledgeBase = [];
    
    public function __construct(Database $db = null) {
        $this->db = $db ?? Database::getInstance();
        $this->apiKey = Config::OPENAI_API_KEY;
        $this->model = Config::OPENAI_MODEL;
    }
    
    /**
     * Generate AI response
     */
    public function generateResponse(string $prompt, array $options = []): string {
        if (empty($this->apiKey)) {
            return 'AI is not configured. Please contact support.';
        }
        
        $systemMessage = $options['system_message'] ?? $this->getDefaultSystemMessage();
        $maxTokens = $options['max_tokens'] ?? Config::AI_MAX_TOKENS;
        $temperature = $options['temperature'] ?? Config::AI_TEMPERATURE;
        $conversation = $options['conversation'] ?? [];
        
        // Build messages array
        $messages = [
            ['role' => 'system', 'content' => $systemMessage]
        ];
        
        // Add knowledge base context
        $knowledgeContext = $this->getKnowledgeContext($prompt);
        if ($knowledgeContext) {
            $messages[] = ['role' => 'system', 'content' => 'Knowledge Base: ' . $knowledgeContext];
        }
        
        // Add conversation history
        foreach ($conversation as $msg) {
            $role = $msg['sender_type'] === 'user' ? 'user' : 'assistant';
            $messages[] = ['role' => $role, 'content' => $msg['content']];
        }
        
        // Add current prompt
        $messages[] = ['role' => 'user', 'content' => $prompt];
        
        try {
            $response = $this->callOpenAI($messages, $maxTokens, $temperature);
            return $response['choices'][0]['message']['content'] ?? 'Sorry, I could not generate a response.';
        } catch (\Exception $e) {
            error_log('OpenAI API error: ' . $e->getMessage());
            return 'Sorry, I am having trouble understanding. Could you rephrase that?';
        }
    }
    
    /**
     * Call OpenAI API
     */
    private function callOpenAI(array $messages, int $maxTokens, float $temperature): array {
        $url = 'https://api.openai.com/v1/chat/completions';
        
        $data = [
            'model' => $this->model,
            'messages' => $messages,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
            'top_p' => 1,
            'frequency_penalty' => 0,
            'presence_penalty' => 0
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            throw new \Exception('Curl error: ' . curl_error($ch));
        }
        
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new \Exception('OpenAI API error: HTTP ' . $httpCode . ' - ' . $response);
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Get default system message
     */
    private function getDefaultSystemMessage(): string {
        return 'You are a helpful customer support assistant. Be concise, friendly, and professional. ' .
               'Answer questions accurately based on the provided knowledge base. ' .
               'If you do not know the answer, suggest contacting a human agent.';
    }
    
    /**
     * Load knowledge base for bot
     */
    public function loadKnowledgeBase(int $botId): void {
        $sql = "SELECT * FROM ai_knowledge_base WHERE bot_id = ? AND is_active = 1";
        $this->knowledgeBase = $this->db->fetchAll($sql, [$botId]);
    }
    
    /**
     * Get relevant knowledge context
     */
    private function getKnowledgeContext(string $query): string {
        if (empty($this->knowledgeBase)) {
            return '';
        }
        
        $relevant = [];
        $queryLower = strtolower($query);
        $queryWords = explode(' ', $queryLower);
        
        foreach ($this->knowledgeBase as $item) {
            $contentLower = strtolower($item['content']);
            $titleLower = strtolower($item['title']);
            
            $score = 0;
            
            // Check title match
            if (str_contains($titleLower, $queryLower)) {
                $score += 10;
            }
            
            // Check word matches
            foreach ($queryWords as $word) {
                if (strlen($word) > 3) {
                    if (str_contains($contentLower, $word)) {
                        $score += 2;
                    }
                    if (str_contains($titleLower, $word)) {
                        $score += 5;
                    }
                }
            }
            
            if ($score > 0) {
                $relevant[] = ['item' => $item, 'score' => $score];
            }
        }
        
        // Sort by relevance score
        usort($relevant, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        // Take top 3 most relevant items
        $context = [];
        foreach (array_slice($relevant, 0, 3) as $item) {
            $context[] = $item['item']['title'] . ': ' . substr($item['item']['content'], 0, 500);
        }
        
        return implode("\n\n", $context);
    }
    
    /**
     * Add knowledge base item
     */
    public function addKnowledgeItem(int $botId, array $data): array {
        $itemData = [
            'bot_id' => $botId,
            'title' => $data['title'],
            'content' => $data['content'],
            'source_type' => $data['source_type'] ?? 'manual',
            'source_file' => $data['source_file'] ?? null,
            'category' => $data['category'] ?? null,
            'tags' => isset($data['tags']) ? json_encode($data['tags']) : null,
            'is_active' => $data['is_active'] ?? true,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $itemId = $this->db->insert('ai_knowledge_base', $itemData);
        
        return $this->db->fetchOne("SELECT * FROM ai_knowledge_base WHERE id = ?", [$itemId]);
    }
    
    /**
     * Update knowledge base item
     */
    public function updateKnowledgeItem(int $itemId, array $data): bool {
        $updateData = [];
        
        $fields = ['title', 'content', 'category', 'is_active'];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }
        
        if (isset($data['tags'])) {
            $updateData['tags'] = json_encode($data['tags']);
        }
        
        if (!empty($updateData)) {
            $this->db->update('ai_knowledge_base', $updateData, 'id = ?', [$itemId]);
            return true;
        }
        
        return false;
    }
    
    /**
     * Delete knowledge base item
     */
    public function deleteKnowledgeItem(int $itemId): bool {
        return $this->db->delete('ai_knowledge_base', 'id = ?', [$itemId]) > 0;
    }
    
    /**
     * Process uploaded file for knowledge base
     */
    public function processKnowledgeFile(string $filePath, string $fileType): string {
        $content = '';
        
        switch ($fileType) {
            case 'application/pdf':
                $content = $this->extractPdfText($filePath);
                break;
            case 'text/plain':
            case 'text/markdown':
                $content = file_get_contents($filePath);
                break;
            case 'application/msword':
            case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
                $content = $this->extractDocText($filePath);
                break;
            default:
                throw new \Exception('Unsupported file type: ' . $fileType);
        }
        
        return $content;
    }
    
    /**
     * Extract text from PDF
     */
    private function extractPdfText(string $filePath): string {
        // This would require a PDF parsing library like TCPDF or similar
        // For now, return a placeholder
        return 'PDF content extraction requires additional library installation.';
    }
    
    /**
     * Extract text from DOC/DOCX
     */
    private function extractDocText(string $filePath): string {
        // This would require a DOC parsing library
        // For now, return a placeholder
        return 'DOC content extraction requires additional library installation.';
    }
    
    /**
     * Analyze sentiment of message
     */
    public function analyzeSentiment(string $text): array {
        $prompt = "Analyze the sentiment of this message and respond with only: POSITIVE, NEGATIVE, or NEUTRAL.\n\nMessage: {$text}";
        
        try {
            $response = $this->generateResponse($prompt, [
                'system_message' => 'You are a sentiment analysis tool. Respond with only one word.',
                'max_tokens' => 10,
                'temperature' => 0
            ]);
            
            $sentiment = strtoupper(trim($response));
            
            return [
                'sentiment' => in_array($sentiment, ['POSITIVE', 'NEGATIVE', 'NEUTRAL']) ? $sentiment : 'NEUTRAL',
                'confidence' => 0.8
            ];
        } catch (\Exception $e) {
            return ['sentiment' => 'NEUTRAL', 'confidence' => 0];
        }
    }
    
    /**
     * Suggest replies for agent
     */
    public function suggestReplies(array $conversation, string $tone = 'professional'): array {
        $context = '';
        foreach (array_slice($conversation, -5) as $msg) {
            $context .= $msg['sender_type'] . ': ' . $msg['content'] . "\n";
        }
        
        $prompt = "Based on this conversation, suggest 3 short reply options for the support agent:\n\n{$context}\n\nTone: {$tone}\n\nSuggestions:";
        
        try {
            $response = $this->generateResponse($prompt, [
                'system_message' => 'You are a helpful assistant that suggests customer support replies.',
                'max_tokens' => 200,
                'temperature' => 0.7
            ]);
            
            // Parse suggestions
            $suggestions = explode("\n", $response);
            $suggestions = array_filter($suggestions, function($s) {
                return strlen(trim($s)) > 10;
            });
            
            return array_slice(array_map('trim', $suggestions), 0, 3);
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * Train from past conversations
     */
    public function trainFromConversations(int $botId, int $limit = 100): array {
        $sql = "SELECT c.*, m.content as message_content, m.sender_type 
                FROM conversations c 
                JOIN messages m ON c.id = m.conversation_id 
                WHERE c.bot_id = ? AND c.rating >= 4 
                ORDER BY c.id DESC LIMIT ?";
        
        $conversations = $this->db->fetchAll($sql, [$botId, $limit]);
        
        $trainingData = [];
        foreach ($conversations as $conv) {
            $trainingData[] = [
                'input' => $conv['message_content'],
                'context' => $conv['source_url'] ?? '',
                'rating' => $conv['rating']
            ];
        }
        
        // Store training data
        // In a real implementation, this would fine-tune the model
        
        return [
            'trained_count' => count($trainingData),
            'status' => 'success'
        ];
    }
    
    /**
     * Set API key
     */
    public function setApiKey(string $apiKey): void {
        $this->apiKey = $apiKey;
    }
    
    /**
     * Set model
     */
    public function setModel(string $model): void {
        $this->model = $model;
    }
}
