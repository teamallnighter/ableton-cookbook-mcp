#!/usr/bin/env php
<?php

/**
 * Integration script for the Ableton Cookbook Issue Reporting System
 * 
 * This script helps integrate the new issue reporting system with your existing Laravel app.
 */

echo "🎯 Ableton Cookbook Issue System Integration\n";
echo "=========================================\n\n";

// Check if we're in the Laravel app directory
if (!file_exists('artisan')) {
    echo "❌ Error: Please run this script from the Laravel app root directory.\n";
    exit(1);
}

echo "✅ Laravel application detected.\n\n";

// Step 1: Run migrations
echo "📊 Running database migrations...\n";
$migrationResult = shell_exec('php artisan migrate --force 2>&1');
echo $migrationResult . "\n";

// Step 2: Create storage directories
echo "📁 Creating storage directories...\n";
$storageCommands = [
    'mkdir -p storage/app/public/issue-uploads',
    'chmod 755 storage/app/public/issue-uploads',
];

foreach ($storageCommands as $command) {
    echo "Running: $command\n";
    shell_exec($command);
}

// Step 3: Create storage link if it doesn't exist
echo "🔗 Creating storage link...\n";
$linkResult = shell_exec('php artisan storage:link 2>&1');
echo $linkResult . "\n";

// Step 4: Clear caches
echo "🧹 Clearing application caches...\n";
$cacheCommands = [
    'php artisan config:clear',
    'php artisan route:clear',
    'php artisan view:clear',
    'php artisan cache:clear',
];

foreach ($cacheCommands as $command) {
    echo "Running: $command\n";
    shell_exec($command . ' 2>&1');
}

echo "\n✅ Integration completed successfully!\n\n";

echo "📋 Next Steps:\n";
echo "1. Update your .env file with mail configuration:\n";
echo "   MAIL_ADMIN_EMAIL=admin@yourdomain.com\n\n";
echo "2. Add admin privileges to your user account in the database:\n";
echo "   UPDATE users SET is_admin = 1 WHERE email = 'your-email@domain.com';\n\n";
echo "3. Visit the following URLs to test the system:\n";
echo "   - Submit Issue: http://your-domain.com/issues/create\n";
echo "   - Admin Dashboard: http://your-domain.com/admin/issues\n\n";
echo "4. The system is now integrated with your existing navigation.\n\n";

echo "🎉 Your issue reporting system is ready to use!\n";
