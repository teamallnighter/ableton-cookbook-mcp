<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Drum Rack Analyzer Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration options for the Ableton Drum Rack
    | Analyzer service, which provides specialized analysis of drum rack
    | files (.adg format).
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Analysis Options
    |--------------------------------------------------------------------------
    |
    | Default options for drum rack analysis. These can be overridden when
    | calling the analyzer methods.
    |
    */
    'analysis' => [
        'verbose' => env('DRUM_ANALYZER_VERBOSE', false),
        'include_performance' => env('DRUM_ANALYZER_INCLUDE_PERFORMANCE', true),
        'export_json' => env('DRUM_ANALYZER_EXPORT_JSON', false),
        'output_folder' => env('DRUM_ANALYZER_OUTPUT_FOLDER', storage_path('app/drum-rack-analysis')),
    ],

    /*
    |--------------------------------------------------------------------------
    | File Validation
    |--------------------------------------------------------------------------
    |
    | Configuration for file validation and size limits.
    |
    */
    'validation' => [
        'max_file_size' => env('DRUM_ANALYZER_MAX_FILE_SIZE', 100 * 1024 * 1024), // 100MB
        'min_file_size' => env('DRUM_ANALYZER_MIN_FILE_SIZE', 100), // 100 bytes
        'supported_extensions' => ['adg'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Analysis
    |--------------------------------------------------------------------------
    |
    | Thresholds and settings for performance analysis and recommendations.
    |
    */
    'performance' => [
        'complexity_thresholds' => [
            'high' => 70,
            'warning' => 50,
        ],
        'heavy_device_limit' => 3,
        'max_chains_warning' => 32,
        'heavy_devices' => [
            'ConvolutionReverb',
            'HybridReverb', 
            'Wavetable',
            'Operator'
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Integration Settings
    |--------------------------------------------------------------------------
    |
    | Settings for integration with the main rack analyzer and other services.
    |
    */
    'integration' => [
        'auto_detect_drum_racks' => env('DRUM_ANALYZER_AUTO_DETECT', true),
        'fallback_to_general_analyzer' => env('DRUM_ANALYZER_FALLBACK', false),
        'cache_analysis_results' => env('DRUM_ANALYZER_CACHE_RESULTS', true),
        'cache_ttl' => env('DRUM_ANALYZER_CACHE_TTL', 3600), // 1 hour
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure logging specifically for drum rack analysis operations.
    |
    */
    'logging' => [
        'channel' => env('DRUM_ANALYZER_LOG_CHANNEL', 'single'),
        'level' => env('DRUM_ANALYZER_LOG_LEVEL', 'info'),
        'log_analysis_stats' => env('DRUM_ANALYZER_LOG_STATS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Drum-Specific Settings
    |--------------------------------------------------------------------------
    |
    | Configuration specific to drum rack analysis features.
    |
    */
    'drum_features' => [
        'analyze_pad_mappings' => true,
        'identify_drum_types' => true,
        'performance_recommendations' => true,
        'sample_detection' => true,
        'synthesizer_detection' => true,
        'macro_controls_analysis' => true,
    ],
];