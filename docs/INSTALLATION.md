# Chatbot Builder System - Installation Guide

## System Requirements

- PHP 8.2 or higher
- MySQL 5.7 or higher / MariaDB 10.3 or higher
- Apache/Nginx web server
- SSL certificate (recommended for production)
- Node.js 18+ (for admin panel build)

## Installation Steps

### 1. Upload Files

Upload all files to your web server directory (e.g., `/var/www/chatbot/`).

```bash
# Using SCP
scp -r chatbot-system/* user@your-server:/var/www/chatbot/

# Or using FTP/SFTP
# Upload all files to your web root directory
```

### 2. Create Database

```bash
# Login to MySQL
mysql -u root -p

# Create database
CREATE DATABASE chatbot_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# Create user (optional but recommended)
CREATE USER 'chatbot_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON chatbot_system.* TO 'chatbot_user'@'localhost';
FLUSH PRIVILEGES;
```

### 3. Import Database Schema

```bash
mysql -u root -p chatbot_system < database/schema.sql
```

### 4. Configure Database Connection

Edit `src/Config.php` and update database credentials:

```php
const DB_HOST = 'localhost';
const DB_NAME = 'chatbot_system';
const DB_USER = 'chatbot_user';
const DB_PASS = 'your_secure_password';
```

### 5. Set File Permissions

```bash
# Set ownership (adjust www-data to your web server user)
chown -R www-data:www-data /var/www/chatbot/

# Set permissions
chmod -R 755 /var/www/chatbot/
chmod -R 775 /var/www/chatbot/assets/uploads/
```

### 6. Configure Web Server

#### Apache (.htaccess)

Create `.htaccess` file in the root directory:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# Protect sensitive files
<FilesMatch "^\.">
    Order allow,deny
    Deny from all
</FilesMatch>

# Disable directory browsing
Options -Indexes

# PHP settings
php_value upload_max_filesize 10M
php_value post_max_size 10M
php_value max_execution_time 300
```

#### Nginx

Add to your server block:

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/chatbot;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(ht|git|env) {
        deny all;
    }

    location /assets/uploads/ {
        deny all;
    }
}
```

### 7. Build Admin Panel

```bash
cd admin
npm install
npm run build
```

### 8. Configure WebSocket Server (Optional)

For real-time features, start the WebSocket server:

```bash
# Install Ratchet PHP
composer require cboden/ratchet

# Start WebSocket server
php websocket/server.php

# Or use Supervisor for production
```

#### Supervisor Configuration

Create `/etc/supervisor/conf.d/chatbot-websocket.conf`:

```ini
[program:chatbot-websocket]
command=php /var/www/chatbot/websocket/server.php
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/chatbot-websocket.log
```

```bash
supervisorctl reread
supervisorctl update
supervisorctl start chatbot-websocket
```

### 9. Configure Cron Jobs

```bash
# Edit crontab
crontab -e

# Add these lines
# Close inactive conversations (every 5 minutes)
*/5 * * * * php /var/www/chatbot/cron/close-inactive.php

# Daily analytics aggregation
0 0 * * * php /var/www/chatbot/cron/daily-analytics.php

# Clean old files (weekly)
0 0 * * 0 php /var/www/chatbot/cron/cleanup.php
```

### 10. SSL Certificate (Let's Encrypt)

```bash
# Install Certbot
sudo apt install certbot python3-certbot-nginx

# Obtain certificate
sudo certbot --nginx -d your-domain.com

# Auto-renewal is configured automatically
```

## Post-Installation

### 1. Access Admin Panel

Navigate to `https://your-domain.com/admin`

Default login:
- Email: `admin@chatbot.com`
- Password: `admin123`

**IMPORTANT:** Change the default password immediately!

### 2. Create Your First Chatbot

1. Login to admin panel
2. Click "New Chatbot"
3. Configure settings
4. Build conversation flow
5. Copy embed code
6. Paste on your website

### 3. Configure AI (Optional)

1. Go to AI Config
2. Add your OpenAI API key
3. Configure system message
4. Upload knowledge base documents
5. Enable AI responses

## Security Checklist

- [ ] Change default admin password
- [ ] Update JWT secret in Config.php
- [ ] Enable HTTPS
- [ ] Set strong database password
- [ ] Configure firewall (allow only ports 80, 443, 8080)
- [ ] Disable PHP error display in production
- [ ] Set up regular backups
- [ ] Configure rate limiting
- [ ] Enable SQL injection protection
- [ ] Set secure file upload restrictions

## Troubleshooting

### Database Connection Error

```
Check:
1. Database credentials in src/Config.php
2. Database exists and user has permissions
3. MySQL service is running: sudo service mysql status
```

### 500 Internal Server Error

```
Check:
1. PHP version (must be 8.2+)
2. File permissions (755 for directories, 644 for files)
3. Apache/Nginx error logs
4. PHP error log: /var/log/php_errors.log
```

### Widget Not Loading

```
Check:
1. Correct bot ID in embed code
2. CORS headers are configured
3. JavaScript is enabled in browser
4. No ad blocker is blocking the script
```

### WebSocket Connection Failed

```
Check:
1. WebSocket server is running
2. Port 8080 is open in firewall
3. Correct WebSocket URL in config
4. SSL certificate for wss://
```

## Updating

### Backup First

```bash
# Backup database
mysqldump -u root -p chatbot_system > backup_$(date +%Y%m%d).sql

# Backup files
tar -czf backup_$(date +%Y%m%d).tar.gz /var/www/chatbot/
```

### Update Files

```bash
# Download new version
# Extract and overwrite files
# Run database migrations if any
php database/migrate.php
```

## Support

For support, please contact:
- Email: support@chatbot.com
- Documentation: https://docs.chatbot.com
- GitHub Issues: https://github.com/your-repo/chatbot-builder/issues

## License

This software is licensed under the MIT License.
