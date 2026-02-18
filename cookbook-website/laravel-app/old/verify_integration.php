#!/usr/bin/env php
<?php

/**
 * Verification script for Issue System Integration
 */

echo "🔍 Verifying Issue System Integration\n";
echo "===================================\n\n";

$checks = [
    'Laravel App' => 'artisan',
    'Migration File' => 'database/migrations/2025_08_18_230000_expand_issue_reporting_system.php',
    'Issue Model' => 'app/Models/Issue.php',
    'IssueType Model' => 'app/Models/IssueType.php',
    'IssueFileUpload Model' => 'app/Models/IssueFileUpload.php',
    'IssueComment Model' => 'app/Models/IssueComment.php',
    'IssueController' => 'app/Http/Controllers/IssueController.php',
    'NotificationService' => 'app/Services/NotificationService.php',
    'Issue Create View' => 'resources/views/issues/create.blade.php',
    'Issue Show View' => 'resources/views/issues/show.blade.php',
    'Issue Index View' => 'resources/views/issues/index.blade.php',
    'Admin Index View' => 'resources/views/admin/issues/index.blade.php',
    'IssuePolicy' => 'app/Policies/IssuePolicy.php',
];

$allGood = true;

foreach ($checks as $name => $file) {
    if (file_exists($file)) {
        echo "✅ $name: Found\n";
    } else {
        echo "❌ $name: Missing ($file)\n";
        $allGood = false;
    }
}

echo "\n";

if ($allGood) {
    echo "🎉 All components are in place!\n\n";
    echo "📝 Next steps:\n";
    echo "1. Run: php artisan migrate\n";
    echo "2. Add is_admin column to users table if needed\n";
    echo "3. Configure mail settings in .env\n";
    echo "4. Test the system at /issues/create\n\n";
} else {
    echo "⚠️  Some components are missing. Please check the integration.\n\n";
}

// Check if Laravel can load the classes
echo "🔧 Testing Laravel class loading...\n";

if (file_exists('vendor/autoload.php')) {
    require_once 'vendor/autoload.php';
    
    try {
        $app = require_once 'bootstrap/app.php';
        echo "✅ Laravel bootstrap: OK\n";
        
        // Test if we can resolve the controller
        if (class_exists('App\Http\Controllers\IssueController')) {
            echo "✅ IssueController: Loadable\n";
        } else {
            echo "❌ IssueController: Not loadable\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Laravel bootstrap failed: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ Composer autoload not found\n";
}

echo "\n🏁 Verification complete!\n";
