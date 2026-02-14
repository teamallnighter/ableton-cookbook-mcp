# PHP Rack & Preset Analyzers

Production-grade parsers for Ableton rack (.adg) and preset (.adv) files.

## Analyzers

### abletonRackAnalyzer (V7)
Parse Ableton audio/MIDI racks to extract device chains.

**Features:**
- Device chain extraction
- Macro mapping analysis
- Edition detection (Intro/Standard/Suite)
- Nested rack support (up to 10 levels deep)
- Stream parsing for large files (10MB+)
- In-memory XPath caching
- Error recovery with partial results

**Usage:**
```php
require_once 'abletonRackAnalyzer/abletonRackAnalyzer-V7.php';

$analyzer = new AbletonRackAnalyzer();
$result = $analyzer->analyzeRack('/path/to/rack.adg');

echo json_encode($result, JSON_PRETTY_PRINT);
```

### abletonDrumRackAnalyzer
Parse drum racks with pad assignments and sample mappings.

### abletonPresetAnalyzer
Parse device presets (.adv) to extract parameter settings.

### abletonSessionAnalyzer
Extract session-level metadata.

## Requirements

- PHP 7.4+
- XML extensions (libxml, dom, simplexml)
- No additional dependencies

## Environment Variables

```bash
ABLETON_MAX_FILE_SIZE=104857600          # 100MB
ABLETON_MAX_MEMORY_USAGE=536870912       # 512MB
ABLETON_ANALYSIS_TIMEOUT=300             # 5 minutes
ABLETON_STREAM_PARSING_THRESHOLD=10485760 # 10MB
```

## Performance

- Small racks (<1MB): ~100-200ms
- Large racks (5-10MB): ~500ms-1s
- Very large (50MB+): stream parsing, 2-5s

## Example Output

```json
{
  "name": "Bass Processing",
  "devices": [
    { "name": "EQ Eight", "type": "AudioEffect" },
    { "name": "Glue Compressor", "type": "AudioEffect" },
    { "name": "Saturator", "type": "AudioEffect" }
  ],
  "macros": [
    { "name": "Filter", "target": "EQ Eight.Frequency" },
    { "name": "Drive", "target": "Saturator.Drive" }
  ],
  "edition": "Standard"
}
```
