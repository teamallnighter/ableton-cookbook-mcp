# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is the **Ableton Drum Rack Analyzer** (ADRA) - a PHP-based tool that parses and analyzes Ableton Live drum rack files (.adg format). The analyzer decompresses gzipped XML files and extracts detailed information about drum rack components, device chains, MIDI mappings, and performance characteristics.

## Architecture

### Core Component
- `abletonRackAnalyzer-v3.php`: Main analyzer class (`AbletonRackAnalyzer`) that handles:
  - Gzip decompression of .adg files
  - XML parsing and validation  
  - Device type mapping (comprehensive mapping for audio effects, instruments, MIDI effects, CV devices)
  - Recursive parsing of nested racks and device chains
  - Macro control analysis
  - Ableton Live version detection

### Key Methods
- `decompressAndParseAbletonFile()`: Decompresses .adg files and validates XML structure
- `parseChainsAndDevices()`: Main parsing function that extracts rack structure
- `detectRackTypeAndDevice()`: Identifies rack type (Audio Effect, Instrument, MIDI Effect)
- `parseSingleChainBranch()`: Parses individual chains with key/velocity ranges
- `parseDevice()`: Recursively parses devices including nested racks

### Framework Integration
- Uses Laravel framework (namespace `App\Services\AbletonRackAnalyzer`)
- Integrates with Illuminate logging (`Log::` facade)
- Designed as a service class for web application integration

## Sample Files
- `drumracks/`: Contains sample .adg files for testing
  - `DECAP DTK 3 Kit.adg`
  - `Fear Pressure Kit.adg`

## Development Notes

### File Structure
- Single-file architecture with comprehensive device mapping
- No external dependencies beyond Laravel framework
- Handles nested rack structures up to 10 levels deep
- Supports all Ableton Live device types including Live 12 additions

### Error Handling
- Comprehensive error handling with detailed logging
- File size validation (100MB limit, 100 byte minimum)
- XML structure validation
- Graceful handling of malformed or corrupted files

### Usage Pattern
The analyzer is designed to be used as a service within a Laravel application for processing uploaded .adg files and generating JSON analysis reports.

## 🎯 Implementation Complete (2025-08-28)

### Core Components Delivered
- **`abletonDrumRackAnalyzer.php`**: Specialized drum rack analyzer (800+ lines)
- **`DrumRackAnalyzerService.php`**: Laravel service wrapper with validation
- **`DrumRackAnalyzerController.php`**: RESTful API controller
- **`DrumRackAnalyzerServiceProvider.php`**: Service provider for dependency injection
- **Routes & Configuration**: Complete Laravel integration setup

### Key Features
- **Drum-Specific Analysis**: MIDI pad mapping, device categorization, velocity ranges
- **Performance Insights**: Complexity scoring, optimization recommendations
- **Batch Processing**: Multiple file analysis with progress tracking
- **Security Measures**: File validation, size limits, temporary file cleanup
- **API Endpoints**: Upload, validate, batch process, analyze from URL

### Validation Status
✅ **Tested with sample files**: Both DECAP DTK 3 Kit.adg and Fear Pressure Kit.adg
✅ **Structure detection**: DrumGroupDevice + InstrumentGroupDevice elements
✅ **File processing**: Gzip decompression and XML parsing working
✅ **Laravel integration**: Complete service architecture ready for deployment

### Laravel Integration Commands
```bash
# Copy integration files
cp -r laravel-integration/app/* /your/laravel/app/
cp laravel-integration/config/* /your/laravel/config/
cp laravel-integration/routes/* /your/laravel/routes/

# Register service provider in config/app.php
App\Providers\DrumRackAnalyzerServiceProvider::class

# Test endpoints
POST /api/drum-rack-analyzer/analyze
POST /api/drum-rack-analyzer/validate
POST /api/drum-rack-analyzer/analyze-batch
```

### Next Steps for Production
1. Copy integration files to Laravel project
2. Register service provider
3. Create storage directories
4. Configure file upload limits
5. Test with real drum rack files

# Project Context Map
Generated: 2025-08-28
Last Updated: 2025-08-28
Auto-reference: YES - Check before every major decision

## 🎯 Project Mental Model
### One-Line Purpose
Analyzes Ableton Live drum rack files (.adg) to extract comprehensive insights about drum samples, device chains, and performance characteristics for music producers.

### Core Business Value
Empowers music producers to understand, optimize, and learn from complex drum rack structures that are typically opaque in Ableton Live's interface.

### User Journey Summary
1. User wants to: Understand the internal structure of complex Ableton drum racks
2. System enables: Decompression and parsing of .adg files to extract detailed component analysis
3. Value delivered: Comprehensive insights into drum samples, device chains, macro controls, and optimization opportunities

## 📐 Architecture Digest
### System Boundaries
```
┌─────────────────────────────────┐
│      Laravel Web App           │
│  (Future Integration Layer)     │
├─────────────────────────────────┤
│    AbletonRackAnalyzer Class    │
│  Tech: PHP with SimpleXML       │
├─────────────────────────────────┤
│       .adg File Processing      │
│  Tech: Gzip + XML Parsing       │
└─────────────────────────────────┘
```

### Key Architectural Decisions
1. **Decision**: Single static PHP class architecture
   - Why: Simplicity and Laravel service integration
   - Trade-off: Less extensible than object-oriented approach
   - Constraint: All methods must be static

2. **Decision**: Comprehensive device mapping dictionary
   - Why: Ableton uses internal device names vs. display names
   - Trade-off: High maintenance overhead for new devices
   - Constraint: Must stay updated with Live versions

3. **Decision**: Recursive parsing with depth limits
   - Why: Handle nested racks safely
   - Trade-off: Deep nesting may be truncated
   - Constraint: Max 10 levels to prevent infinite recursion

## 🗂️ Codebase Map
### Directory Structure & Purpose
```
abletonDrumRackAnalyzer/
├── abletonRackAnalyzer-v3.php    # [Core analyzer class]
│   └── Pattern: Static methods, comprehensive error handling
├── drumracks/                    # [Sample .adg files for testing]
│   ├── DECAP DTK 3 Kit.adg      # [13KB test file]
│   └── Fear Pressure Kit.adg     # [104KB test file]
├── PRD.md                        # [Product requirements document]
│   └── Pattern: User personas, feature requirements
├── CLAUDE.md                     # [Claude Code guidance]
└── PROJECT_CONTEXT.md            # [This file]
```

### File Naming Conventions
- Main class: `abletonRackAnalyzer-v3.php` (version suffixed)
- Sample files: `[Kit Name].adg` (Ableton device group format)
- Documentation: `UPPERCASE.md` for project docs

## 🔑 Critical Business Rules (from PRD)
### Must Always Be True
1. **Rule**: "Parser must handle all Ableton Live versions gracefully"
   - Implementation: `extractAbletonVersionInfo()` with fallback parsing
   - Test coverage: Version detection across different Live versions

2. **Rule**: "File size validation prevents resource exhaustion"
   - Implementation: 100MB max, 100 byte minimum in `decompressAndParseAbletonFile()`
   - Never bypass in: File processing pipeline

3. **Rule**: "Device mapping must be comprehensive for user experience"
   - Implementation: 200+ device mappings in `$deviceTypeMap`
   - Coverage: Audio effects, instruments, MIDI effects, CV devices, Live 12 additions

### Domain Constraints
- Max nesting depth: 10 levels (prevents infinite recursion)
- Supported file format: .adg (gzipped XML only)
- Required framework: Laravel (uses `Illuminate\Support\Facades\Log`)
- Memory constraint: Files over 100MB rejected

## 🔧 Code Patterns Library
### Pattern 1: Error Handling with Logging
```php
// Extracted from: decompressAndParseAbletonFile()
try {
    $result = @simplexml_load_string($xmlContent);
    if ($result === false) {
        Log::error("XML parsing error in $filePath");
        return null;
    }
    return $result;
} catch (Exception $e) {
    Log::error("Unexpected error processing $filePath: " . $e->getMessage());
    return null;
}
```
When to use: All file processing and XML parsing operations

### Pattern 2: XPath Navigation
```php
// Extracted from: parseDevice()
$userNameElem = $deviceElem->xpath('UserName');
if (!empty($userNameElem) && isset($userNameElem[0]['Value'])) {
    $customName = trim((string)$userNameElem[0]['Value']);
}
```
When to use: All XML element access and attribute extraction

### Pattern 3: Recursive Structure Processing
```php
// Extracted from: parseSingleChainBranch()
public static function parseSingleChainBranch($branchXml, $chainIndex = 0, $verbose = false, $depth = 0)
{
    if ($depth > 10) {
        if ($verbose) Log::warning("Max nesting depth reached");
        return null;
    }
    // Process nested devices...
}
```
When to use: Processing any nested Ableton structures (racks, chains, devices)

## 🔌 Integration Points
### External Dependencies
| Dependency | Purpose | Version | Constraint |
|------------|---------|---------|------------|
| Laravel Framework | Logging, Service Integration | Any | Required for `Log::` facade |
| PHP SimpleXML | XML parsing | Built-in | Core XML processing |
| PHP Gzip | File decompression | Built-in | .adg file format requirement |

### Internal APIs
| Method | Input | Output | Purpose |
|--------|-------|--------|---------|
| `parseChainsAndDevices()` | XML root, filename | Rack info array | Main analysis entry point |
| `decompressAndParseAbletonFile()` | File path | SimpleXML object | File processing |
| `exportAnalysisToJson()` | Rack info, path | JSON file | Results export |

## 🚫 Constraints & Limitations
### Technical Constraints
- PHP version: Must support SimpleXML and gzip
- Memory limit: Implicit via 100MB file size limit  
- Laravel dependency: Required for logging facade

### Business Constraints  
- File format: .adg files only (Ableton device groups)
- Ableton versions: All supported, but newer devices need mapping updates
- Analysis depth: 10-level nesting limit for safety

## 📊 State Management Strategy
### Data Flow
```
.adg File → Gzip Decompress → XML Parse → Device Detection → 
Chain Analysis → Device Parsing → Macro Control Extraction → 
JSON Export
```

### Data Structures
- Main output: Associative array with standardized keys
- Device mapping: Static dictionary (200+ entries)
- Error tracking: Arrays for `parsing_errors` and `parsing_warnings`

## 🧪 Testing Philosophy
### Test Data Available
- `DECAP DTK 3 Kit.adg`: Lightweight test file (13KB)
- `Fear Pressure Kit.adg`: Complex test file (104KB)
- Both files represent real-world drum rack complexity

### Validation Strategy
- File size validation before processing
- XML structure validation after decompression
- Version compatibility checks
- Device type recognition verification

## 🔍 Quick Reference Lookup
### Common Tasks → Implementation
| Task | Method | Key Details |
|------|--------|-------------|
| Analyze .adg file | `parseChainsAndDevices()` | Main entry point, handles all analysis |
| Add new device type | Update `$deviceTypeMap` | Maps internal → display names |
| Handle parsing errors | Check `parsing_errors` array | Logged and returned in results |
| Export results | `exportAnalysisToJson()` | Creates `[filename]_analysis.json` |
| Find all .adg files | `findAdgFiles()` | Recursive directory search |

### Key Data Structure Fields
| Field | Type | Purpose |
|-------|------|---------|
| `rack_name` | String | User-visible rack name |
| `rack_type` | String | AudioEffect/Instrument/MidiEffect GroupDevice |
| `chains` | Array | Individual device chains with ranges |
| `macro_controls` | Array | Named macro assignments |
| `ableton_version` | String | Live version that created the rack |
| `parsing_errors` | Array | Critical failures during analysis |

## 🚀 Development Workflow
### Local Setup Verification
```bash
# These should all work after Laravel integration:
php artisan tinker  # Laravel console
# Test file processing:
AbletonRackAnalyzer::decompressAndParseAbletonFile('drumracks/DECAP DTK 3 Kit.adg')
```

### Before Committing Checklist
- [ ] Test with both sample .adg files
- [ ] Verify no new PHP errors/warnings
- [ ] Update device mapping for any new Ableton devices
- [ ] Test error handling with malformed files
- [ ] Check memory usage with large files

## 💡 Project-Specific Gotchas
1. **Gotcha**: Ableton device names vs. display names
   - **Solution**: Always use `$deviceTypeMap` for user-facing names

2. **Gotcha**: XML namespace issues in .adg files
   - **Solution**: Use xpath with care, validate structure before access

3. **Gotcha**: Nested rack depth can cause memory issues
   - **Solution**: 10-level depth limit with early termination

4. **Gotcha**: Custom device names override detection
   - **Solution**: Extract both UserName (custom) and type (original)

## 📝 Terminology Glossary
| Project Term | Means | Don't Confuse With |
|--------------|-------|--------------------|
| .adg file | Ableton Device Group (gzipped XML) | .als project file |
| Rack | Container for multiple device chains | Single audio device |
| Chain | Individual device sequence with key/velocity ranges | Single device |
| Device | Individual audio processor/instrument | Plugin (broader term) |
| Macro Control | User-assignable control knob | Device-specific parameter |
| Branch Preset | XML structure containing chain data | Device preset |

## 🎵 Domain-Specific Knowledge
### Ableton Live Context
- **Drum Racks**: Specialized instrument racks for percussion
- **Device Chains**: Parallel processing paths with key/velocity splits
- **Macro Controls**: 1-8 user-assignable knobs per rack
- **Sample Dependencies**: Drum racks reference external audio files

### Target Users (from PRD)
1. **Marcus the Beat Architect**: Professional producer, efficiency-focused
   - Needs: Performance optimization, collaboration tools
2. **Sarah the Sound Explorer**: Learning-focused sound designer  
   - Needs: Educational insights, technique understanding

## 🔄 Living Document Rules
### Auto-Update Triggers
- When finding new Ableton device types → Add to device mapping
- When discovering parsing edge cases → Document in gotchas
- When adding new analysis features → Update data structure docs
- When supporting new Live versions → Update version handling

### Reference Cadence
- Before adding device support: Check device mapping patterns
- Before XML parsing: Review xpath navigation patterns  
- Before error handling: Check existing error categorization
- When debugging: Review gotchas and known limitations

## 📋 Version History
- **v3**: Current PHP implementation with Laravel integration
- **Previous**: Python implementation (referenced in code comments)
- **Future**: Web interface for drag-and-drop analysis

## 🎯 Success Metrics (from PRD)
- Parse 100% of .adg files without crashes
- Support all Ableton Live versions (9-12+)
- Educational value for learning producers
- Performance optimization insights for professionals