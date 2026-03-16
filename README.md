# Chatbot Builder System

A complete, professional chatbot builder system similar to Collect.Chat. Create intelligent chatbots with drag-and-drop flow builder, AI integration, real-time chat, and comprehensive analytics.

![Chatbot Builder](https://img.shields.io/badge/Chatbot-Builder-blue)
![PHP](https://img.shields.io/badge/PHP-8.2-purple)
![React](https://img.shields.io/badge/React-18-blue)
![License](https://img.shields.io/badge/License-MIT-green)

## Features

### Widget Features
- **Easy Embed** - Single script tag installation
- **Auto-popup** - Configurable delay and triggers
- **Mobile Responsive** - Works on all devices
- **File Upload** - Support for images and documents
- **Quick Replies** - Pre-defined response buttons
- **Typing Indicator** - Animated typing animation
- **Sound Notifications** - Audio alerts for new messages
- **Unread Badge** - Red notification dot
- **Star Rating** - Customer satisfaction feedback
- **Multi-language** - Support for multiple languages

### Admin Panel Features
- **Visual Flow Builder** - Drag-and-drop conversation designer
- **AI Integration** - OpenAI GPT-4 support
- **Real-time Chat** - Live agent takeover
- **Lead Management** - CRM with scoring
- **Analytics Dashboard** - Comprehensive reporting
- **Team Management** - Multi-agent support
- **Theme Customization** - 10+ pre-built themes
- **Webhook Integration** - Zapier ready

### Technical Features
- **Pure PHP 8.2** - No framework dependency
- **Vanilla JavaScript** - No jQuery required
- **WebSocket Support** - Real-time communication
- **JWT Authentication** - Secure API access
- **Rate Limiting** - Spam protection
- **File Upload Security** - Validated and sanitized
- **SQL Injection Protection** - Prepared statements
- **XSS Protection** - Output encoding

## Quick Start

### Installation

```bash
# 1. Clone repository
git clone https://github.com/your-repo/chatbot-builder.git
cd chatbot-builder

# 2. Create database
mysql -u root -p < database/schema.sql

# 3. Configure database
# Edit src/Config.php with your database credentials

# 4. Set permissions
chmod -R 755 .
chmod -R 775 assets/uploads/

# 5. Build admin panel
cd admin && npm install && npm run build
```

### Create Your First Chatbot

1. Login to admin panel at `/admin`
2. Click "New Chatbot"
3. Design conversation flow
4. Copy embed code
5. Paste on your website

### Embed Code Example

```html
<!-- Chatbot Widget -->
<script>
(function() {
    var s = document.createElement('script');
    s.src = 'https://yourdomain.com/widget.js?id=YOUR_BOT_ID';
    s.async = true;
    s.setAttribute('data-bot-id', 'YOUR_BOT_ID');
    s.setAttribute('data-position', 'bottom-right');
    s.setAttribute('data-auto-popup', '5000');
    document.body.appendChild(s);
})();
</script>
```

## Screenshots

### Admin Dashboard
![Dashboard](docs/screenshots/dashboard.png)

### Flow Builder
![Flow Builder](docs/screenshots/flow-builder.png)

### Live Chat
![Live Chat](docs/screenshots/live-chat.png)

### Analytics
![Analytics](docs/screenshots/analytics.png)

## File Structure

```
chatbot-system/
├── api/                    # API endpoints
│   ├── auth.php           # Authentication
│   ├── bots.php           # Bot management
│   ├── chat.php           # Chat handler
│   ├── flows.php          # Flow management
│   ├── analytics.php      # Analytics
│   └── upload.php         # File upload
├── src/                    # Core PHP classes
│   ├── Config.php         # Configuration
│   ├── Database.php       # Database handler
│   ├── Chatbot.php        # Main chatbot class
│   ├── FlowEngine.php     # Flow execution
│   ├── AIHandler.php      # OpenAI integration
│   ├── FileManager.php    # File handling
│   └── JWT.php            # Authentication
├── admin/                  # React admin panel
│   ├── src/
│   │   ├── pages/         # Page components
│   │   ├── hooks/         # Custom hooks
│   │   └── layouts/       # Layout components
│   └── dist/              # Build output
├── websocket/              # WebSocket server
│   └── server.php         # Ratchet server
├── widget.js               # Widget JavaScript
├── database/
│   └── schema.sql         # Database schema
└── docs/                   # Documentation
    ├── INSTALLATION.md
    └── API.md
```

## Database Schema

### Core Tables
- `users` - Admin and agent accounts
- `chatbots` - Chatbot configurations
- `visitors` - Website visitors
- `conversations` - Chat sessions
- `messages` - Chat messages
- `flows` - Conversation flows
- `leads` - Captured leads
- `analytics` - Statistics and metrics

## API Documentation

See [API.md](docs/API.md) for complete API reference.

### Quick Example

```javascript
// Initialize chat
const response = await fetch('/api/chat.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    action: 'init',
    bot_id: 'bot_abc123'
  })
});

const data = await response.json();
console.log(data.data.conversation_id);
```

## Node Types (Flow Builder)

| Type | Description |
|------|-------------|
| Message | Send text message |
| Input | Collect user input |
| Image | Send image |
| File | Send file |
| Condition | IF-ELSE logic |
| Delay | Wait duration |
| API | HTTP request |
| AI | GPT-4 response |
| Quick Reply | Button options |
| Rating | Star feedback |
| Transfer | Human handover |

## Configuration

### Environment Variables

```php
// src/Config.php
const DB_HOST = 'localhost';
const DB_NAME = 'chatbot_system';
const DB_USER = 'root';
const DB_PASS = 'password';

const JWT_SECRET = 'your-secret-key';
const OPENAI_API_KEY = 'sk-...';
```

### Theme Presets

- Gradient (Indigo to Purple)
- Minimal (Black & White)
- Corporate (Blue)
- Fun (Orange to Red)
- Nature (Green)
- Ocean (Blue)
- Sunset (Pink to Purple)
- Dark Mode
- Elegant (Violet)
- Fresh (Lime to Green)

## WebSocket Events

### Client to Server
- `auth` - Authenticate connection
- `join_conversation` - Join chat room
- `send_message` - Send message
- `typing` - Typing indicator
- `request_human` - Human takeover

### Server to Client
- `new_message` - New message received
- `agent_joined` - Agent joined chat
- `agent_left` - Agent left chat
- `typing` - User typing status

## Browser Support

- Chrome 80+
- Firefox 75+
- Safari 13+
- Edge 80+
- Opera 67+

## Performance

- Widget size: ~15KB gzipped
- API response: < 100ms average
- Database queries: Optimized with indexes
- Caching: Built-in support

## Security

- Prepared SQL statements
- Input sanitization
- Output encoding
- File upload validation
- Rate limiting
- JWT authentication
- CORS configuration
- XSS protection

## Contributing

1. Fork the repository
2. Create feature branch
3. Commit changes
4. Push to branch
5. Create Pull Request

## License

MIT License - see [LICENSE](LICENSE) file

## Support

- Documentation: https://docs.chatbot.com
- Email: support@chatbot.com
- GitHub Issues: https://github.com/your-repo/chatbot-builder/issues

## Roadmap

- [ ] WhatsApp Business API integration
- [ ] Facebook Messenger support
- [ ] Telegram Bot API
- [ ] Multi-language AI responses
- [ ] Advanced analytics
- [ ] Mobile app for agents
- [ ] Voice messages
- [ ] Video chat

## Credits

Built with:
- PHP 8.2
- React 18
- Tailwind CSS
- shadcn/ui
- Ratchet WebSocket
- OpenAI GPT-4

---

**Made with ❤️ for better customer conversations**
