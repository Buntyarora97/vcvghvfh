-- =====================================================
-- CHATBOT BUILDER SYSTEM - DATABASE SCHEMA
-- Similar to Collect.Chat
-- =====================================================

-- Drop tables if exist (for fresh install)
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS analytics;
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS conversations;
DROP TABLE IF EXISTS flows;
DROP TABLE IF EXISTS leads;
DROP TABLE IF EXISTS files;
DROP TABLE IF EXISTS agents;
DROP TABLE IF EXISTS chatbots;
DROP TABLE IF EXISTS visitors;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS webhooks;
DROP TABLE IF EXISTS canned_responses;
DROP TABLE IF EXISTS team_assignments;
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- 1. USERS TABLE (Admin, Agents, Owners)
-- =====================================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    role ENUM('super_admin', 'admin', 'agent', 'owner') DEFAULT 'owner',
    avatar VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    timezone VARCHAR(50) DEFAULT 'UTC',
    language VARCHAR(10) DEFAULT 'en',
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    last_login DATETIME DEFAULT NULL,
    email_verified_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 2. CHATBOTS TABLE
-- =====================================================
CREATE TABLE chatbots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    settings_json JSON NOT NULL,
    theme_config JSON NOT NULL,
    status ENUM('active', 'inactive', 'draft', 'archived') DEFAULT 'draft',
    embed_code TEXT DEFAULT NULL,
    unique_id VARCHAR(32) NOT NULL UNIQUE,
    welcome_message TEXT DEFAULT 'Hi there! How can I help you today?',
    fallback_message TEXT DEFAULT "I didn't understand that. Could you rephrase?",
    offline_message TEXT DEFAULT 'We are currently offline. Leave a message and we will get back to you.',
    business_hours JSON DEFAULT NULL,
    auto_popup_delay INT DEFAULT 5000,
    position ENUM('bottom-right', 'bottom-left', 'center') DEFAULT 'bottom-right',
    primary_color VARCHAR(7) DEFAULT '#6366f1',
    secondary_color VARCHAR(7) DEFAULT '#8b5cf6',
    font_family VARCHAR(50) DEFAULT 'Inter',
    border_radius INT DEFAULT 20,
    shadow_intensity INT DEFAULT 2,
    background_blur BOOLEAN DEFAULT FALSE,
    custom_css TEXT DEFAULT NULL,
    sound_enabled BOOLEAN DEFAULT TRUE,
    typing_indicator_enabled BOOLEAN DEFAULT TRUE,
    file_upload_enabled BOOLEAN DEFAULT TRUE,
    max_file_size INT DEFAULT 5242880,
    allowed_file_types VARCHAR(255) DEFAULT 'jpg,jpeg,png,gif,pdf,doc,docx',
    ai_enabled BOOLEAN DEFAULT FALSE,
    ai_config JSON DEFAULT NULL,
    webhook_url VARCHAR(500) DEFAULT NULL,
    zapier_enabled BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_unique_id (unique_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 3. VISITORS TABLE
-- =====================================================
CREATE TABLE visitors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    visitor_hash VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(100) DEFAULT NULL,
    email VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    custom_fields JSON DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    country VARCHAR(100) DEFAULT NULL,
    city VARCHAR(100) DEFAULT NULL,
    timezone VARCHAR(50) DEFAULT NULL,
    browser VARCHAR(100) DEFAULT NULL,
    browser_version VARCHAR(50) DEFAULT NULL,
    os VARCHAR(100) DEFAULT NULL,
    device_type ENUM('desktop', 'mobile', 'tablet') DEFAULT 'desktop',
    screen_resolution VARCHAR(20) DEFAULT NULL,
    language VARCHAR(10) DEFAULT NULL,
    referrer_url TEXT DEFAULT NULL,
    first_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    total_visits INT DEFAULT 1,
    total_chats INT DEFAULT 0,
    lead_score INT DEFAULT 0,
    notes TEXT DEFAULT NULL,
    INDEX idx_visitor_hash (visitor_hash),
    INDEX idx_email (email),
    INDEX idx_lead_score (lead_score),
    INDEX idx_first_seen (first_seen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 4. CONVERSATIONS TABLE
-- =====================================================
CREATE TABLE conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bot_id INT NOT NULL,
    visitor_id INT NOT NULL,
    source_url TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    country VARCHAR(100) DEFAULT NULL,
    device VARCHAR(100) DEFAULT NULL,
    status ENUM('active', 'closed', 'archived', 'spam') DEFAULT 'active',
    is_live_chat BOOLEAN DEFAULT FALSE,
    assigned_agent_id INT DEFAULT NULL,
    agent_joined_at DATETIME DEFAULT NULL,
    agent_left_at DATETIME DEFAULT NULL,
    rating INT DEFAULT NULL,
    rating_comment TEXT DEFAULT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at DATETIME DEFAULT NULL,
    last_message_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    message_count INT DEFAULT 0,
    duration_seconds INT DEFAULT 0,
    tags JSON DEFAULT NULL,
    FOREIGN KEY (bot_id) REFERENCES chatbots(id) ON DELETE CASCADE,
    FOREIGN KEY (visitor_id) REFERENCES visitors(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_agent_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_bot_id (bot_id),
    INDEX idx_visitor_id (visitor_id),
    INDEX idx_status (status),
    INDEX idx_started_at (started_at),
    INDEX idx_last_message (last_message_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 5. MESSAGES TABLE
-- =====================================================
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    type ENUM('text', 'image', 'file', 'audio', 'video', 'location', 'quick_reply', 'rating', 'form', 'typing', 'system') DEFAULT 'text',
    content TEXT NOT NULL,
    sender_type ENUM('bot', 'user', 'agent', 'system') DEFAULT 'bot',
    sender_id INT DEFAULT NULL,
    sender_name VARCHAR(100) DEFAULT NULL,
    sender_avatar VARCHAR(255) DEFAULT NULL,
    file_url VARCHAR(500) DEFAULT NULL,
    file_name VARCHAR(255) DEFAULT NULL,
    file_size INT DEFAULT NULL,
    file_type VARCHAR(100) DEFAULT NULL,
    quick_replies JSON DEFAULT NULL,
    form_data JSON DEFAULT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    read_at DATETIME DEFAULT NULL,
    is_edited BOOLEAN DEFAULT FALSE,
    edited_at DATETIME DEFAULT NULL,
    reply_to_id INT DEFAULT NULL,
    metadata JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    INDEX idx_conversation_id (conversation_id),
    INDEX idx_sender_type (sender_type),
    INDEX idx_created_at (created_at),
    INDEX idx_is_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 6. FLOWS TABLE (Bot Conversation Flows)
-- =====================================================
CREATE TABLE flows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bot_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    nodes_json JSON NOT NULL,
    connections_json JSON NOT NULL,
    trigger_type ENUM('welcome', 'keyword', 'url', 'time', 'event') DEFAULT 'welcome',
    trigger_value VARCHAR(255) DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    is_default BOOLEAN DEFAULT FALSE,
    priority INT DEFAULT 0,
    variables JSON DEFAULT NULL,
    conditions JSON DEFAULT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (bot_id) REFERENCES chatbots(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_bot_id (bot_id),
    INDEX idx_trigger_type (trigger_type),
    INDEX idx_is_active (is_active),
    INDEX idx_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 7. LEADS TABLE
-- =====================================================
CREATE TABLE leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bot_id INT NOT NULL,
    visitor_id INT NOT NULL,
    conversation_id INT DEFAULT NULL,
    data_json JSON NOT NULL,
    score INT DEFAULT 0,
    status ENUM('new', 'contacted', 'qualified', 'converted', 'lost', 'nurturing') DEFAULT 'new',
    source VARCHAR(100) DEFAULT 'chatbot',
    assigned_to INT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    tags JSON DEFAULT NULL,
    email_sent BOOLEAN DEFAULT FALSE,
    email_sent_at DATETIME DEFAULT NULL,
    last_contact_at DATETIME DEFAULT NULL,
    next_follow_up DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (bot_id) REFERENCES chatbots(id) ON DELETE CASCADE,
    FOREIGN KEY (visitor_id) REFERENCES visitors(id) ON DELETE CASCADE,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_bot_id (bot_id),
    INDEX idx_visitor_id (visitor_id),
    INDEX idx_status (status),
    INDEX idx_score (score),
    INDEX idx_assigned_to (assigned_to),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 8. FILES TABLE
-- =====================================================
CREATE TABLE files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bot_id INT NOT NULL,
    conversation_id INT DEFAULT NULL,
    file_name VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    uploaded_by_type ENUM('user', 'agent', 'system') DEFAULT 'user',
    uploaded_by_id INT DEFAULT NULL,
    thumbnail_path VARCHAR(500) DEFAULT NULL,
    is_public BOOLEAN DEFAULT FALSE,
    download_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bot_id) REFERENCES chatbots(id) ON DELETE CASCADE,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE SET NULL,
    INDEX idx_bot_id (bot_id),
    INDEX idx_conversation_id (conversation_id),
    INDEX idx_file_type (file_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 9. ANALYTICS TABLE
-- =====================================================
CREATE TABLE analytics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bot_id INT NOT NULL,
    metric_type ENUM('chat_started', 'chat_ended', 'message_sent', 'file_uploaded', 'lead_captured', 'page_view', 'widget_opened', 'widget_closed', 'quick_reply_clicked', 'rating_submitted', 'agent_joined', 'agent_left', 'conversation_transferred') NOT NULL,
    value INT DEFAULT 1,
    visitor_id INT DEFAULT NULL,
    conversation_id INT DEFAULT NULL,
    metadata JSON DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    country VARCHAR(100) DEFAULT NULL,
    device_type ENUM('desktop', 'mobile', 'tablet') DEFAULT 'desktop',
    browser VARCHAR(100) DEFAULT NULL,
    os VARCHAR(100) DEFAULT NULL,
    source_url TEXT DEFAULT NULL,
    referrer_url TEXT DEFAULT NULL,
    hour_of_day INT DEFAULT NULL,
    day_of_week INT DEFAULT NULL,
    date_recorded DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bot_id) REFERENCES chatbots(id) ON DELETE CASCADE,
    FOREIGN KEY (visitor_id) REFERENCES visitors(id) ON DELETE SET NULL,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE SET NULL,
    INDEX idx_bot_id (bot_id),
    INDEX idx_metric_type (metric_type),
    INDEX idx_date_recorded (date_recorded),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 10. AGENTS TABLE (Agent Status & Settings)
-- =====================================================
CREATE TABLE agents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    status ENUM('online', 'away', 'busy', 'offline') DEFAULT 'offline',
    max_chats INT DEFAULT 3,
    current_chats INT DEFAULT 0,
    total_chats_handled INT DEFAULT 0,
    avg_response_time INT DEFAULT 0,
    avg_rating DECIMAL(2,1) DEFAULT 0.0,
    skills JSON DEFAULT NULL,
    departments JSON DEFAULT NULL,
    working_hours JSON DEFAULT NULL,
    notifications_enabled BOOLEAN DEFAULT TRUE,
    sound_enabled BOOLEAN DEFAULT TRUE,
    last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_last_active (last_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 11. WEBHOOKS TABLE
-- =====================================================
CREATE TABLE webhooks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bot_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    url VARCHAR(500) NOT NULL,
    secret VARCHAR(255) DEFAULT NULL,
    events JSON NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_triggered_at DATETIME DEFAULT NULL,
    last_response_code INT DEFAULT NULL,
    last_response_body TEXT DEFAULT NULL,
    retry_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (bot_id) REFERENCES chatbots(id) ON DELETE CASCADE,
    INDEX idx_bot_id (bot_id),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 12. CANNED RESPONSES TABLE
-- =====================================================
CREATE TABLE canned_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bot_id INT NOT NULL,
    shortcut VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    category VARCHAR(50) DEFAULT 'general',
    tags JSON DEFAULT NULL,
    usage_count INT DEFAULT 0,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (bot_id) REFERENCES chatbots(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_bot_id (bot_id),
    INDEX idx_shortcut (shortcut),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 13. TEAM ASSIGNMENTS TABLE
-- =====================================================
CREATE TABLE team_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    agent_id INT NOT NULL,
    assigned_by INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at DATETIME DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_conversation_id (conversation_id),
    INDEX idx_agent_id (agent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 14. AI KNOWLEDGE BASE TABLE
-- =====================================================
CREATE TABLE ai_knowledge_base (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bot_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    source_type ENUM('manual', 'pdf', 'txt', 'url', 'api') DEFAULT 'manual',
    source_file VARCHAR(500) DEFAULT NULL,
    category VARCHAR(100) DEFAULT NULL,
    tags JSON DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    usage_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (bot_id) REFERENCES chatbots(id) ON DELETE CASCADE,
    INDEX idx_bot_id (bot_id),
    INDEX idx_category (category),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 15. SESSIONS TABLE (For WebSocket & Real-time)
-- =====================================================
CREATE TABLE sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) NOT NULL UNIQUE,
    user_id INT DEFAULT NULL,
    visitor_id INT DEFAULT NULL,
    conversation_id INT DEFAULT NULL,
    socket_id VARCHAR(255) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    is_authenticated BOOLEAN DEFAULT FALSE,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (visitor_id) REFERENCES visitors(id) ON DELETE CASCADE,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    INDEX idx_session_id (session_id),
    INDEX idx_user_id (user_id),
    INDEX idx_visitor_id (visitor_id),
    INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- INSERT DEFAULT DATA
-- =====================================================

-- Default admin user (password: admin123 - change in production!)
INSERT INTO users (email, password, name, role, status) VALUES 
('admin@chatbot.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Admin', 'super_admin', 'active');

-- Default themes configuration
INSERT INTO chatbots (
    user_id, 
    name, 
    description,
    settings_json, 
    theme_config, 
    status, 
    unique_id,
    welcome_message,
    fallback_message,
    position,
    primary_color,
    secondary_color,
    font_family,
    border_radius,
    shadow_intensity,
    sound_enabled,
    typing_indicator_enabled,
    file_upload_enabled
) VALUES (
    1,
    'Demo Bot',
    'A demo chatbot for testing',
    '{"auto_popup": true, "popup_delay": 5000, "show_branding": true, "require_email": false, "show_avatar": true, "avatar_url": "", "typing_speed": 30, "session_timeout": 1800}',
    '{"header_bg": "gradient", "header_gradient_start": "#6366f1", "header_gradient_end": "#8b5cf6", "chat_bg": "#ffffff", "bot_bubble_bg": "#f3f4f6", "bot_bubble_text": "#1f2937", "user_bubble_bg": "#6366f1", "user_bubble_text": "#ffffff", "input_bg": "#f9fafb", "input_border": "#e5e7eb", "button_color": "#6366f1"}',
    'active',
    'demo_bot_001',
    'Hi there! Welcome to our website. How can I help you today?',
    "I'm sorry, I didn't understand that. Could you please rephrase?",
    'bottom-right',
    '#6366f1',
    '#8b5cf6',
    'Inter',
    20,
    2,
    TRUE,
    TRUE,
    TRUE
);

-- Default welcome flow
INSERT INTO flows (
    bot_id,
    name,
    description,
    nodes_json,
    connections_json,
    trigger_type,
    is_active,
    is_default,
    priority,
    created_by
) VALUES (
    1,
    'Welcome Flow',
    'Default welcome conversation flow',
    '[
        {
            "id": "start",
            "type": "message",
            "data": {
                "message": "Hi there! Welcome to our website. What's your name?"
            },
            "position": {"x": 100, "y": 100}
        },
        {
            "id": "input_name",
            "type": "input",
            "data": {
                "variable": "name",
                "placeholder": "Enter your name...",
                "validation": "text"
            },
            "position": {"x": 100, "y": 250}
        },
        {
            "id": "greeting",
            "type": "message",
            "data": {
                "message": "Nice to meet you, {{name}}! How can I help you today?",
                "quick_replies": ["Pricing", "Features", "Support", "Contact Sales"]
            },
            "position": {"x": 100, "y": 400}
        }
    ]',
    '[
        {"from": "start", "to": "input_name"},
        {"from": "input_name", "to": "greeting"}
    ]',
    'welcome',
    TRUE,
    TRUE,
    1,
    1
);

-- Default canned responses
INSERT INTO canned_responses (bot_id, shortcut, message, category, created_by) VALUES
(1, 'hello', 'Hello! How can I assist you today?', 'greeting', 1),
(1, 'thanks', "You're welcome! Is there anything else I can help you with?", 'general', 1),
(1, 'bye', 'Thank you for chatting with us. Have a great day!', 'closing', 1),
(1, 'help', 'I can help you with: Pricing information, Product features, Technical support, or General inquiries. What would you like to know?', 'general', 1);

-- =====================================================
-- VIEWS FOR ANALYTICS
-- =====================================================

CREATE VIEW v_conversation_stats AS
SELECT 
    c.bot_id,
    DATE(c.started_at) as date,
    COUNT(*) as total_conversations,
    SUM(CASE WHEN c.status = 'active' THEN 1 ELSE 0 END) as active_conversations,
    SUM(CASE WHEN c.is_live_chat = TRUE THEN 1 ELSE 0 END) as live_chats,
    AVG(c.duration_seconds) as avg_duration,
    AVG(c.rating) as avg_rating
FROM conversations c
GROUP BY c.bot_id, DATE(c.started_at);

CREATE VIEW v_lead_stats AS
SELECT 
    l.bot_id,
    DATE(l.created_at) as date,
    COUNT(*) as total_leads,
    SUM(CASE WHEN l.status = 'new' THEN 1 ELSE 0 END) as new_leads,
    SUM(CASE WHEN l.status = 'qualified' THEN 1 ELSE 0 END) as qualified_leads,
    SUM(CASE WHEN l.status = 'converted' THEN 1 ELSE 0 END) as converted_leads,
    AVG(l.score) as avg_score
FROM leads l
GROUP BY l.bot_id, DATE(l.created_at);

CREATE VIEW v_hourly_stats AS
SELECT 
    bot_id,
    HOUR(created_at) as hour,
    DAYOFWEEK(created_at) as day_of_week,
    COUNT(*) as total_events
FROM analytics
GROUP BY bot_id, HOUR(created_at), DAYOFWEEK(created_at);

-- =====================================================
-- STORED PROCEDURES
-- =====================================================

DELIMITER //

CREATE PROCEDURE sp_get_bot_stats(IN p_bot_id INT, IN p_start_date DATE, IN p_end_date DATE)
BEGIN
    SELECT 
        COUNT(DISTINCT c.id) as total_conversations,
        COUNT(DISTINCT c.visitor_id) as unique_visitors,
        AVG(c.duration_seconds) as avg_duration,
        AVG(c.rating) as avg_rating,
        COUNT(DISTINCT l.id) as total_leads,
        SUM(CASE WHEN c.is_live_chat THEN 1 ELSE 0 END) as live_chats
    FROM conversations c
    LEFT JOIN leads l ON c.id = l.conversation_id
    WHERE c.bot_id = p_bot_id
    AND DATE(c.started_at) BETWEEN p_start_date AND p_end_date;
END //

CREATE PROCEDURE sp_update_visitor_stats(IN p_visitor_id INT)
BEGIN
    UPDATE visitors v
    SET 
        total_chats = (SELECT COUNT(*) FROM conversations WHERE visitor_id = p_visitor_id),
        last_seen = NOW()
    WHERE id = p_visitor_id;
END //

CREATE PROCEDURE sp_close_inactive_conversations(IN p_timeout_minutes INT)
BEGIN
    UPDATE conversations
    SET 
        status = 'closed',
        ended_at = NOW()
    WHERE status = 'active'
    AND last_message_at < DATE_SUB(NOW(), INTERVAL p_timeout_minutes MINUTE);
END //

DELIMITER ;

-- =====================================================
-- TRIGGERS
-- =====================================================

DELIMITER //

CREATE TRIGGER trg_conversation_after_insert
AFTER INSERT ON conversations
FOR EACH ROW
BEGIN
    INSERT INTO analytics (bot_id, metric_type, visitor_id, conversation_id, date_recorded)
    VALUES (NEW.bot_id, 'chat_started', NEW.visitor_id, NEW.id, CURDATE());
END //

CREATE TRIGGER trg_message_after_insert
AFTER INSERT ON messages
FOR EACH ROW
BEGIN
    UPDATE conversations
    SET 
        last_message_at = NEW.created_at,
        message_count = message_count + 1
    WHERE id = NEW.conversation_id;
END //

CREATE TRIGGER trg_lead_after_insert
AFTER INSERT ON leads
FOR EACH ROW
BEGIN
    INSERT INTO analytics (bot_id, metric_type, visitor_id, conversation_id, date_recorded)
    VALUES (NEW.bot_id, 'lead_captured', NEW.visitor_id, NEW.conversation_id, CURDATE());
END //

DELIMITER ;
