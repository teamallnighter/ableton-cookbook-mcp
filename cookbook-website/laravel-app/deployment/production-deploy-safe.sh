#!/bin/bash

# 🚀 Safe Production Deployment Script
# This script safely deploys to production without breaking dependencies

set -e

echo "🚀 Safe Production Deployment..."

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

# Check if running as root or deploy user
if [ "$EUID" -ne 0 ] && [ "$USER" != "deploy" ]; then
    print_error "Please run as root or deploy user"
    exit 1
fi

cd $APP_PATH

print_status "Step 1: Pulling latest changes"
git pull origin main

print_status "Step 2: Installing dependencies (keeping dev for stability)"
export COMPOSER_ALLOW_SUPERUSER=1
composer install --optimize-autoloader --no-interaction

print_status "Step 3: Clearing caches"
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear

print_status "Step 4: Building production assets"
npm ci
npm run build

print_status "Step 5: Optimizing for production"
php artisan config:cache
php artisan route:cache
php artisan view:cache

print_status "Step 6: Setting permissions"
if [ "$EUID" -eq 0 ]; then
    chown -R www-data:www-data $APP_PATH
    chmod -R 755 $APP_PATH
    chmod -R 775 $APP_PATH/storage
    chmod -R 775 $APP_PATH/bootstrap/cache
    chown -R www-data:www-data $APP_PATH/public/build
fi

print_status "Step 7: Restarting services"
if [ "$EUID" -eq 0 ]; then
    systemctl reload nginx
    systemctl restart php8.2-fpm
fi

print_status "Step 8: Testing application"
php artisan --version

echo ""
print_status "🎉 Safe production deployment complete!"
echo -e "${GREEN}Site is running with all dependencies intact${NC}"
echo -e "${YELLOW}Note: Dev dependencies kept for stability${NC}"