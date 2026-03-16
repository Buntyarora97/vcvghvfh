<?php
/**
 * Chatbot Builder System - Analytics API
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/JWT.php';

use Chatbot\Config;
use Chatbot\Database;
use Chatbot\JWT;

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? $_GET['action'] ?? 'dashboard';

try {
    $db = Database::getInstance();
    
    // Verify authentication for admin endpoints
    $userId = JWT::getUserId();
    if (!$userId && !in_array($action, ['track'])) {
        throw new Exception('Not authenticated');
    }
    
    $response = match($action) {
        'dashboard' => handleDashboard($db, $input, $userId),
        'stats' => handleStats($db, $input),
        'conversations' => handleConversations($db, $input),
        'leads' => handleLeads($db, $input),
        'messages' => handleMessages($db, $input),
        'geo' => handleGeo($db, $input),
        'hours' => handleHours($db, $input),
        'sources' => handleSources($db, $input),
        'track' => handleTrack($db, $input),
        'export' => handleExport($db, $input),
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
 * Handle dashboard stats
 */
function handleDashboard(Database $db, array $input, int $userId): array {
    $botId = $input['bot_id'] ?? null;
    
    // Get user's bots
    if ($botId) {
        $bots = [$db->fetchOne("SELECT id FROM chatbots WHERE id = ? AND user_id = ?", [$botId, $userId])];
    } else {
        $bots = $db->fetchAll("SELECT id FROM chatbots WHERE user_id = ?", [$userId]);
    }
    
    $botIds = array_column($bots, 'id');
    
    if (empty($botIds)) {
        return [
            'total_chats' => 0,
            'total_leads' => 0,
            'active_now' => 0,
            'avg_rating' => 0,
            'chart_data' => []
        ];
    }
    
    $placeholders = implode(',', array_fill(0, count($botIds), '?'));
    
    // Today's stats
    $todayChats = $db->fetchColumn(
        "SELECT COUNT(*) FROM conversations WHERE bot_id IN ($placeholders) AND DATE(started_at) = CURDATE()",
        $botIds
    );
    
    $todayLeads = $db->fetchColumn(
        "SELECT COUNT(*) FROM leads WHERE bot_id IN ($placeholders) AND DATE(created_at) = CURDATE()",
        $botIds
    );
    
    $activeNow = $db->fetchColumn(
        "SELECT COUNT(*) FROM conversations WHERE bot_id IN ($placeholders) AND status = 'active' AND last_message_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)",
        $botIds
    );
    
    $avgRating = $db->fetchColumn(
        "SELECT AVG(rating) FROM conversations WHERE bot_id IN ($placeholders) AND rating IS NOT NULL AND DATE(started_at) = CURDATE()",
        $botIds
    );
    
    // Last 7 days chart data
    $chartData = $db->fetchAll(
        "SELECT 
            DATE(started_at) as date,
            COUNT(*) as chats,
            COUNT(DISTINCT visitor_id) as visitors
        FROM conversations 
        WHERE bot_id IN ($placeholders) 
        AND started_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(started_at)
        ORDER BY date",
        $botIds
    );
    
    return [
        'total_chats_today' => (int) $todayChats,
        'total_leads_today' => (int) $todayLeads,
        'active_now' => (int) $activeNow,
        'avg_rating_today' => round((float) $avgRating, 1),
        'chart_data' => $chartData
    ];
}

/**
 * Handle stats request
 */
function handleStats(Database $db, array $input): array {
    $botId = $input['bot_id'] ?? 0;
    $period = $input['period'] ?? 'today';
    
    $dateRange = match($period) {
        'today' => [date('Y-m-d'), date('Y-m-d')],
        'yesterday' => [date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day'))],
        'week' => [date('Y-m-d', strtotime('-7 days')), date('Y-m-d')],
        'month' => [date('Y-m-d', strtotime('-30 days')), date('Y-m-d')],
        'year' => [date('Y-m-d', strtotime('-365 days')), date('Y-m-d')],
        default => [date('Y-m-d'), date('Y-m-d')]
    };
    
    [$start, $end] = $dateRange;
    
    // Total conversations
    $totalChats = $db->fetchColumn(
        "SELECT COUNT(*) FROM conversations WHERE bot_id = ? AND DATE(started_at) BETWEEN ? AND ?",
        [$botId, $start, $end]
    );
    
    // Unique visitors
    $uniqueVisitors = $db->fetchColumn(
        "SELECT COUNT(DISTINCT visitor_id) FROM conversations WHERE bot_id = ? AND DATE(started_at) BETWEEN ? AND ?",
        [$botId, $start, $end]
    );
    
    // Total messages
    $totalMessages = $db->fetchColumn(
        "SELECT COUNT(*) FROM messages m 
         JOIN conversations c ON m.conversation_id = c.id 
         WHERE c.bot_id = ? AND DATE(m.created_at) BETWEEN ? AND ?",
        [$botId, $start, $end]
    );
    
    // Total leads
    $totalLeads = $db->fetchColumn(
        "SELECT COUNT(*) FROM leads WHERE bot_id = ? AND DATE(created_at) BETWEEN ? AND ?",
        [$botId, $start, $end]
    );
    
    // Average conversation duration
    $avgDuration = $db->fetchColumn(
        "SELECT AVG(duration_seconds) FROM conversations WHERE bot_id = ? AND DATE(started_at) BETWEEN ? AND ?",
        [$botId, $start, $end]
    );
    
    // Average rating
    $avgRating = $db->fetchColumn(
        "SELECT AVG(rating) FROM conversations WHERE bot_id = ? AND DATE(started_at) BETWEEN ? AND ? AND rating IS NOT NULL",
        [$botId, $start, $end]
    );
    
    // Response time (approximation)
    $avgResponseTime = $db->fetchColumn(
        "SELECT AVG(TIMESTAMPDIFF(SECOND, c.started_at, m.created_at)) 
         FROM conversations c 
         JOIN messages m ON c.id = m.conversation_id 
         WHERE c.bot_id = ? AND m.sender_type = 'bot' AND DATE(c.started_at) BETWEEN ? AND ?",
        [$botId, $start, $end]
    );
    
    return [
        'period' => $period,
        'total_chats' => (int) $totalChats,
        'unique_visitors' => (int) $uniqueVisitors,
        'total_messages' => (int) $totalMessages,
        'total_leads' => (int) $totalLeads,
        'avg_duration_seconds' => round((float) $avgDuration, 0),
        'avg_rating' => round((float) $avgRating, 1),
        'avg_response_time_seconds' => round((float) $avgResponseTime, 0)
    ];
}

/**
 * Handle conversations list
 */
function handleConversations(Database $db, array $input): array {
    $botId = $input['bot_id'] ?? 0;
    $page = (int) ($input['page'] ?? 1);
    $perPage = (int) ($input['per_page'] ?? 20);
    $status = $input['status'] ?? null;
    
    $sql = "SELECT c.*, v.name as visitor_name, v.email as visitor_email, v.country 
            FROM conversations c 
            LEFT JOIN visitors v ON c.visitor_id = v.id 
            WHERE c.bot_id = ?";
    $params = [$botId];
    
    if ($status) {
        $sql .= " AND c.status = ?";
        $params[] = $status;
    }
    
    $sql .= " ORDER BY c.last_message_at DESC";
    
    return $db->paginate($sql, $params, $page, $perPage);
}

/**
 * Handle leads list
 */
function handleLeads(Database $db, array $input): array {
    $botId = $input['bot_id'] ?? 0;
    $page = (int) ($input['page'] ?? 1);
    $perPage = (int) ($input['per_page'] ?? 20);
    $status = $input['status'] ?? null;
    
    $sql = "SELECT l.*, v.name as visitor_name, v.email as visitor_email 
            FROM leads l 
            LEFT JOIN visitors v ON l.visitor_id = v.id 
            WHERE l.bot_id = ?";
    $params = [$botId];
    
    if ($status) {
        $sql .= " AND l.status = ?";
        $params[] = $status;
    }
    
    $sql .= " ORDER BY l.created_at DESC";
    
    return $db->paginate($sql, $params, $page, $perPage);
}

/**
 * Handle messages analytics
 */
function handleMessages(Database $db, array $input): array {
    $botId = $input['bot_id'] ?? 0;
    $days = (int) ($input['days'] ?? 7);
    
    // Popular messages (word cloud data)
    $messages = $db->fetchAll(
        "SELECT content FROM messages m 
         JOIN conversations c ON m.conversation_id = c.id 
         WHERE c.bot_id = ? AND m.sender_type = 'user' 
         AND m.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
         LIMIT 1000",
        [$botId, $days]
    );
    
    // Extract words for word cloud
    $wordCounts = [];
    foreach ($messages as $msg) {
        $words = str_word_count(strtolower($msg['content']), 1);
        foreach ($words as $word) {
            if (strlen($word) > 3) {
                $wordCounts[$word] = ($wordCounts[$word] ?? 0) + 1;
            }
        }
    }
    
    arsort($wordCounts);
    
    return [
        'word_cloud' => array_slice($wordCounts, 0, 50, true),
        'total_messages' => count($messages)
    ];
}

/**
 * Handle geo analytics
 */
function handleGeo(Database $db, array $input): array {
    $botId = $input['bot_id'] ?? 0;
    $days = (int) ($input['days'] ?? 30);
    
    $countries = $db->fetchAll(
        "SELECT country, COUNT(*) as count 
         FROM conversations 
         WHERE bot_id = ? AND country IS NOT NULL 
         AND started_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
         GROUP BY country 
         ORDER BY count DESC 
         LIMIT 20",
        [$botId, $days]
    );
    
    $cities = $db->fetchAll(
        "SELECT city, country, COUNT(*) as count 
         FROM visitors v 
         JOIN conversations c ON v.id = c.visitor_id 
         WHERE c.bot_id = ? AND v.city IS NOT NULL 
         AND c.started_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
         GROUP BY city, country 
         ORDER BY count DESC 
         LIMIT 20",
        [$botId, $days]
    );
    
    return [
        'countries' => $countries,
        'cities' => $cities
    ];
}

/**
 * Handle hourly analytics
 */
function handleHours(Database $db, array $input): array {
    $botId = $input['bot_id'] ?? 0;
    $days = (int) ($input['days'] ?? 30);
    
    $hours = $db->fetchAll(
        "SELECT 
            HOUR(started_at) as hour,
            COUNT(*) as count 
         FROM conversations 
         WHERE bot_id = ? 
         AND started_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
         GROUP BY HOUR(started_at)
         ORDER BY hour",
        [$botId, $days]
    );
    
    // Fill in missing hours
    $hourlyData = array_fill(0, 24, 0);
    foreach ($hours as $h) {
        $hourlyData[(int) $h['hour']] = (int) $h['count'];
    }
    
    return [
        'hours' => $hourlyData,
        'peak_hour' => array_search(max($hourlyData), $hourlyData)
    ];
}

/**
 * Handle sources analytics
 */
function handleSources(Database $db, array $input): array {
    $botId = $input['bot_id'] ?? 0;
    $days = (int) ($input['days'] ?? 30);
    
    $sources = $db->fetchAll(
        "SELECT 
            CASE 
                WHEN source_url LIKE '%google%' THEN 'Google'
                WHEN source_url LIKE '%facebook%' THEN 'Facebook'
                WHEN source_url LIKE '%twitter%' THEN 'Twitter'
                WHEN source_url LIKE '%linkedin%' THEN 'LinkedIn'
                WHEN source_url IS NULL OR source_url = '' THEN 'Direct'
                ELSE 'Other'
            END as source,
            COUNT(*) as count 
         FROM conversations 
         WHERE bot_id = ? 
         AND started_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
         GROUP BY source
         ORDER BY count DESC",
        [$botId, $days]
    );
    
    return ['sources' => $sources];
}

/**
 * Handle track event
 */
function handleTrack(Database $db, array $input): array {
    $botId = $input['bot_id'] ?? 0;
    $event = $input['event'] ?? '';
    $visitorId = $input['visitor_id'] ?? null;
    $conversationId = $input['conversation_id'] ?? null;
    $metadata = $input['metadata'] ?? [];
    
    if (empty($event)) {
        throw new Exception('Event type is required');
    }
    
    $data = [
        'bot_id' => $botId,
        'metric_type' => $event,
        'visitor_id' => $visitorId,
        'conversation_id' => $conversationId,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'source_url' => $_SERVER['HTTP_REFERER'] ?? null,
        'device_type' => detectDeviceType(),
        'browser' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'date_recorded' => date('Y-m-d'),
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    if (!empty($metadata)) {
        $data['metadata'] = json_encode($metadata);
    }
    
    $db->insert('analytics', $data);
    
    return ['status' => 'tracked'];
}

/**
 * Handle export
 */
function handleExport(Database $db, array $input): array {
    $botId = $input['bot_id'] ?? 0;
    $type = $input['type'] ?? 'leads';
    $format = $input['format'] ?? 'csv';
    
    $data = match($type) {
        'leads' => $db->fetchAll(
            "SELECT l.*, v.name, v.email, v.phone, v.country 
             FROM leads l 
             LEFT JOIN visitors v ON l.visitor_id = v.id 
             WHERE l.bot_id = ?",
            [$botId]
        ),
        'conversations' => $db->fetchAll(
            "SELECT c.*, v.name, v.email, v.country 
             FROM conversations c 
             LEFT JOIN visitors v ON c.visitor_id = v.id 
             WHERE c.bot_id = ?",
            [$botId]
        ),
        default => []
    };
    
    if ($format === 'csv') {
        $filename = $type . '_' . date('Y-m-d') . '.csv';
        $filepath = Config::UPLOAD_PATH . 'exports/' . $filename;
        
        if (!is_dir(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }
        
        $fp = fopen($filepath, 'w');
        
        if (!empty($data)) {
            fputcsv($fp, array_keys($data[0]));
            foreach ($data as $row) {
                fputcsv($fp, $row);
            }
        }
        
        fclose($fp);
        
        return [
            'download_url' => Config::getUploadUrl('exports/' . $filename),
            'record_count' => count($data)
        ];
    }
    
    return ['data' => $data];
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
