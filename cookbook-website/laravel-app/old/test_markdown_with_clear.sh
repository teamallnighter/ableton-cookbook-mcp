#!/bin/bash

cd /Volumes/BassDaddy/projects/abletonCookbook/abletonCookbookPHP/laravel-app

echo "Clearing Laravel caches..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

echo -e "\nTesting MarkdownService..."

# Create a test file for the markdown service
cat > test_markdown_real.php << 'EOF'
<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\MarkdownService;

echo "Testing MarkdownService...\n\n";

$service = new MarkdownService();
$content = "## Multiband Chorus \r\n\r\n### Low\r\n\r\n### Mid Low\r\n\r\n### Mid high \r\n\r\n### Highs";

echo "Input markdown:\n$content\n\n";

try {
    $html = $service->parseToHtml($content);
    echo "✅ SUCCESS: HTML generated:\n$html\n\n";
    
    $preview = $service->getPreview($content);
    echo "✅ SUCCESS: Preview generated:\n$preview\n\n";
    
    echo "All tests passed!\n";
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    exit(1);
}
EOF

php test_markdown_real.php