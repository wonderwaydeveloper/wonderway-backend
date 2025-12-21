# WonderWay Backend - Production Deployment Guide

## 🚀 Production Deployment

### Prerequisites

- **Server Requirements:**
  - Ubuntu 20.04+ or CentOS 8+
  - 4+ CPU cores
  - 8GB+ RAM
  - 100GB+ SSD storage
  - Docker & Docker Compose installed

- **Domain & SSL:**
  - Domain name configured
  - SSL certificate (Let's Encrypt recommended)

### Quick Deployment

```bash
# 1. Clone repository
git clone https://github.com/your-org/wonderway-backend.git
cd wonderway-backend

# 2. Configure environment
cp .env.example .env.production
nano .env.production

# 3. Deploy with Docker
docker-compose -f docker-compose.yml up -d

# 4. Initialize application
docker-compose exec app php artisan migrate --force
docker-compose exec app php artisan db:seed --force
docker-compose exec app php artisan storage:link
```

## 📋 Environment Configuration

### Required Environment Variables

```env
# Application
APP_NAME="WonderWay"
APP_ENV=production
APP_KEY=base64:your-32-character-secret-key
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=wonderway
DB_USERNAME=wonderway
DB_PASSWORD=secure_random_password

# Redis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Mail
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-email
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls

# Broadcasting
BROADCAST_DRIVER=reverb
REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_HOST=your-domain.com
REVERB_PORT=443
REVERB_SCHEME=https

# Security
ENCRYPTION_KEY=your-encryption-key
JWT_SECRET=your-jwt-secret

# File Storage
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket-name

# Monitoring
SENTRY_LARAVEL_DSN=your-sentry-dsn
```

## 🐳 Docker Production Setup

### 1. Production Docker Compose

```yaml
# docker-compose.prod.yml
version: '3.8'

services:
  app:
    image: wonderway/backend:latest
    container_name: wonderway-app
    restart: unless-stopped
    environment:
      - APP_ENV=production
    volumes:
      - storage_data:/var/www/html/storage
    networks:
      - wonderway-network

  nginx:
    image: nginx:alpine
    container_name: wonderway-nginx
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./docker/nginx-prod.conf:/etc/nginx/nginx.conf
      - ./ssl:/etc/nginx/ssl
    networks:
      - wonderway-network

  mysql:
    image: mysql:8.0
    container_name: wonderway-mysql
    restart: unless-stopped
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
      MYSQL_DATABASE: ${DB_DATABASE}
      MYSQL_USER: ${DB_USERNAME}
      MYSQL_PASSWORD: ${DB_PASSWORD}
    volumes:
      - mysql_data:/var/lib/mysql
    networks:
      - wonderway-network

  redis:
    image: redis:7-alpine
    container_name: wonderway-redis
    restart: unless-stopped
    volumes:
      - redis_data:/data
    networks:
      - wonderway-network

volumes:
  mysql_data:
  redis_data:
  storage_data:

networks:
  wonderway-network:
    driver: bridge
```

### 2. Deploy to Production

```bash
# Deploy latest version
docker-compose -f docker-compose.prod.yml pull
docker-compose -f docker-compose.prod.yml up -d

# Run migrations
docker-compose exec app php artisan migrate --force

# Clear and optimize caches
docker-compose exec app php artisan config:cache
docker-compose exec app php artisan route:cache
docker-compose exec app php artisan view:cache
docker-compose exec app php artisan optimize
```

## 🔧 Server Configuration

### 1. Nginx Configuration

```nginx
# /etc/nginx/sites-available/wonderway
server {
    listen 80;
    server_name your-domain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name your-domain.com;
    root /var/www/html/public;
    index index.php;

    # SSL Configuration
    ssl_certificate /etc/nginx/ssl/cert.pem;
    ssl_certificate_key /etc/nginx/ssl/key.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # Gzip Compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml;

    # Rate Limiting
    limit_req_zone $binary_remote_addr zone=api:10m rate=10r/s;
    limit_req zone=api burst=20 nodelay;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # WebSocket proxy for Laravel Reverb
    location /app/ {
        proxy_pass http://websocket:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

### 2. SSL Certificate Setup

```bash
# Install Certbot
sudo apt install certbot python3-certbot-nginx

# Generate SSL certificate
sudo certbot --nginx -d your-domain.com

# Auto-renewal
sudo crontab -e
# Add: 0 12 * * * /usr/bin/certbot renew --quiet
```

## 📊 Monitoring & Logging

### 1. Application Monitoring

```bash
# Install monitoring stack
docker-compose -f docker-compose.monitoring.yml up -d

# Access dashboards
# Grafana: http://your-domain:3000 (admin/admin123)
# Prometheus: http://your-domain:9090
```

### 2. Log Management

```bash
# View application logs
docker-compose logs -f app

# View specific service logs
docker-compose logs -f nginx
docker-compose logs -f mysql
docker-compose logs -f redis

# Log rotation setup
sudo nano /etc/logrotate.d/wonderway
```

### 3. Health Checks

```bash
# Application health
curl https://your-domain.com/health

# Database health
docker-compose exec mysql mysqladmin ping

# Redis health
docker-compose exec redis redis-cli ping

# Queue status
docker-compose exec app php artisan queue:monitor
```

## 🔄 Backup & Recovery

### 1. Database Backup

```bash
#!/bin/bash
# backup.sh
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups"

# Create backup directory
mkdir -p $BACKUP_DIR

# Database backup
docker-compose exec mysql mysqldump -u root -p$MYSQL_ROOT_PASSWORD wonderway > $BACKUP_DIR/db_backup_$DATE.sql

# Compress backup
gzip $BACKUP_DIR/db_backup_$DATE.sql

# Upload to S3 (optional)
aws s3 cp $BACKUP_DIR/db_backup_$DATE.sql.gz s3://your-backup-bucket/

# Clean old backups (keep last 7 days)
find $BACKUP_DIR -name "db_backup_*.sql.gz" -mtime +7 -delete
```

### 2. File Storage Backup

```bash
# Backup storage files
tar -czf storage_backup_$(date +%Y%m%d).tar.gz storage/

# Upload to S3
aws s3 cp storage_backup_$(date +%Y%m%d).tar.gz s3://your-backup-bucket/
```

### 3. Automated Backup

```bash
# Add to crontab
0 2 * * * /path/to/backup.sh
```

## 🚨 Troubleshooting

### Common Issues

1. **Application not starting:**
   ```bash
   # Check logs
   docker-compose logs app
   
   # Verify environment
   docker-compose exec app php artisan config:show
   ```

2. **Database connection issues:**
   ```bash
   # Test connection
   docker-compose exec app php artisan tinker
   # DB::connection()->getPdo();
   ```

3. **Queue not processing:**
   ```bash
   # Restart queue workers
   docker-compose restart queue
   
   # Check queue status
   docker-compose exec app php artisan queue:monitor
   ```

4. **WebSocket connection issues:**
   ```bash
   # Check Reverb status
   docker-compose logs websocket
   
   # Test WebSocket connection
   wscat -c ws://your-domain.com:8080/app/your-app-key
   ```

### Performance Optimization

1. **Database optimization:**
   ```sql
   -- Add indexes for better performance
   CREATE INDEX idx_posts_user_created ON posts(user_id, created_at);
   CREATE INDEX idx_follows_follower ON follows(follower_id);
   ```

2. **Cache optimization:**
   ```bash
   # Warm up caches
   docker-compose exec app php artisan cache:warmup
   
   # Monitor cache hit rates
   docker-compose exec redis redis-cli info stats
   ```

3. **Queue optimization:**
   ```bash
   # Scale queue workers
   docker-compose up -d --scale queue=3
   ```

## 📈 Scaling

### Horizontal Scaling

```yaml
# docker-compose.scale.yml
services:
  app:
    deploy:
      replicas: 3
  
  queue:
    deploy:
      replicas: 5

  nginx:
    image: nginx:alpine
    volumes:
      - ./docker/nginx-lb.conf:/etc/nginx/nginx.conf
```

### Load Balancer Configuration

```nginx
upstream app_servers {
    server app1:9000;
    server app2:9000;
    server app3:9000;
}

server {
    location ~ \.php$ {
        fastcgi_pass app_servers;
    }
}
```

## 🔐 Security Checklist

- [ ] SSL certificate installed and configured
- [ ] Firewall configured (only ports 80, 443, 22 open)
- [ ] Database access restricted to application only
- [ ] Strong passwords for all services
- [ ] Regular security updates applied
- [ ] Backup encryption enabled
- [ ] Monitoring and alerting configured
- [ ] Rate limiting enabled
- [ ] Security headers configured

## 📞 Support

For production support:
- Email: support@wonderway.com
- Slack: #wonderway-production
- Documentation: https://docs.wonderway.com

---

**Production Deployment Complete! 🎉**

Your WonderWay Backend is now running in production with enterprise-grade security, monitoring, and scalability.