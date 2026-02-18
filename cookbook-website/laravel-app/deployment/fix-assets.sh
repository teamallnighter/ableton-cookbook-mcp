#!/bin/bash

# 🎨 Fix Production Assets - Quick Recovery Script
# Use this to fix styling/asset issues without full deployment

set -e

echo "🎨 Fixing Production Assets..."

# Configuration  
APP_PATH="/var/www/ableton-cookbook/laravel-app"
DOMAIN="ableton.recipes"

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
    print_error "Please run as root: sudo bash fix-assets.sh"
    exit 1
fi

cd $APP_PATH

print_status "Step 1: Install Node.js dependencies"
npm ci

print_status "Step 2: Build production assets"
npm run build

# Verify build was successful
if [ ! -d "public/build" ] || [ ! -f "public/build/manifest.json" ]; then
    print_error "Build failed - no build directory or manifest found"
    exit 1
fi

print_status "Step 3: Clear caches"
php artisan view:clear
php artisan config:clear

print_status "Step 4: Set permissions"
chown -R www-data:www-data public/build
chmod -R 755 public/build

print_status "Step 5: Restart services"
systemctl reload nginx
systemctl restart php8.2-fpm

print_status "Step 6: Test assets"
sleep 2

# Test main site
HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" https://$DOMAIN)
print_status "Main site: HTTP $HTTP_STATUS"

# Test if we can find any CSS file in the build directory
CSS_FILE=$(find public/build/assets -name "*.css" -type f | head -n1)
if [ ! -z "$CSS_FILE" ]; then
    CSS_URL="https://$DOMAIN/${CSS_FILE#public/}"
    CSS_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$CSS_URL")
    print_status "CSS asset test: HTTP $CSS_STATUS"
else
    print_error "No CSS files found in build directory"
fi

echo ""
print_status "🎨 Asset fix complete!"
echo -e "${GREEN}Test your site: https://$DOMAIN${NC}"
echo ""

if [ $HTTP_STATUS -eq 200 ]; then
    print_status "✨ Your styling should now be working!"
else
    print_warning "Site may have other issues - check error logs"
fi