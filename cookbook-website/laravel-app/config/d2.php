<?php

return [
    /*
    |--------------------------------------------------------------------------
    | D2 Diagram Configuration
    |--------------------------------------------------------------------------
    |
    | This file configures the D2 diagram generation service for different
    | environments. D2 must be installed on the server for this to work.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | D2 Enabled
    |--------------------------------------------------------------------------
    |
    | Enable or disable D2 diagram generation. Useful for disabling in
    | environments where D2 is not installed or during maintenance.
    |
    */
    'enabled' => env('D2_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | D2 Binary Path
    |--------------------------------------------------------------------------
    |
    | The full path to the D2 binary. On Ubuntu/production servers, this
    | should be an absolute path like '/usr/local/bin/d2'. On development
    | machines with D2 in PATH, you can use just 'd2'.
    |
    */
    'binary_path' => env('D2_BINARY_PATH', '/usr/local/bin/d2'),

    /*
    |--------------------------------------------------------------------------
    | Use System Path
    |--------------------------------------------------------------------------
    |
    | When true, uses 'd2' command assuming it's in system PATH.
    | When false, uses the explicit binary_path above.
    | Defaults to true for local development, false for production.
    |
    */
    'use_system_path' => env('D2_USE_SYSTEM_PATH', env('APP_ENV') === 'local'),

    /*
    |--------------------------------------------------------------------------
    | Temporary Directory
    |--------------------------------------------------------------------------
    |
    | Directory for storing temporary D2 files during diagram generation.
    | Must be writable by the web server user (www-data on Ubuntu).
    |
    */
    'temp_path' => env('D2_TEMP_PATH', storage_path('app/temp/d2')),

    /*
    |--------------------------------------------------------------------------
    | Command Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum time in seconds to wait for D2 to generate a diagram.
    | Prevents hanging on complex diagrams. Default is 10 seconds.
    |
    */
    'timeout' => env('D2_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Enable caching of generated diagrams to improve performance.
    | Cache TTL is in seconds (3600 = 1 hour).
    |
    */
    'cache_enabled' => env('D2_CACHE_ENABLED', true),
    'cache_ttl' => env('D2_CACHE_TTL', 3600),

    /*
    |--------------------------------------------------------------------------
    | Diagram Defaults
    |--------------------------------------------------------------------------
    |
    | Default settings for diagram generation.
    |
    */
    'defaults' => [
        'layout' => 'elk',           // Layout engine: elk, dagre, tala
        'theme' => 'default',         // Theme: default, sketch, terminal
        'pad' => 100,                 // Padding around diagram
        'animation_interval' => 0,    // Animation interval in ms (0 = disabled)
        'font_size' => 14,            // Default font size
        'dark_mode' => false,         // Dark mode rendering
    ],

    /*
    |--------------------------------------------------------------------------
    | Format Support
    |--------------------------------------------------------------------------
    |
    | Supported output formats and their MIME types.
    |
    */
    'formats' => [
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'pdf' => 'application/pdf',
        'ascii' => 'text/plain',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Rate limits for diagram generation per user/IP.
    | Format: 'requests,minutes'
    |
    */
    'rate_limits' => [
        'authenticated' => '60,1',    // 60 requests per minute for logged-in users
        'guest' => '20,1',            // 20 requests per minute for guests
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configure logging for D2 operations.
    |
    */
    'logging' => [
        'enabled' => env('D2_LOGGING_ENABLED', true),
        'channel' => env('D2_LOG_CHANNEL', 'stack'),
        'level' => env('D2_LOG_LEVEL', 'error'), // debug, info, warning, error
    ],
];