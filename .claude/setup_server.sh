#!/bin/bash
set -e

echo "🚀 Setting up Ableton Cookbook on Spaceship server..."

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
DOMAIN="ableton.recipes"
APP_PATH="/var/www/ableton-cookbook"
CURRENT_PATH="$APP_PATH/current"
SHARED_PATH="$APP_PATH/shared"

echo -e "${YELLOW}Step 1: Creating Nginx configuration...${NC}"
cat > /etc/nginx/sites-available/ableton-cookbook <<'EOF'
server {
    listen 80;
    listen [::]:80;
    server_name ableton.recipes www.ableton.recipes;
    
    # Redirect to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name ableton.recipes www.ableton.recipes;
    
    root /var/www/ableton-cookbook/current/public;
    index index.php index.html;
    
    # SSL certificates (let them get created first, then uncomment)
    # ssl_certificate /etc/letsencrypt/live/ableton.recipes/fullchain.pem;
    # ssl_certificate_key /etc/letsencrypt/live/ableton.recipes/privkey.pem;
    # ssl_protocols TLSv1.2 TLSv1.3;
    # ssl_ciphers HIGH:!aNULL:!MD5;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    
    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_types text/plain text/css text/xml text/javascript application/json application/javascript application/xml+rss;
    
    # Client max body size (for file uploads)
    client_max_body_size 20M;
    
    # Logs
    access_log /var/log/nginx/ableton-cookbook-access.log;
    error_log /var/log/nginx/ableton-cookbook-error.log;
    
    # Laravel public directory
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    # PHP-FPM configuration
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        
        # Performance tuning
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        fastcgi_busy_buffers_size 256k;
        fastcgi_read_timeout 300;
    }
    
    # Deny access to sensitive files
    location ~ /\.(?!well-known).* {
        deny all;
    }
    
    # Cache static assets
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
EOF

echo -e "${GREEN}✓ Nginx config created${NC}"

echo -e "${YELLOW}Step 2: Enabling site...${NC}"
ln -sf /etc/nginx/sites-available/ableton-cookbook /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
echo -e "${GREEN}✓ Site enabled${NC}"

echo -e "${YELLOW}Step 3: Creating shared directories...${NC}"
mkdir -p $SHARED_PATH/storage/app/public
mkdir -p $SHARED_PATH/storage/framework/cache
mkdir -p $SHARED_PATH/storage/framework/sessions
mkdir -p $SHARED_PATH/storage/framework/views
mkdir -p $SHARED_PATH/storage/logs
chown -R deploy:www-data $SHARED_PATH
chmod -R 775 $SHARED_PATH/storage
echo -e "${GREEN}✓ Shared directories created${NC}"

echo -e "${YELLOW}Step 4: Creating .env file...${NC}"
if [ ! -f "$SHARED_PATH/.env" ]; then
    cat > $SHARED_PATH/.env <<'ENVEOF'
APP_NAME="Ableton Cookbook"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_TIMEZONE=UTC
APP_URL=https://ableton.recipes

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file
APP_MAINTENANCE_STORE=database

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ableton_cookbook
DB_USERNAME=ableton_user
DB_PASSWORD=CHANGE_ME_SECURE_PASSWORD

SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=.ableton.recipes

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=redis

CACHE_STORE=redis
CACHE_PREFIX=

MEMCACHED_HOST=127.0.0.1

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=log
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@ableton.recipes"
MAIL_FROM_NAME="${APP_NAME}"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

VITE_APP_NAME="${APP_NAME}"
ENVEOF
    
    echo -e "${GREEN}✓ .env file created${NC}"
    echo -e "${YELLOW}⚠️  IMPORTANT: Update database credentials in $SHARED_PATH/.env${NC}"
else
    echo -e "${YELLOW}ℹ️  .env file already exists, skipping${NC}"
fi

echo -e "${YELLOW}Step 5: Setting up database...${NC}"
read -p "Enter MySQL root password: " -s MYSQL_ROOT_PASS
echo
read -p "Enter password for ableton_user: " -s DB_PASS
echo

mysql -u root -p"$MYSQL_ROOT_PASS" <<SQLEOF
CREATE DATABASE IF NOT EXISTS ableton_cookbook CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'ableton_user'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON ableton_cookbook.* TO 'ableton_user'@'localhost';
FLUSH PRIVILEGES;
SQLEOF

# Update .env with actual password
sed -i "s/DB_PASSWORD=CHANGE_ME_SECURE_PASSWORD/DB_PASSWORD=$DB_PASS/" $SHARED_PATH/.env

echo -e "${GREEN}✓ Database created and user configured${NC}"

echo -e "${YELLOW}Step 6: Waiting for deployment to complete...${NC}"
while [ ! -d "$CURRENT_PATH" ]; do
    echo "Waiting for GitHub Actions to deploy..."
    sleep 5
done
echo -e "${GREEN}✓ Deployment detected${NC}"

echo -e "${YELLOW}Step 7: Generating application key...${NC}"
cd $CURRENT_PATH
sudo -u deploy php artisan key:generate --force
echo -e "${GREEN}✓ App key generated${NC}"

echo -e "${YELLOW}Step 8: Running migrations...${NC}"
sudo -u deploy php artisan migrate --force
echo -e "${GREEN}✓ Migrations completed${NC}"

echo -e "${YELLOW}Step 9: Optimizing application...${NC}"
sudo -u deploy php artisan config:cache
sudo -u deploy php artisan route:cache
sudo -u deploy php artisan view:cache
echo -e "${GREEN}✓ Application optimized${NC}"

echo -e "${YELLOW}Step 10: Setting up queue worker (optional)...${NC}"
cat > /etc/supervisor/conf.d/ableton-cookbook-worker.conf <<'SUPERVISOREOF'
[program:ableton-cookbook-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/ableton-cookbook/current/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=deploy
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/ableton-cookbook/shared/storage/logs/worker.log
stopwaitsecs=3600
SUPERVISOREOF

supervisorctl reread
supervisorctl update
supervisorctl start ableton-cookbook-worker:*
echo -e "${GREEN}✓ Queue workers started${NC}"

echo ""
echo -e "${GREEN}═══════════════════════════════════════════════════${NC}"
echo -e "${GREEN}✓ Setup complete!${NC}"
echo -e "${GREEN}═══════════════════════════════════════════════════${NC}"
echo ""
echo -e "${YELLOW}Next steps:${NC}"
echo "1. Visit https://ableton.recipes (note: SSL cert needs setup first)"
echo "2. Set up SSL: certbot --nginx -d ableton.recipes -d www.ableton.recipes"
echo "3. Create admin user: cd $CURRENT_PATH && php artisan tinker"
echo "   Then run: User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('password')]);"
echo ""
echo -e "${YELLOW}Configuration files:${NC}"
echo "• Nginx: /etc/nginx/sites-available/ableton-cookbook"
echo "• .env: $SHARED_PATH/.env"
echo "• App: $CURRENT_PATH"
echo ""
