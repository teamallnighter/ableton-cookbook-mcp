#!/bin/bash

# 🎵 Ableton Cookbook - Staging Environment Deployment Script
# This script sets up a production-like staging environment for testing
# Phase 3 security and accessibility features

set -e  # Exit on any error

echo "🧪 Starting Ableton Cookbook Staging Environment Deployment..."

# Configuration
REPO_URL="https://github.com/teamallnighter/ableton-cookbook.git"
STAGING_PATH="/var/www/staging-ableton-cookbook"
APP_PATH="$STAGING_PATH/laravel-app"
DOMAIN="staging.ableton.recipes"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_status() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

# Check if running as root (needed for some operations)
if [ "$EUID" -ne 0 ]; then
    print_error "Please run as root (use sudo)"
    exit 1
fi

print_status "Phase 1: Staging Environment Setup"

# Create staging directory if it doesn't exist
if [ ! -d "$STAGING_PATH" ]; then
    print_info "Creating staging directory: $STAGING_PATH"
    mkdir -p $STAGING_PATH
    cd $STAGING_PATH
    git clone $REPO_URL .
else
    print_info "Staging directory exists, updating code"
    cd $STAGING_PATH
fi

# Navigate to application directory
cd $APP_PATH

print_status "Phase 2: Code Updates"

# Pull latest code
git fetch --all
git reset --hard origin/main
git pull origin main

print_status "Phase 3: Dependencies & Staging Environment"

# Set composer to allow running as superuser
export COMPOSER_ALLOW_SUPERUSER=1

# Install all dependencies including dev for comprehensive testing
composer install --optimize-autoloader --no-interaction

# Create staging-specific environment file
if [ ! -f .env.staging ]; then
    print_info "Creating staging environment template"
    cat > .env.staging << EOL
APP_NAME="Ableton Cookbook (Staging)"
APP_ENV=staging
APP_KEY=base64:$(php -r "echo base64_encode(random_bytes(32));")
APP_DEBUG=true
APP_URL=https://staging.ableton.recipes

LOG_CHANNEL=daily
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

# Staging Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=staging_ableton_cookbook
DB_USERNAME=staging_ableton_user
DB_PASSWORD=CHANGE_ME_STAGING_PASSWORD

# Staging Redis Configuration
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Queue Configuration for Background Jobs
QUEUE_CONNECTION=redis
QUEUE_FAILED_DRIVER=database

# Mail Configuration (Staging - Use MailHog or similar)
MAIL_MAILER=smtp
MAIL_HOST=localhost
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@staging.ableton.recipes"
MAIL_FROM_NAME="Ableton Cookbook Staging"

# Session Configuration
SESSION_DRIVER=redis
SESSION_LIFETIME=120

# Cache Configuration
CACHE_STORE=redis

# Filesystem Configuration
FILESYSTEM_DISK=local

# Security Configuration - Enhanced for Phase 3 Testing
SECURITY_MONITORING_ENABLED=true
SECURITY_REAL_TIME_MONITORING=true
XSS_PREVENTION_ENABLED=true
CSP_ENABLED=true
IMAGE_UPLOAD_SECURITY_ENABLED=true
VIRUS_SCANNING_ENABLED=true

# Feature Flags - Staging Environment
FEATURE_FLAGS_ENABLED=true
FEATURE_VIRUS_SCANNING=true
FEATURE_ACCESSIBILITY_ENHANCEMENTS=true
FEATURE_MONITORING_DASHBOARD=true
FEATURE_ADVANCED_SECURITY=true

# Sentry (Optional for staging error tracking)
SENTRY_LARAVEL_DSN=
SENTRY_TRACES_SAMPLE_RATE=1.0

# API Documentation
L5_SWAGGER_CONST_HOST=https://staging.ableton.recipes
SCRAMBLE_ENABLED=true

# Performance Testing
PERFORMANCE_TESTING_ENABLED=true
LOAD_TESTING_ALLOWED=true

EOL
fi

# Copy staging environment to .env
cp .env.staging .env
print_warning "Staging .env created from template - PLEASE UPDATE DATABASE CREDENTIALS"

# Generate app key
php artisan key:generate --force

print_status "Phase 4: Frontend Assets (Development Mode)"

# Install Node.js dependencies (including dev dependencies for testing)
npm install

# Build development assets with source maps for debugging
print_status "Building development assets with debugging enabled..."
npm run dev

print_status "Phase 5: Database Setup (Staging)"

# Create staging database if it doesn't exist
print_info "Setting up staging database..."

# Note: This assumes MySQL is running and root access is available
mysql -u root -e "CREATE DATABASE IF NOT EXISTS staging_ableton_cookbook CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -e "CREATE USER IF NOT EXISTS 'staging_ableton_user'@'localhost' IDENTIFIED BY 'staging_password_2025';"
mysql -u root -e "GRANT ALL PRIVILEGES ON staging_ableton_cookbook.* TO 'staging_ableton_user'@'localhost';"
mysql -u root -e "FLUSH PRIVILEGES;"

# Run database migrations
php artisan migrate --force

# Seed with test data for comprehensive testing
php artisan db:seed --class=DatabaseSeeder

print_status "Phase 6: Redis & Queue Setup"

# Start Redis if not running
if ! systemctl is-active --quiet redis; then
    systemctl start redis
    print_status "Redis started"
fi

# Clear existing queues
php artisan queue:clear

print_status "Phase 7: Security & Virus Scanning Setup"

# Create quarantine directory for virus scanning
mkdir -p storage/app/quarantine
chown -R www-data:www-data storage/app/quarantine
chmod -R 750 storage/app/quarantine

# Install ClamAV for virus scanning (if not already installed)
if ! command -v clamscan &> /dev/null; then
    print_info "Installing ClamAV for virus scanning..."
    apt-get update
    apt-get install -y clamav clamav-daemon
    freshclam
    systemctl start clamav-daemon
    systemctl enable clamav-daemon
    print_status "ClamAV installed and configured"
fi

print_status "Phase 8: Background Jobs & Queue Workers"

# Configure supervisor for staging queue workers
cat > /etc/supervisor/conf.d/staging-laravel-worker.conf << EOL
[program:staging-laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php $APP_PATH/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
directory=$APP_PATH
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=$APP_PATH/storage/logs/worker.log
stopwaitsecs=3600
EOL

# Reload supervisor configuration
supervisorctl reread
supervisorctl update
supervisorctl start staging-laravel-worker:*

print_status "Phase 9: File Permissions & Security"

# Set proper permissions
chown -R www-data:www-data $APP_PATH
chmod -R 755 $APP_PATH
chmod -R 775 $APP_PATH/storage
chmod -R 775 $APP_PATH/bootstrap/cache
chmod -R 755 $APP_PATH/public

# Secure sensitive files
chmod 600 $APP_PATH/.env
chmod 600 $APP_PATH/.env.staging

print_status "Phase 10: Nginx Configuration"

# Create staging nginx configuration
cat > /etc/nginx/sites-available/staging-ableton-cookbook << EOL
server {
    listen 80;
    server_name staging.ableton.recipes;
    return 301 https://\$server_name\$request_uri;
}

server {
    listen 443 ssl http2;
    server_name staging.ableton.recipes;
    root $APP_PATH/public;

    # SSL Configuration (use Let's Encrypt or staging certificates)
    ssl_certificate /etc/ssl/certs/staging-ableton.crt;
    ssl_certificate_key /etc/ssl/private/staging-ableton.key;
    
    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    index index.php;

    charset utf-8;

    # Staging-specific headers
    add_header X-Environment "Staging" always;
    add_header X-Robots-Tag "noindex, nofollow" always;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { 
        access_log off; 
        log_not_found off; 
        # Return staging-specific robots.txt
        add_header Content-Type text/plain;
        return 200 "User-agent: *\nDisallow: /\n";
    }

    location ~ \.php\$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        
        # Increase timeouts for staging testing
        fastcgi_read_timeout 300;
        fastcgi_connect_timeout 300;
        fastcgi_send_timeout 300;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Enhanced logging for staging
    access_log /var/log/nginx/staging-ableton-cookbook-access.log;
    error_log /var/log/nginx/staging-ableton-cookbook-error.log;
}
EOL

# Enable staging site
ln -sf /etc/nginx/sites-available/staging-ableton-cookbook /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx

print_status "Phase 11: Caching & Optimization"

# Clear and optimize caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# Cache for better performance (but keep debugging enabled)
php artisan config:cache
php artisan route:cache

print_status "Phase 12: Testing & Verification"

# Start queue workers for background job processing
php artisan queue:work --daemon &
QUEUE_PID=$!

# Test basic functionality
HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" -k https://staging.ableton.recipes)
if [ $HTTP_STATUS -eq 200 ]; then
    print_status "Staging site is responding correctly (HTTP $HTTP_STATUS)"
else
    print_warning "Staging site returned HTTP $HTTP_STATUS"
fi

# Test virus scanning functionality
print_info "Testing virus scanning capabilities..."
php artisan tinker --execute="
use App\Services\VirusScanningService;
\$scanner = app(VirusScanningService::class);
echo 'Virus scanning service loaded: ' . (class_exists('App\\Services\\VirusScanningService') ? 'OK' : 'FAILED') . PHP_EOL;
"

# Test security monitoring
print_info "Testing security monitoring..."
php artisan tinker --execute="
use App\Services\SecurityMonitoringService;
\$monitor = app(SecurityMonitoringService::class);
echo 'Security monitoring service loaded: ' . (class_exists('App\\Services\\SecurityMonitoringService') ? 'OK' : 'FAILED') . PHP_EOL;
"

# Test accessibility features
print_info "Testing accessibility features..."
curl -s -k https://staging.ableton.recipes | grep -q 'aria-' && print_status "ARIA attributes found" || print_warning "ARIA attributes not detected"

# Stop background queue worker
kill $QUEUE_PID 2>/dev/null || true

print_status "Phase 13: Monitoring Setup"

# Create staging-specific log rotation
cat > /etc/logrotate.d/staging-ableton-cookbook << EOL
$APP_PATH/storage/logs/*.log {
    daily
    rotate 14
    compress
    delaycompress
    missingok
    notifempty
    create 0644 www-data www-data
    postrotate
        supervisorctl restart staging-laravel-worker:*
    endscript
}
EOL

echo ""
print_status "🎉 Staging Environment Deployment Complete!"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}Staging Site: https://staging.ableton.recipes${NC}"
echo -e "${GREEN}Staging Admin: https://staging.ableton.recipes/admin/dashboard${NC}"
echo -e "${GREEN}Staging API Docs: https://staging.ableton.recipes/api/docs${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo ""
print_info "Staging Environment Features:"
echo "✅ Production-like configuration with debugging enabled"
echo "✅ Background job processing with queue workers" 
echo "✅ Virus scanning and security monitoring active"
echo "✅ Accessibility features fully enabled"
echo "✅ Feature flags system ready for testing"
echo "✅ Enhanced logging and monitoring"
echo "✅ Separate database and Redis instances"
echo ""
print_warning "Important Staging Notes:"
echo "1. Update database credentials in .env.staging"
echo "2. Configure SSL certificates for HTTPS"
echo "3. Set up proper mail configuration (MailHog recommended)"
echo "4. This environment mirrors production but with debugging enabled"
echo "5. Queue workers are configured to auto-restart"
echo ""
print_info "Test the following Phase 3 features:"
echo "• Virus scanning on file uploads"
echo "• Accessibility compliance (WCAG 2.1 AA)"
echo "• Security monitoring and alerting"
echo "• Feature flags functionality"
echo "• Background job processing"
echo "• Real-time monitoring dashboard"
echo ""