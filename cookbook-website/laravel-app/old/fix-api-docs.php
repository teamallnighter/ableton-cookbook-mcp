#!/usr/bin/env php
<?php

/**
 * Fix API Documentation Server URLs
 * 
 * This script updates the generated Swagger API documentation to use
 * the correct server URLs based on the current environment configuration.
 * 
 * Usage:
 *   php fix-api-docs.php
 * 
 * This should be run after deploying to production or whenever the APP_URL changes.
 */

require_once __DIR__ . '/vendor/autoload.php';

// Load the Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';

// Get the current APP_URL and environment
$appUrl = env('APP_URL', 'http://localhost:8000');
$appEnv = env('APP_ENV', 'local');

echo "🔧 Fixing API documentation server URLs...\n";
echo "Environment: {$appEnv}\n";
echo "App URL: {$appUrl}\n\n";

// Paths to the API documentation files
$storagePath = __DIR__ . '/storage/api-docs/api-docs.json';
$publicPath = __DIR__ . '/public/api-docs.json';

$files = [$storagePath, $publicPath];
$updated = 0;

foreach ($files as $filePath) {
    if (!file_exists($filePath)) {
        echo "⚠️  Skipping {$filePath} (file not found)\n";
        continue;
    }

    // Read the current documentation
    $content = file_get_contents($filePath);
    if (!$content) {
        echo "❌ Failed to read {$filePath}\n";
        continue;
    }

    $data = json_decode($content, true);
    if (!$data) {
        echo "❌ Failed to parse JSON in {$filePath}\n";
        continue;
    }

    // Update server configuration
    if ($appEnv === 'production') {
        // Production: only show the production server
        $data['servers'] = [
            [
                'url' => $appUrl,
                'description' => 'Production API Server'
            ]
        ];
    } else {
        // Development: show both local and production servers
        $data['servers'] = [
            [
                'url' => $appUrl,
                'description' => 'Local Development Server'
            ],
            [
                'url' => 'https://ableton.recipes',
                'description' => 'Production Server'
            ]
        ];
    }

    // Save the updated documentation
    $updatedContent = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (file_put_contents($filePath, $updatedContent)) {
        echo "✅ Updated {$filePath}\n";
        $updated++;
    } else {
        echo "❌ Failed to write {$filePath}\n";
    }
}

if ($updated > 0) {
    echo "\n🎉 Successfully updated {$updated} API documentation file(s)!\n";
    echo "📄 API documentation is available at: /api/docs\n";
    
    if (isset($data['servers'])) {
        echo "\n📋 Configured servers:\n";
        foreach ($data['servers'] as $server) {
            echo "  • {$server['url']} - {$server['description']}\n";
        }
    }
} else {
    echo "\n⚠️  No files were updated. Make sure to generate the API documentation first:\n";
    echo "   php artisan l5-swagger:generate\n";
}

echo "\n";