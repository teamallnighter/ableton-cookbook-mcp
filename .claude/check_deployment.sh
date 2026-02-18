#!/bin/bash

# Quick deployment status checker
SERVER="root@209.74.71.83"
PORT="22022"
SSH_OPTS="-p $PORT -o PubkeyAuthentication=no"

echo "🔍 Checking deployment status..."
echo ""

echo "📁 Checking releases directory:"
ssh $SSH_OPTS $SERVER "ls -lah /var/www/ableton-cookbook/releases/ 2>&1 | tail -10"
echo ""

echo "🔗 Checking current symlink:"
ssh $SSH_OPTS $SERVER "ls -la /var/www/ableton-cookbook/ | grep current"
echo ""

echo "📝 Checking nginx config:"
ssh $SSH_OPTS $SERVER "test -f /etc/nginx/sites-available/ableton-cookbook && echo '✓ Nginx config exists' || echo '✗ Nginx config missing'"
echo ""

echo "🌐 Testing site response:"
curl -I https://ableton.recipes 2>&1 | grep -E "HTTP|server:" | head -5
echo ""

echo "💾 Checking .env file:"
ssh $SSH_OPTS $SERVER "test -f /var/www/ableton-cookbook/shared/.env && echo '✓ .env exists' || echo '✗ .env missing'"
echo ""

echo "📊 Recent deployment log:"
ssh $SSH_OPTS $SERVER "tail -20 /var/www/ableton-cookbook/shared/storage/logs/laravel.log 2>&1 || echo 'No Laravel logs yet'"
