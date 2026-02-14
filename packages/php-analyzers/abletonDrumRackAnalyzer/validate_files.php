<?php

echo "=== Drum Rack File Validation ===\n\n";

$files = [
    'drumracks/DECAP DTK 3 Kit.adg',
    'drumracks/Fear Pressure Kit.adg'
];

foreach ($files as $file) {
    echo "Testing: $file\n";
    
    if (!file_exists($file)) {
        echo "  ✗ File not found\n";
        continue;
    }
    
    $fileSize = filesize($file);
    echo "  File size: " . round($fileSize / 1024, 2) . " KB\n";
    
    // Test gzip decompression
    $xmlContent = @file_get_contents("compress.zlib://$file");
    if ($xmlContent === false) {
        echo "  ✗ Failed to decompress\n";
        continue;
    }
    
    echo "  ✓ Decompressed successfully (" . round(strlen($xmlContent) / 1024, 2) . " KB XML)\n";
    
    // Test XML parsing
    $xml = @simplexml_load_string($xmlContent);
    if ($xml === false) {
        echo "  ✗ XML parsing failed\n";
        continue;
    }
    
    echo "  ✓ XML parsed successfully\n";
    echo "  Root element: " . $xml->getName() . "\n";
    
    // Check for drum rack elements
    $drumRack = $xml->xpath('.//DrumRack');
    $drumGroup = $xml->xpath('.//DrumGroupDevice');
    $instrumentGroup = $xml->xpath('.//InstrumentGroupDevice');
    
    echo "  DrumRack elements: " . count($drumRack) . "\n";
    echo "  DrumGroupDevice elements: " . count($drumGroup) . "\n";
    echo "  InstrumentGroupDevice elements: " . count($instrumentGroup) . "\n";
    
    if (!empty($drumRack) || !empty($drumGroup) || !empty($instrumentGroup)) {
        echo "  ✓ Drum rack structure detected\n";
    } else {
        echo "  ⚠ No obvious drum rack structure found\n";
    }
    
    echo "\n";
}

echo "=== Validation Complete ===\n";