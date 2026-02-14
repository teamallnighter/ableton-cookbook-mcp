# Ableton Drum Rack Analyzer - Laravel Integration Guide

## 🎯 Overview

This Laravel integration provides a complete service for analyzing Ableton Live drum rack files (.adg) with a focus on drum-specific features like pad mappings, velocity ranges, and performance optimization.

## 📁 Files Structure

```
laravel-integration/
├── app/
│   ├── Services/
│   │   ├── AbletonDrumRackAnalyzer/
│   │   │   └── AbletonDrumRackAnalyzer.php    # Core analyzer class
│   │   └── DrumRackAnalyzerService.php        # Laravel service wrapper
│   ├── Http/Controllers/
│   │   └── DrumRackAnalyzerController.php     # API controller
│   └── Providers/
│       └── DrumRackAnalyzerServiceProvider.php # Service provider
├── routes/
│   └── drum-rack-analyzer.php                 # Route definitions
└── config/
    └── drum-rack-analyzer.php                 # Configuration file
```

## 🚀 Installation

### 1. Copy Files to Your Laravel Project

Copy the integration files to your Laravel project:

```bash
# Copy the service files
cp -r laravel-integration/app/* /path/to/your/laravel/app/
cp -r laravel-integration/config/* /path/to/your/laravel/config/
cp -r laravel-integration/routes/* /path/to/your/laravel/routes/
```

### 2. Register the Service Provider

Add to your `config/app.php`:

```php
'providers' => [
    // ...
    App\Providers\DrumRackAnalyzerServiceProvider::class,
],
```

### 3. Publish Configuration (Optional)

```bash
php artisan vendor:publish --tag=config --provider="App\Providers\DrumRackAnalyzerServiceProvider"
```

### 4. Create Storage Directories

```bash
mkdir -p storage/app/temp/drum-racks
mkdir -p storage/app/drum-rack-analysis
```

## 🛠️ Usage Examples

### Basic Service Usage

```php
use App\Services\DrumRackAnalyzerService;

$analyzer = app(DrumRackAnalyzerService::class);

// Analyze a drum rack file
$result = $analyzer->analyzeDrumRack('/path/to/drumrack.adg', [
    'verbose' => true,
    'include_performance' => true,
    'export_json' => true
]);

if ($result['success']) {
    $drumRackInfo = $result['data'];
    echo "Analyzed: " . $drumRackInfo['drum_rack_name'];
    echo "Active pads: " . $drumRackInfo['drum_statistics']['active_pads'];
}
```

### API Usage Examples

#### 1. Upload and Analyze

```bash
curl -X POST http://your-app.com/api/drum-rack-analyzer/analyze \
  -F "file=@drumrack.adg" \
  -F "options[verbose]=true" \
  -F "options[include_performance]=true"
```

Response:
```json
{
  "success": true,
  "message": "Drum rack analyzed successfully",
  "statistics": {
    "drum_rack_name": "My Drum Kit",
    "total_chains": 16,
    "active_chains": 12,
    "total_devices": 28,
    "complexity_score": 45
  },
  "data": {
    "drum_rack_name": "My Drum Kit",
    "drum_chains": [...],
    "drum_statistics": {...},
    "performance_analysis": {...}
  }
}
```

#### 2. Batch Analysis

```bash
curl -X POST http://your-app.com/api/drum-rack-analyzer/analyze-batch \
  -F "files[]=@drumrack1.adg" \
  -F "files[]=@drumrack2.adg" \
  -F "options[include_performance]=true"
```

#### 3. Validate File

```bash
curl -X POST http://your-app.com/api/drum-rack-analyzer/validate \
  -F "file=@drumrack.adg"
```

#### 4. Analyze from URL

```bash
curl -X POST http://your-app.com/api/drum-rack-analyzer/analyze-from-url \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://example.com/drumrack.adg",
    "options": {
      "verbose": true
    }
  }'
```

## 🎵 Drum-Specific Features

### 1. Pad Mapping Analysis
- Automatically detects MIDI note assignments (C1=36, D1=38, etc.)
- Maps to standard drum types (Kick, Snare, Hi-Hat, etc.)
- Analyzes key and velocity ranges

### 2. Device Categorization
- **Drum Synthesizers**: Kick, Snare, Hi-Hat, Cymbal, Tom, etc.
- **Samplers**: Sampler, Simpler, Impulse, DrumSampler
- **Drum Effects**: DrumBuss, GlueCompressor, etc.

### 3. Performance Analysis
- Complexity scoring (0-100)
- Resource usage recommendations
- Optimization suggestions for CPU-heavy setups

### 4. Statistics Generation
```php
$statistics = $analyzer->getAnalysisStatistics($result);
// Returns:
// - total_chains, active_chains
// - sample_based_pads, synthesized_pads
// - complexity_score
// - parsing_errors/warnings count
```

## ⚙️ Configuration Options

Edit `config/drum-rack-analyzer.php`:

```php
return [
    'limits' => [
        'max_file_size' => 100 * 1024 * 1024, // 100MB
        'batch_size' => 10, // Max files in batch
    ],
    
    'analysis' => [
        'include_performance_by_default' => true,
        'max_nesting_depth' => 10,
    ],
    
    'performance' => [
        'complexity_thresholds' => [
            'low' => 30,
            'medium' => 60,
            'high' => 80,
        ],
    ],
];
```

## 🧪 Testing

### Validate Sample Files
```bash
php validate_files.php
```

### Test Analysis
```php
// In your Laravel app
$analyzer = app(DrumRackAnalyzerService::class);

// Test with sample files
$files = $analyzer->findDrumRackFiles('path/to/drumracks/');
foreach ($files as $file) {
    $validation = $analyzer->validateDrumRackFile($file);
    if ($validation['valid']) {
        $result = $analyzer->analyzeDrumRack($file);
        // Process result...
    }
}
```

## 🔧 Artisan Commands

Create custom commands for batch processing:

```php
// app/Console/Commands/AnalyzeDrumRacks.php
class AnalyzeDrumRacks extends Command
{
    protected $signature = 'drum-racks:analyze {directory}';
    
    public function handle(DrumRackAnalyzerService $analyzer)
    {
        $files = $analyzer->findDrumRackFiles($this->argument('directory'));
        $result = $analyzer->analyzeDrumRackBatch($files);
        
        $this->info("Analyzed {$result['summary']['successful']} files");
    }
}
```

## 🚨 Error Handling

The service provides comprehensive error handling:

```php
$result = $analyzer->analyzeDrumRack($filePath);

if (!$result['success']) {
    // Handle error
    Log::error('Drum rack analysis failed', [
        'file' => $filePath,
        'error' => $result['error']
    ]);
}

// Check for parsing warnings
if (!empty($result['data']['parsing_warnings'])) {
    foreach ($result['data']['parsing_warnings'] as $warning) {
        Log::warning("Parsing warning: $warning");
    }
}
```

## 📊 Sample Output Structure

```json
{
  "drum_rack_name": "Fear Pressure Kit",
  "rack_type": "drum_rack",
  "macro_controls": [
    {"name": "Decay", "value": 0.5, "index": 0}
  ],
  "drum_chains": [
    {
      "name": "Kick",
      "chain_index": 0,
      "devices": [
        {
          "type": "DSKick",
          "name": "DS Kick",
          "is_on": true,
          "drum_context": {
            "is_drum_synthesizer": true,
            "is_sampler": false
          }
        }
      ],
      "drum_annotations": {
        "midi_note": 36,
        "drum_type": "C1 (Kick)",
        "key_range": {"low_key": 36, "high_key": 36}
      }
    }
  ],
  "drum_statistics": {
    "total_pads": 16,
    "active_pads": 12,
    "sample_based_pads": 8,
    "synthesized_pads": 4
  },
  "performance_analysis": {
    "complexity_score": 45,
    "recommendations": [
      "Consider freezing complex chains for better performance"
    ]
  }
}
```

## 🔐 Security Considerations

1. **File Upload Validation**: Strict MIME type and extension checking
2. **Size Limits**: 100MB maximum file size
3. **Temporary File Cleanup**: Automatic cleanup of uploaded files
4. **Rate Limiting**: API rate limiting to prevent abuse
5. **Error Sanitization**: Sensitive paths not exposed in production

## 🚀 Production Deployment

1. **Queue Processing**: Use Laravel queues for large file analysis
2. **Caching**: Enable caching for repeated file analysis
3. **Storage**: Use S3 or similar for file storage
4. **Monitoring**: Log analysis metrics and performance

```php
// Example queue job
class AnalyzeDrumRackJob implements ShouldQueue
{
    public function handle(DrumRackAnalyzerService $analyzer)
    {
        $result = $analyzer->analyzeDrumRack($this->filePath);
        // Store result in database or cache
    }
}
```

## 🤝 Contributing

The analyzer supports easy extension for new Ableton Live devices and features. Add new device mappings in the `$drumDeviceTypeMap` array or enhance the analysis logic in the parsing methods.

---

**✅ Ready for Laravel Integration!** This drum rack analyzer provides comprehensive analysis of Ableton drum racks with a Laravel-native service interface.