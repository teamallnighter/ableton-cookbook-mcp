<?php

namespace App\Services\AbletonRackAnalyzer;

use Exception;
use SimpleXMLElement;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use JsonSerializable;

/**
 * Ableton Rack Analyzer - V5
 * 
 * Chris Connelly | Bass Daddy Devices
 * 
 * Includes:
 * - Core analyzer with all device mappings
 * - DTOs for type safety
 * - Caching for performance
 * - Metrics tracking
 * - Error recovery
 * - Edition detection
 * 
 * Usage:
 *   $analyzer = new AbletonRackAnalyzer();
 *   $result = $analyzer->analyze($filePath);
 */
class AbletonRackAnalyzer
{
    /**
     * Complete device type mappings (150+ devices)
     */
    public const DEVICE_TYPE_MAP = [
        // Audio Effects
        "AlignDelay" => "Align Delay",
        "Amp" => "Amp",
        "AudioEffectGroupDevice" => "Audio Effect Rack",
        "AutoFilter" => "Auto Filter",
        "AutoPan" => "Auto Pan",
        "AutoShift" => "Auto Shift",
        "BeatRepeat" => "Beat Repeat",
        "Cabinet" => "Cabinet",
        "ChannelEq" => "Channel EQ",
        "Chorus" => "Chorus-Ensemble",
        "ChromaticChorus" => "Chorus-Ensemble",
        "ChorusEnsemble" => "Chorus-Ensemble",
        "Compressor2" => "Compressor",
        "Compressor" => "Compressor",
        "Corpus" => "Corpus",
        "Delay" => "Delay",
        "DrumBuss" => "Drum Buss",
        "DynamicTube" => "Dynamic Tube",
        "Tube" => "Dynamic Tube",
        "Echo" => "Echo",
        "EnvelopeFollower" => "Envelope Follower",
        "FilterEQ3" => "EQ Three",
        "Eq3" => "EQ Three",
        "EQThree" => "EQ Three",
        "Eq8" => "EQ Eight",
        "EQEight" => "EQ Eight",
        "Erosion" => "Erosion",
        "ExternalAudioEffect" => "External Audio Effect",
        "FilterDelay" => "Filter Delay",
        "Gate" => "Gate",
        "GlueCompressor" => "Glue Compressor",
        "GrainDelay" => "Grain Delay",
        "HybridReverb" => "Hybrid Reverb",
        "LFO" => "LFO",
        "Limiter" => "Limiter",
        "Looper" => "Looper",
        "MultibandDynamics" => "Multiband Dynamics",
        "MultibandCompressor" => "Multiband Dynamics",
        "Overdrive" => "Overdrive",
        "Pedal" => "Pedal",
        "Phaser" => "Phaser",
        "PhaserFlanger" => "Phaser-Flanger",
        "Flanger" => "Flanger",
        "PhaserNew" => "Phaser-Flanger",
        "Redux" => "Redux",
        "Resonators" => "Resonators",
        "Reverb" => "Reverb",
        "Roar" => "Roar",
        "Saturator" => "Saturator",
        "Shaper" => "Shaper",
        "Shifter" => "Shifter",
        "FrequencyShifter" => "Frequency Shifter",
        "Frequency" => "Frequency Shifter",
        "SpectralResonator" => "Spectral Resonator",
        "SpectralTime" => "Spectral Time",
        "Spectrum" => "Spectrum",
        "Tuner" => "Tuner",
        "Utility" => "Utility",
        "VinylDistortion" => "Vinyl Distortion",
        "Vocoder" => "Vocoder",

        // Instruments
        "AnalogDevice" => "Analog",
        "Analog" => "Analog",
        "Collision" => "Collision",
        "DrumRack" => "Drum Rack",
        "InstrumentRack" => "Instrument Rack",
        "Electric" => "Electric",
        "ExternalInstrument" => "External Instrument",
        "GranulatorIII" => "Granulator III",
        "Granulator" => "Granulator III",
        "InstrumentImpulse" => "Impulse",
        "Impulse" => "Impulse",
        "Meld" => "Meld",
        "Operator" => "Operator",
        "Poli" => "Poli",
        "Sampler" => "Sampler",
        "Simpler" => "Simpler",
        "Tension" => "Tension",
        "Wavetable" => "Wavetable",
        "Bass" => "Bass",
        "Drift" => "Drift",
        "DrumSampler" => "Drum Sampler",
        "InstrumentGroupDevice" => "Instrument Rack",
        "MidiEffectGroupDevice" => "MIDI Effect Rack",
        "DrumGroupDevice" => "Drum Rack",

        // MIDI Effects
        "Arpeggiator" => "Arpeggiator",
        "Arpeggiate" => "Arpeggiator",
        "CCControl" => "CC Control",
        "Chord" => "Chord",
        "NoteEcho" => "Note Echo",
        "NoteLength" => "Note Length",
        "Pitch" => "Pitch",
        "Random" => "Random",
        "Scale" => "Scale",
        "Velocity" => "Velocity",
    ];

    public const BRANCH_TYPE_MAP = [
        "AudioEffectGroupDevice" => "AudioEffectBranchPreset",
        "InstrumentGroupDevice" => "InstrumentBranchPreset",
        "MidiEffectGroupDevice" => "MidiEffectBranchPreset"
    ];

    // Edition detection maps
    private const SUITE_ONLY_DEVICES = [
        'operator',
        'analog',
        'collision',
        'tension',
        'electric',
        'sampler',
        'wavetable',
        'poli',
        'bass',
        'drift',
        'meld',
        'amp',
        'cabinet',
        'corpus',
        'drumbus',
        'echo',
        'filterdelay',
        'gluecompressor',
        'hybridreverb',
        'multibandcompressor',
        'overdrive',
        'pedal',
        'resonators',
        'saturator',
        'vocoder',
    ];

    private const STANDARD_DEVICES = [
        'eq8',
        'eqeight',
        'compressor2',
        'compressor',
        'autofilter',
        'reverb',
        'delay',
        'chorus',
        'phaser',
        'flanger',
        'autopan',
        'gate',
        'limiter',
        'beatrepeat',
        'looper',
        'grain',
        'simpler',
        'impulse',
        'drumrack',
    ];

    /**
     * Configuration
     */
    private array $config;
    private array $xpathCache = [];
    private array $performanceMetrics = [];
    private int $xpathCacheHits = 0;
    private int $xpathCacheMisses = 0;
    private float $analysisStartTime;
    private int $analysisStartMemory;

    /**
     * Constructor
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'max_depth' => 10,
            'max_file_size' => 104857600,
            'verbose' => false,
            'enable_caching' => true,
            'cache_lifetime' => 3600,
            'collect_metrics' => true,
            'enable_error_recovery' => true,
            'detect_edition' => true,
        ], $config);
    }

    /**
     * Main analysis method
     */
    public function analyze(string $filePath): RackAnalysis
    {
        $this->startAnalysis();

        try {
            // Try cache first
            if ($this->config['enable_caching']) {
                $cached = $this->getCachedAnalysis($filePath);
                if ($cached !== null) {
                    return $cached;
                }
            }

            // Perform analysis
            $result = $this->performAnalysis($filePath);

            // Cache if valid
            if ($this->config['enable_caching'] && $result->isValid()) {
                $this->cacheAnalysis($filePath, $result);
            }

            return $result;
        } catch (Exception $e) {
            if ($this->config['enable_error_recovery']) {
                return $this->performPartialAnalysis($filePath, $e);
            }
            return RackAnalysis::createError(basename($filePath), $e->getMessage());
        } finally {
            $this->endAnalysis();
        }
    }

    /**
     * Perform the actual analysis
     */
    private function performAnalysis(string $filePath): RackAnalysis
    {
        $xml = $this->decompressAndParseAbletonFile($filePath);
        if (!$xml) {
            throw new Exception("Failed to parse file");
        }

        $result = $this->parseChainsAndDevices($xml, $filePath, $this->config['verbose']);

        // Detect edition if enabled
        $requiredEdition = null;
        $editionAnalysis = null;
        if ($this->config['detect_edition']) {
            $editionAnalysis = $this->analyzeEditionRequirements($result['chains']);
            $requiredEdition = $editionAnalysis['required_edition'];
        }

        return new RackAnalysis(
            rackName: $result['rack_name'],
            rackType: $result['rack_type'],
            useCase: $result['use_case'],
            macroControls: $result['macro_controls'],
            chains: $result['chains'],
            parsingErrors: $result['parsing_errors'],
            parsingWarnings: $result['parsing_warnings'],
            abletonVersion: $result['ableton_version'],
            versionDetails: $result['version_details'],
            fileHash: hash_file('sha256', $filePath),
            analysisTimeMs: (int)((microtime(true) - $this->analysisStartTime) * 1000),
            performanceMetrics: $this->collectFinalMetrics(),
            requiredEdition: $requiredEdition,
            editionAnalysis: $editionAnalysis
        );
    }

    /**
     * Decompress and parse Ableton file
     */
    public function decompressAndParseAbletonFile(string $filePath): ?SimpleXMLElement
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return null;
        }

        $fileSize = filesize($filePath);
        if ($fileSize > $this->config['max_file_size'] || $fileSize < 100) {
            return null;
        }

        $oldSetting = libxml_disable_entity_loader(true);
        $xmlContent = @file_get_contents("compress.zlib://$filePath");
        libxml_disable_entity_loader($oldSetting);

        if ($xmlContent === false || empty($xmlContent)) {
            return null;
        }

        $xml = @simplexml_load_string($xmlContent);
        return $xml === false ? null : $xml;
    }

    /**
     * Parse chains and devices - main parsing logic
     */
    public function parseChainsAndDevices(SimpleXMLElement $root, ?string $filename = null, bool $verbose = false): array
    {
        $rackName = $this->extractRackNameFromXml($root) ?? pathinfo(basename($filename), PATHINFO_FILENAME) ?? "Unknown";
        $versionInfo = $this->extractAbletonVersionInfo($root);

        $rackInfo = [
            "rack_name" => $rackName,
            "use_case" => $rackName,
            "macro_controls" => [],
            "chains" => [],
            "parsing_errors" => [],
            "parsing_warnings" => [],
            "ableton_version" => $versionInfo["ableton_version"],
            "version_details" => $versionInfo
        ];

        list($rackType, $mainDevice) = $this->detectRackTypeAndDevice($root);
        $rackInfo["rack_type"] = $rackType;

        if (!$rackType || !$mainDevice) {
            $rackInfo["parsing_errors"][] = "Unable to detect rack type or main device";
            return $rackInfo;
        }

        // Parse macros
        for ($i = 0; $i < 16; $i++) {
            $macroNameElem = $this->getXpathCached($mainDevice, "MacroDisplayNames.$i");
            if (!empty($macroNameElem)) {
                $macroName = (string)($macroNameElem[0]['Value'] ?? "Macro " . ($i + 1));
                if ($macroName !== "Macro " . ($i + 1)) {
                    $macroControlElem = $this->getXpathCached($mainDevice, "MacroControls.$i/Manual");
                    $macroValue = !empty($macroControlElem) ? (float)($macroControlElem[0]['Value'] ?? 0) : 0.0;

                    $rackInfo["macro_controls"][] = [
                        "name" => $macroName,
                        "value" => $macroValue,
                        "index" => $i
                    ];
                }
            }
        }

        // Parse chains
        $branchPresetsElem = $this->getXpathCached($root, './/GroupDevicePreset/BranchPresets');
        if (!empty($branchPresetsElem)) {
            $branchType = self::BRANCH_TYPE_MAP[$rackType] ?? null;
            if ($branchType) {
                $branches = $this->getXpathCached($branchPresetsElem[0], $branchType);
                foreach ($branches as $idx => $branch) {
                    $chain = $this->parseSingleChainBranch($branch, $idx, $verbose, 0);
                    if ($chain) {
                        $rackInfo["chains"][] = $chain;
                    }
                }
            }
        }

        return $rackInfo;
    }

    /**
     * Parse single chain branch
     */
    public function parseSingleChainBranch(SimpleXMLElement $branchXml, int $chainIndex = 0, bool $verbose = false, int $depth = 0): array
    {
        $chain = [
            "name" => "Chain " . ($chainIndex + 1),
            "is_soloed" => false,
            "devices" => [],
            "chain_index" => $chainIndex,
            "annotations" => [
                "description" => null,
                "purpose" => null,
                "key_range" => null,
                "velocity_range" => null,
                "tags" => []
            ]
        ];

        $nameElem = $this->getXpathCached($branchXml, 'Name');
        if (!empty($nameElem)) {
            $chain["name"] = (string)($nameElem[0]['Value'] ?? $chain["name"]);
        }

        $devicePresets = $this->getXpathCached($branchXml, 'DevicePresets');
        if (!empty($devicePresets)) {
            foreach ($devicePresets[0] as $devicePreset) {
                $deviceElem = $this->getXpathCached($devicePreset, 'Device');
                if (!empty($deviceElem)) {
                    foreach ($deviceElem[0] as $child) {
                        $deviceInfo = $this->parseDevice($child, $devicePreset, $depth + 1, $verbose);
                        if ($deviceInfo) {
                            $chain["devices"][] = $deviceInfo;
                        }
                    }
                }
            }
        }

        return $chain;
    }

    /**
     * Parse device element
     */
    public function parseDevice(SimpleXMLElement $deviceElem, ?SimpleXMLElement $parentPreset = null, int $depth = 0, bool $verbose = false): ?array
    {
        if ($depth > $this->config['max_depth']) {
            return null;
        }

        $deviceType = $deviceElem->getName();
        $standardDeviceName = self::DEVICE_TYPE_MAP[$deviceType] ?? $deviceType;

        $userNameElem = $this->getXpathCached($deviceElem, 'UserName');
        $customName = !empty($userNameElem) ? trim((string)($userNameElem[0]['Value'] ?? '')) : null;

        $displayName = ($customName && $customName !== $standardDeviceName) ? $customName : $standardDeviceName;

        $deviceInfo = [
            "type" => $deviceType,
            "name" => $displayName,
            "is_on" => true,
            "standard_name" => $standardDeviceName
        ];

        $onElem = $this->getXpathCached($deviceElem, 'On/Manual');
        if (!empty($onElem)) {
            $deviceInfo["is_on"] = (string)($onElem[0]['Value'] ?? 'true') === 'true';
        }

        // Handle nested racks
        if (in_array($deviceType, ["AudioEffectGroupDevice", "InstrumentGroupDevice", "MidiEffectGroupDevice"])) {
            $nestedChains = [];
            $branchPresets = $this->getXpathCached($deviceElem, 'BranchPresets');

            if (!empty($branchPresets)) {
                $nestedType = self::BRANCH_TYPE_MAP[$deviceType] ?? null;
                if ($nestedType) {
                    $nestedBranches = $this->getXpathCached($branchPresets[0], $nestedType);
                    foreach ($nestedBranches as $idx => $branch) {
                        $nestedChain = $this->parseSingleChainBranch($branch, $idx, $verbose, $depth + 1);
                        if ($nestedChain) {
                            $nestedChains[] = $nestedChain;
                        }
                    }
                }
            }
            $deviceInfo["chains"] = $nestedChains;
        }

        return $deviceInfo;
    }

    /**
     * Detect rack type and device
     */
    public function detectRackTypeAndDevice(SimpleXMLElement $root): array
    {
        $groupPreset = $this->getXpathCached($root, './/GroupDevicePreset');
        if (!empty($groupPreset)) {
            $device = $this->getXpathCached($groupPreset[0], 'Device');
            if (!empty($device)) {
                foreach ($device[0] as $child) {
                    $childName = $child->getName();
                    if (array_key_exists($childName, self::BRANCH_TYPE_MAP)) {
                        return [$childName, $child];
                    }
                }
            }
        }

        foreach (array_keys(self::BRANCH_TYPE_MAP) as $rackType) {
            $device = $this->getXpathCached($root, ".//$rackType");
            if (!empty($device)) {
                return [$rackType, $device[0]];
            }
        }

        return [null, null];
    }

    /**
     * Extract rack name from XML
     */
    public function extractRackNameFromXml(SimpleXMLElement $root): ?string
    {
        $nameElem = $this->getXpathCached($root, './/GroupDevicePreset/Name');
        if (!empty($nameElem) && isset($nameElem[0]['Value'])) {
            $name = trim((string)$nameElem[0]['Value']);
            if ($name) return $name;
        }
        return null;
    }

    /**
     * Extract version info
     */
    public function extractAbletonVersionInfo(SimpleXMLElement $root): array
    {
        $versionInfo = [
            "ableton_version" => null,
            "major_version" => null,
            "minor_version" => null,
            "build_number" => null,
            "revision" => null
        ];

        if ($root->getName() === "Ableton") {
            $attrs = $root->attributes();
            if (isset($attrs['MajorVersion'])) {
                $versionInfo["major_version"] = (int)$attrs['MajorVersion'];
            }
            if (isset($attrs['MinorVersion'])) {
                $versionInfo["minor_version"] = (int)$attrs['MinorVersion'];
            }
        }

        if ($versionInfo["major_version"] !== null && $versionInfo["minor_version"] !== null) {
            $versionInfo["ableton_version"] = $versionInfo["major_version"] . '.' . $versionInfo["minor_version"];
        }

        return $versionInfo;
    }

    /**
     * XPath caching
     */
    protected function getXpathCached(SimpleXMLElement $node, string $expression): array
    {
        $nodeHash = spl_object_hash($node);

        if (!isset($this->xpathCache[$nodeHash][$expression])) {
            $this->xpathCacheMisses++;
            $result = $node->xpath($expression);
            $this->xpathCache[$nodeHash][$expression] = $result === false ? [] : $result;
        } else {
            $this->xpathCacheHits++;
        }

        return $this->xpathCache[$nodeHash][$expression];
    }

    /**
     * Clear XPath cache
     */
    public function clearXpathCache(): void
    {
        $this->xpathCache = [];
    }

    /**
     * Analyze edition requirements
     */
    private function analyzeEditionRequirements(array $chains): array
    {
        $result = [
            'required_edition' => 'intro',
            'suite_devices' => [],
            'standard_devices' => [],
            'total_devices' => 0,
        ];

        $devices = $this->extractAllDevicesFromChains($chains);
        $result['total_devices'] = count($devices);

        foreach ($devices as $device) {
            $deviceType = strtolower(preg_replace('/[0-9]+/', '', $device['type'] ?? ''));

            if (in_array($deviceType, self::SUITE_ONLY_DEVICES)) {
                $result['suite_devices'][] = $device['name'] ?? $deviceType;
                $result['required_edition'] = 'suite';
            } elseif (in_array($deviceType, self::STANDARD_DEVICES) && $result['required_edition'] === 'intro') {
                $result['standard_devices'][] = $device['name'] ?? $deviceType;
                $result['required_edition'] = 'standard';
            }
        }

        return $result;
    }

    /**
     * Extract all devices from chains
     */
    private function extractAllDevicesFromChains(array $chains): array
    {
        $devices = [];
        foreach ($chains as $chain) {
            foreach ($chain['devices'] ?? [] as $device) {
                $devices[] = $device;
                if (isset($device['chains'])) {
                    $devices = array_merge($devices, $this->extractAllDevicesFromChains($device['chains']));
                }
            }
        }
        return $devices;
    }

    /**
     * Performance tracking
     */
    private function startAnalysis(): void
    {
        $this->analysisStartTime = microtime(true);
        $this->analysisStartMemory = memory_get_usage(true);
        $this->xpathCacheHits = 0;
        $this->xpathCacheMisses = 0;
    }

    private function endAnalysis(): void
    {
        $this->clearXpathCache();
    }

    private function collectFinalMetrics(): ?array
    {
        if (!$this->config['collect_metrics']) {
            return null;
        }

        return [
            'total_duration_ms' => round((microtime(true) - $this->analysisStartTime) * 1000, 2),
            'memory_used_mb' => round((memory_get_usage(true) - $this->analysisStartMemory) / 1048576, 2),
            'xpath_cache_hits' => $this->xpathCacheHits,
            'xpath_cache_misses' => $this->xpathCacheMisses,
        ];
    }

    /**
     * Caching methods
     */
    private function getCachedAnalysis(string $filePath): ?RackAnalysis
    {
        $fileHash = hash_file('sha256', $filePath);
        $cached = Cache::get("ableton_analysis_{$fileHash}");

        return $cached ? RackAnalysis::fromArray($cached) : null;
    }

    private function cacheAnalysis(string $filePath, RackAnalysis $analysis): void
    {
        $fileHash = hash_file('sha256', $filePath);
        Cache::put("ableton_analysis_{$fileHash}", $analysis->toArray(), $this->config['cache_lifetime']);
    }

    /**
     * Error recovery
     */
    private function performPartialAnalysis(string $filePath, Exception $e): RackAnalysis
    {
        try {
            $xml = $this->decompressAndParseAbletonFile($filePath);
            if (!$xml) {
                return RackAnalysis::createError(basename($filePath), $e->getMessage());
            }

            $rackName = $this->extractRackNameFromXml($xml) ?? basename($filePath);
            $versionInfo = $this->extractAbletonVersionInfo($xml);

            return new RackAnalysis(
                rackName: $rackName,
                rackType: 'Unknown',
                useCase: $rackName,
                macroControls: [],
                chains: [],
                parsingErrors: ["Partial parsing: " . $e->getMessage()],
                parsingWarnings: ["Some data could not be extracted"],
                abletonVersion: $versionInfo['ableton_version'],
                versionDetails: $versionInfo,
                fileHash: hash_file('sha256', $filePath),
                analysisTimeMs: (int)((microtime(true) - $this->analysisStartTime) * 1000),
                performanceMetrics: null,
                requiredEdition: null,
                editionAnalysis: null
            );
        } catch (Exception $e2) {
            return RackAnalysis::createError(basename($filePath), $e->getMessage() . ' | ' . $e2->getMessage());
        }
    }
}

/**
 * ====================================
 * DTOs (Data Transfer Objects) Below
 * ====================================
 */

/**
 * Main Rack Analysis Result DTO
 */
class RackAnalysis implements JsonSerializable
{
    public function __construct(
        public readonly string $rackName,
        public readonly ?string $rackType,
        public readonly string $useCase,
        public readonly array $macroControls,
        public readonly array $chains,
        public readonly array $parsingErrors,
        public readonly array $parsingWarnings,
        public readonly ?string $abletonVersion,
        public readonly array $versionDetails,
        public readonly ?string $fileHash = null,
        public readonly ?int $analysisTimeMs = null,
        public readonly ?array $performanceMetrics = null,
        public readonly ?string $requiredEdition = null,
        public readonly ?array $editionAnalysis = null,
    ) {}

    public function hasErrors(): bool
    {
        return !empty($this->parsingErrors);
    }

    public function hasWarnings(): bool
    {
        return !empty($this->parsingWarnings);
    }

    public function isValid(): bool
    {
        return !$this->hasErrors() && $this->rackType !== null;
    }

    public function getDeviceCount(): int
    {
        $count = 0;
        foreach ($this->chains as $chain) {
            $count += $this->countDevicesInChain($chain);
        }
        return $count;
    }

    public function getUniqueDeviceTypes(): array
    {
        $types = [];
        foreach ($this->chains as $chain) {
            $this->collectDeviceTypes($chain, $types);
        }
        return array_unique($types);
    }

    public function getChainCount(): int
    {
        return count($this->chains);
    }

    public function getMacroCount(): int
    {
        return count($this->macroControls);
    }

    public function getRequiredEdition(): string
    {
        return $this->requiredEdition ?? 'intro';
    }

    public function requiresSuite(): bool
    {
        return $this->requiredEdition === 'suite';
    }

    public function requiresStandardOrHigher(): bool
    {
        return in_array($this->requiredEdition, ['standard', 'suite']);
    }

    public function toArray(): array
    {
        return [
            'rack_name' => $this->rackName,
            'rack_type' => $this->rackType,
            'use_case' => $this->useCase,
            'macro_controls' => $this->macroControls,
            'chains' => $this->chains,
            'parsing_errors' => $this->parsingErrors,
            'parsing_warnings' => $this->parsingWarnings,
            'ableton_version' => $this->abletonVersion,
            'version_details' => $this->versionDetails,
            'file_hash' => $this->fileHash,
            'analysis_time_ms' => $this->analysisTimeMs,
            'performance_metrics' => $this->performanceMetrics,
            'required_edition' => $this->requiredEdition,
            'edition_analysis' => $this->editionAnalysis,
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    public static function fromArray(array $data): self
    {
        return new self(
            rackName: $data['rack_name'] ?? 'Unknown',
            rackType: $data['rack_type'] ?? null,
            useCase: $data['use_case'] ?? 'Unknown',
            macroControls: $data['macro_controls'] ?? [],
            chains: $data['chains'] ?? [],
            parsingErrors: $data['parsing_errors'] ?? [],
            parsingWarnings: $data['parsing_warnings'] ?? [],
            abletonVersion: $data['ableton_version'] ?? null,
            versionDetails: $data['version_details'] ?? [],
            fileHash: $data['file_hash'] ?? null,
            analysisTimeMs: $data['analysis_time_ms'] ?? null,
            performanceMetrics: $data['performance_metrics'] ?? null,
            requiredEdition: $data['required_edition'] ?? null,
            editionAnalysis: $data['edition_analysis'] ?? null,
        );
    }

    public static function createError(string $filename, string $error): self
    {
        return new self(
            rackName: pathinfo($filename, PATHINFO_FILENAME),
            rackType: null,
            useCase: 'Error',
            macroControls: [],
            chains: [],
            parsingErrors: [$error],
            parsingWarnings: [],
            abletonVersion: null,
            versionDetails: [],
        );
    }

    private function countDevicesInChain(array $chain): int
    {
        $count = count($chain['devices'] ?? []);
        foreach ($chain['devices'] ?? [] as $device) {
            if (isset($device['chains'])) {
                foreach ($device['chains'] as $nestedChain) {
                    $count += $this->countDevicesInChain($nestedChain);
                }
            }
        }
        return $count;
    }

    private function collectDeviceTypes(array $chain, array &$types): void
    {
        foreach ($chain['devices'] ?? [] as $device) {
            $types[] = $device['type'] ?? 'Unknown';
            if (isset($device['chains'])) {
                foreach ($device['chains'] as $nestedChain) {
                    $this->collectDeviceTypes($nestedChain, $types);
                }
            }
        }
    }
}
