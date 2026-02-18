<?php

namespace App\Services;

use App\Models\Rack;
use App\Models\NestedChain;
use App\Models\EnhancedRackAnalysis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use DOMDocument;
use DOMXPath;
use Exception;

/**
 * NestedChainAnalyzer Service
 *
 * Constitutional Requirement: ALL CHAINS must be detected and included in analysis.
 * This service implements comprehensive nested chain detection with constitutional compliance validation.
 */
class NestedChainAnalyzer
{
    private const MAX_ANALYSIS_DURATION_MS = 5000; // Constitutional 5-second limit
    private const MAX_NESTING_DEPTH = 10; // Reasonable safety limit
    private const ANALYZER_VERSION = '1.0.0';

    private array $detectedChains = [];
    private array $complianceIssues = [];
    private int $totalDevicesAnalyzed = 0;
    private array $deviceTypeBreakdown = [];
    private float $analysisStartTime;

    /**
     * Analyze nested chains in a rack file
     */
    public function analyzeRack(Rack $rack): array
    {
        $this->analysisStartTime = microtime(true);
        $this->resetAnalysisState();

        Log::info('Starting enhanced nested chain analysis', [
            'rack_uuid' => $rack->uuid,
            'constitutional_requirement' => 'ALL CHAINS must be detected'
        ]);

        try {
            // Validate rack file exists
            if (!Storage::exists($rack->file_path)) {
                throw new Exception('Rack file not found: ' . $rack->file_path);
            }

            // Load and decompress rack file
            $xmlContent = $this->loadRackXml($rack->file_path);

            // Parse XML and detect all chains
            $dom = $this->parseXmlContent($xmlContent);
            $xpath = new DOMXPath($dom);

            // Constitutional requirement: detect ALL chains
            $this->detectAllChains($rack, $xpath);

            // Validate constitutional compliance
            $constitutionalCompliant = $this->validateConstitutionalCompliance();

            // Calculate analysis duration
            $analysisDurationMs = (int) ((microtime(true) - $this->analysisStartTime) * 1000);

            // Build analysis results
            $results = [
                'rack_uuid' => $rack->uuid,
                'analysis_complete' => true,
                'constitutional_compliant' => $constitutionalCompliant,
                'nested_chains_detected' => count($this->detectedChains),
                'max_nesting_depth' => $this->calculateMaxDepth(),
                'total_devices' => $this->totalDevicesAnalyzed,
                'device_type_breakdown' => $this->deviceTypeBreakdown,
                'analysis_duration_ms' => $analysisDurationMs,
                'compliance_issues' => $this->complianceIssues,
                'analyzer_version' => self::ANALYZER_VERSION,
                'processed_at' => now(),
                'hierarchy_preview' => $this->buildHierarchyPreview()
            ];

            // Check if analysis exceeded constitutional time limit
            if ($analysisDurationMs > self::MAX_ANALYSIS_DURATION_MS) {
                $this->complianceIssues[] = "Analysis duration ({$analysisDurationMs}ms) exceeded constitutional limit (" . self::MAX_ANALYSIS_DURATION_MS . "ms)";
                $results['constitutional_compliant'] = false;
            }

            Log::info('Enhanced nested chain analysis completed', [
                'rack_uuid' => $rack->uuid,
                'chains_detected' => count($this->detectedChains),
                'constitutional_compliant' => $constitutionalCompliant,
                'duration_ms' => $analysisDurationMs
            ]);

            return $results;

        } catch (Exception $e) {
            $analysisDurationMs = (int) ((microtime(true) - $this->analysisStartTime) * 1000);

            Log::error('Enhanced nested chain analysis failed', [
                'rack_uuid' => $rack->uuid,
                'error' => $e->getMessage(),
                'duration_ms' => $analysisDurationMs
            ]);

            return [
                'rack_uuid' => $rack->uuid,
                'analysis_complete' => false,
                'constitutional_compliant' => false,
                'error' => $e->getMessage(),
                'analysis_duration_ms' => $analysisDurationMs,
                'processed_at' => now()
            ];
        }
    }

    /**
     * Detect all chains in the rack XML (constitutional requirement)
     */
    private function detectAllChains(Rack $rack, DOMXPath $xpath): void
    {
        // Constitutional requirement: ALL CHAINS must be detected
        // XPath patterns to find all possible chain structures
        $chainPatterns = [
            '//DeviceChain',                           // Standard device chains
            '//MidiControllers//*[contains(name(), "Chain")]', // MIDI controller chains
            '//GroupDevice//DeviceChain',              // Nested group device chains
            '//InstrumentVector//DeviceChain',         // Instrument rack chains
            '//DrumPadVector//DeviceChain',            // Drum rack chains
            '//EffectChain',                           // Effect chains
            '//SendsListWrapper//DeviceChain',         // Send chains
            '//ReturnVectorCluster//DeviceChain',      // Return chains
            '//InputRouting//DeviceChain',             // Input routing chains
            '//OutputRouting//DeviceChain',            // Output routing chains
        ];

        foreach ($chainPatterns as $pattern) {
            $chainNodes = $xpath->query($pattern);

            if ($chainNodes->length > 0) {
                Log::debug('Found chains with pattern', [
                    'pattern' => $pattern,
                    'count' => $chainNodes->length
                ]);

                foreach ($chainNodes as $chainNode) {
                    $this->analyzeChainNode($rack, $chainNode, $xpath);
                }
            }
        }

        // Additional constitutional validation: scan for any missed chains
        $this->performComprehensiveChainScan($rack, $xpath);
    }

    /**
     * Analyze individual chain node
     */
    private function analyzeChainNode(Rack $rack, $chainNode, DOMXPath $xpath, int $depth = 0, ?string $parentChainId = null): void
    {
        if ($depth > self::MAX_NESTING_DEPTH) {
            $this->complianceIssues[] = "Chain nesting depth exceeded maximum limit ({$depth} > " . self::MAX_NESTING_DEPTH . ")";
            return;
        }

        // Generate unique chain identifier
        $chainPath = $chainNode->getNodePath();
        $chainIdentifier = $this->generateChainIdentifier($chainPath, $depth);

        // Skip if already analyzed
        if (isset($this->detectedChains[$chainIdentifier])) {
            return;
        }

        // Analyze devices in this chain
        $devices = $this->analyzeChainDevices($chainNode, $xpath);
        $deviceCount = count($devices);

        // Update device statistics
        $this->totalDevicesAnalyzed += $deviceCount;
        $this->updateDeviceTypeBreakdown($devices);

        // Determine chain type
        $chainType = $this->determineChainType($chainNode, $devices);

        // Create chain record
        $chainData = [
            'rack_id' => $rack->id,
            'chain_identifier' => $chainIdentifier,
            'xml_path' => $chainPath,
            'parent_chain_id' => $parentChainId,
            'depth_level' => $depth,
            'device_count' => $deviceCount,
            'is_empty' => $deviceCount === 0,
            'chain_type' => $chainType,
            'devices' => $devices,
            'parameters' => $this->extractChainParameters($chainNode, $xpath),
            'chain_metadata' => $this->extractChainMetadata($chainNode),
            'analyzed_at' => now()
        ];

        $this->detectedChains[$chainIdentifier] = $chainData;

        // Constitutional requirement: recursively analyze nested chains
        $this->analyzeNestedChains($rack, $chainNode, $xpath, $depth + 1, $chainIdentifier);
    }

    /**
     * Analyze nested chains within a parent chain
     */
    private function analyzeNestedChains(Rack $rack, $parentChainNode, DOMXPath $xpath, int $depth, string $parentChainId): void
    {
        // Look for nested chains within this chain
        $nestedChainPatterns = [
            './/DeviceChain',
            './/GroupDevice//DeviceChain',
            './/InstrumentVector//DeviceChain',
            './/DrumPadVector//DeviceChain'
        ];

        foreach ($nestedChainPatterns as $pattern) {
            $nestedChains = $xpath->query($pattern, $parentChainNode);

            foreach ($nestedChains as $nestedChain) {
                // Skip the parent chain itself
                if ($nestedChain === $parentChainNode) {
                    continue;
                }

                $this->analyzeChainNode($rack, $nestedChain, $xpath, $depth, $parentChainId);
            }
        }
    }

    /**
     * Analyze devices within a chain
     */
    private function analyzeChainDevices($chainNode, DOMXPath $xpath): array
    {
        $devices = [];

        // Device patterns to find all device types
        $devicePatterns = [
            './/DeviceSlot//Device',
            './/Device[not(ancestor::DeviceSlot)]',
            './/MxDeviceAudioEffect',
            './/MxDeviceMidiEffect',
            './/PluginDevice',
            './/GroupDevice',
            './/AuDevice'
        ];

        $deviceIndex = 0;
        foreach ($devicePatterns as $pattern) {
            $deviceNodes = $xpath->query($pattern, $chainNode);

            foreach ($deviceNodes as $deviceNode) {
                $device = $this->extractDeviceInfo($deviceNode, $xpath, $deviceIndex++);
                if ($device) {
                    $devices[] = $device;
                }
            }
        }

        return $devices;
    }

    /**
     * Extract device information
     */
    private function extractDeviceInfo($deviceNode, DOMXPath $xpath, int $index): ?array
    {
        try {
            // Get device name
            $deviceName = $this->extractDeviceName($deviceNode, $xpath);
            if (!$deviceName) {
                return null;
            }

            // Get device type
            $deviceType = $this->extractDeviceType($deviceNode);

            // Check if Max for Live device
            $isMaxForLive = $deviceNode->nodeName === 'MxDeviceAudioEffect' ||
                           $deviceNode->nodeName === 'MxDeviceMidiEffect';

            // Extract parameters
            $parameters = $this->extractDeviceParameters($deviceNode, $xpath);

            return [
                'device_name' => $deviceName,
                'device_type' => $deviceType,
                'device_index' => $index,
                'is_max_for_live' => $isMaxForLive,
                'parameters' => $parameters,
                'xml_node_name' => $deviceNode->nodeName
            ];

        } catch (Exception $e) {
            Log::warning('Failed to extract device info', [
                'node_name' => $deviceNode->nodeName ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Extract device name from XML node
     */
    private function extractDeviceName($deviceNode, DOMXPath $xpath): ?string
    {
        // Multiple patterns to find device names
        $namePatterns = [
            './/OriginalName/@Value',
            './/FileName/@Value',
            './/Name/@Value',
            './/UserName/@Value',
            './/DeviceName/@Value'
        ];

        foreach ($namePatterns as $pattern) {
            $nameNode = $xpath->query($pattern, $deviceNode)->item(0);
            if ($nameNode && !empty($nameNode->nodeValue)) {
                return trim($nameNode->nodeValue);
            }
        }

        // Fallback to node name
        return $deviceNode->nodeName;
    }

    /**
     * Extract device type
     */
    private function extractDeviceType($deviceNode): string
    {
        $nodeName = $deviceNode->nodeName;

        // Map XML node names to device types
        $typeMap = [
            'MxDeviceAudioEffect' => 'max_for_live_audio',
            'MxDeviceMidiEffect' => 'max_for_live_midi',
            'PluginDevice' => 'plugin',
            'GroupDevice' => 'group',
            'AuDevice' => 'audio_unit',
            'DeviceSlot' => 'device_slot'
        ];

        return $typeMap[$nodeName] ?? 'unknown';
    }

    /**
     * Extract device parameters
     */
    private function extractDeviceParameters($deviceNode, DOMXPath $xpath): array
    {
        $parameters = [];

        // Look for parameter nodes
        $paramNodes = $xpath->query('.//Parameter', $deviceNode);

        foreach ($paramNodes as $paramNode) {
            $paramId = $xpath->query('./@Id', $paramNode)->item(0)?->nodeValue;
            $paramValue = $xpath->query('./Manual/@Value', $paramNode)->item(0)?->nodeValue;

            if ($paramId !== null) {
                $parameters[$paramId] = $paramValue;
            }
        }

        return $parameters;
    }

    /**
     * Extract chain parameters
     */
    private function extractChainParameters($chainNode, DOMXPath $xpath): array
    {
        $parameters = [];

        // Look for macro controls and other chain-level parameters
        $macroNodes = $xpath->query('.//MacroControls//MacroControl', $chainNode);

        foreach ($macroNodes as $macroNode) {
            $macroId = $xpath->query('./@Id', $macroNode)->item(0)?->nodeValue;
            $macroValue = $xpath->query('./Manual/@Value', $macroNode)->item(0)?->nodeValue;

            if ($macroId !== null) {
                $parameters["macro_{$macroId}"] = $macroValue;
            }
        }

        return $parameters;
    }

    /**
     * Extract chain metadata
     */
    private function extractChainMetadata($chainNode): array
    {
        return [
            'node_name' => $chainNode->nodeName,
            'attributes' => $this->getNodeAttributes($chainNode),
            'has_sends' => $this->hasChildNode($chainNode, 'Sends'),
            'has_returns' => $this->hasChildNode($chainNode, 'Returns'),
            'has_automation' => $this->hasChildNode($chainNode, 'AutomationEnvelopes')
        ];
    }

    /**
     * Get node attributes
     */
    private function getNodeAttributes($node): array
    {
        $attributes = [];

        if ($node->hasAttributes()) {
            foreach ($node->attributes as $attr) {
                $attributes[$attr->nodeName] = $attr->nodeValue;
            }
        }

        return $attributes;
    }

    /**
     * Check if node has child with specific name
     */
    private function hasChildNode($node, string $childName): bool
    {
        foreach ($node->childNodes as $child) {
            if ($child->nodeName === $childName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine chain type based on context and devices
     */
    private function determineChainType($chainNode, array $devices): string
    {
        $nodeName = $chainNode->nodeName;
        $parentNode = $chainNode->parentNode;

        // Determine type based on context
        if ($parentNode) {
            $parentName = $parentNode->nodeName;

            if (str_contains($parentName, 'Instrument')) {
                return 'instrument';
            } elseif (str_contains($parentName, 'DrumPad')) {
                return 'drum_pad';
            } elseif (str_contains($parentName, 'Effect')) {
                return 'audio_effect';
            } elseif (str_contains($parentName, 'Midi')) {
                return 'midi_effect';
            }
        }

        // Determine type based on devices
        foreach ($devices as $device) {
            if (str_contains(strtolower($device['device_type']), 'instrument')) {
                return 'instrument';
            } elseif (str_contains(strtolower($device['device_type']), 'midi')) {
                return 'midi_effect';
            }
        }

        // Default to audio effect for chains with devices
        return !empty($devices) ? 'audio_effect' : 'unknown';
    }

    /**
     * Generate unique chain identifier
     */
    private function generateChainIdentifier(string $xmlPath, int $depth): string
    {
        $pathHash = substr(md5($xmlPath), 0, 8);
        $depthPrefix = str_pad((string) $depth, 2, '0', STR_PAD_LEFT);

        return "chain_{$depthPrefix}_{$pathHash}";
    }

    /**
     * Update device type breakdown statistics
     */
    private function updateDeviceTypeBreakdown(array $devices): void
    {
        foreach ($devices as $device) {
            $type = $device['device_type'];
            $this->deviceTypeBreakdown[$type] = ($this->deviceTypeBreakdown[$type] ?? 0) + 1;
        }
    }

    /**
     * Perform comprehensive chain scan to ensure ALL chains are detected
     */
    private function performComprehensiveChainScan(Rack $rack, DOMXPath $xpath): void
    {
        // Constitutional validation: scan entire document for any missed chains
        $allNodesWithChain = $xpath->query('//*[contains(name(), "Chain")]');

        $scannedChains = [];
        foreach ($allNodesWithChain as $node) {
            $scannedChains[] = $node->getNodePath();
        }

        $detectedPaths = array_column($this->detectedChains, 'xml_path');
        $missedPaths = array_diff($scannedChains, $detectedPaths);

        if (!empty($missedPaths)) {
            $this->complianceIssues[] = 'Comprehensive scan detected ' . count($missedPaths) . ' potentially missed chains';

            Log::warning('Potential chains missed during primary analysis', [
                'rack_uuid' => $rack->uuid,
                'missed_paths' => $missedPaths,
                'constitutional_violation' => 'ALL CHAINS requirement not fully satisfied'
            ]);

            // Attempt to analyze missed chains
            foreach ($missedPaths as $missedPath) {
                $missedNode = $xpath->query($missedPath)->item(0);
                if ($missedNode) {
                    $this->analyzeChainNode($rack, $missedNode, $xpath);
                }
            }
        }
    }

    /**
     * Validate constitutional compliance
     */
    private function validateConstitutionalCompliance(): bool
    {
        // Constitutional requirements validation
        $isCompliant = true;

        // Requirement 1: ALL CHAINS must be detected (no specific count, but must be comprehensive)
        if (empty($this->detectedChains)) {
            // Check if this is actually a rack with no chains (valid) or missed chains (invalid)
            $this->complianceIssues[] = 'No chains detected - verify this is expected for this rack type';
        }

        // Requirement 2: Analysis must complete within 5 seconds
        $currentDuration = (microtime(true) - $this->analysisStartTime) * 1000;
        if ($currentDuration > self::MAX_ANALYSIS_DURATION_MS) {
            $this->complianceIssues[] = "Analysis duration exceeded constitutional limit ({$currentDuration}ms > " . self::MAX_ANALYSIS_DURATION_MS . "ms)";
            $isCompliant = false;
        }

        // Requirement 3: All detected chains must have valid identifiers and paths
        foreach ($this->detectedChains as $chain) {
            if (empty($chain['chain_identifier']) || empty($chain['xml_path'])) {
                $this->complianceIssues[] = 'Invalid chain detected without proper identifier or XML path';
                $isCompliant = false;
            }
        }

        return $isCompliant && empty($this->complianceIssues);
    }

    /**
     * Calculate maximum nesting depth
     */
    private function calculateMaxDepth(): int
    {
        $maxDepth = 0;

        foreach ($this->detectedChains as $chain) {
            $maxDepth = max($maxDepth, $chain['depth_level']);
        }

        return $maxDepth;
    }

    /**
     * Build hierarchy preview for quick visualization
     */
    private function buildHierarchyPreview(): array
    {
        $preview = [];

        // Get root chains (depth 0)
        $rootChains = array_filter($this->detectedChains, fn($chain) => $chain['depth_level'] === 0);

        foreach ($rootChains as $rootChain) {
            $preview[] = [
                'chain_identifier' => $rootChain['chain_identifier'],
                'device_count' => $rootChain['device_count'],
                'depth_level' => $rootChain['depth_level'],
                'chain_type' => $rootChain['chain_type'],
                'has_children' => $this->hasChildChains($rootChain['chain_identifier'])
            ];
        }

        return array_slice($preview, 0, 10); // Limit preview to first 10 chains
    }

    /**
     * Check if chain has child chains
     */
    private function hasChildChains(string $parentChainId): bool
    {
        foreach ($this->detectedChains as $chain) {
            if ($chain['parent_chain_id'] === $parentChainId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reset analysis state for new analysis
     */
    private function resetAnalysisState(): void
    {
        $this->detectedChains = [];
        $this->complianceIssues = [];
        $this->totalDevicesAnalyzed = 0;
        $this->deviceTypeBreakdown = [];
    }

    /**
     * Load and decompress rack XML content
     */
    private function loadRackXml(string $filePath): string
    {
        $fileContent = Storage::get($filePath);

        // .adg files are gzipped XML
        $xmlContent = gzdecode($fileContent);

        if ($xmlContent === false) {
            throw new Exception('Failed to decompress rack file - invalid gzip format');
        }

        return $xmlContent;
    }

    /**
     * Parse XML content and return DOM document
     */
    private function parseXmlContent(string $xmlContent): DOMDocument
    {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        // Suppress XML parsing warnings and handle them gracefully
        libxml_use_internal_errors(true);

        $loaded = $dom->loadXML($xmlContent);

        if (!$loaded) {
            $errors = libxml_get_errors();
            $errorMessages = array_map(fn($error) => trim($error->message), $errors);
            libxml_clear_errors();

            throw new Exception('Failed to parse XML content: ' . implode('; ', $errorMessages));
        }

        libxml_use_internal_errors(false);

        return $dom;
    }

    /**
     * Get detected chains for storage
     */
    public function getDetectedChains(): array
    {
        return $this->detectedChains;
    }

    /**
     * Get analysis statistics
     */
    public function getAnalysisStatistics(): array
    {
        return [
            'total_chains_detected' => count($this->detectedChains),
            'total_devices_analyzed' => $this->totalDevicesAnalyzed,
            'device_type_breakdown' => $this->deviceTypeBreakdown,
            'max_nesting_depth' => $this->calculateMaxDepth(),
            'compliance_issues_count' => count($this->complianceIssues),
            'analyzer_version' => self::ANALYZER_VERSION
        ];
    }
}