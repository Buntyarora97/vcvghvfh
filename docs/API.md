# Chatbot Builder System - API Documentation

## Base URL

```
https://your-domain.com/api/
```

## Authentication

Most endpoints require JWT authentication. Include the token in the Authorization header:

```
Authorization: Bearer <your-jwt-token>
```

## Endpoints

### Authentication

#### Login
```http
POST /auth.php
Content-Type: application/json

{
  "action": "login",
  "email": "user@example.com",
  "password": "password123"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIs...",
    "user": {
      "id": 1,
      "email": "user@example.com",
      "name": "John Doe",
      "role": "admin"
    }
  }
}
```

#### Register
```http
POST /auth.php
Content-Type: application/json

{
  "action": "register",
  "name": "John Doe",
  "email": "user@example.com",
  "password": "password123"
}
```

#### Get Current User
```http
GET /auth.php?action=me
Authorization: Bearer <token>
```

---

### Chatbots

#### List Chatbots
```http
GET /bots.php
Authorization: Bearer <token>
```

**Query Parameters:**
- `page` (optional): Page number (default: 1)
- `per_page` (optional): Items per page (default: 20)
- `status` (optional): Filter by status

#### Get Single Chatbot
```http
GET /bots.php?id={bot_id}
Authorization: Bearer <token>
```

#### Create Chatbot
```http
POST /bots.php
Authorization: Bearer <token>
Content-Type: application/json

{
  "name": "My Chatbot",
  "description": "A helpful assistant",
  "welcome_message": "Hi! How can I help?",
  "primary_color": "#6366f1",
  "position": "bottom-right"
}
```

#### Update Chatbot
```http
PUT /bots.php?id={bot_id}
Authorization: Bearer <token>
Content-Type: application/json

{
  "name": "Updated Name",
  "status": "active",
  "settings": {
    "auto_popup": true,
    "popup_delay": 5000
  }
}
```

#### Delete Chatbot
```http
DELETE /bots.php?id={bot_id}
Authorization: Bearer <token>
```

---

### Flows

#### List Flows
```http
GET /flows.php?bot_id={bot_id}
Authorization: Bearer <token>
```

#### Get Single Flow
```http
GET /flows.php?id={flow_id}
Authorization: Bearer <token>
```

#### Create Flow
```http
POST /flows.php
Authorization: Bearer <token>
Content-Type: application/json

{
  "bot_id": 1,
  "name": "Welcome Flow",
  "trigger_type": "welcome",
  "nodes": [
    {
      "id": "start",
      "type": "message",
      "data": {
        "message": "Hello! Welcome to our website."
      },
      "position": { "x": 100, "y": 100 }
    }
  ],
  "connections": []
}
```

#### Update Flow
```http
PUT /flows.php?id={flow_id}
Authorization: Bearer <token>
Content-Type: application/json

{
  "name": "Updated Flow",
  "nodes": [...],
  "connections": [...]
}
```

#### Delete Flow
```http
DELETE /flows.php?id={flow_id}
Authorization: Bearer <token>
```

---

### Widget (Public)

#### Get Widget Configuration
```http
GET /widget-config.php?id={bot_unique_id}
```

**Response:**
```json
{
  "success": true,
  "config": {
    "bot_id": "bot_abc123",
    "bot_name": "My Chatbot",
    "welcome_message": "Hi there!",
    "primary_color": "#6366f1",
    "position": "bottom-right",
    "auto_popup_delay": 5000
  }
}
```

---

### Chat (Public)

#### Initialize Chat
```http
POST /chat.php
Content-Type: application/json

{
  "action": "init",
  "bot_id": "bot_abc123",
  "visitor_hash": "visitor_xyz789",
  "source_url": "https://example.com/page"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "conversation_id": 123,
    "visitor_id": 456,
    "welcome_message": "Hi there! How can I help?",
    "messages": []
  }
}
```

#### Send Message
```http
POST /chat.php
Content-Type: application/json

{
  "action": "send_message",
  "bot_id": "bot_abc123",
  "conversation_id": 123,
  "visitor_id": 456,
  "message": "What are your pricing plans?"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "message_id": 789,
    "response": "We offer three pricing plans...",
    "type": "ai"
  }
}
```

#### Get Messages
```http
POST /chat.php
Content-Type: application/json

{
  "action": "get_messages",
  "bot_id": "bot_abc123",
  "conversation_id": 123,
  "last_id": 0
}
```

#### Upload File
```http
POST /upload.php
Content-Type: multipart/form-data

bot_id: bot_abc123
conversation_id: 123
file: [binary file data]
```

**Response:**
```json
{
  "success": true,
  "file": {
    "id": 1,
    "name": "document.pdf",
    "url": "https://...",
    "size": 1024567
  }
}
```

---

### Analytics

#### Dashboard Stats
```http
GET /analytics.php?action=dashboard
Authorization: Bearer <token>
```

#### Get Stats
```http
GET /analytics.php?action=stats&period=week
Authorization: Bearer <token>
```

**Periods:** `today`, `yesterday`, `week`, `month`, `year`

#### Get Conversations
```http
GET /analytics.php?action=conversations&page=1&per_page=20
Authorization: Bearer <token>
```

#### Get Leads
```http
GET /analytics.php?action=leads&status=new
Authorization: Bearer <token>
```

#### Get Geo Analytics
```http
GET /analytics.php?action=geo&days=30
Authorization: Bearer <token>
```

#### Get Hourly Stats
```http
GET /analytics.php?action=hours&days=7
Authorization: Bearer <token>
```

#### Get Traffic Sources
```http
GET /analytics.php?action=sources&days=30
Authorization: Bearer <token>
```

#### Export Data
```http
GET /analytics.php?action=export&type=leads&format=csv
Authorization: Bearer <token>
```

---

## Error Responses

All errors follow this format:

```json
{
  "success": false,
  "error": "Error message description"
}
```

### HTTP Status Codes

- `200` - Success
- `400` - Bad Request (invalid parameters)
- `401` - Unauthorized (invalid or missing token)
- `403` - Forbidden (insufficient permissions)
- `404` - Not Found
- `500` - Internal Server Error

## Rate Limiting

API requests are limited to:
- 100 requests per minute per IP (public endpoints)
- 1000 requests per minute per user (authenticated endpoints)

Rate limit headers:
```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1640995200
```

## WebSocket Events

### Connection
```javascript
const ws = new WebSocket('wss://your-domain.com:8080');

ws.onopen = () => {
  ws.send(JSON.stringify({
    action: 'auth',
    token: 'your-jwt-token',
    user_type: 'agent'
  }));
};
```

### Events

#### Agent Joined
```json
{
  "action": "agent_joined",
  "agent_name": "John Doe",
  "message": "John Doe has joined the conversation"
}
```

#### New Message
```json
{
  "action": "new_message",
  "message_id": 123,
  "conversation_id": 456,
  "message": "Hello!",
  "sender_type": "user",
  "created_at": "2024-01-01T12:00:00Z"
}
```

#### Typing Indicator
```json
{
  "action": "typing",
  "sender_type": "user",
  "is_typing": true
}
```

## SDK Examples

### JavaScript
```javascript
// Initialize chat
const initChat = async (botId) => {
  const response = await fetch('https://your-domain.com/api/chat.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      action: 'init',
      bot_id: botId
    })
  });
  return await response.json();
};

// Send message
const sendMessage = async (conversationId, message) => {
  const response = await fetch('https://your-domain.com/api/chat.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      action: 'send_message',
      conversation_id: conversationId,
      message
    })
  });
  return await response.json();
};
```

### PHP
```php
<?php
class ChatbotAPI {
    private $baseUrl = 'https://your-domain.com/api/';
    private $token;

    public function __construct($token) {
        $this->token = $token;
    }

    public function request($endpoint, $method = 'GET', $data = null) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json'
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }

    public function getBots() {
        return $this->request('bots.php');
    }

    public function createBot($data) {
        return $this->request('bots.php', 'POST', $data);
    }
}
```

## Changelog

### v1.0.0
- Initial release
- Core chatbot functionality
- Flow builder
- AI integration
- Analytics dashboard
