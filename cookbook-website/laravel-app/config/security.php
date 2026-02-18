<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration options for the comprehensive security
    | system including XSS prevention, CSP policies, and monitoring settings.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | XSS Prevention Settings
    |--------------------------------------------------------------------------
    */
    'xss' => [
        'enabled' => env('XSS_PREVENTION_ENABLED', true),
        'strict_mode' => env('XSS_STRICT_MODE', true),
        'auto_sanitize' => env('XSS_AUTO_SANITIZE', true),
        'emergency_mode' => env('XSS_EMERGENCY_MODE', false),
        
        // Content length limits
        'max_content_length' => env('XSS_MAX_CONTENT_LENGTH', 500000), // 500KB
        'max_nesting_level' => env('XSS_MAX_NESTING_LEVEL', 20),
        
        // Rate limiting
        'rate_limit_enabled' => env('XSS_RATE_LIMIT_ENABLED', true),
        'rate_limit_attempts' => env('XSS_RATE_LIMIT_ATTEMPTS', 10),
        'rate_limit_decay' => env('XSS_RATE_LIMIT_DECAY', 3600), // 1 hour
        
        // Monitoring
        'log_attempts' => env('XSS_LOG_ATTEMPTS', true),
        'log_level' => env('XSS_LOG_LEVEL', 'warning'),
        'notify_critical' => env('XSS_NOTIFY_CRITICAL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy Settings
    |--------------------------------------------------------------------------
    */
    'csp' => [
        'enabled' => env('CSP_ENABLED', true),
        'report_only' => env('CSP_REPORT_ONLY', false),
        'report_uri' => env('CSP_REPORT_URI'),
        
        // Nonce settings
        'use_nonce' => env('CSP_USE_NONCE', true),
        'nonce_length' => env('CSP_NONCE_LENGTH', 16),
        
        // Allowed domains for embeds
        'allowed_frame_sources' => [
            'https://www.youtube.com',
            'https://youtube.com', 
            'https://w.soundcloud.com',
            'https://soundcloud.com',
        ],
        
        // Custom directives
        'custom_directives' => env('CSP_CUSTOM_DIRECTIVES', []),
        
        // Environment-specific settings
        'development' => [
            'allow_unsafe_eval' => true,
            'allow_unsafe_inline_styles' => true,
        ],
        
        'production' => [
            'upgrade_insecure_requests' => true,
            'block_all_mixed_content' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Upload Security Settings
    |--------------------------------------------------------------------------
    */
    'image_upload' => [
        'enabled' => env('IMAGE_UPLOAD_SECURITY_ENABLED', true),
        'scan_content' => env('IMAGE_SCAN_CONTENT', true),
        'scan_exif' => env('IMAGE_SCAN_EXIF', true),
        'strip_exif' => env('IMAGE_STRIP_EXIF', true),
        'validate_signatures' => env('IMAGE_VALIDATE_SIGNATURES', true),
        
        // File size limits
        'max_file_size' => env('IMAGE_MAX_FILE_SIZE', 5120), // 5MB in KB
        'max_dimensions' => env('IMAGE_MAX_DIMENSIONS', 4000), // 4000px
        
        // Allowed formats
        'allowed_mime_types' => [
            'image/jpeg',
            'image/jpg',
            'image/png', 
            'image/gif',
            'image/webp',
        ],
        
        'allowed_extensions' => [
            'jpg', 'jpeg', 'png', 'gif', 'webp'
        ],
        
        // Security scanning
        'malware_patterns' => [
            '/<\?php/i',
            '/<script/i', 
            '/javascript:/i',
            '/eval\s*\(/i',
            '/system\s*\(/i',
            '/exec\s*\(/i',
        ],
        
        // Processing options
        'recreate_images' => env('IMAGE_RECREATE', true),
        'optimize_images' => env('IMAGE_OPTIMIZE', true),
        'generate_thumbnails' => env('IMAGE_GENERATE_THUMBNAILS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Markdown Security Settings
    |--------------------------------------------------------------------------
    */
    'markdown' => [
        'strict_sanitization' => env('MARKDOWN_STRICT_SANITIZATION', true),
        'allow_html' => env('MARKDOWN_ALLOW_HTML', false),
        'escape_html' => env('MARKDOWN_ESCAPE_HTML', true),
        
        // Allowed HTML tags
        'allowed_tags' => [
            'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'del', 's', 'strike',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'ul', 'ol', 'li', 'dl', 'dt', 'dd',
            'blockquote', 'pre', 'code',
            'a', 'img', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
            'div', 'span'
        ],
        
        // Conditionally allowed tags (for embeds)
        'conditional_tags' => [
            'iframe' // Only for whitelisted domains
        ],
        
        // Media embed settings
        'allow_embeds' => env('MARKDOWN_ALLOW_EMBEDS', true),
        'max_embeds' => env('MARKDOWN_MAX_EMBEDS', 10),
        'allowed_embed_domains' => [
            'youtube.com',
            'www.youtube.com',
            'soundcloud.com', 
            'w.soundcloud.com',
        ],
        
        // Validation settings
        'validate_urls' => env('MARKDOWN_VALIDATE_URLS', true),
        'block_private_urls' => env('MARKDOWN_BLOCK_PRIVATE_URLS', true),
        'max_url_length' => env('MARKDOWN_MAX_URL_LENGTH', 2048),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Monitoring Settings
    |--------------------------------------------------------------------------
    */
    'monitoring' => [
        'enabled' => env('SECURITY_MONITORING_ENABLED', true),
        'real_time' => env('SECURITY_REAL_TIME_MONITORING', true),
        'store_incidents' => env('SECURITY_STORE_INCIDENTS', true),
        
        // Incident retention
        'incident_retention_days' => env('SECURITY_INCIDENT_RETENTION', 30),
        'critical_incident_retention_days' => env('SECURITY_CRITICAL_RETENTION', 365),
        
        // Alerting
        'alert_channels' => [
            'log' => env('SECURITY_ALERT_LOG', true),
            'email' => env('SECURITY_ALERT_EMAIL', false),
            'slack' => env('SECURITY_ALERT_SLACK', false),
            'database' => env('SECURITY_ALERT_DATABASE', true),
        ],
        
        // Metrics
        'collect_metrics' => env('SECURITY_COLLECT_METRICS', true),
        'metrics_retention_hours' => env('SECURITY_METRICS_RETENTION', 168), // 1 week
        
        // Threat intelligence
        'threat_intelligence' => env('SECURITY_THREAT_INTELLIGENCE', false),
        'auto_block_threats' => env('SECURITY_AUTO_BLOCK', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Headers Configuration
    |--------------------------------------------------------------------------
    */
    'headers' => [
        'enabled' => env('SECURITY_HEADERS_ENABLED', true),
        
        // Individual header controls
        'x_content_type_options' => env('HEADER_X_CONTENT_TYPE_OPTIONS', 'nosniff'),
        'x_frame_options' => env('HEADER_X_FRAME_OPTIONS', 'DENY'),
        'x_xss_protection' => env('HEADER_X_XSS_PROTECTION', '1; mode=block'),
        'referrer_policy' => env('HEADER_REFERRER_POLICY', 'strict-origin-when-cross-origin'),
        
        // HSTS settings
        'hsts_enabled' => env('HSTS_ENABLED', true),
        'hsts_max_age' => env('HSTS_MAX_AGE', 31536000), // 1 year
        'hsts_include_subdomains' => env('HSTS_INCLUDE_SUBDOMAINS', true),
        'hsts_preload' => env('HSTS_PRELOAD', true),
        
        // Cross-Origin policies
        'cross_origin_embedder_policy' => env('COEP', 'require-corp'),
        'cross_origin_opener_policy' => env('COOP', 'same-origin'),
        'cross_origin_resource_policy' => env('CORP', 'same-origin'),
        
        // Permissions Policy
        'permissions_policy' => [
            'camera' => '()',
            'microphone' => '()',
            'geolocation' => '()',
            'interest-cohort' => '()',
            'payment' => '()',
            'usb' => '()',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Emergency Response Settings
    |--------------------------------------------------------------------------
    */
    'emergency' => [
        'enabled' => env('SECURITY_EMERGENCY_ENABLED', true),
        'auto_trigger_threshold' => env('SECURITY_EMERGENCY_THRESHOLD', 10), // incidents per hour
        'lockdown_duration' => env('SECURITY_LOCKDOWN_DURATION', 3600), // 1 hour
        
        // Emergency actions
        'actions' => [
            'enable_strict_csp' => true,
            'disable_file_uploads' => true,
            'enable_emergency_sanitization' => true,
            'block_suspicious_ips' => true,
            'notify_administrators' => true,
        ],
        
        // Recovery settings
        'auto_recovery' => env('SECURITY_AUTO_RECOVERY', true),
        'recovery_threshold' => env('SECURITY_RECOVERY_THRESHOLD', 1), // incidents per hour
        'manual_recovery_required' => env('SECURITY_MANUAL_RECOVERY', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Development and Testing Settings
    |--------------------------------------------------------------------------
    */
    'testing' => [
        'enabled' => env('SECURITY_TESTING_ENABLED', false),
        'simulate_attacks' => env('SECURITY_SIMULATE_ATTACKS', false),
        'log_test_results' => env('SECURITY_LOG_TESTS', true),
        
        // Test payloads
        'test_xss_payloads' => [
            '<script>alert("xss")</script>',
            'javascript:alert(1)',
            '<img src=x onerror=alert(1)>',
            'data:text/html,<script>alert(1)</script>',
        ],
    ],
];