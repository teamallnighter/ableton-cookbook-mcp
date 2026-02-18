#!/bin/bash

# 🔧 Fix Composer Collision Package Issue
# This script fixes the missing Collision package error in production

set -e

echo "🔧 Fixing Composer Collision Package Issue..."

# Configuration
APP_PATH="/var/www/ableton-cookbook/laravel-app"

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

print_status() { echo -e "${GREEN}✅ $1${NC}"; }
print_warning() { echo -e "${YELLOW}⚠️  $1${NC}"; }
print_error() { echo -e "${RED}❌ $1${NC}"; }

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    print_error "Please run as root: sudo bash fix-composer-issue.sh"
    exit 1
fi

cd $APP_PATH

print_status "Step 1: Setting composer to allow superuser"
export COMPOSER_ALLOW_SUPERUSER=1

print_status "Step 2: Installing all dependencies (including dev)"
composer install --optimize-autoloader --no-interaction

print_status "Step 3: Removing dev dependencies for production"
composer install --no-dev --optimize-autoloader --no-interaction

print_status "Step 4: Clearing all caches"
php artisan cache:clear || true
php artisan config:clear || true
php artisan view:clear || true
php artisan route:clear || true

print_status "Step 5: Setting proper permissions"
chown -R www-data:www-data $APP_PATH
chmod -R 755 $APP_PATH
chmod -R 775 $APP_PATH/storage
chmod -R 775 $APP_PATH/bootstrap/cache

print_status "Step 6: Restarting services"
systemctl reload nginx
systemctl restart php8.2-fpm

print_status "Step 7: Testing application"
php artisan --version

echo ""
print_status "🎉 Composer issue fixed!"
echo -e "${GREEN}Your application should now be working properly${NC}"
echo ""

# Final check
if php artisan list > /dev/null 2>&1; then
    print_status "✨ Laravel is running successfully!"
else
    print_error "Laravel may still have issues - check logs"
    echo "Run: tail -f storage/logs/laravel.log"
fi