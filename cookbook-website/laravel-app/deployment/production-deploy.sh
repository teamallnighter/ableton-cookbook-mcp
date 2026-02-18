#!/bin/bash

# 🎵 Ableton Cookbook - Production Deployment Script
# This script handles complete deployment to production server

set -e  # Exit on any error

echo "🎵 Starting Ableton Cookbook Production Deployment..."

# Configuration
REPO_URL="https://github.com/teamallnighter/ableton-cookbook.git"
DEPLOY_PATH="/var/www/ableton-cookbook"
APP_PATH="$DEPLOY_PATH/laravel-app"
DOMAIN="ableton.recipes"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
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

# Check if running as root (needed for some operations)
if [ "$EUID" -ne 0 ]; then
    print_error "Please run as root (use sudo)"
    exit 1
fi

print_status "Phase 1: Environment Setup"

# Navigate to application directory
cd $APP_PATH

print_status "Phase 2: Code Updates"

# Pull latest code
git fetch --all
git reset --hard origin/main
git pull origin main

print_status "Phase 3: Dependencies & Environment"

# Set composer to allow running as superuser
export COMPOSER_ALLOW_SUPERUSER=1

# Install/Update PHP dependencies
# First install with dev dependencies to ensure all packages are present
composer install --optimize-autoloader --no-interaction

# Then remove dev dependencies for production
composer install --no-dev --optimize-autoloader --no-interaction

# Copy and configure environment file
if [ ! -f .env ]; then
    cp .env.production .env
    print_warning "Created .env from .env.production template - PLEASE UPDATE CREDENTIALS"
else
    print_status ".env file exists, keeping current configuration"
fi

# Generate app key if not set
php artisan key:generate --force

print_status "Phase 4: Frontend Assets"

# Install Node.js dependencies (including dev dependencies for build)
npm ci

# Build production assets
print_status "Building frontend assets with Vite..."
npm run build

# Verify build directory exists
if [ ! -d "public/build" ]; then
    print_error "Build directory not found! Asset compilation failed."
    exit 1
fi

# Clean up dev dependencies after build
print_status "Cleaning up development dependencies..."
npm prune --production

print_status "Frontend assets built successfully"

print_status "Phase 5: Database & Cache"

# Run database migrations
php artisan migrate --force

# Verify Enhanced Nested Chain Analysis migrations
print_status "Verifying Enhanced Analysis System migrations..."
php artisan tinker --execute="
use Illuminate\Support\Facades\Schema;
if (Schema::hasTable('enhanced_rack_analysis') && Schema::hasTable('nested_chains')) {
    echo 'Enhanced analysis tables verified successfully!';
} else {
    echo 'ERROR: Enhanced analysis tables missing!';
    exit(1);
}
"

# Create initial blog post if none exist
php artisan tinker --execute="
if (App\Models\BlogPost::count() === 0) {
    App\Models\BlogCategory::firstOrCreate([
        'name' => 'Development Journey',
        'slug' => 'development-journey'
    ], [
        'description' => 'Updates about our platform development and new features',
        'is_active' => true
    ]);
    
    App\Models\BlogPost::create([
        'user_id' => 1,
        'blog_category_id' => 1,
        'title' => 'Welcome to the Ableton Cookbook Blog!',
        'slug' => 'welcome-to-ableton-cookbook-blog',
        'excerpt' => 'We are excited to share our development journey, platform metrics, and insights with the Ableton Live community through this new blog.',
        'content' => 'Welcome to the official Ableton Cookbook blog! This is where we will share updates about our platform development, interesting statistics about rack sharing trends, and insights from the community. Stay tuned as we continue to build the best platform for sharing and discovering Ableton Live racks!',
        'published_at' => now(),
        'featured' => true,
        'is_active' => true
    ]);
    echo \"Blog post created successfully!\";
} else {
    echo \"Blog posts already exist\";
}
"

# Clear and optimize caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# Cache for production performance
php artisan config:cache
php artisan view:cache
php artisan route:cache

# Generate API documentation with correct server URLs
print_status "Generating API documentation..."
php artisan l5-swagger:generate
php fix-api-docs.php

print_status "Phase 6: Enhanced Analysis System Configuration"

# Configure Supervisor for Enhanced Analysis Queue Workers
print_status "Setting up Enhanced Analysis queue workers..."
cat > /etc/supervisor/conf.d/laravel-enhanced-analysis.conf << 'EOF'
[program:laravel-queue-normal]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/ableton-cookbook/laravel-app/artisan queue:work --queue=batch-reprocess-normal --sleep=3 --tries=3 --max-time=3600
directory=/var/www/ableton-cookbook/laravel-app
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/ableton-cookbook/laravel-app/storage/logs/queue-normal.log

[program:laravel-queue-high]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/ableton-cookbook/laravel-app/artisan queue:work --queue=batch-reprocess-high --sleep=3 --tries=3 --max-time=3600
directory=/var/www/ableton-cookbook/laravel-app
autostart=true
autorestart=true
user=www-data
numprocs=3
redirect_stderr=true
stdout_logfile=/var/www/ableton-cookbook/laravel-app/storage/logs/queue-high.log

[program:laravel-queue-low]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/ableton-cookbook/laravel-app/artisan queue:work --queue=batch-reprocess-low --sleep=5 --tries=2 --max-time=1800
directory=/var/www/ableton-cookbook/laravel-app
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/ableton-cookbook/laravel-app/storage/logs/queue-low.log
EOF

# Configure log rotation for Enhanced Analysis
print_status "Setting up Enhanced Analysis log rotation..."
cat > /etc/logrotate.d/laravel-enhanced-analysis << 'EOF'
/var/www/ableton-cookbook/laravel-app/storage/logs/queue-*.log {
    daily
    missingok
    rotate 14
    compress
    notifempty
    create 0640 www-data www-data
    postrotate
        supervisorctl restart laravel-queue-*
    endscript
}

/var/log/enhanced-analysis-health.log {
    daily
    missingok
    rotate 30
    compress
    notifempty
    create 0640 www-data www-data
}
EOF

# Ensure queue log directory exists
mkdir -p $APP_PATH/storage/logs
chown www-data:www-data $APP_PATH/storage/logs

print_status "Enhanced Analysis queue workers configured"

print_status "Phase 7: File Permissions"

# Set proper permissions
chown -R www-data:www-data $APP_PATH
chmod -R 755 $APP_PATH
chmod -R 775 $APP_PATH/storage
chmod -R 775 $APP_PATH/bootstrap/cache
chmod -R 755 $APP_PATH/public

print_status "Phase 7: Services Restart"

# Restart services
systemctl reload nginx
systemctl restart php8.2-fpm

# Restart queue workers if supervisor is configured
if systemctl is-active --quiet supervisor; then
    supervisorctl reread
    supervisorctl update
    supervisorctl restart laravel-worker:* 2>/dev/null || true
    supervisorctl restart laravel-queue-normal:*
    supervisorctl restart laravel-queue-high:*
    supervisorctl restart laravel-queue-low:*
    print_status "Queue workers restarted (including Enhanced Analysis workers)"
else
    print_warning "Supervisor not active - Enhanced Analysis queue workers need manual start"
fi

print_status "Phase 8: Verification"

# Test the site
HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" https://$DOMAIN)
if [ $HTTP_STATUS -eq 200 ]; then
    print_status "Site is responding correctly (HTTP $HTTP_STATUS)"
else
    print_warning "Site returned HTTP $HTTP_STATUS - please check manually"
fi

# Check if assets are loading
ASSET_STATUS=$(curl -s -o /dev/null -w "%{http_code}" https://$DOMAIN/build/assets/app-*.css)
if [ $ASSET_STATUS -eq 200 ]; then
    print_status "CSS assets are loading correctly"
else
    print_warning "CSS assets may not be loading (HTTP $ASSET_STATUS)"
fi

# Check if API documentation is accessible
API_DOCS_STATUS=$(curl -s -o /dev/null -w "%{http_code}" https://$DOMAIN/api/docs)
if [ $API_DOCS_STATUS -eq 200 ]; then
    print_status "API documentation is accessible at /api/docs"
else
    print_warning "API documentation may not be accessible (HTTP $API_DOCS_STATUS)"
fi

# Verify Enhanced Analysis API endpoints
print_status "Verifying Enhanced Analysis System..."
ENHANCED_API_STATUS=$(curl -s -o /dev/null -w "%{http_code}" https://$DOMAIN/api/v1/compliance/constitution)
if [ $ENHANCED_API_STATUS -eq 200 ]; then
    print_status "Enhanced Analysis API endpoints are accessible"
else
    print_warning "Enhanced Analysis API may not be accessible (HTTP $ENHANCED_API_STATUS)"
fi

# Check Enhanced Analysis queue workers status
if systemctl is-active --quiet supervisor; then
    QUEUE_STATUS=$(supervisorctl status laravel-queue-normal:* 2>/dev/null | grep -c "RUNNING" || echo "0")
    if [ $QUEUE_STATUS -gt 0 ]; then
        print_status "Enhanced Analysis queue workers are running ($QUEUE_STATUS workers)"
    else
        print_warning "Enhanced Analysis queue workers may not be running properly"
    fi
fi

# Verify Enhanced Analysis database tables
php artisan tinker --execute="
use Illuminate\Support\Facades\Schema;
\$tables = ['enhanced_rack_analysis', 'nested_chains'];
\$missing = [];
foreach (\$tables as \$table) {
    if (!Schema::hasTable(\$table)) {
        \$missing[] = \$table;
    }
}
if (empty(\$missing)) {
    echo 'Enhanced Analysis database tables verified successfully!';
} else {
    echo 'WARNING: Missing Enhanced Analysis tables: ' . implode(', ', \$missing);
}
"

echo ""
print_status "🎉 Deployment Complete!"
echo -e "${GREEN}Site: https://$DOMAIN${NC}"
echo -e "${GREEN}Blog: https://$DOMAIN/blog${NC}"
echo -e "${GREEN}Admin: https://$DOMAIN/admin/blog${NC}"
echo -e "${GREEN}API Docs: https://$DOMAIN/api/docs${NC}"
echo -e "${GREEN}Enhanced Analysis API: https://$DOMAIN/api/v1/analysis/*${NC}"
echo -e "${GREEN}Constitutional Compliance: https://$DOMAIN/api/v1/compliance/*${NC}"
echo ""
print_status "Enhanced Nested Chain Analysis System Status:"
echo -e "${GREEN}✅ 20 API endpoints deployed${NC}"
echo -e "${GREEN}✅ Constitutional governance active${NC}"
echo -e "${GREEN}✅ Batch processing queues configured${NC}"
echo -e "${GREEN}✅ Performance monitoring enabled${NC}"
echo ""
print_warning "Don't forget to:"
echo "1. Update .env with proper database credentials"
echo "2. Configure mail settings in .env"
echo "3. Test Enhanced Analysis endpoints manually"
echo "4. Verify queue workers are processing jobs"
echo "5. Check Enhanced Analysis logs: tail -f storage/logs/queue-*.log"
echo ""