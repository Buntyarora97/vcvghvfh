<?php
/**
 * Chatbot Builder System - Main Chatbot Class
 */

namespace Chatbot;

class Chatbot {
    private Database $db;
    private ?array $botData = null;
    private ?array $visitorData = null;
    private ?array $conversationData = null;
    
    public function __construct(Database $db = null) {
        $this->db = $db ?? Database::getInstance();
    }
    
    /**
     * Load chatbot by unique ID
     */
    public function load(string $uniqueId): bool {
        $sql = "SELECT * FROM chatbots WHERE unique_id = ? AND status = 'active'";
        $this->botData = $this->db->fetchOne($sql, [$uniqueId]);
        return $this->botData !== null;
    }
    
    /**
     * Load chatbot by ID
     */
    public function loadById(int $id): bool {
        $sql = "SELECT * FROM chatbots WHERE id = ?";
        $this->botData = $this->db->fetchOne($sql, [$id]);
        return $this->botData !== null;
    }
    
    /**
     * Get bot data
     */
    public function getData(): ?array {
        return $this->botData;
    }
    
    /**
     * Get bot ID
     */
    public function getId(): ?int {
        return $this->botData['id'] ?? null;
    }
    
    /**
     * Get bot settings
     */
    public function getSettings(): array {
        $settings = json_decode($this->botData['settings_json'] ?? '{}', true);
        return array_merge(Config::WIDGET_DEFAULTS, $settings);
    }
    
    /**
     * Get theme config
     */
    public function getThemeConfig(): array {
        return json_decode($this->botData['theme_config'] ?? '{}', true);
    }
    
    /**
     * Get or create visitor
     */
    public function getOrCreateVisitor(string $visitorHash, array $data = []): array {
        $sql = "SELECT * FROM visitors WHERE visitor_hash = ?";
        $visitor = $this->db->fetchOne($sql, [$visitorHash]);
        
        if ($visitor) {
            // Update last seen and visit count
            $this->db->update('visitors', [
                'last_seen' => date('Y-m-d H:i:s'),
                'total_visits' => $visitor['total_visits'] + 1
            ], 'id = ?', [$visitor['id']]);
            
            $visitor['total_visits']++;
            $this->visitorData = $visitor;
            return $visitor;
        }
        
        // Create new visitor
        $visitorData = [
            'visitor_hash' => $visitorHash,
            'ip_address' => $data['ip'] ?? $_SERVER['REMOTE_ADDR'] ?? null,
            'browser' => $data['browser'] ?? $_SERVER['HTTP_USER_AGENT'] ?? null,
            'device_type' => $data['device_type'] ?? 'desktop',
            'country' => $data['country'] ?? null,
            'city' => $data['city'] ?? null,
            'referrer_url' => $data['referrer'] ?? null,
            'first_seen' => date('Y-m-d H:i:s'),
            'last_seen' => date('Y-m-d H:i:s')
        ];
        
        $visitorId = $this->db->insert('visitors', $visitorData);
        $this->visitorData = $this->db->fetchOne("SELECT * FROM visitors WHERE id = ?", [$visitorId]);
        
        return $this->visitorData;
    }
    
    /**
     * Get visitor data
     */
    public function getVisitor(): ?array {
        return $this->visitorData;
    }
    
    /**
     * Start new conversation
     */
    public function startConversation(int $visitorId, array $data = []): array {
        $conversationData = [
            'bot_id' => $this->getId(),
            'visitor_id' => $visitorId,
            'source_url' => $data['source_url'] ?? $_SERVER['HTTP_REFERER'] ?? null,
            'ip_address' => $data['ip'] ?? $_SERVER['REMOTE_ADDR'] ?? null,
            'country' => $data['country'] ?? null,
            'device' => $data['device'] ?? null,
            'status' => 'active',
            'started_at' => date('Y-m-d H:i:s'),
            'last_message_at' => date('Y-m-d H:i:s')
        ];
        
        $conversationId = $this->db->insert('conversations', $conversationData);
        $this->conversationData = $this->db->fetchOne("SELECT * FROM conversations WHERE id = ?", [$conversationId]);
        
        // Log analytics
        $this->logAnalytics('chat_started', $visitorId, $conversationId);
        
        return $this->conversationData;
    }
    
    /**
     * Get conversation
     */
    public function getConversation(int $conversationId): ?array {
        $sql = "SELECT * FROM conversations WHERE id = ? AND bot_id = ?";
        return $this->db->fetchOne($sql, [$conversationId, $this->getId()]);
    }
    
    /**
     * Get active conversation for visitor
     */
    public function getActiveConversation(int $visitorId): ?array {
        $sql = "SELECT * FROM conversations 
                WHERE bot_id = ? AND visitor_id = ? AND status = 'active'
                ORDER BY started_at DESC LIMIT 1";
        $conversation = $this->db->fetchOne($sql, [$this->getId(), $visitorId]);
        
        if ($conversation) {
            $this->conversationData = $conversation;
        }
        
        return $conversation;
    }
    
    /**
     * Add message to conversation
     */
    public function addMessage(int $conversationId, string $type, string $content, array $data = []): array {
        $messageData = [
            'conversation_id' => $conversationId,
            'type' => $type,
            'content' => $content,
            'sender_type' => $data['sender_type'] ?? 'bot',
            'sender_id' => $data['sender_id'] ?? null,
            'sender_name' => $data['sender_name'] ?? null,
            'sender_avatar' => $data['sender_avatar'] ?? null,
            'file_url' => $data['file_url'] ?? null,
            'file_name' => $data['file_name'] ?? null,
            'file_size' => $data['file_size'] ?? null,
            'file_type' => $data['file_type'] ?? null,
            'quick_replies' => isset($data['quick_replies']) ? json_encode($data['quick_replies']) : null,
            'form_data' => isset($data['form_data']) ? json_encode($data['form_data']) : null,
            'is_read' => $data['is_read'] ?? false,
            'reply_to_id' => $data['reply_to_id'] ?? null,
            'metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $messageId = $this->db->insert('messages', $messageData);
        
        return $this->db->fetchOne("SELECT * FROM messages WHERE id = ?", [$messageId]);
    }
    
    /**
     * Get conversation messages
     */
    public function getMessages(int $conversationId, int $limit = 50, int $offset = 0): array {
        $sql = "SELECT * FROM messages 
                WHERE conversation_id = ? 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?";
        return $this->db->fetchAll($sql, [$conversationId, $limit, $offset]);
    }
    
    /**
     * Mark messages as read
     */
    public function markAsRead(int $conversationId, string $senderType): void {
        $this->db->update('messages', [
            'is_read' => true,
            'read_at' => date('Y-m-d H:i:s')
        ], 'conversation_id = ? AND sender_type != ? AND is_read = false', [$conversationId, $senderType]);
    }
    
    /**
     * Get unread count
     */
    public function getUnreadCount(int $conversationId, string $senderType): int {
        $sql = "SELECT COUNT(*) FROM messages 
                WHERE conversation_id = ? AND sender_type != ? AND is_read = false";
        return (int) $this->db->fetchColumn($sql, [$conversationId, $senderType]);
    }
    
    /**
     * Close conversation
     */
    public function closeConversation(int $conversationId, array $data = []): bool {
        $conversation = $this->getConversation($conversationId);
        
        if (!$conversation) {
            return false;
        }
        
        $duration = 0;
        if ($conversation['started_at']) {
            $duration = time() - strtotime($conversation['started_at']);
        }
        
        $this->db->update('conversations', [
            'status' => 'closed',
            'ended_at' => date('Y-m-d H:i:s'),
            'duration_seconds' => $duration,
            'rating' => $data['rating'] ?? null,
            'rating_comment' => $data['rating_comment'] ?? null
        ], 'id = ?', [$conversationId]);
        
        $this->logAnalytics('chat_ended', $conversation['visitor_id'], $conversationId);
        
        return true;
    }
    
    /**
     * Save lead
     */
    public function saveLead(int $visitorId, array $data, ?int $conversationId = null): array {
        $leadData = [
            'bot_id' => $this->getId(),
            'visitor_id' => $visitorId,
            'conversation_id' => $conversationId,
            'data_json' => json_encode($data),
            'score' => $this->calculateLeadScore($data),
            'status' => 'new',
            'source' => 'chatbot',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $leadId = $this->db->insert('leads', $leadData);
        
        // Update visitor with lead info
        if (isset($data['name'])) {
            $this->db->update('visitors', ['name' => $data['name']], 'id = ?', [$visitorId]);
        }
        if (isset($data['email'])) {
            $this->db->update('visitors', ['email' => $data['email']], 'id = ?', [$visitorId]);
        }
        if (isset($data['phone'])) {
            $this->db->update('visitors', ['phone' => $data['phone']], 'id = ?', [$visitorId]);
        }
        
        return $this->db->fetchOne("SELECT * FROM leads WHERE id = ?", [$leadId]);
    }
    
    /**
     * Calculate lead score
     */
    private function calculateLeadScore(array $data): int {
        $score = 0;
        
        if (!empty($data['email'])) $score += 20;
        if (!empty($data['phone'])) $score += 30;
        if (!empty($data['name'])) $score += 10;
        if (!empty($data['company'])) $score += 15;
        if (!empty($data['budget'])) $score += 25;
        
        return min($score, 100);
    }
    
    /**
     * Check if within business hours
     */
    public function isBusinessHours(): bool {
        $hours = json_decode($this->botData['business_hours'] ?? 'null', true);
        
        if (!$hours) {
            $hours = Config::BUSINESS_HOURS;
        }
        
        $now = new \DateTime('now', new \DateTimeZone($this->botData['timezone'] ?? 'UTC'));
        $day = strtolower($now->format('l'));
        $time = $now->format('H:i');
        
        if (!isset($hours[$day]) || empty($hours[$day])) {
            return false;
        }
        
        [$open, $close] = $hours[$day];
        
        return $time >= $open && $time <= $close;
    }
    
    /**
     * Get welcome flow
     */
    public function getWelcomeFlow(): ?array {
        $sql = "SELECT * FROM flows 
                WHERE bot_id = ? AND trigger_type = 'welcome' AND is_active = 1
                ORDER BY priority DESC, id ASC LIMIT 1";
        return $this->db->fetchOne($sql, [$this->getId()]);
    }
    
    /**
     * Get flow by keyword
     */
    public function getFlowByKeyword(string $keyword): ?array {
        $sql = "SELECT * FROM flows 
                WHERE bot_id = ? AND trigger_type = 'keyword' AND is_active = 1
                AND (trigger_value LIKE ? OR ? LIKE CONCAT('%', trigger_value, '%'))
                ORDER BY priority DESC LIMIT 1";
        return $this->db->fetchOne($sql, [$this->getId(), "%{$keyword}%", $keyword]);
    }
    
    /**
     * Log analytics event
     */
    public function logAnalytics(string $metricType, ?int $visitorId = null, ?int $conversationId = null, array $metadata = []): void {
        $data = [
            'bot_id' => $this->getId(),
            'metric_type' => $metricType,
            'visitor_id' => $visitorId,
            'conversation_id' => $conversationId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'source_url' => $_SERVER['HTTP_REFERER'] ?? null,
            'date_recorded' => date('Y-m-d'),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        if (!empty($metadata)) {
            $data['metadata'] = json_encode($metadata);
        }
        
        $this->db->insert('analytics', $data);
    }
    
    /**
     * Get analytics for date range
     */
    public function getAnalytics(string $startDate, string $endDate, ?string $metricType = null): array {
        $sql = "SELECT * FROM analytics 
                WHERE bot_id = ? AND date_recorded BETWEEN ? AND ?";
        $params = [$this->getId(), $startDate, $endDate];
        
        if ($metricType) {
            $sql .= " AND metric_type = ?";
            $params[] = $metricType;
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Get stats summary
     */
    public function getStats(string $period = 'today'): array {
        $dateRange = match($period) {
            'today' => [date('Y-m-d'), date('Y-m-d')],
            'yesterday' => [date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day'))],
            'week' => [date('Y-m-d', strtotime('-7 days')), date('Y-m-d')],
            'month' => [date('Y-m-d', strtotime('-30 days')), date('Y-m-d')],
            default => [date('Y-m-d'), date('Y-m-d')]
        };
        
        [$start, $end] = $dateRange;
        
        // Total conversations
        $totalChats = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM conversations WHERE bot_id = ? AND DATE(started_at) BETWEEN ? AND ?",
            [$this->getId(), $start, $end]
        );
        
        // Unique visitors
        $uniqueVisitors = $this->db->fetchColumn(
            "SELECT COUNT(DISTINCT visitor_id) FROM conversations WHERE bot_id = ? AND DATE(started_at) BETWEEN ? AND ?",
            [$this->getId(), $start, $end]
        );
        
        // Total leads
        $totalLeads = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM leads WHERE bot_id = ? AND DATE(created_at) BETWEEN ? AND ?",
            [$this->getId(), $start, $end]
        );
        
        // Average rating
        $avgRating = $this->db->fetchColumn(
            "SELECT AVG(rating) FROM conversations WHERE bot_id = ? AND DATE(started_at) BETWEEN ? AND ? AND rating IS NOT NULL",
            [$this->getId(), $start, $end]
        );
        
        // Active now
        $activeNow = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM conversations WHERE bot_id = ? AND status = 'active' AND last_message_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)",
            [$this->getId()]
        );
        
        return [
            'total_chats' => (int) $totalChats,
            'unique_visitors' => (int) $uniqueVisitors,
            'total_leads' => (int) $totalLeads,
            'avg_rating' => round((float) $avgRating, 1),
            'active_now' => (int) $activeNow,
            'period' => $period
        ];
    }
    
    /**
     * Get embed code
     */
    public function getEmbedCode(): string {
        $uniqueId = $this->botData['unique_id'] ?? '';
        $position = $this->botData['position'] ?? 'bottom-right';
        $autoPopup = $this->botData['auto_popup_delay'] ?? 5000;
        
        return <<<HTML
<!-- Chatbot Widget -->
<script>
(function() {
    var s = document.createElement('script');
    s.src = '{$this->getWidgetUrl()}';
    s.async = true;
    s.setAttribute('data-bot-id', '{$uniqueId}');
    s.setAttribute('data-position', '{$position}');
    s.setAttribute('data-auto-popup', '{$autoPopup}');
    document.body.appendChild(s);
})();
</script>
<!-- End Chatbot Widget -->
HTML;
    }
    
    /**
     * Get widget URL
     */
    private function getWidgetUrl(): string {
        return Config::getUrl('widget.js');
    }
    
    /**
     * Create new chatbot
     */
    public function create(array $data, int $userId): array {
        $uniqueId = $this->generateUniqueId();
        
        $botData = [
            'user_id' => $userId,
            'name' => $data['name'] ?? 'New Chatbot',
            'description' => $data['description'] ?? null,
            'settings_json' => json_encode($data['settings'] ?? []),
            'theme_config' => json_encode($data['theme'] ?? []),
            'status' => 'draft',
            'unique_id' => $uniqueId,
            'welcome_message' => $data['welcome_message'] ?? 'Hi there! How can I help you today?',
            'fallback_message' => $data['fallback_message'] ?? "I didn't understand that. Could you rephrase?",
            'position' => $data['position'] ?? 'bottom-right',
            'primary_color' => $data['primary_color'] ?? '#6366f1',
            'secondary_color' => $data['secondary_color'] ?? '#8b5cf6',
            'font_family' => $data['font_family'] ?? 'Inter',
            'border_radius' => $data['border_radius'] ?? 20,
            'shadow_intensity' => $data['shadow_intensity'] ?? 2,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $botId = $this->db->insert('chatbots', $botData);
        
        // Generate embed code
        $this->db->update('chatbots', [
            'embed_code' => $this->getEmbedCode()
        ], 'id = ?', [$botId]);
        
        return $this->db->fetchOne("SELECT * FROM chatbots WHERE id = ?", [$botId]);
    }
    
    /**
     * Update chatbot
     */
    public function update(int $botId, array $data): bool {
        $updateData = [];
        
        $fields = ['name', 'description', 'welcome_message', 'fallback_message', 
                   'offline_message', 'position', 'primary_color', 'secondary_color',
                   'font_family', 'border_radius', 'shadow_intensity', 'background_blur',
                   'custom_css', 'sound_enabled', 'typing_indicator_enabled', 
                   'file_upload_enabled', 'max_file_size', 'allowed_file_types',
                   'ai_enabled', 'webhook_url', 'zapier_enabled', 'status'];
        
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }
        
        if (isset($data['settings'])) {
            $updateData['settings_json'] = json_encode($data['settings']);
        }
        
        if (isset($data['theme'])) {
            $updateData['theme_config'] = json_encode($data['theme']);
        }
        
        if (isset($data['business_hours'])) {
            $updateData['business_hours'] = json_encode($data['business_hours']);
        }
        
        if (isset($data['ai_config'])) {
            $updateData['ai_config'] = json_encode($data['ai_config']);
        }
        
        if (!empty($updateData)) {
            $this->db->update('chatbots', $updateData, 'id = ?', [$botId]);
            return true;
        }
        
        return false;
    }
    
    /**
     * Delete chatbot
     */
    public function delete(int $botId): bool {
        return $this->db->delete('chatbots', 'id = ?', [$botId]) > 0;
    }
    
    /**
     * Generate unique ID
     */
    private function generateUniqueId(): string {
        return 'bot_' . bin2hex(random_bytes(8));
    }
    
    /**
     * Get all chatbots for user
     */
    public function getUserChatbots(int $userId): array {
        $sql = "SELECT * FROM chatbots WHERE user_id = ? ORDER BY created_at DESC";
        return $this->db->fetchAll($sql, [$userId]);
    }
}
