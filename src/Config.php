<?php
/**
 * Chatbot Builder System - Configuration File
 */

namespace Chatbot;

class Config {
    // Database Configuration
    const DB_HOST = 'localhost';
    const DB_NAME = 'u872449974_chatbots';
    const DB_USER = 'u872449974_chatbots';
    const DB_PASS = 'Bunty@9729621995';
    const DB_CHARSET = 'utf8mb4';
    
    // Application Settings
    const APP_NAME = 'Chatbot Builder';
    const APP_VERSION = '1.0.0';
    const APP_URL = 'https://yourdomain.com';
    const APP_DEBUG = false;
    
    // JWT Settings
    const JWT_SECRET = 'your-secret-key-change-this-in-production';
    const JWT_EXPIRY = 86400; // 24 hours
    
    // File Upload Settings
    const MAX_FILE_SIZE = 5242880; // 5MB
    const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    const ALLOWED_DOC_TYPES = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    const UPLOAD_PATH = __DIR__ . '/../assets/uploads/';
    
    // WebSocket Settings
    const WS_HOST = '0.0.0.0';
    const WS_PORT = 8080;
    
    // AI Settings
    const OPENAI_API_KEY = '';
    const OPENAI_MODEL = 'gpt-4';
    const AI_MAX_TOKENS = 500;
    const AI_TEMPERATURE = 0.7;
    
    // Rate Limiting
    const RATE_LIMIT_MESSAGES = 100; // per hour per IP
    const RATE_LIMIT_WINDOW = 3600; // 1 hour
    
    // Session Settings
    const SESSION_TIMEOUT = 1800; // 30 minutes
    const COOKIE_LIFETIME = 86400; // 24 hours
    
    // Email Settings
    const SMTP_HOST = 'smtp.gmail.com';
    const SMTP_PORT = 587;
    const SMTP_USER = '';
    const SMTP_PASS = '';
    const SMTP_FROM = 'noreply@chatbot.com';
    
    // Business Hours (default)
    const BUSINESS_HOURS = [
        'monday' => ['09:00', '18:00'],
        'tuesday' => ['09:00', '18:00'],
        'wednesday' => ['09:00', '18:00'],
        'thursday' => ['09:00', '18:00'],
        'friday' => ['09:00', '18:00'],
        'saturday' => [],
        'sunday' => []
    ];
    
    // Widget Default Settings
    const WIDGET_DEFAULTS = [
        'position' => 'bottom-right',
        'auto_popup' => true,
        'popup_delay' => 5000,
        'primary_color' => '#6366f1',
        'secondary_color' => '#8b5cf6',
        'font_family' => 'Inter',
        'border_radius' => 20,
        'shadow_intensity' => 2,
        'sound_enabled' => true,
        'typing_indicator' => true,
        'file_upload' => true
    ];
    
    // Theme Presets
    const THEME_PRESETS = [
        'gradient' => [
            'name' => 'Gradient',
            'header_bg' => 'gradient',
            'header_gradient_start' => '#6366f1',
            'header_gradient_end' => '#8b5cf6',
            'primary_color' => '#6366f1',
            'secondary_color' => '#8b5cf6'
        ],
        'minimal' => [
            'name' => 'Minimal',
            'header_bg' => 'solid',
            'header_color' => '#ffffff',
            'primary_color' => '#000000',
            'secondary_color' => '#666666'
        ],
        'corporate' => [
            'name' => 'Corporate',
            'header_bg' => 'solid',
            'header_color' => '#1e40af',
            'primary_color' => '#1e40af',
            'secondary_color' => '#3b82f6'
        ],
        'fun' => [
            'name' => 'Fun',
            'header_bg' => 'gradient',
            'header_gradient_start' => '#f59e0b',
            'header_gradient_end' => '#ef4444',
            'primary_color' => '#f59e0b',
            'secondary_color' => '#ef4444'
        ],
        'nature' => [
            'name' => 'Nature',
            'header_bg' => 'gradient',
            'header_gradient_start' => '#10b981',
            'header_gradient_end' => '#059669',
            'primary_color' => '#10b981',
            'secondary_color' => '#059669'
        ],
        'ocean' => [
            'name' => 'Ocean',
            'header_bg' => 'gradient',
            'header_gradient_start' => '#0ea5e9',
            'header_gradient_end' => '#0284c7',
            'primary_color' => '#0ea5e9',
            'secondary_color' => '#0284c7'
        ],
        'sunset' => [
            'name' => 'Sunset',
            'header_bg' => 'gradient',
            'header_gradient_start' => '#ec4899',
            'header_gradient_end' => '#8b5cf6',
            'primary_color' => '#ec4899',
            'secondary_color' => '#8b5cf6'
        ],
        'dark' => [
            'name' => 'Dark Mode',
            'header_bg' => 'solid',
            'header_color' => '#1f2937',
            'primary_color' => '#4b5563',
            'secondary_color' => '#6b7280',
            'dark_mode' => true
        ],
        'elegant' => [
            'name' => 'Elegant',
            'header_bg' => 'solid',
            'header_color' => '#7c3aed',
            'primary_color' => '#7c3aed',
            'secondary_color' => '#a78bfa'
        ],
        'fresh' => [
            'name' => 'Fresh',
            'header_bg' => 'gradient',
            'header_gradient_start' => '#84cc16',
            'header_gradient_end' => '#22c55e',
            'primary_color' => '#84cc16',
            'secondary_color' => '#22c55e'
        ]
    ];
    
    /**
     * Get database DSN
     */
    public static function getDSN(): string {
        return "mysql:host=" . self::DB_HOST . ";dbname=" . self::DB_NAME . ";charset=" . self::DB_CHARSET;
    }
    
    /**
     * Get full URL
     */
    public static function getUrl(string $path = ''): string {
        return rtrim(self::APP_URL, '/') . '/' . ltrim($path, '/');
    }
    
    /**
     * Get upload URL
     */
    public static function getUploadUrl(string $file = ''): string {
        return self::getUrl('assets/uploads/' . $file);
    }
}
