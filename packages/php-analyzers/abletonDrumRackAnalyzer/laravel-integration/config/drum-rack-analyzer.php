<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Drum Rack Analyzer Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for the Ableton Drum Rack Analyzer service
    |
    */

    // File processing limits
    'limits' => [
        'max_file_size' => 100 * 1024 * 1024, // 100MB in bytes
        'min_file_size' => 100, // 100 bytes minimum
        'batch_size' => 10, // Maximum files in batch processing
        'max_processing_time' => 300, // 5 minutes timeout
    ],

    // Analysis options
    'analysis' => [
        'default_verbose' => false,
        'include_performance_by_default' => true,
        'export_json_by_default' => false,
        'max_nesting_depth' => 10,
    ],

    // Storage configuration
    'storage' => [
        'temp_directory' => 'temp/drum-racks',
        'output_directory' => 'drum-rack-analysis',
        'cleanup_temp_files' => true,
        'keep_exported_json' => true,
    ],

    // Performance thresholds
    'performance' => [
        'complexity_thresholds' => [
            'low' => 30,
            'medium' => 60,
            'high' => 80,
        ],
        'heavy_devices' => [
            'ConvolutionReverb',
            'HybridReverb', 
            'Wavetable',
            'Operator',
            'Granulator',
            'Collision',
        ],
    ],

    // Device categorization
    'device_categories' => [
        'drum_synthesizers' => [
            'Kick', 'Snare', 'HiHat', 'Cymbal', 'Tom', 'Clap', 'BassDrum', 'FMDrum', 'Perc',
            'DSKick', 'DSSnare', 'DSHH', 'DSCymbal', 'DSTom', 'DSClap', 'DSFM', 'DSAnalog', 'DSDrum', 'DSPenta'
        ],
        'samplers' => [
            'Sampler', 'Simpler', 'Impulse', 'DrumSampler'
        ],
        'drum_effects' => [
            'DrumBuss', 'GlueCompressor', 'Compressor', 'Compressor2', 'MultibandDynamics'
        ],
    ],

    // Drum pad mapping (MIDI note to drum type)
    'drum_pad_mapping' => [
        36 => 'C1 (Kick)',
        37 => 'C#1',
        38 => 'D1 (Snare)',
        39 => 'D#1',
        40 => 'E1 (Snare Alt)',
        41 => 'F1',
        42 => 'F#1 (Hi-Hat Closed)',
        43 => 'G1',
        44 => 'G#1 (Hi-Hat Pedal)',
        45 => 'A1',
        46 => 'A#1 (Hi-Hat Open)',
        47 => 'B1',
        48 => 'C2',
        49 => 'C#2 (Crash)',
        50 => 'D2',
        51 => 'D#2 (Ride)',
        52 => 'E2',
        53 => 'F2',
        54 => 'F#2',
        55 => 'G2',
        56 => 'G#2',
        57 => 'A2 (Crash 2)',
        58 => 'A#2',
        59 => 'B2 (Ride 2)',
    ],

    // Logging configuration
    'logging' => [
        'enabled' => true,
        'level' => 'info', // debug, info, warning, error
        'log_successful_analysis' => true,
        'log_performance_metrics' => true,
    ],

    // API configuration
    'api' => [
        'rate_limiting' => [
            'enabled' => true,
            'max_requests_per_minute' => 30,
            'max_batch_requests_per_hour' => 10,
        ],
        'cors' => [
            'enabled' => true,
            'allowed_origins' => ['*'],
            'allowed_methods' => ['GET', 'POST'],
        ],
    ],

    // Validation rules
    'validation' => [
        'allowed_extensions' => ['adg'],
        'allowed_mime_types' => ['application/octet-stream'],
        'require_drum_rack_detection' => false, // Set to true to require drum rack presence
    ],

    // Feature flags
    'features' => [
        'batch_processing' => true,
        'url_analysis' => true,
        'performance_analysis' => true,
        'json_export' => true,
        'web_interface' => true,
    ],

    // Cache configuration
    'cache' => [
        'enabled' => false, // Enable caching of analysis results
        'ttl' => 3600, // 1 hour cache TTL
        'key_prefix' => 'drum_rack_analysis_',
    ],

];