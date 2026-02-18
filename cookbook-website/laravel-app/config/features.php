<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Feature Flags Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for the feature flag system.
    | Features can be enabled/disabled based on environment, user targeting,
    | percentage rollouts, and custom conditions.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Feature Flag System Settings
    |--------------------------------------------------------------------------
    */
    'enabled' => env('FEATURE_FLAGS_ENABLED', true),
    'cache_ttl' => env('FEATURE_FLAGS_CACHE_TTL', 300), // 5 minutes
    'analytics_enabled' => env('FEATURE_FLAGS_ANALYTICS', true),
    'admin_interface' => env('FEATURE_FLAGS_ADMIN_INTERFACE', true),

    /*
    |--------------------------------------------------------------------------
    | Feature Flags Definitions
    |--------------------------------------------------------------------------
    |
    | Define all available feature flags here with their default configurations.
    | Each flag can be overridden at runtime through the admin interface.
    |
    */
    'flags' => [
        
        /*
        |--------------------------------------------------------------------------
        | Phase 3 Security Features
        |--------------------------------------------------------------------------
        */
        'virus_scanning' => [
            'enabled' => env('FEATURE_VIRUS_SCANNING', true),
            'description' => 'Advanced virus scanning and malware detection for file uploads',
            'category' => 'security',
            'environments' => ['production', 'staging'],
            'rollout_percentage' => env('VIRUS_SCANNING_ROLLOUT', 100),
            'conditions' => [
                [
                    'type' => 'context_value',
                    'key' => 'file_upload',
                    'operator' => 'equals',
                    'value' => true
                ]
            ],
            'created_at' => '2025-08-27T00:00:00Z',
            'last_updated' => '2025-08-27T00:00:00Z'
        ],

        'advanced_security_monitoring' => [
            'enabled' => env('FEATURE_ADVANCED_SECURITY', true),
            'description' => 'Real-time security monitoring and threat detection',
            'category' => 'security',
            'environments' => ['production', 'staging'],
            'rollout_percentage' => 100,
            'conditions' => [],
            'created_at' => '2025-08-27T00:00:00Z',
            'last_updated' => '2025-08-27T00:00:00Z'
        ],

        'file_quarantine_system' => [
            'enabled' => env('FEATURE_FILE_QUARANTINE', true),
            'description' => 'Automatic quarantine system for infected files',
            'category' => 'security',
            'environments' => ['production', 'staging'],
            'rollout_percentage' => 100,
            'conditions' => [],
            'created_at' => '2025-08-27T00:00:00Z',
            'last_updated' => '2025-08-27T00:00:00Z'
        ],

        'emergency_response_system' => [
            'enabled' => env('FEATURE_EMERGENCY_RESPONSE', true),
            'description' => 'Automated emergency response for critical security threats',
            'category' => 'security',
            'environments' => ['production', 'staging'],
            'rollout_percentage' => 100,
            'conditions' => [],
            'created_at' => '2025-08-27T00:00:00Z',
            'last_updated' => '2025-08-27T00:00:00Z'
        ],

        /*
        |--------------------------------------------------------------------------
        | Phase 3 Accessibility Features  
        |--------------------------------------------------------------------------
        */
        'accessibility_enhancements' => [
            'enabled' => env('FEATURE_ACCESSIBILITY_ENHANCEMENTS', true),
            'description' => 'WCAG 2.1 AA compliance features including keyboard navigation and screen reader support',
            'category' => 'accessibility',
            'environments' => ['production', 'staging', 'local'],
            'rollout_percentage' => 100,
            'conditions' => [],
            'created_at' => '2025-08-27T00:00:00Z',
            'last_updated' => '2025-08-27T00:00:00Z'
        ],

        'tree_view_virtualization' => [
            'enabled' => env('FEATURE_TREE_VIRTUALIZATION', true),
            'description' => 'Advanced virtualization for large device trees (500+ devices)',
            'category' => 'performance',
            'environments' => ['production', 'staging', 'local'],
            'rollout_percentage' => 100,
            'conditions' => [
                [
                    'type' => 'context_value',
                    'key' => 'device_count',
                    'operator' => 'greater_than',
                    'value' => 100
                ]
            ],
            'created_at' => '2025-08-27T00:00:00Z',
            'last_updated' => '2025-08-27T00:00:00Z'
        ],

        'reduced_motion_support' => [
            'enabled' => env('FEATURE_REDUCED_MOTION', true),
            'description' => 'Support for users with vestibular disorders (reduced motion preferences)',
            'category' => 'accessibility',
            'environments' => ['production', 'staging', 'local'],
            'rollout_percentage' => 100,
            'conditions' => [],
            'created_at' => '2025-08-27T00:00:00Z',
            'last_updated' => '2025-08-27T00:00:00Z'
        ],

        'screen_reader_enhancements' => [
            'enabled' => env('FEATURE_SCREEN_READER', true),
            'description' => 'Enhanced screen reader support with live announcements',
            'category' => 'accessibility',
            'environments' => ['production', 'staging', 'local'],
            'rollout_percentage' => 100,
            'conditions' => [],
            'created_at' => '2025-08-27T00:00:00Z',
            'last_updated' => '2025-08-27T00:00:00Z'
        ],

        /*
        |--------------------------------------------------------------------------
        | Infrastructure & Monitoring Features
        |--------------------------------------------------------------------------
        */
        'monitoring_dashboard' => [
            'enabled' => env('FEATURE_MONITORING_DASHBOARD', true),
            'description' => 'Real-time monitoring dashboard with security and performance metrics',
            'category' => 'infrastructure',
            'environments' => ['production', 'staging'],
            'rollout_percentage' => 100,
            'target_users' => [], // Can be restricted to admin users
            'conditions' => [],
            'created_at' => '2025-08-27T00:00:00Z',
            'last_updated' => '2025-08-27T00:00:00Z'
        ],

        'real_time_alerts' => [
            'enabled' => env('FEATURE_REAL_TIME_ALERTS', true),
            'description' => 'Real-time security and system alerts',
            'category' => 'infrastructure',
            'environments' => ['production', 'staging'],
            'rollout_percentage' => 100,
            'conditions' => [],
            'created_at' => '2025-08-27T00:00:00Z',
            'last_updated' => '2025-08-27T00:00:00Z'
        ],

        'advanced_analytics' => [
            'enabled' => env('FEATURE_ADVANCED_ANALYTICS', true),
            'description' => 'Advanced analytics and reporting for security and performance',
            'category' => 'analytics',
            'environments' => ['production', 'staging'],
            'rollout_percentage' => 100,
            'conditions' => [],
            'created_at' => '2025-08-27T00:00:00Z',
            'last_updated' => '2025-08-27T00:00:00Z'
        ],

        /*
        |--------------------------------------------------------------------------
        | User Experience Features
        |--------------------------------------------------------------------------
        */
        'enhanced_error_handling' => [
            'enabled' => env('FEATURE_ENHANCED_ERROR_HANDLING', true),
            'description' => 'User-friendly error messages with actionable feedback',
            'category' => 'user_experience',
            'environments' => ['production', 'staging', 'local'],
            'rollout_percentage' => 100,
            'conditions' => [],
            'created_at' => '2025-08-27T00:00:00Z',
            'last_updated' => '2025-08-27T00:00:00Z'
        ],

        'auto_save_functionality' => [
            'enabled' => env('FEATURE_AUTO_SAVE', true),
            'description' => 'Automatic saving of user content with conflict resolution',
            'category' => 'user_experience',
            'environments' => ['production', 'staging', 'local'],
            'rollout_percentage' => 100,
            'conditions' => [],
            'created_at' => '2025-08-27T00:00:00Z',
            'last_updated' => '2025-08-27T00:00:00Z'
        ],

        'loading_state_indicators' => [
            'enabled' => env('FEATURE_LOADING_STATES', true),
            'description' => 'Comprehensive loading state indicators for all async operations',
            'category' => 'user_experience',
            'environments' => ['production', 'staging', 'local'],
            'rollout_percentage' => 100,
            'conditions' => [],
            'created_at' => '2025-08-27T00:00:00Z',
            'last_updated' => '2025-08-27T00:00:00Z'
        ],

        /*
        |--------------------------------------------------------------------------
        | API & Integration Features
        |--------------------------------------------------------------------------
        */
        'api_authentication' => [
            'enabled' => env('FEATURE_API_AUTHENTICATION', true),
            'description' => 'Enhanced API authentication for desktop applications',
            'category' => 'api',
            'environments' => ['production', 'staging'],
            'rollout_percentage' => 100,
            'conditions' => [],
            'created_at' => '2025-08-27T00:00:00Z',
            'last_updated' => '2025-08-27T00:00:00Z'
        ],

        'api_rate_limiting' => [
            'enabled' => env('FEATURE_API_RATE_LIMITING', true),
            'description' => 'Advanced API rate limiting with user-specific quotas',
            'category' => 'api',
            'environments' => ['production', 'staging'],
            'rollout_percentage' => 100,
            'conditions' => [],
            'created_at' => '2025-08-27T00:00:00Z',
            'last_updated' => '2025-08-27T00:00:00Z'
        ],

        'api_documentation_v2' => [
            'enabled' => env('FEATURE_API_DOCS_V2', true),
            'description' => 'Modern API documentation with Scramble integration',
            'category' => 'api',
            'environments' => ['production', 'staging'],
            'rollout_percentage' => 100,
            'conditions' => [],
            'created_at' => '2025-08-27T00:00:00Z',
            'last_updated' => '2025-08-27T00:00:00Z'
        ],

        /*
        |--------------------------------------------------------------------------
        | Content & Media Features
        |--------------------------------------------------------------------------
        */
        'how_to_articles' => [
            'enabled' => env('FEATURE_HOW_TO_ARTICLES', true),
            'description' => 'Rich markdown how-to articles for racks',
            'category' => 'content',
            'environments' => ['production', 'staging', 'local'],
            'rollout_percentage' => 100,
            'conditions' => [],
            'created_at' => '2025-08-27T00:00:00Z',
            'last_updated' => '2025-08-27T00:00:00Z'
        ],

        'secure_image_uploads' => [
            'enabled' => env('FEATURE_SECURE_IMAGE_UPLOADS', true),
            'description' => 'Secure image upload system with content sanitization',
            'category' => 'content',
            'environments' => ['production', 'staging'],
            'rollout_percentage' => 100,
            'conditions' => [],
            'created_at' => '2025-08-27T00:00:00Z',
            'last_updated' => '2025-08-27T00:00:00Z'
        ],

        'markdown_editor_enhancements' => [
            'enabled' => env('FEATURE_MARKDOWN_EDITOR', true),
            'description' => 'Enhanced markdown editor with real-time preview',
            'category' => 'content',
            'environments' => ['production', 'staging', 'local'],
            'rollout_percentage' => 100,
            'conditions' => [],
            'created_at' => '2025-08-27T00:00:00Z',
            'last_updated' => '2025-08-27T00:00:00Z'
        ],

        /*
        |--------------------------------------------------------------------------
        | Experimental & Beta Features
        |--------------------------------------------------------------------------
        */
        'ai_rack_analysis' => [
            'enabled' => env('FEATURE_AI_ANALYSIS', false),
            'description' => 'AI-powered rack analysis and recommendations (Beta)',
            'category' => 'experimental',
            'environments' => ['staging', 'local'],
            'rollout_percentage' => 10, // Limited beta rollout
            'conditions' => [],
            'created_at' => '2025-08-27T00:00:00Z',
            'last_updated' => '2025-08-27T00:00:00Z'
        ],

        'collaborative_editing' => [
            'enabled' => env('FEATURE_COLLABORATIVE_EDITING', false),
            'description' => 'Real-time collaborative editing of rack documentation (Experimental)',
            'category' => 'experimental',
            'environments' => ['staging', 'local'],
            'rollout_percentage' => 5, // Very limited experimental rollout
            'target_users' => [], // Specific beta users only
            'conditions' => [],
            'created_at' => '2025-08-27T00:00:00Z',
            'last_updated' => '2025-08-27T00:00:00Z'
        ],

        'desktop_app_integration' => [
            'enabled' => env('FEATURE_DESKTOP_APP', false),
            'description' => 'Desktop application integration and sync (Coming Soon)',
            'category' => 'experimental',
            'environments' => ['staging', 'local'],
            'rollout_percentage' => 0, // Not ready for users yet
            'conditions' => [],
            'created_at' => '2025-08-27T00:00:00Z',
            'last_updated' => '2025-08-27T00:00:00Z'
        ],

        /*
        |--------------------------------------------------------------------------
        | Performance Features
        |--------------------------------------------------------------------------
        */
        'background_job_processing' => [
            'enabled' => env('FEATURE_BACKGROUND_JOBS', true),
            'description' => 'Asynchronous background job processing for file analysis',
            'category' => 'performance',
            'environments' => ['production', 'staging'],
            'rollout_percentage' => 100,
            'conditions' => [],
            'created_at' => '2025-08-27T00:00:00Z',
            'last_updated' => '2025-08-27T00:00:00Z'
        ],

        'redis_caching' => [
            'enabled' => env('FEATURE_REDIS_CACHING', true),
            'description' => 'Advanced Redis caching for improved performance',
            'category' => 'performance',
            'environments' => ['production', 'staging'],
            'rollout_percentage' => 100,
            'conditions' => [],
            'created_at' => '2025-08-27T00:00:00Z',
            'last_updated' => '2025-08-27T00:00:00Z'
        ],

        'cdn_integration' => [
            'enabled' => env('FEATURE_CDN_INTEGRATION', false),
            'description' => 'CDN integration for static assets (Future)',
            'category' => 'performance',
            'environments' => ['production'],
            'rollout_percentage' => 0,
            'conditions' => [],
            'created_at' => '2025-08-27T00:00:00Z',
            'last_updated' => '2025-08-27T00:00:00Z'
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flag Categories
    |--------------------------------------------------------------------------
    */
    'categories' => [
        'security' => [
            'name' => 'Security',
            'description' => 'Security and threat protection features',
            'color' => '#dc2626'
        ],
        'accessibility' => [
            'name' => 'Accessibility',
            'description' => 'Accessibility compliance and inclusive design features',
            'color' => '#059669'
        ],
        'performance' => [
            'name' => 'Performance',
            'description' => 'Performance optimization and scalability features',
            'color' => '#2563eb'
        ],
        'infrastructure' => [
            'name' => 'Infrastructure',
            'description' => 'Infrastructure monitoring and operational features',
            'color' => '#7c3aed'
        ],
        'user_experience' => [
            'name' => 'User Experience',
            'description' => 'User interface and experience improvements',
            'color' => '#ea580c'
        ],
        'content' => [
            'name' => 'Content',
            'description' => 'Content creation and management features',
            'color' => '#0891b2'
        ],
        'api' => [
            'name' => 'API',
            'description' => 'API and integration features',
            'color' => '#65a30d'
        ],
        'analytics' => [
            'name' => 'Analytics',
            'description' => 'Analytics and reporting features',
            'color' => '#c026d3'
        ],
        'experimental' => [
            'name' => 'Experimental',
            'description' => 'Beta and experimental features',
            'color' => '#f59e0b'
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flag Admin Interface
    |--------------------------------------------------------------------------
    */
    'admin' => [
        'enabled' => env('FEATURE_FLAGS_ADMIN_ENABLED', true),
        'middleware' => ['auth', 'admin'], // Restrict to admin users
        'route_prefix' => 'admin/feature-flags',
        'permissions' => [
            'view' => 'view-feature-flags',
            'create' => 'create-feature-flags',
            'update' => 'update-feature-flags',
            'delete' => 'delete-feature-flags'
        ]
    ]
];