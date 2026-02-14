<?php

namespace App\Services\AbletonRackAnalyzer;

use Exception;
use SimpleXMLElement;
use Illuminate\Support\Facades\Log;

/**
 * Ableton Rack Analyzer - Object-Oriented Version with Improved Security and Maintainability
 * 
 * - Parses Ableton files, extracting rack, device, and chain information.
 * - Uses XPath with caching for performance.
 * - Designed for testability and clarity.
 */
class AbletonRackAnalyzer
{
    /**
     * Map of device types to standardized names.
     * Fill this with actual mappings as needed.
     */
    public const DEVICE_TYPE_MAP = [
        // 'AudioEffectDevice' => 'Audio Effect',
        // 'InstrumentDevice' => 'Instrument',
        // Add actual mappings here
    ];

    /**
     * Map of branch types within racks.
     * Fill this with actual mappings as needed.
     */
    public const BRANCH_TYPE_MAP = [
        // 'InstrumentChain' => 'InstrumentChain',
        // 'EffectChain' => 'EffectChain',
        // Add actual mappings here
    ];

    /**
     * XPath expressions used repeatedly.
     */
    public const XPATH_GROUP_DEVICE_PRESET = './/GroupDevicePreset';
    public const XPATH_BRANCH_PRESETS = './/BranchPresets';
    public const XPATH_NAME = './/Name';
    public const XPATH_DEVICE = 'Device';
    public const XPATH_Ableton = './/Ableton';
    // Add more XPath constants as needed

    /**
     * Internal cache for XPath queries per node.
     * Keyed by object hash.
     */
    protected $xpathCache = [];

    /**
     * Constructor.
     */
    public function __construct()
    {
        // Initialize or configure if needed
    }

    /**
     * Parses an Ableton file, decompresses, and loads as XML.
     * Protects against XXE attacks by disabling external entities.
     * 
     * @param string $filePath Path to the compressed Ableton file.
     * @return SimpleXMLElement|null Parsed XML or null on failure.
     */
    public function decompressAndParseAbletonFile(string $filePath): ?SimpleXMLElement
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            Log::error("File not accessible: $filePath");
            return null;
        }

        $fileSize = filesize($filePath);
        if ($fileSize > 100 * 1024 * 1024) {
            Log::warning("File too large ($fileSize bytes): $filePath");
            return null;
        }
        if ($fileSize < 100) {
            Log::warning("File too small ($fileSize bytes): $filePath");
            return null;
        }

        // Disable external entities for security
        libxml_disable_entity_loader(true);
        $xmlContent = @file_get_contents("compress.zlib://$filePath");
        libxml_disable_entity_loader(false);

        if ($xmlContent === false || trim($xmlContent) === '') {
            Log::error("Failed to decompress or empty content: $filePath");
            return null;
        }

        $trimmed = ltrim($xmlContent);
        if (!str_starts_with($trimmed, '<?xml') && !str_starts_with($trimmed, '<')) {
            Log::error("Invalid XML format: $filePath");
            return null;
        }

        $xml = @simplexml_load_string($xmlContent);
        if ($xml === false) {
            Log::error("XML parsing error: $filePath");
            return null;
        }

        $rootTag = $xml->getName();
        if (!in_array($rootTag, ['Ableton', 'GroupDevicePreset', 'PresetRef'])) {
            Log::warning("Unexpected root tag '$rootTag' in: $filePath");
        }

        return $xml;
    }

    /**
     * Cache XPath query results for a node.
     * Prevents repeated XPath evaluations for performance.
     * 
     * @param SimpleXMLElement $node
     * @param string $expression XPath expression
     * @return array Result of XPath query.
     */
    protected function getXpathCached(SimpleXMLElement $node, string $expression): array
    {
        $nodeHash = spl_object_hash($node);
        if (!isset($this->xpathCache[$nodeHash])) {
            $this->xpathCache[$nodeHash] = [];
        }
        if (!isset($this->xpathCache[$nodeHash][$expression])) {
            $this->xpathCache[$nodeHash][$expression] = $node->xpath($expression);
        }
        return $this->xpathCache[$nodeHash][$expression];
    }

    /**
     * Clears all XPath caches.
     */
    public function clearXpathCache(): void
    {
        $this->xpathCache = [];
    }

    /**
     * Detect the rack type and main device from the root XML node.
     *
     * @param SimpleXMLElement $root
     * @return array[$rackType, $mainDevice]
     */
    public function detectRackTypeAndDevice(SimpleXMLElement $root): array
    {
        $groupPresetNodes = $this->getXpathCached($root, self::XPATH_GROUP_DEVICE_PRESET);
        if (!empty($groupPresetNodes)) {
            // Check within GroupDevicePreset
            $deviceNodes = $this->getXpathCached($groupPresetNodes[0], self::XPATH_DEVICE);
            if (!empty($deviceNodes)) {
                foreach ($deviceNodes[0] as $child) {
                    $deviceName = $child->getName();
                    if (array_key_exists($deviceName, self::BRANCH_TYPE_MAP)) {
                        return [$deviceName, $child];
                    }
                }
            }
        }
        // Check at root level for device types
        foreach (array_keys(self::BRANCH_TYPE_MAP) as $rackType) {
            $deviceNodes = $this->getXpathCached($root, ".//{$rackType}");
            if (!empty($deviceNodes)) {
                return [$rackType, $deviceNodes[0]];
            }
        }

        return [null, null];
    }

    /**
     * Extract the rack name from the XML.
     *
     * @param SimpleXMLElement $root
     * @return string|null
     */
    public function extractRackNameFromXml(SimpleXMLElement $root): ?string
    {
        $nameElems = $this->getXpathCached($root, './/GroupDevicePreset/Name');
        if (!empty($nameElems) && isset($nameElems[0]['Value'])) {
            $name = trim((string)$nameElems[0]['Value']);
            if ($name !== '') {
                return $name;
            }
        }
        // Check UserName in device within group preset
        $deviceNodes = $this->getXpathCached($root, './/GroupDevicePreset/Device');
        if (!empty($deviceNodes) && isset($deviceNodes[0])) {
            foreach ($deviceNodes[0] as $child) {
                $userNameElems = $this->getXpathCached($child, 'UserName');
                if (!empty($userNameElems) && isset($userNameElems[0]['Value'])) {
                    $name = trim((string)$userNameElems[0]['Value']);
                    if ($name !== '') {
                        return $name;
                    }
                }
            }
        }
        // Fallback: root level Name
        $nameElems = $this->getXpathCached($root, './/Name');
        if (!empty($nameElems) && isset($nameElems[0]['Value'])) {
            $name = trim((string)$nameElems[0]['Value']);
            if ($name !== '') {
                return $name;
            }
        }
        return null;
    }

    /**
     * Extract version info from the Ableton XML root node.
     *
     * @param SimpleXMLElement $root
     * @return array Version information with keys: 'ableton_version', 'major_version', 'minor_version', 'build_number', 'revision'
     */
    public function extractAbletonVersionInfo(SimpleXMLElement $root): array
    {
        $info = [
            'ableton_version' => null,
            'major_version' => null,
            'minor_version' => null,
            'build_number' => null,
            'revision' => null,
        ];

        if ($root->getName() === 'Ableton') {
            $attrs = $root->attributes();
            $info['major_version'] = isset($attrs['MajorVersion']) ? (int)$attrs['MajorVersion'] : null;
            $info['minor_version'] = isset($attrs['MinorVersion']) ? (int)$attrs['MinorVersion'] : null;
            $info['build_number'] = isset($attrs['BuildNumber']) ? (int)$attrs['BuildNumber'] : null;
            $info['revision'] = isset($attrs['Revision']) ? (int)$attrs['Revision'] : null;
        }

        $abletonEls = $this->getXpathCached($root, self::XPATH_Ableton);
        if (!empty($abletonEls)) {
            $abletonEl = $abletonEls[0];
            $attrs = $abletonEl->attributes();

            if ($info['major_version'] === null && isset($attrs['MajorVersion'])) {
                $info['major_version'] = (int)$attrs['MajorVersion'];
            }
            if ($info['minor_version'] === null && isset($attrs['MinorVersion'])) {
                $info['minor_version'] = (int)$attrs['MinorVersion'];
            }

            // fallback for nested MajorVersion/MinorVersion nodes (e.g., inside nodes)
            if ($info['major_version'] === null) {
                $mVer = $this->getXpathCached($abletonEl, 'MajorVersion');
                if (!empty($mVer) && isset($mVer[0]['Value'])) {
                    $info['major_version'] = (int)$mVer[0]['Value'];
                }
            }
            if ($info['minor_version'] === null) {
                $mVer = $this->getXpathCached($abletonEl, 'MinorVersion');
                if (!empty($mVer) && isset($mVer[0]['Value'])) {
                    $info['minor_version'] = (int)$mVer[0]['Value'];
                }
            }
            if ($info['build_number'] === null) {
                $bNum = $this->getXpathCached($abletonEl, 'BuildNumber');
                if (!empty($bNum) && isset($bNum[0]['Value'])) {
                    $info['build_number'] = (int)$bNum[0]['Value'];
                }
            }

            // Compose version string
            if ($info['major_version'] !== null) {
                $parts = [$info['major_version']];
                if ($info['minor_version'] !== null) {
                    $parts[] = $info['minor_version'];
                    if ($info['revision'] !== null) {
                        $parts[] = $info['revision'];
                    }
                }
                $info['ableton_version'] = implode('.', $parts);
            }
        }

        // Fallback parsing if no version info found
        if (!$info['ableton_version']) {
            $attrs = $root->attributes();
            if (isset($attrs['Version'])) {
                $info['ableton_version'] = (string)$attrs['Version'];
            } elseif (isset($attrs['Creator']) && strpos((string)$attrs['Creator'], 'Ableton Live') !== false) {
                if (preg_match('/(\d+\.\d+(?:\.\d+)?)/', (string)$attrs['Creator'], $matches)) {
                    $info['ableton_version'] = $matches[1];
                }
            }
        }

        return $info;
    }

    /**
     * Parse the entire rack, including chains and devices.
     * Uses iterative methods for nested structures.
     * 
     * @param SimpleXMLElement $root
     * @param string|null $filename optional filename for rack name fallback
     * @param bool $verbose for logging detailed info
     * @return array structured rack info
     */
    public function parseChainsAndDevices(SimpleXMLElement $root, ?string $filename = null, bool $verbose = false): array
    {
        $rackName = $this->extractRackNameFromXml($root);
        if (!$rackName && $filename !== null) {
            $rackName = pathinfo($filename, PATHINFO_FILENAME);
        } elseif (!$rackName) {
            $rackName = "Unknown";
        }

        $versionInfo = $this->extractAbletonVersionInfo($root);

        $rackInfo = [
            'rack_name' => $rackName,
            'use_case' => $rackName,
            'macro_controls' => [],
            'chains' => [],
            'parsing_errors' => [],
            'parsing_warnings' => [],
            'ableton_version' => $versionInfo['ableton_version'],
            'version_details' => $versionInfo,
        ];

        // Detect rack type
        list($rackType, $mainDevice) = $this->detectRackTypeAndDevice($root);
        if ($verbose) {
            Log::debug("Detected rackType: " . ($rackType ?? 'null'));
        }
        $rackInfo['rack_type'] = $rackType;

        if (!$rackType) {
            $msg = "Unknown rack type; unable to detect.";
            Log::error($msg);
            $rackInfo['parsing_errors'][] = $msg;
            return $rackInfo;
        }

        if ($mainDevice === null) {
            $msg = "No main device found for rack type '$rackType'.";
            Log::error($msg);
            $rackInfo['parsing_errors'][] = $msg;
            return $rackInfo;
        }

        // Parse macro controls
        for ($i = 0; $i < 16; $i++) {
            try {
                $macroNameElems = $this->getXpathCached($mainDevice, "MacroDisplayNames.$i");
                if (!empty($macroNameElems) && isset($macroNameElems[0]['Value'])) {
                    $macroName = trim((string)$macroNameElems[0]['Value']);
                    if ($macroName !== "Macro " . ($i + 1)) {
                        $macroControlElems = $this->getXpathCached($mainDevice, "MacroControls.$i/Manual");
                        $macroValue = 0.0;
                        if (!empty($macroControlElems) && isset($macroControlElems[0]['Value'])) {
                            $macroValue = (float)$macroControlElems[0]['Value'];
                        }
                        $rackInfo['macro_controls'][] = [
                            'name' => $macroName,
                            'value' => $macroValue,
                            'index' => $i,
                        ];
                    }
                }
            } catch (Exception $e) {
                Log::warning("Error parsing macro $i: " . $e->getMessage());
                $rackInfo['parsing_warnings'][] = "Macro $i: " . $e->getMessage();
            }
        }

        // Parse branches/chains
        $branchPresetsNodes = $this->getXpathCached($root, self::XPATH_GROUP_DEVICE_PRESET);
        if (!empty($branchPresetsNodes)) {
            $branchType = $this->getBranchPresetType($rackType);
            if ($branchType) {
                $branches = $this->getXpathCached($branchPresetsNodes[0], $branchType);
                if ($verbose) {
                    Log::debug("Found " . count($branches) . " branches of type $branchType");
                }
                foreach ($branches as $idx => $branch) {
                    try {
                        $chain = $this->parseSingleChainBranch($branch, $idx, $verbose, 0);
                        if ($chain) {
                            $rackInfo['chains'][] = $chain;
                        } else {
                            $msgWarn = "Failed to parse chain at index $idx";
                            Log::warning($msgWarn);
                            $rackInfo['parsing_warnings'][] = $msgWarn;
                        }
                    } catch (Exception $e) {
                        Log::error("Error parsing chain $idx: " . $e->getMessage());
                        $rackInfo['parsing_errors'][] = "Chain $idx: " . $e->getMessage();
                    }
                }
            } else {
                $msg = "Unknown branch type for rack of type $rackType.";
                Log::error($msg);
                $rackInfo['parsing_errors'][] = $msg;
            }
        } else {
            if ($verbose) {
                Log::info("No branch presets found");
            }
        }

        return $rackInfo;
    }

    /**
     * Parse a single chain branch iteratively.
     * 
     * @param SimpleXMLElement $branchXml
     * @param int $chainIndex
     * @param bool $verbose
     * @param int $depth
     * @return array Chain data structure.
     */
    public function parseSingleChainBranch(SimpleXMLElement $branchXml, int $chainIndex = 0, bool $verbose = false, int $depth = 0): array
    {
        $chain = [
            'name' => 'Chain ' . ($chainIndex + 1),
            'is_soloed' => false,
            'devices' => [],
            'chain_index' => $chainIndex,
            'annotations' => [
                'description' => null,
                'purpose' => null,
                'key_range' => null,
                'velocity_range' => null,
                'tags' => [],
            ],
        ];

        // Name
        $nameElems = $this->getXpathCached($branchXml, 'Name');
        if (!empty($nameElems) && isset($nameElems[0]['Value'])) {
            $nameVal = trim((string)$nameElems[0]['Value']);
            if ($nameVal !== '') {
                $chain['name'] = $nameVal;
            }
        }

        // IsSoloed
        $soloElems = $this->getXpathCached($branchXml, 'IsSoloed');
        if (!empty($soloElems) && isset($soloElems[0]['Value'])) {
            $chain['is_soloed'] = ((string)$soloElems[0]['Value']) === 'true';
        }

        // Range info: KeyRange/Min/Max
        $minKeys = $this->getXpathCached($branchXml, 'KeyRange/Min');
        $maxKeys = $this->getXpathCached($branchXml, 'KeyRange/Max');
        if (!empty($minKeys) && !empty($maxKeys)) {
            $chain['annotations']['key_range'] = [
                'low_key' => (int)(($minKeys[0]['Value']) ?? 0),
                'high_key' => (int)(($maxKeys[0]['Value']) ?? 127),
            ];
        }

        // VelocityRange Range
        $minVel = $this->getXpathCached($branchXml, 'VelocityRange/Min');
        $maxVel = $this->getXpathCached($branchXml, 'VelocityRange/Max');
        if (!empty($minVel) && !empty($maxVel)) {
            $chain['annotations']['velocity_range'] = [
                'low_vel' => (int)(($minVel[0]['Value']) ?? 1),
                'high_vel' => (int)(($maxVel[0]['Value']) ?? 127),
            ];
        }

        // Devices: DevicePresets
        $devicePresetsNodes = $this->getXpathCached($branchXml, 'DevicePresets');
        if (!empty($devicePresetsNodes)) {
            foreach ($devicePresetsNodes[0] as $devicePreset) {
                $deviceElems = $this->getXpathCached($devicePreset, 'Device');
                if (!empty($deviceElems) && isset($deviceElems[0])) {
                    foreach ($deviceElems[0] as $childDevice) {
                        $deviceInfo = $this->parseDevice($childDevice, $devicePreset, $depth + 1, $verbose);
                        if ($deviceInfo) {
                            $chain['devices'][] = $deviceInfo;
                        }
                    }
                }
            }
        }

        return $chain;
    }

    /**
     * Parse a device element. Recursive for nested racks.
     * 
     * @param SimpleXMLElement $deviceElem
     * @param SimpleXMLElement|null $parentPreset
     * @param int $depth
     * @param bool $verbose
     * @return array|null Device info or null if max depth exceeded.
     */
    public function parseDevice(SimpleXMLElement $deviceElem, ?SimpleXMLElement $parentPreset = null, int $depth = 0, bool $verbose = false): ?array
    {
        if ($depth > 20) {
            if ($verbose) {
                Log::warning("Max depth reached at {$deviceElem->getName()}");
            }
            return null;
        }

        $type = $deviceElem->getName();
        $standardName = self::DEVICE_TYPE_MAP[$type] ?? $type;

        // UserName override
        $userNameElems = $this->getXpathCached($deviceElem, 'UserName');
        $customName = (!empty($userNameElems) && isset($userNameElems[0]['Value'])) ? trim((string)$userNameElems[0]['Value']) : null;

        $displayName = ($customName && $customName !== '' && $customName !== $standardName) ? $customName : $standardName;
        $presetName = ($customName && $customName !== '' && $customName !== $standardName) ? $standardName : null;

        $deviceInfo = [
            'type' => $type,
            'name' => $displayName,
            'is_on' => true,
            'standard_name' => $standardName,
        ];

        // On/Off status
        $onElems = $this->getXpathCached($deviceElem, 'On/Manual');
        if (!empty($onElems) && isset($onElems[0]['Value'])) {
            $deviceInfo['is_on'] = ((string)$onElems[0]['Value']) === 'true';
        }

        // Nested racks/effect groups
        if (in_array($type, ["AudioEffectGroupDevice", "InstrumentGroupDevice", "MidiEffectGroupDevice"])) {
            $nestedChains = [];

            // Check device's BranchPresets
            $branchPresetsArr = $this->getXpathCached($deviceElem, 'BranchPresets');
            // fallback to parent preset if needed
            if (empty($branchPresetsArr) && $parentPreset !== null && $parentPreset->getName() === 'GroupDevicePreset') {
                $branchPresetsArr = $this->getXpathCached($parentPreset, 'BranchPresets');
            }

            if (!empty($branchPresetsArr)) {
                $nestedType = $this->getBranchPresetType($type);
                if ($nestedType) {
                    $nestedBranches = $this->getXpathCached($branchPresetsArr[0], $nestedType);
                    foreach ($nestedBranches as $idx => $branch) {
                        $nestedChain = $this->parseSingleChainBranch($branch, $idx, $verbose, $depth + 1);
                        if ($nestedChain) {
                            $nestedChains[] = $nestedChain;
                        }
                    }
                }
            }

            $deviceInfo['chains'] = $nestedChains;
        }

        return $deviceInfo;
    }

    /**
     * Helper to determine branch preset type based on rack type.
     *
     * @param string $rackType
     * @return string|null
     */
    protected function getBranchPresetType(string $rackType): ?string
    {
        return self::BRANCH_TYPE_MAP[$rackType] ?? null;
    }

    /**
     * Helper to determine XPath expression for branch presets based on rack type.
     *
     * @param string $rackType
     * @return string|null
     */
    protected function getBranchPresetXPath(string $rackType): ?string
    {
        // Map rackType to branch XML node name if needed
        // e.g., 'InstrumentChain' => 'InstrumentChain', etc.
        // For now, assuming same as getBranchPresetType
        return $this->getBranchPresetType($rackType);
    }
}
