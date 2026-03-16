/**
 * Chatbot Builder System - Widget JavaScript
 * Vanilla JS, no dependencies
 */

(function() {
    'use strict';
    
    // Get script attributes
    const script = document.currentScript || document.querySelector('script[data-bot-id]');
    const botId = script?.getAttribute('data-bot-id') || '';
    const position = script?.getAttribute('data-position') || 'bottom-right';
    const autoPopup = parseInt(script?.getAttribute('data-auto-popup') || '5000');
    
    if (!botId) {
        console.error('Chatbot Widget: Bot ID is required');
        return;
    }
    
    // Configuration
    const CONFIG = {
        botId: botId,
        position: position,
        autoPopup: autoPopup,
        apiUrl: (script?.src?.replace('/widget.js', '') || '') + '/api/',
        widgetUrl: script?.src?.replace('/widget.js', '') || '',
        soundEnabled: true,
        typingIndicator: true
    };
    
    // State
    let state = {
        isOpen: false,
        isMinimized: false,
        conversationId: null,
        visitorId: null,
        visitorName: null,
        messages: [],
        isTyping: false,
        unreadCount: 0,
        config: null,
        theme: null,
        socket: null,
        lastMessageId: 0
    };
    
    // DOM Elements
    let elements = {};
    
    // Audio context for sounds
    let audioContext = null;
    
    /**
     * Initialize widget
     */
    function init() {
        loadConfig().then(() => {
            createStyles();
            createWidget();
            bindEvents();
            initChat();
            
            // Auto popup after delay
            if (CONFIG.autoPopup > 0) {
                setTimeout(() => {
                    if (!state.isOpen && state.unreadCount === 0) {
                        showPopupNotification();
                    }
                }, CONFIG.autoPopup);
            }
        }).catch(err => {
            console.error('Chatbot Widget: Failed to initialize', err);
        });
    }
    
    /**
     * Load widget configuration
     */
    async function loadConfig() {
        const response = await fetch(`${CONFIG.apiUrl}widget-config.php?id=${CONFIG.botId}`);
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Failed to load config');
        }
        
        state.config = data.config;
        state.theme = data.config.theme;
        
        // Update config from server
        CONFIG.soundEnabled = data.config.sound_enabled;
        CONFIG.typingIndicator = data.config.typing_indicator;
    }
    
    /**
     * Create widget styles
     */
    function createStyles() {
        const styles = document.createElement('style');
        styles.textContent = `
            /* Chatbot Widget Styles */
            .cb-widget {
                --cb-primary: ${state.config?.primary_color || '#6366f1'};
                --cb-secondary: ${state.config?.secondary_color || '#8b5cf6'};
                --cb-font: ${state.config?.font_family || 'Inter, sans-serif'};
                --cb-radius: ${state.config?.border_radius || 20}px;
                --cb-shadow: ${getShadowIntensity(state.config?.shadow_intensity || 2)};
                
                font-family: var(--cb-font);
                position: fixed;
                z-index: 999999;
                ${getPositionStyles()}
            }
            
            .cb-button {
                width: 60px;
                height: 60px;
                border-radius: 50%;
                background: linear-gradient(135deg, var(--cb-primary), var(--cb-secondary));
                border: none;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: var(--cb-shadow);
                transition: all 0.3s ease;
                position: relative;
            }
            
            .cb-button:hover {
                transform: scale(1.1);
                box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
            }
            
            .cb-button svg {
                width: 28px;
                height: 28px;
                fill: white;
            }
            
            .cb-button-pulse {
                position: absolute;
                width: 100%;
                height: 100%;
                border-radius: 50%;
                background: var(--cb-primary);
                opacity: 0.4;
                animation: cb-pulse 3s infinite;
            }
            
            @keyframes cb-pulse {
                0% { transform: scale(1); opacity: 0.4; }
                50% { transform: scale(1.3); opacity: 0; }
                100% { transform: scale(1); opacity: 0.4; }
            }
            
            .cb-badge {
                position: absolute;
                top: -5px;
                right: -5px;
                width: 22px;
                height: 22px;
                background: #ef4444;
                color: white;
                font-size: 12px;
                font-weight: bold;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                animation: cb-bounce 0.5s ease;
            }
            
            @keyframes cb-bounce {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.2); }
            }
            
            .cb-window {
                position: fixed;
                ${getWindowPosition()}
                width: 380px;
                height: 600px;
                max-height: calc(100vh - 100px);
                background: white;
                border-radius: var(--cb-radius);
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
                display: flex;
                flex-direction: column;
                overflow: hidden;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                opacity: 0;
                transform: scale(0.9) translateY(20px);
                pointer-events: none;
            }
            
            .cb-window.open {
                opacity: 1;
                transform: scale(1) translateY(0);
                pointer-events: all;
            }
            
            .cb-header {
                height: 70px;
                background: linear-gradient(135deg, var(--cb-primary), var(--cb-secondary));
                color: white;
                display: flex;
                align-items: center;
                padding: 0 20px;
                flex-shrink: 0;
            }
            
            .cb-header-avatar {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.2);
                display: flex;
                align-items: center;
                justify-content: center;
                margin-right: 12px;
            }
            
            .cb-header-avatar svg {
                width: 24px;
                height: 24px;
                fill: white;
            }
            
            .cb-header-info {
                flex: 1;
            }
            
            .cb-header-name {
                font-weight: 600;
                font-size: 16px;
            }
            
            .cb-header-status {
                font-size: 12px;
                opacity: 0.8;
                display: flex;
                align-items: center;
                gap: 6px;
            }
            
            .cb-status-dot {
                width: 8px;
                height: 8px;
                background: #22c55e;
                border-radius: 50%;
                animation: cb-blink 2s infinite;
            }
            
            @keyframes cb-blink {
                0%, 100% { opacity: 1; }
                50% { opacity: 0.5; }
            }
            
            .cb-header-close {
                width: 36px;
                height: 36px;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.1);
                border: none;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: background 0.2s;
            }
            
            .cb-header-close:hover {
                background: rgba(255, 255, 255, 0.2);
            }
            
            .cb-header-close svg {
                width: 20px;
                height: 20px;
                fill: white;
            }
            
            .cb-messages {
                flex: 1;
                overflow-y: auto;
                padding: 20px;
                background: #f8f9fa;
                display: flex;
                flex-direction: column;
                gap: 12px;
            }
            
            .cb-message {
                max-width: 80%;
                padding: 12px 16px;
                border-radius: 18px;
                font-size: 14px;
                line-height: 1.5;
                animation: cb-message-in 0.3s ease;
                word-wrap: break-word;
            }
            
            @keyframes cb-message-in {
                from {
                    opacity: 0;
                    transform: translateY(10px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            .cb-message-bot {
                align-self: flex-start;
                background: white;
                color: #1f2937;
                border: 1px solid #e5e7eb;
                border-radius: 18px 18px 18px 4px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            }
            
            .cb-message-user {
                align-self: flex-end;
                background: var(--cb-primary);
                color: white;
                border-radius: 18px 18px 4px 18px;
            }
            
            .cb-message-system {
                align-self: center;
                background: #f3f4f6;
                color: #6b7280;
                font-size: 12px;
                padding: 8px 16px;
            }
            
            .cb-message-time {
                font-size: 11px;
                opacity: 0.6;
                margin-top: 4px;
            }
            
            .cb-typing {
                display: flex;
                align-items: center;
                gap: 4px;
                padding: 16px 20px;
            }
            
            .cb-typing-dot {
                width: 8px;
                height: 8px;
                background: #9ca3af;
                border-radius: 50%;
                animation: cb-typing 1.4s infinite ease-in-out both;
            }
            
            .cb-typing-dot:nth-child(1) { animation-delay: -0.32s; }
            .cb-typing-dot:nth-child(2) { animation-delay: -0.16s; }
            
            @keyframes cb-typing {
                0%, 80%, 100% { transform: scale(0.6); }
                40% { transform: scale(1); }
            }
            
            .cb-quick-replies {
                display: flex;
                gap: 8px;
                padding: 0 20px 12px;
                overflow-x: auto;
                scrollbar-width: none;
            }
            
            .cb-quick-replies::-webkit-scrollbar {
                display: none;
            }
            
            .cb-quick-reply {
                flex-shrink: 0;
                padding: 8px 16px;
                border: 1px solid var(--cb-primary);
                background: white;
                color: var(--cb-primary);
                border-radius: 16px;
                font-size: 13px;
                cursor: pointer;
                transition: all 0.2s;
                white-space: nowrap;
            }
            
            .cb-quick-reply:hover {
                background: var(--cb-primary);
                color: white;
            }
            
            .cb-input-area {
                padding: 12px 16px;
                background: white;
                border-top: 1px solid #e5e7eb;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            
            .cb-input-attach {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                border: none;
                background: transparent;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: background 0.2s;
            }
            
            .cb-input-attach:hover {
                background: #f3f4f6;
            }
            
            .cb-input-attach svg {
                width: 20px;
                height: 20px;
                fill: #6b7280;
            }
            
            .cb-input-field {
                flex: 1;
                padding: 10px 16px;
                border: 1px solid #e5e7eb;
                border-radius: 24px;
                font-size: 14px;
                outline: none;
                transition: border-color 0.2s;
            }
            
            .cb-input-field:focus {
                border-color: var(--cb-primary);
            }
            
            .cb-input-send {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                border: none;
                background: var(--cb-primary);
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.2s;
            }
            
            .cb-input-send:hover {
                background: var(--cb-secondary);
                transform: scale(1.05);
            }
            
            .cb-input-send svg {
                width: 20px;
                height: 20px;
                fill: white;
            }
            
            .cb-popup {
                position: fixed;
                ${getPopupPosition()}
                background: white;
                border-radius: 16px;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
                padding: 16px;
                width: 300px;
                animation: cb-popup-in 0.4s ease;
                z-index: 999998;
            }
            
            @keyframes cb-popup-in {
                from {
                    opacity: 0;
                    transform: translateX(100px);
                }
                to {
                    opacity: 1;
                    transform: translateX(0);
                }
            }
            
            .cb-popup-header {
                display: flex;
                align-items: center;
                gap: 12px;
                margin-bottom: 8px;
            }
            
            .cb-popup-avatar {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                background: linear-gradient(135deg, var(--cb-primary), var(--cb-secondary));
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .cb-popup-avatar svg {
                width: 24px;
                height: 24px;
                fill: white;
            }
            
            .cb-popup-name {
                font-weight: 600;
                font-size: 14px;
            }
            
            .cb-popup-message {
                font-size: 13px;
                color: #6b7280;
                line-height: 1.4;
            }
            
            .cb-popup-close {
                position: absolute;
                top: 8px;
                right: 8px;
                width: 24px;
                height: 24px;
                border-radius: 50%;
                border: none;
                background: #f3f4f6;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .cb-popup-close svg {
                width: 14px;
                height: 14px;
                fill: #6b7280;
            }
            
            /* Mobile styles */
            @media (max-width: 480px) {
                .cb-window {
                    position: fixed;
                    top: 0 !important;
                    left: 0 !important;
                    right: 0 !important;
                    bottom: 0 !important;
                    width: 100%;
                    height: 100%;
                    max-height: 100vh;
                    border-radius: 0;
                }
                
                .cb-popup {
                    display: none;
                }
            }
            
            /* File message */
            .cb-file-message {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 10px;
                background: rgba(0, 0, 0, 0.05);
                border-radius: 12px;
            }
            
            .cb-file-icon {
                width: 40px;
                height: 40px;
                background: var(--cb-primary);
                border-radius: 8px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .cb-file-icon svg {
                width: 20px;
                height: 20px;
                fill: white;
            }
            
            .cb-file-info {
                flex: 1;
            }
            
            .cb-file-name {
                font-size: 13px;
                font-weight: 500;
            }
            
            .cb-file-size {
                font-size: 11px;
                opacity: 0.6;
            }
            
            /* Rating stars */
            .cb-rating {
                display: flex;
                gap: 4px;
                padding: 12px;
                justify-content: center;
            }
            
            .cb-rating-star {
                width: 32px;
                height: 32px;
                cursor: pointer;
                fill: #d1d5db;
                transition: fill 0.2s;
            }
            
            .cb-rating-star:hover,
            .cb-rating-star.active {
                fill: #fbbf24;
            }
            
            /* Scrollbar styling */
            .cb-messages::-webkit-scrollbar {
                width: 6px;
            }
            
            .cb-messages::-webkit-scrollbar-track {
                background: transparent;
            }
            
            .cb-messages::-webkit-scrollbar-thumb {
                background: #d1d5db;
                border-radius: 3px;
            }
        `;
        
        document.head.appendChild(styles);
    }
    
    /**
     * Get position styles
     */
    function getPositionStyles() {
        switch (CONFIG.position) {
            case 'bottom-left':
                return 'left: 20px; bottom: 20px;';
            case 'center':
                return 'left: 50%; bottom: 20px; transform: translateX(-50%);';
            case 'bottom-right':
            default:
                return 'right: 20px; bottom: 20px;';
        }
    }
    
    /**
     * Get window position
     */
    function getWindowPosition() {
        switch (CONFIG.position) {
            case 'bottom-left':
                return 'left: 20px; bottom: 90px;';
            case 'center':
                return 'left: 50%; bottom: 90px; transform: translateX(-50%);';
            case 'bottom-right':
            default:
                return 'right: 20px; bottom: 90px;';
        }
    }
    
    /**
     * Get popup position
     */
    function getPopupPosition() {
        switch (CONFIG.position) {
            case 'bottom-left':
                return 'left: 100px; bottom: 90px;';
            case 'center':
                return 'left: 50%; bottom: 90px; transform: translateX(-50%);';
            case 'bottom-right':
            default:
                return 'right: 100px; bottom: 90px;';
        }
    }
    
    /**
     * Get shadow intensity
     */
    function getShadowIntensity(level) {
        const shadows = [
            'none',
            '0 2px 8px rgba(0, 0, 0, 0.1)',
            '0 4px 16px rgba(0, 0, 0, 0.12)',
            '0 8px 24px rgba(0, 0, 0, 0.15)',
            '0 12px 32px rgba(0, 0, 0, 0.2)'
        ];
        return shadows[level] || shadows[2];
    }
    
    /**
     * Create widget DOM
     */
    function createWidget() {
        const widget = document.createElement('div');
        widget.className = 'cb-widget';
        widget.innerHTML = `
            <button class="cb-button" id="cb-button">
                <span class="cb-button-pulse"></span>
                <svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
                <span class="cb-badge" id="cb-badge" style="display: none;">0</span>
            </button>
            
            <div class="cb-window" id="cb-window">
                <div class="cb-header">
                    <div class="cb-header-avatar">
                        <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                    </div>
                    <div class="cb-header-info">
                        <div class="cb-header-name" id="cb-bot-name">${state.config?.bot_name || 'Chatbot'}</div>
                        <div class="cb-header-status">
                            <span class="cb-status-dot"></span>
                            <span>Online</span>
                        </div>
                    </div>
                    <button class="cb-header-close" id="cb-close">
                        <svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                    </button>
                </div>
                
                <div class="cb-messages" id="cb-messages"></div>
                
                <div class="cb-quick-replies" id="cb-quick-replies"></div>
                
                <div class="cb-input-area">
                    <button class="cb-input-attach" id="cb-attach" title="Attach file">
                        <svg viewBox="0 0 24 24"><path d="M16.5 6v11.5c0 2.21-1.79 4-4 4s-4-1.79-4-4V5c0-1.38 1.12-2.5 2.5-2.5s2.5 1.12 2.5 2.5v10.5c0 .55-.45 1-1 1s-1-.45-1-1V6H10v9.5c0 1.38 1.12 2.5 2.5 2.5s2.5-1.12 2.5-2.5V5c0-2.21-1.79-4-4-4S7 2.79 7 5v12.5c0 3.04 2.46 5.5 5.5 5.5s5.5-2.46 5.5-5.5V6h-1.5z"/></svg>
                    </button>
                    <input type="file" id="cb-file-input" style="display: none;" accept="${state.config?.allowed_file_types?.join(',') || '*'}">
                    <input type="text" class="cb-input-field" id="cb-input" placeholder="Type a message...">
                    <button class="cb-input-send" id="cb-send">
                        <svg viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(widget);
        
        // Store elements
        elements = {
            button: document.getElementById('cb-button'),
            badge: document.getElementById('cb-badge'),
            window: document.getElementById('cb-window'),
            close: document.getElementById('cb-close'),
            messages: document.getElementById('cb-messages'),
            quickReplies: document.getElementById('cb-quick-replies'),
            input: document.getElementById('cb-input'),
            send: document.getElementById('cb-send'),
            attach: document.getElementById('cb-attach'),
            fileInput: document.getElementById('cb-file-input')
        };
    }
    
    /**
     * Bind events
     */
    function bindEvents() {
        // Toggle window
        elements.button.addEventListener('click', toggleWindow);
        elements.close.addEventListener('click', toggleWindow);
        
        // Send message
        elements.send.addEventListener('click', sendMessage);
        elements.input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') sendMessage();
        });
        
        // File attachment
        elements.attach.addEventListener('click', () => elements.fileInput.click());
        elements.fileInput.addEventListener('change', handleFileUpload);
        
        // Close popup on outside click
        document.addEventListener('click', (e) => {
            const popup = document.querySelector('.cb-popup');
            if (popup && !popup.contains(e.target) && !elements.button.contains(e.target)) {
                popup.remove();
            }
        });
    }
    
    /**
     * Toggle chat window
     */
    function toggleWindow() {
        state.isOpen = !state.isOpen;
        elements.window.classList.toggle('open', state.isOpen);
        
        if (state.isOpen) {
            state.unreadCount = 0;
            updateBadge();
            elements.input.focus();
            
            // Remove popup if exists
            const popup = document.querySelector('.cb-popup');
            if (popup) popup.remove();
            
            // Scroll to bottom
            scrollToBottom();
        }
    }
    
    /**
     * Initialize chat session
     */
    async function initChat() {
        try {
            const visitorHash = localStorage.getItem('cb_visitor_hash') || generateVisitorHash();
            localStorage.setItem('cb_visitor_hash', visitorHash);
            
            const response = await fetch(`${CONFIG.apiUrl}chat.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'init',
                    bot_id: CONFIG.botId,
                    visitor_hash: visitorHash,
                    source_url: window.location.href,
                    referrer: document.referrer
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                state.conversationId = data.data.conversation_id;
                state.visitorId = data.data.visitor_id;
                state.visitorName = data.data.visitor_name;
                
                // Load existing messages
                if (data.data.messages) {
                    data.data.messages.forEach(msg => addMessage(msg));
                }
                
                // Show welcome message if new conversation
                if (!data.data.is_return_visitor && data.data.welcome_message) {
                    addMessage({
                        type: 'text',
                        content: data.data.welcome_message,
                        sender_type: 'bot',
                        created_at: new Date().toISOString()
                    });
                }
                
                // Start polling for new messages
                startPolling();
            }
        } catch (err) {
            console.error('Chatbot: Failed to initialize chat', err);
        }
    }
    
    /**
     * Generate visitor hash
     */
    function generateVisitorHash() {
        return 'v_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
    }
    
    /**
     * Send message
     */
    async function sendMessage() {
        const message = elements.input.value.trim();
        if (!message) return;
        
        elements.input.value = '';
        
        // Add user message to UI
        addMessage({
            type: 'text',
            content: message,
            sender_type: 'user',
            created_at: new Date().toISOString()
        });
        
        // Show typing indicator
        showTyping();
        
        try {
            const response = await fetch(`${CONFIG.apiUrl}chat.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'send_message',
                    bot_id: CONFIG.botId,
                    conversation_id: state.conversationId,
                    visitor_id: state.visitorId,
                    message: message
                })
            });
            
            const data = await response.json();
            
            hideTyping();
            
            if (data.success) {
                // Add bot response
                addMessage({
                    type: 'text',
                    content: data.data.response,
                    sender_type: 'bot',
                    sender_name: state.config?.bot_name,
                    created_at: new Date().toISOString(),
                    quick_replies: data.data.quick_replies ? JSON.stringify(data.data.quick_replies) : null
                });
                
                // Show quick replies
                if (data.data.quick_replies) {
                    showQuickReplies(data.data.quick_replies);
                }
                
                playSound();
            }
        } catch (err) {
            hideTyping();
            console.error('Chatbot: Failed to send message', err);
        }
    }
    
    /**
     * Add message to UI
     */
    function addMessage(msg) {
        const messageEl = document.createElement('div');
        messageEl.className = `cb-message cb-message-${msg.sender_type}`;
        
        let content = msg.content;
        
        // Handle file messages
        if (msg.type === 'file' && msg.file_url) {
            content = `
                <div class="cb-file-message">
                    <div class="cb-file-icon">
                        <svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                    </div>
                    <div class="cb-file-info">
                        <div class="cb-file-name">${msg.file_name || 'File'}</div>
                        ${msg.file_size ? `<div class="cb-file-size">${formatBytes(msg.file_size)}</div>` : ''}
                    </div>
                </div>
            `;
        }
        
        messageEl.innerHTML = `
            <div>${content}</div>
            <div class="cb-message-time">${formatTime(msg.created_at)}</div>
        `;
        
        elements.messages.appendChild(messageEl);
        scrollToBottom();
        
        // Store quick replies
        if (msg.quick_replies) {
            try {
                const replies = JSON.parse(msg.quick_replies);
                showQuickReplies(replies);
            } catch (e) {}
        }
    }
    
    /**
     * Show typing indicator
     */
    function showTyping() {
        if (!CONFIG.typingIndicator) return;
        
        state.isTyping = true;
        const typingEl = document.createElement('div');
        typingEl.className = 'cb-message cb-message-bot cb-typing';
        typingEl.id = 'cb-typing';
        typingEl.innerHTML = `
            <div class="cb-typing-dot"></div>
            <div class="cb-typing-dot"></div>
            <div class="cb-typing-dot"></div>
        `;
        elements.messages.appendChild(typingEl);
        scrollToBottom();
    }
    
    /**
     * Hide typing indicator
     */
    function hideTyping() {
        state.isTyping = false;
        const typingEl = document.getElementById('cb-typing');
        if (typingEl) typingEl.remove();
    }
    
    /**
     * Show quick replies
     */
    function showQuickReplies(replies) {
        elements.quickReplies.innerHTML = '';
        
        replies.forEach(reply => {
            const btn = document.createElement('button');
            btn.className = 'cb-quick-reply';
            btn.textContent = reply;
            btn.addEventListener('click', () => {
                elements.input.value = reply;
                sendMessage();
                elements.quickReplies.innerHTML = '';
            });
            elements.quickReplies.appendChild(btn);
        });
    }
    
    /**
     * Handle file upload
     */
    async function handleFileUpload(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        const formData = new FormData();
        formData.append('file', file);
        formData.append('bot_id', CONFIG.botId);
        formData.append('conversation_id', state.conversationId);
        
        try {
            const response = await fetch(`${CONFIG.apiUrl}upload.php`, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                addMessage({
                    type: 'file',
                    content: `File: ${data.file.name}`,
                    sender_type: 'user',
                    file_url: data.file.url,
                    file_name: data.file.name,
                    file_size: data.file.size,
                    created_at: new Date().toISOString()
                });
            }
        } catch (err) {
            console.error('Chatbot: Failed to upload file', err);
        }
        
        // Reset input
        elements.fileInput.value = '';
    }
    
    /**
     * Show popup notification
     */
    function showPopupNotification() {
        const popup = document.createElement('div');
        popup.className = 'cb-popup';
        popup.innerHTML = `
            <button class="cb-popup-close" id="cb-popup-close">
                <svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
            </button>
            <div class="cb-popup-header">
                <div class="cb-popup-avatar">
                    <svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
                </div>
                <div class="cb-popup-name">${state.config?.bot_name || 'Chatbot'}</div>
            </div>
            <div class="cb-popup-message">${state.config?.welcome_message || 'Hi there! How can I help you today?'}</div>
        `;
        
        document.body.appendChild(popup);
        
        // Close popup
        document.getElementById('cb-popup-close').addEventListener('click', () => {
            popup.remove();
        });
        
        // Open chat on click
        popup.addEventListener('click', (e) => {
            if (!e.target.closest('#cb-popup-close')) {
                toggleWindow();
                popup.remove();
            }
        });
        
        // Auto remove after 8 seconds
        setTimeout(() => {
            if (popup.parentNode) popup.remove();
        }, 8000);
    }
    
    /**
     * Start polling for new messages
     */
    function startPolling() {
        setInterval(async () => {
            if (!state.isOpen) return;
            
            try {
                const response = await fetch(`${CONFIG.apiUrl}chat.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'get_messages',
                        bot_id: CONFIG.botId,
                        conversation_id: state.conversationId,
                        last_id: state.lastMessageId
                    })
                });
                
                const data = await response.json();
                
                if (data.success && data.data.messages) {
                    data.data.messages.forEach(msg => {
                        if (msg.id > state.lastMessageId) {
                            state.lastMessageId = msg.id;
                            if (msg.sender_type !== 'user') {
                                addMessage(msg);
                                if (!state.isOpen) {
                                    state.unreadCount++;
                                    updateBadge();
                                }
                            }
                        }
                    });
                }
            } catch (err) {
                // Silent fail for polling
            }
        }, 3000);
    }
    
    /**
     * Update badge
     */
    function updateBadge() {
        if (state.unreadCount > 0) {
            elements.badge.textContent = state.unreadCount > 9 ? '9+' : state.unreadCount;
            elements.badge.style.display = 'flex';
        } else {
            elements.badge.style.display = 'none';
        }
    }
    
    /**
     * Scroll to bottom
     */
    function scrollToBottom() {
        elements.messages.scrollTop = elements.messages.scrollHeight;
    }
    
    /**
     * Play notification sound
     */
    function playSound() {
        if (!CONFIG.soundEnabled) return;
        
        try {
            if (!audioContext) {
                audioContext = new (window.AudioContext || window.webkitAudioContext)();
            }
            
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            oscillator.frequency.value = 800;
            oscillator.type = 'sine';
            
            gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);
            
            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.3);
        } catch (e) {
            // Silent fail
        }
    }
    
    /**
     * Format time
     */
    function formatTime(timestamp) {
        const date = new Date(timestamp);
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
    
    /**
     * Format bytes
     */
    function formatBytes(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
