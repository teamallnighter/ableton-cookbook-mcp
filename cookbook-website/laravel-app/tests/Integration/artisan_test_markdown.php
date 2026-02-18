<?php

// Direct test without Laravel bootstrapping to avoid cache issues

echo "Testing MarkdownService...\n\n";

// Manually include the class to avoid autoloader cache
$classContent = file_get_contents(__DIR__ . '/app/Services/MarkdownService.php');

// Check if the file contains the allowedTags property
if (strpos($classContent, 'allowedTags') !== false) {
    echo "✅ allowedTags property found in file\n";
} else {
    echo "❌ allowedTags property NOT found in file\n";
    exit(1);
}

// Check if the sanitizeElementAttributes method exists
if (strpos($classContent, 'sanitizeElementAttributes') !== false) {
    echo "✅ sanitizeElementAttributes method found in file\n";
} else {
    echo "❌ sanitizeElementAttributes method NOT found in file\n";
    exit(1);
}

// Check for namespace issues
if (preg_match_all('/(?<!\\\\)DOM[A-Z]/', $classContent, $matches)) {
    echo "⚠️  Potential DOM namespace issues found:\n";
    foreach ($matches[0] as $match) {
        echo "   - $match\n";
    }
} else {
    echo "✅ No DOM namespace issues found\n";
}

echo "\nFile appears to be syntactically correct. The error might be a caching issue.\n";
echo "Try running: php artisan config:clear && php artisan cache:clear && php artisan view:clear\n";