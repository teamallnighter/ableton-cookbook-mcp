<?php

require_once 'abletonDrumRackAnalyzer.php';

use App\Services\AbletonDrumRackAnalyzer\AbletonDrumRackAnalyzer;

// Simple logging mock for testing
class Log {
    public static function error($message, $context = []) {
        echo "[ERROR] $message\n";
        if (!empty($context)) {
            echo "Context: " . json_encode($context) . "\n";
        }
    }
    
    public static function warning($message, $context = []) {
        echo "[WARNING] $message\n";
        if (!empty($context)) {
            echo "Context: " . json_encode($context) . "\n";
        }
    }
    
    public static function info($message, $context = []) {
        echo "[INFO] $message\n";
        if (!empty($context)) {
            echo "Context: " . json_encode($context) . "\n";
        }
    }
    
    public static function debug($message, $context = []) {
        echo "[DEBUG] $message\n";
        if (!empty($context)) {
            echo "Context: " . json_encode($context) . "\n";
        }
    }
}

// Create a mock namespace for Illuminate\Support\Facades
namespace Illuminate\Support\Facades {
    class Log extends \Log {}
}

// Test the analyzer
echo "=== Drum Rack Analyzer Test ===\n\n";

$testFile = 'drumracks/DECAP DTK 3 Kit.adg';

echo "Testing file: $testFile\n";

if (!file_exists($testFile)) {
    echo "ERROR: Test file not found: $testFile\n";
    exit(1);
}

echo "File size: " . round(filesize($testFile) / 1024, 2) . " KB\n\n";

// Test 1: File decompression and parsing
echo "=== Test 1: File Decompression ===\n";
$xml = AbletonDrumRackAnalyzer::decompressAndParseAbletonFile($testFile);

if ($xml) {
    echo "✓ File decompressed and parsed successfully\n";
    echo "Root element: " . $xml->getName() . "\n";
} else {
    echo "✗ Failed to decompress and parse file\n";
    exit(1);
}

// Test 2: Drum rack detection
echo "\n=== Test 2: Drum Rack Detection ===\n";
list($rackType, $mainDevice) = AbletonDrumRackAnalyzer::detectDrumRackTypeAndDevice($xml);

if ($rackType) {
    echo "✓ Detected rack type: $rackType\n";
} else {
    echo "✗ Could not detect drum rack type\n";
}

// Test 3: Name extraction
echo "\n=== Test 3: Name Extraction ===\n";
$drumRackName = AbletonDrumRackAnalyzer::extractDrumRackName($xml);
echo "Drum rack name: " . ($drumRackName ?? 'Unknown') . "\n";

// Test 4: Version extraction
echo "\n=== Test 4: Version Extraction ===\n";
$versionInfo = AbletonDrumRackAnalyzer::extractAbletonVersionInfo($xml);
echo "Ableton version: " . ($versionInfo['ableton_version'] ?? 'Unknown') . "\n";

// Test 5: Full analysis
echo "\n=== Test 5: Full Drum Rack Analysis ===\n";
$analysis = AbletonDrumRackAnalyzer::parseDrumRackChainsAndDevices($xml, $testFile, true);

if ($analysis) {
    echo "✓ Analysis completed successfully\n";
    echo "Drum rack name: " . $analysis['drum_rack_name'] . "\n";
    echo "Total chains: " . count($analysis['drum_chains']) . "\n";
    echo "Active pads: " . $analysis['drum_statistics']['active_pads'] . "\n";
    echo "Sample-based pads: " . $analysis['drum_statistics']['sample_based_pads'] . "\n";
    echo "Synthesized pads: " . $analysis['drum_statistics']['synthesized_pads'] . "\n";
    echo "Macro controls: " . count($analysis['macro_controls']) . "\n";
    echo "Parsing errors: " . count($analysis['parsing_errors']) . "\n";
    echo "Parsing warnings: " . count($analysis['parsing_warnings']) . "\n";
    
    // Show first few chains
    if (!empty($analysis['drum_chains'])) {
        echo "\n--- Sample Chains ---\n";
        $count = 0;
        foreach ($analysis['drum_chains'] as $chain) {
            if ($count >= 3) break;
            echo "Chain: " . $chain['name'] . "\n";
            echo "  Devices: " . count($chain['devices']) . "\n";
            if (isset($chain['drum_annotations']['midi_note'])) {
                echo "  MIDI Note: " . $chain['drum_annotations']['midi_note'] . "\n";
            }
            if (isset($chain['drum_annotations']['drum_type'])) {
                echo "  Drum Type: " . $chain['drum_annotations']['drum_type'] . "\n";
            }
            $count++;
        }
    }
    
} else {
    echo "✗ Analysis failed\n";
}

// Test 6: Performance analysis
echo "\n=== Test 6: Performance Analysis ===\n";
$performance = AbletonDrumRackAnalyzer::analyzeDrumRackPerformance($analysis);
echo "Complexity score: " . $performance['complexity_score'] . "\n";
echo "Recommendations: " . count($performance['recommendations']) . "\n";
foreach ($performance['recommendations'] as $rec) {
    echo "  - $rec\n";
}

// Test 7: JSON export
echo "\n=== Test 7: JSON Export ===\n";
$jsonPath = AbletonDrumRackAnalyzer::exportDrumRackAnalysisToJson($analysis, $testFile, '.');
if ($jsonPath && file_exists($jsonPath)) {
    echo "✓ JSON exported to: $jsonPath\n";
    echo "JSON file size: " . round(filesize($jsonPath) / 1024, 2) . " KB\n";
} else {
    echo "✗ JSON export failed\n";
}

echo "\n=== Test Complete ===\n";