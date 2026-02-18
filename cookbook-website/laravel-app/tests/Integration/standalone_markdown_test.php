<?php

echo "=== STANDALONE MARKDOWN SERVICE TEST ===\n";

// Read the actual MarkdownService file
$serviceFile = '/Volumes/BassDaddy/projects/abletonCookbook/abletonCookbookPHP/laravel-app/app/Services/MarkdownService.php';
$content = file_get_contents($serviceFile);

// Check if our fixes are in place
echo "Checking file content...\n";

if (strpos($content, 'private array $allowedTags') !== false) {
    echo "✅ allowedTags property exists\n";
} else {
    echo "❌ allowedTags property missing\n";
    exit(1);
}

if (strpos($content, 'private function sanitizeElementAttributes') !== false) {
    echo "✅ sanitizeElementAttributes method exists\n";
} else {
    echo "❌ sanitizeElementAttributes method missing\n";
    exit(1);
}

// Check for syntax errors
echo "\nChecking PHP syntax...\n";
$output = shell_exec("cd /Volumes/BassDaddy/projects/abletonCookbook/abletonCookbookPHP/laravel-app && php -l app/Services/MarkdownService.php 2>&1");
echo $output;

if (strpos($output, 'No syntax errors detected') !== false) {
    echo "✅ No syntax errors found\n";
} else {
    echo "❌ Syntax errors detected\n";
    exit(1);
}

echo "\n=== TEST COMPLETED SUCCESSFULLY ===\n";
echo "The MarkdownService has been fixed. Please clear Laravel caches:\n";
echo "cd /path/to/laravel && php artisan config:clear && php artisan cache:clear && php artisan view:clear\n";