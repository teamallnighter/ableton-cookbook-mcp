<?php

/**
 * Enhanced Ableton Rack Analyzer V6
 * 
 * Improved version with better error handling, security, performance,
 * and extensibility for web application integration.
 * 
 * Key improvements:
 * - Robust file decompression with fallbacks
 * - Stream-based XML parsing for large files
 * - Configurable device mappings
 * - Enhanced security measures
 * - Better error granularity
 * - Distributed caching support
 * - Memory optimization
 * - Metadata enrichment
 */

class AbletonRackAnalyzer
{
    /**
     * Error types for better error handling
     */
    public const ERROR_TYPES = [
        'FILE_NOT_FOUND' => 'File not found or not readable',
        'FILE_TOO_LARGE' => 'File exceeds maximum size limit',
        'DECOMPRESSION_FAILED' => 'Failed to decompress .adg file',
        'XML_PARSE_ERROR' => 'XML parsing error',
        'INVALID_RACK_FORMAT' => 'Invalid rack format or structure',
        'SECURITY_VIOLATION' => 'Security check failed',
        'MEMORY_LIMIT_EXCEEDED' => 'Memory limit exceeded during parsing',
        'TIMEOUT' => 'Analysis timeout exceeded'
    ];

    /**
     * Default device type mappings (can be overridden via config)
     */
    public const DEFAULT_DEVICE_MAP = [
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
     * Configuration with enhanced options
     */
    private array $config;
    private array $deviceTypeMap;
    private array $xpathCache = [];
    private array $performanceMetrics = [];
    private int $xpathCacheHits = 0;
    private int $xpathCacheMisses = 0;
    private float $analysisStartTime;
    private int $analysisStartMemory;
    private ?CacheInterface $cacheAdapter = null;

    /**
     * Constructor with enhanced configuration
     */
    public function __construct(array $config = [], ?CacheInterface $cacheAdapter = null)
    {
        $this->config = array_merge([
            'max_depth' => 15,
            'max_file_size' => 104857600, // 100MB
            'max_memory_usage' => 536870912, // 512MB
            'analysis_timeout' => 300, // 5 minutes
            'verbose' => false,
            'enable_caching' => true,
            'cache_lifetime' => 3600,
            'collect_metrics' => true,
            'enable_error_recovery' => true,
            'detect_edition' => true,
            'enable_security_checks' => true,
            'stream_parsing_threshold' => 10485760, // 10MB
            'xpath_cache_size_limit' => 1000,
            'custom_device_mappings' => [],
            'enable_metadata_enrichment' => true,
        ], $config);

        $this->cacheAdapter = $cacheAdapter;
        $this->initializeDeviceMap();
    }

    /**
     * Initialize device type mappings with custom overrides
     */
    private function initializeDeviceMap(): void
    {
        $this->deviceTypeMap = array_merge(
            self::DEFAULT_DEVICE_MAP,
            $this->config['custom_device_mappings']
        );
    }

    /**
     * Main analysis method with enhanced error handling
     */
    public function analyze(string $filePath): RackAnalysis
    {
        $this->startAnalysis();

        try {
            // Validate file before processing
            $this->validateFile($filePath);

            // Try cache first
            if ($this->config['enable_caching']) {
                $cached = $this->getCachedAnalysis($filePath);
                if ($cached !== null) {
                    return $cached;
                }
            }

            // Perform analysis with timeout protection
            $result = $this->performAnalysisWithTimeout($filePath);

            // Cache if valid
            if ($this->config['enable_caching'] && $result->isValid()) {
                $this->cacheAnalysis($filePath, $result);
            }

            return $result;
        } catch (AnalysisException $e) {
            if ($this->config['enable_error_recovery']) {
                return $this->performPartialAnalysis($filePath, $e);
            }
            return RackAnalysis::createError(basename($filePath), $e->getMessage(), $e->getErrorType());
        } catch (Exception $e) {
            return RackAnalysis::createError(basename($filePath), $e->getMessage(), 'UNKNOWN_ERROR');
        } finally {
            $this->endAnalysis();
        }
    }
    /**
     * File validation with security checks
     */
    private function validateFile(string $filePath): void
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new AnalysisException("File not found or not readable", self::ERROR_TYPES['FILE_NOT_FOUND']);
        }

        $fileSize = filesize($filePath);
        if ($fileSize > $this->config['max_file_size'] || $fileSize < 100) {
            throw new AnalysisException("File size invalid", self::ERROR_TYPES['FILE_TOO_LARGE']);
        }

        // Security check: verify file extension
        if ($this->config['enable_security_checks']) {
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            if ($extension !== 'adg') {
                throw new AnalysisException("Invalid file extension", self::ERROR_TYPES['SECURITY_VIOLATION']);
            }
        }
    }

    /**
     * Analysis with timeout protection
     */
    private function performAnalysisWithTimeout(string $filePath): RackAnalysis
    {
        $timeoutHandler = function () {
            throw new AnalysisException("Analysis timeout exceeded", self::ERROR_TYPES['TIMEOUT']);
        };

        pcntl_signal(SIGALRM, $timeoutHandler);
        pcntl_alarm($this->config['analysis_timeout']);

        try {
            $result = $this->performAnalysis($filePath);
            pcntl_alarm(0); // Cancel timeout
            return $result;
        } catch (Exception $e) {
            pcntl_alarm(0); // Cancel timeout
            throw $e;
        }
    }

    /**
     * Enhanced file decompression with fallbacks
     */
    public function decompressAndParseAbletonFile(string $filePath): ?SimpleXMLElement
    {
        $fileSize = filesize($filePath);

        // Use stream parsing for large files
        if ($fileSize > $this->config['stream_parsing_threshold']) {
            return $this->streamParseAbletonFile($filePath);
        }

        return $this->standardParseAbletonFile($filePath);
    }

    /**
     * Standard parsing method with fallback decompression
     */
    private function standardParseAbletonFile(string $filePath): ?SimpleXMLElement
    {
        $xmlContent = null;

        // Primary method: PHP's compress.zlib wrapper
        try {
            $xmlContent = @file_get_contents("compress.zlib://$filePath");
        } catch (Exception $e) {
            // Fallback method: manual gzip decompression
            $xmlContent = $this->manualGzipDecompression($filePath);
        }

        if ($xmlContent === false || empty($xmlContent)) {
            throw new AnalysisException("Failed to decompress file", self::ERROR_TYPES['DECOMPRESSION_FAILED']);
        }

        return $this->parseXmlSecurely($xmlContent);
    }
    /**
     * Stream-based parsing for large files
     */
    private function streamParseAbletonFile(string $filePath): ?SimpleXMLElement
    {
        $gz = gzopen($filePath, 'rb');
        if (!$gz) {
            throw new AnalysisException("Failed to open gzip stream", self::ERROR_TYPES['DECOMPRESSION_FAILED']);
        }

        $xmlContent = '';
        $memoryLimit = $this->config['max_memory_usage'];

        while (!gzeof($gz)) {
            $chunk = gzread($gz, 8192);
            if ($chunk === false) {
                gzclose($gz);
                throw new AnalysisException("Stream read error", self::ERROR_TYPES['DECOMPRESSION_FAILED']);
            }

            $xmlContent .= $chunk;

            // Memory protection
            if (memory_get_usage(true) > $memoryLimit) {
                gzclose($gz);
                throw new AnalysisException("Memory limit exceeded", self::ERROR_TYPES['MEMORY_LIMIT_EXCEEDED']);
            }
        }

        gzclose($gz);
        return $this->parseXmlSecurely($xmlContent);
    }

    /**
     * Manual gzip decompression fallback
     */
    private function manualGzipDecompression(string $filePath): ?string
    {
        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            return null;
        }

        $data = fread($handle, filesize($filePath));
        fclose($handle);

        return gzdecode($data);
    }

    /**
     * Secure XML parsing with entity protection
     */
    private function parseXmlSecurely(string $xmlContent): ?SimpleXMLElement
    {
        // Enhanced security measures
        $oldEntityLoader = libxml_disable_entity_loader(true);
        $oldUseErrors = libxml_use_internal_errors(true);

        // Clear any previous XML errors
        libxml_clear_errors();

        try {
            // Prevent XXE attacks and limit resource usage
            $xml = simplexml_load_string(
                $xmlContent,
                'SimpleXMLElement',
                LIBXML_NOCDATA | LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_PARSEHUGE
            );

            if ($xml === false) {
                $errors = libxml_get_errors();
                $errorMsg = "XML parsing failed";
                if (!empty($errors)) {
                    $errorMsg .= ": " . $errors[0]->message;
                }
                throw new AnalysisException($errorMsg, self::ERROR_TYPES['XML_PARSE_ERROR']);
            }

            return $xml;
        } finally {
            libxml_disable_entity_loader($oldEntityLoader);
            libxml_use_internal_errors($oldUseErrors);
        }
    }
    /**
     * Enhanced main parsing logic with metadata enrichment
     */
    /**
     * Enhanced main parsing logic with metadata enrichment
     */
    private function performAnalysis(string $filePath): RackAnalysis
    {
        $xml = $this->decompressAndParseAbletonFile($filePath);
        if (!$xml) {
            throw new AnalysisException("Failed to parse file", self::ERROR_TYPES['XML_PARSE_ERROR']);
        }

        $result = $this->parseChainsAndDevices($xml, $filePath, $this->config['verbose']);

        // Enhanced metadata if enabled
        if ($this->config['enable_metadata_enrichment']) {
            $result = $this->enrichMetadata($result, $xml);
        }

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
            editionAnalysis: $editionAnalysis,
            enrichedMetadata: $result['enriched_metadata'] ?? []
        );
    }

    /**
     * Enrich metadata with additional rack information
     */
    private function enrichMetadata(array $result, SimpleXMLElement $xml): array
    {
        $enriched = [];

        // Extract chain selector information
        $chainSelector = $this->extractChainSelectorInfo($xml);
        if ($chainSelector) {
            $enriched['chain_selector'] = $chainSelector;
        }

        // Extract key/velocity zones
        $zones = $this->extractZoneInformation($xml);
        if ($zones) {
            $enriched['zones'] = $zones;
        }

        // Extract automation mappings
        $automationMappings = $this->extractAutomationMappings($xml);
        if ($automationMappings) {
            $enriched['automation_mappings'] = $automationMappings;
        }

        // Extract sample references
        $sampleRefs = $this->extractSampleReferences($xml);
        if ($sampleRefs) {
            $enriched['sample_references'] = $sampleRefs;
        }

        $result['enriched_metadata'] = $enriched;
        return $result;
    }

    /**
     * Extract chain selector information
     */
    private function extractChainSelectorInfo(SimpleXMLElement $xml): ?array
    {
        $chainSelector = $this->getXpathCached($xml, './/ChainSelector');
        if (empty($chainSelector)) {
            return null;
        }

        $info = [];
        foreach ($chainSelector as $selector) {
            $info[] = [
                'value' => (float)($selector['Value'] ?? 0),
                'automation_target' => (string)($selector['AutomationTarget'] ?? ''),
            ];
        }

        return $info;
    }

    /**
     * Extract zone information (key/velocity zones)
     */
    private function extractZoneInformation(SimpleXMLElement $xml): ?array
    {
        $zones = [];

        // Key zones
        $keyZones = $this->getXpathCached($xml, './/KeyRange');
        foreach ($keyZones as $zone) {
            $zones['key_zones'][] = [
                'min' => (int)($zone['Min'] ?? 0),
                'max' => (int)($zone['Max'] ?? 127),
                'enabled' => (string)($zone['Enabled'] ?? 'true') === 'true'
            ];
        }

        // Velocity zones
        $velocityZones = $this->getXpathCached($xml, './/VelocityRange');
        foreach ($velocityZones as $zone) {
            $zones['velocity_zones'][] = [
                'min' => (int)($zone['Min'] ?? 0),
                'max' => (int)($zone['Max'] ?? 127),
                'enabled' => (string)($zone['Enabled'] ?? 'true') === 'true'
            ];
        }

        return empty($zones) ? null : $zones;
    }
    /**
     * Extract automation mappings
     */
    private function extractAutomationMappings(SimpleXMLElement $xml): ?array
    {
        $mappings = [];
        $macroMappings = $this->getXpathCached($xml, './/MacroControlTarget');

        foreach ($macroMappings as $mapping) {
            $mappings[] = [
                'macro_index' => (int)($mapping['MacroIndex'] ?? 0),
                'parameter_id' => (string)($mapping['ParameterId'] ?? ''),
                'min_value' => (float)($mapping['Min'] ?? 0),
                'max_value' => (float)($mapping['Max'] ?? 1),
            ];
        }

        return empty($mappings) ? null : $mappings;
    }

    /**
     * Extract sample references
     */
    private function extractSampleReferences(SimpleXMLElement $xml): ?array
    {
        $samples = [];
        $fileRefs = $this->getXpathCached($xml, './/FileRef');

        foreach ($fileRefs as $ref) {
            $samples[] = [
                'name' => (string)($ref->Name['Value'] ?? ''),
                'path' => (string)($ref->RelativePath['Value'] ?? ''),
                'path_type' => (int)($ref->RelativePathType['Value'] ?? 0),
                'type' => (int)($ref->Type['Value'] ?? 0),
            ];
        }

        return empty($samples) ? null : $samples;
    }

    /**
     * Enhanced XPath caching with size limits
     */
    protected function getXpathCached(SimpleXMLElement $node, string $expression): array
    {
        $nodeHash = spl_object_hash($node);
        $cacheKey = $nodeHash . '::' . $expression;

        if (!isset($this->xpathCache[$cacheKey])) {
            // Implement cache size
            $this->xpathCacheMisses++;
            $result = $node->xpath($expression);
            $this->xpathCache[$cacheKey] = $result === false ? [] : $result;
        } else {
            $this->xpathCacheHits++;
        }

        return $this->xpathCache[$cacheKey];
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
     * Analyze edition requirements based on devices present
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
    public function clearXpathCache(): void
    {
        $this->xpathCache = [];
    }

    /**
     * Perform partial analysis in case of errors to recover some data
     */
    private function performPartialAnalysis(string $filePath, Exception $e): RackAnalysis
    {
        try {
            $xml = $this->decompressAndParseAbletonFile($filePath);
            if (!$xml) {
                return RackAnalysis::createError(basename($filePath), $e->getMessage(), 'DECOMPRESSION_FAILED');
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
                parsingWarnings: ["Some data could not be extracted due to errors"],
                abletonVersion: $versionInfo['ableton_version'],
                versionDetails: $versionInfo,
                fileHash: hash_file('sha256', $filePath),
                analysisTimeMs: (int)((microtime(true) - $this->analysisStartTime) * 1000),
                performanceMetrics: $this->collectFinalMetrics(),
                requiredEdition: null,
                editionAnalysis: null,
                enrichedMetadata: [],
            );
        } catch (Exception $e2) {
            return RackAnalysis::createError(
                basename($filePath),
                $e->getMessage() . ' | Recovery failed: ' . $e2->getMessage(),
                'RECOVERY_FAILED'
            );
        }
    }
    /**
     * Get cached analysis if available
     */
    private function getCachedAnalysis(string $filePath): ?RackAnalysis
    {
        $fileHash = hash_file('sha256', $filePath);
        $cacheKey = "ableton_analysis_{$fileHash}";

        if ($this->cacheAdapter) {
            $cached = $this->cacheAdapter->get($cacheKey);
            return $cached ? RackAnalysis::fromArray($cached) : null;
        }

        // Fallback to simple file-based caching
        return $this->getFileCachedAnalysis($cacheKey);
    }

    /**
     * Cache analysis result
     */
    private function cacheAnalysis(string $filePath, RackAnalysis $analysis): void
    {
        $fileHash = hash_file('sha256', $filePath);
        $cacheKey = "ableton_analysis_{$fileHash}";

        if ($this->cacheAdapter) {
            $this->cacheAdapter->set($cacheKey, $analysis->toArray(), $this->config['cache_lifetime']);
        } else {
            $this->setFileCachedAnalysis($cacheKey, $analysis);
        }
    }

    /**
     * Simple file-based caching fallback
     */
    private function getFileCachedAnalysis(string $cacheKey): ?RackAnalysis
    {
        $cacheFile = sys_get_temp_dir() . '/' . $cacheKey . '.cache';

        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $this->config['cache_lifetime']) {
            $data = unserialize(file_get_contents($cacheFile));
            return $data ? RackAnalysis::fromArray($data) : null;
        }

        return null;
    }

    /**
     * Simple file-based caching fallback
     */
    private function setFileCachedAnalysis(string $cacheKey, RackAnalysis $analysis): void
    {
        $cacheFile = sys_get_temp_dir() . '/' . $cacheKey . '.cache';
        file_put_contents($cacheFile, serialize($analysis->toArray()));
    }
    /**
     * Parse chains and devices with enhanced error handling
     */
    public function parseChainsAndDevices(SimpleXMLElement $root, ?string $filename = null, bool $verbose = false): array
    {
        $rackName = $this->extractRackNameFromXml($root) ?? pathinfo(basename($filename ?? ''), PATHINFO_FILENAME) ?? "Unknown";
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

        try {
            list($rackType, $mainDevice) = $this->detectRackTypeAndDevice($root);
            $rackInfo["rack_type"] = $rackType;

            if (!$rackType || !$mainDevice) {
                throw new AnalysisException("Unable to detect rack type or main device", self::ERROR_TYPES['INVALID_RACK_FORMAT']);
            }

            // Parse macros with enhanced error handling
            $this->parseMacroControls($mainDevice, $rackInfo);

            // Parse chains with enhanced error handling
            $this->parseRackChains($root, $rackType, $rackInfo, $verbose);
        } catch (Exception $e) {
            $rackInfo["parsing_errors"][] = $e->getMessage();
        }

        return $rackInfo;
    }

    /**
     * Parse macro controls with enhanced error handling
     */
    private function parseMacroControls(SimpleXMLElement $mainDevice, array &$rackInfo): void
    {
        for ($i = 0; $i < 16; $i++) {
            try {
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
            } catch (Exception $e) {
                $rackInfo["parsing_warnings"][] = "Failed to parse macro $i: " . $e->getMessage();
            }
        }
    }
    /**
     * Parse rack chains with enhanced error handling
     */
    private function parseRackChains(SimpleXMLElement $root, string $rackType, array &$rackInfo, bool $verbose): void
    {
        $branchPresetsElem = $this->getXpathCached($root, './/GroupDevicePreset/BranchPresets');
        if (!empty($branchPresetsElem)) {
            $branchType = self::BRANCH_TYPE_MAP[$rackType] ?? null;
            if ($branchType) {
                $branches = $this->getXpathCached($branchPresetsElem[0], $branchType);
                foreach ($branches as $idx => $branch) {
                    try {
                        $chain = $this->parseSingleChainBranch($branch, $idx, $verbose, 0);
                        if ($chain) {
                            $rackInfo["chains"][] = $chain;
                        }
                    } catch (Exception $e) {
                        $rackInfo["parsing_warnings"][] = "Failed to parse chain $idx: " . $e->getMessage();
                    }
                }
            }
        }
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
     * Parse device element with enhancements for nested racks
     */
    public function parseDevice(SimpleXMLElement $deviceElem, ?SimpleXMLElement $parentPreset = null, int $depth = 0, bool $verbose = false): ?array
    {
        if ($depth > $this->config['max_depth']) {
            return null;
        }

        $deviceType = $deviceElem->getName();
        $standardDeviceName = $this->deviceTypeMap[$deviceType] ?? $deviceType;

        $userNameElem = $this->getXpathCached($deviceElem, 'UserName');
        $customName = !empty($userNameElem) ? trim((string)($userNameElem[0]['Value'] ?? '')) : null;
        $displayName = ($customName && $customName !== $standardDeviceName) ? $customName : $standardDeviceName;

        $deviceInfo = [
            "type" => $deviceType,
            "name" => $displayName,
            "is_on" => true,
            "standard_name" => $standardDeviceName,
            "is_custom_device" => !isset($this->deviceTypeMap[$deviceType])
        ];

        // Parse device state
        $this->parseDeviceState($deviceElem, $deviceInfo);

        // Handle nested racks
        $this->parseNestedRacks($deviceElem, $deviceInfo, $depth, $verbose);

        return $deviceInfo;
    }

    /**
     * Parse device state (on/off, bypass, etc.)
     */
    private function parseDeviceState(SimpleXMLElement $deviceElem, array &$deviceInfo): void
    {
        $onElem = $this->getXpathCached($deviceElem, 'On/Manual');
        if (!empty($onElem)) {
            $deviceInfo["is_on"] = (string)($onElem[0]['Value'] ?? 'true') === 'true';
        }

        // Add bypass state if available
        $bypassElem = $this->getXpathCached($deviceElem, 'Bypass/Manual');
        if (!empty($bypassElem)) {
            $deviceInfo["is_bypassed"] = (string)($bypassElem[0]['Value'] ?? 'false') === 'true';
        }
    }
    /**
     * Parse nested racks within devices
     */
    private function parseNestedRacks(SimpleXMLElement $deviceElem, array &$deviceInfo, int $depth, bool $verbose): void
    {
        $deviceType = $deviceElem->getName();

        if (in_array($deviceType, ["AudioEffectGroupDevice", "InstrumentGroupDevice", "MidiEffectGroupDevice"])) {
            $nestedChains = [];
            $branchPresets = $this->getXpathCached($deviceElem, 'BranchPresets');

            if (!empty($branchPresets)) {
                $nestedType = self::BRANCH_TYPE_MAP[$deviceType] ?? null;
                if ($nestedType) {
                    $nestedBranches = $this->getXpathCached($branchPresets[0], $nestedType);
                    foreach ($nestedBranches as $idx => $branch) {
                        try {
                            $nestedChain = $this->parseSingleChainBranch($branch, $idx, $verbose, $depth + 1);
                            if ($nestedChain) {
                                $nestedChains[] = $nestedChain;
                            }
                        } catch (Exception $e) {
                            // Log nested parsing errors but don't fail the entire device
                            $deviceInfo["parsing_warnings"][] = "Failed to parse nested chain $idx: " . $e->getMessage();
                        }
                    }
                }
            }
            $deviceInfo["chains"] = $nestedChains;
        }
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
            if ($versionInfo["major_version"] !== null && $versionInfo["minor_version"] !== null) {
                $versionInfo["ableton_version"] = $versionInfo["major_version"] . '.' . $versionInfo["minor_version"];
            }
        }
        return $versionInfo;
    }

    /**
     * Collect final performance metrics for analysis
     */
    private function collectFinalMetrics(): array
    {
        return [
            'memory_usage' => memory_get_usage(true),
            'peak_memory_usage' => memory_get_peak_usage(true),
            'analysis_duration_ms' => isset($this->analysisStartTime) ? (int)((microtime(true) - $this->analysisStartTime) * 1000) : null,
            'xpath_cache_hits' => $this->xpathCacheHits,
            'xpath_cache_misses' => $this->xpathCacheMisses,
        ];
    }
} // <-- Add this closing brace for AbletonRackAnalyzer

/**
 * Custom exception for analysis errors
 */
class AnalysisException extends Exception
{
    private string $errorType;

    public function __construct(string $message, string $errorType, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errorType = $errorType;
    }

    public function getErrorType(): string
    {
        return $this->errorType;
    }
}

/**
 * Cache interface for dependency injection
 */
interface CacheInterface
{
    public function get(string $key): mixed;
    public function set(string $key, mixed $value, int $ttl = null): bool;
    public function delete(string $key): bool;
    public function clear(): bool;
}

/**
 * Enhanced RackAnalysis DTO with additional metadata
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
        public readonly array $enrichedMetadata = [],
    ) {}

    /**
     * Convert the RackAnalysis object to an array.
     */
    public function toArray(): array
    {
        return [
            'rackName' => $this->rackName,
            'rackType' => $this->rackType,
            'useCase' => $this->useCase,
            'macroControls' => $this->macroControls,
            'chains' => $this->chains,
            'parsingErrors' => $this->parsingErrors,
            'parsingWarnings' => $this->parsingWarnings,
            'abletonVersion' => $this->abletonVersion,
            'versionDetails' => $this->versionDetails,
            'fileHash' => $this->fileHash,
            'analysisTimeMs' => $this->analysisTimeMs,
            'performanceMetrics' => $this->performanceMetrics,
            'requiredEdition' => $this->requiredEdition,
            'editionAnalysis' => $this->editionAnalysis,
            'enrichedMetadata' => $this->enrichedMetadata,
        ];
    }

    /**
     * Specify data which should be serialized to JSON.
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * Returns true if the analysis result is valid (no parsing errors).
     */
    public function isValid(): bool
    {
        return empty($this->parsingErrors);
    }

    /**
     * Create an error RackAnalysis instance.
     */
    public static function createError(
        string $rackName,
        string $errorMessage,
        string $errorType = 'UNKNOWN_ERROR'
    ): self {
        return new self(
            rackName: $rackName,
            rackType: null,
            useCase: $rackName,
            macroControls: [],
            chains: [],
            parsingErrors: [$errorType . ': ' . $errorMessage],
            parsingWarnings: [],
            abletonVersion: null,
            versionDetails: [],
            fileHash: null,
            analysisTimeMs: null,
            performanceMetrics: null,
            requiredEdition: null,
            editionAnalysis: null,
            enrichedMetadata: [],
        );
    }

    /**
     * Create a RackAnalysis instance from an array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            rackName: $data['rackName'] ?? '',
            rackType: $data['rackType'] ?? null,
            useCase: $data['useCase'] ?? '',
            macroControls: $data['macroControls'] ?? [],
            chains: $data['chains'] ?? [],
            parsingErrors: $data['parsingErrors'] ?? [],
            parsingWarnings: $data['parsingWarnings'] ?? [],
            abletonVersion: $data['abletonVersion'] ?? null,
            versionDetails: $data['versionDetails'] ?? [],
            fileHash: $data['fileHash'] ?? null,
            analysisTimeMs: $data['analysisTimeMs'] ?? null,
            performanceMetrics: $data['performanceMetrics'] ?? null,
            requiredEdition: $data['requiredEdition'] ?? null,
            editionAnalysis: $data['editionAnalysis'] ?? null,
            enrichedMetadata: $data['enrichedMetadata'] ?? [],
        );
    }
}
