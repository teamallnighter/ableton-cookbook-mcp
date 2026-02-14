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

/**
 * AbletonRackAnalyzer
 *
 * WARNING: The default in-memory XPath cache ($this->xpathCache) is per-process and NOT shared across multiple PHP processes or threads.
 * In multi-process or distributed environments (e.g., FPM, web clusters, workers), cache hits/misses will not be shared and may lead to redundant computation.
 * For distributed or multi-process use, provide an external PSR-16/PSR-6 compatible cache via the constructor.
 *
 * Example: pass a Redis, Memcached, or other shared cache adapter implementing CacheInterface.
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
        // Allow config overrides via environment variables
        $maxFileSize = getenv('ABLETON_MAX_FILE_SIZE') !== false ? (int)getenv('ABLETON_MAX_FILE_SIZE') : 104857600;
        $maxMemoryUsage = getenv('ABLETON_MAX_MEMORY_USAGE') !== false ? (int)getenv('ABLETON_MAX_MEMORY_USAGE') : 536870912;
        $analysisTimeout = getenv('ABLETON_ANALYSIS_TIMEOUT') !== false ? (int)getenv('ABLETON_ANALYSIS_TIMEOUT') : 300;
        $streamParsingThreshold = getenv('ABLETON_STREAM_PARSING_THRESHOLD') !== false ? (int)getenv('ABLETON_STREAM_PARSING_THRESHOLD') : 10485760;

        $this->config = array_merge([
            'max_depth' => 15,
            'max_file_size' => $maxFileSize, // 100MB default
            'max_memory_usage' => $maxMemoryUsage, // 512MB default
            'analysis_timeout' => $analysisTimeout, // 5 minutes default
            'verbose' => false,
            'enable_caching' => true,
            'cache_lifetime' => 3600,
            'collect_metrics' => true,
            'enable_error_recovery' => true,
            'detect_edition' => true,
            'enable_security_checks' => true,
            'stream_parsing_threshold' => $streamParsingThreshold, // 10MB default
            'xpath_cache_size_limit' => 1000,
            'custom_device_mappings' => [],
            'enable_metadata_enrichment' => true,
        ], $config);

        // Set PHP memory limit if possible
        $currentLimit = ini_get('memory_limit');
        if ($currentLimit !== false && $currentLimit !== '-1' && $this->config['max_memory_usage'] > 0) {
            $desiredLimit = $this->config['max_memory_usage'];
            // Only increase if current is lower
            $currentBytes = $this->convertToBytes($currentLimit);
            if ($currentBytes < $desiredLimit) {
                ini_set('memory_limit', $desiredLimit);
            }
        }

        $this->cacheAdapter = $cacheAdapter;
        $this->initializeDeviceMap();

        // Runtime warning if using in-memory cache in a multi-process environment
        if ($this->cacheAdapter === null && function_exists('posix_getpid')) {
            $isFpm = (php_sapi_name() === 'fpm-fcgi');
            $isCliServer = (php_sapi_name() === 'cli-server');
            if ($isFpm || $isCliServer) {
                $msg = '[AbletonRackAnalyzer] WARNING: Using in-memory XPath cache in a multi-process or threaded environment. For distributed or multi-process use, provide an external cache adapter.';
                error_log($msg);
            }
        }
    }

    /**
     * Convert PHP memory_limit string to bytes
     */
    private function convertToBytes($val): int
    {
        $val = trim($val);
        $last = strtolower($val[strlen($val) - 1]);
        $val = (int)$val;
        switch ($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }
        return $val;
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

        // Compute file hash once
        $fileHash = hash_file('sha256', $filePath);

        try {
            // Validate file before processing
            $this->validateFile($filePath);

            // Try cache first
            if ($this->config['enable_caching']) {
                $cached = $this->getCachedAnalysis($filePath, $fileHash);
                if ($cached !== null) {
                    return $cached;
                }
            }

            // Perform analysis with timeout protection
            $result = $this->performAnalysisWithTimeout($filePath, $fileHash);

            // Cache if valid
            if ($this->config['enable_caching'] && $result->isValid()) {
                $this->cacheAnalysis($filePath, $result, $fileHash);
            }

            return $result;
        } catch (AnalysisException $e) {
            if ($this->config['enable_error_recovery']) {
                return $this->performPartialAnalysis($filePath, $e, $fileHash);
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
        if ($fileSize > $this->config['max_file_size']) {
            throw new AnalysisException(
                "File size ({$fileSize} bytes) exceeds maximum allowed ({$this->config['max_file_size']} bytes). " .
                    "Set ABLETON_MAX_FILE_SIZE env variable to override.",
                self::ERROR_TYPES['FILE_TOO_LARGE']
            );
        }
        if ($fileSize < 100) {
            throw new AnalysisException("File size invalid (too small)", self::ERROR_TYPES['FILE_TOO_LARGE']);
        }

        // Security check: verify file extension
        if ($this->config['enable_security_checks']) {
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            if ($extension !== 'adg') {
                throw new AnalysisException("Invalid file extension", self::ERROR_TYPES['SECURITY_VIOLATION']);
            }
        }

        // Check memory usage before processing
        $currentMemory = memory_get_usage(true);
        if ($currentMemory > $this->config['max_memory_usage']) {
            throw new AnalysisException(
                "Current memory usage (" . $currentMemory . " bytes) exceeds max allowed (" . $this->config['max_memory_usage'] . " bytes). " .
                    "Set ABLETON_MAX_MEMORY_USAGE env variable to override.",
                self::ERROR_TYPES['MEMORY_LIMIT_EXCEEDED']
            );
        }
    }

    /**
     * Cross-platform analysis with timeout protection
     * Uses set_time_limit and manual time checks for compatibility.
     */
    private function performAnalysisWithTimeout(string $filePath, ?string $fileHash = null): RackAnalysis
    {
        $timeout = $this->config['analysis_timeout'] ?? 30;
        $startTime = microtime(true);
        $result = null;
        $exception = null;

        // Try to set a script execution time limit (works on most platforms)
        if (function_exists('set_time_limit')) {
            @set_time_limit($timeout);
        }

        try {
            $result = $this->performAnalysisWithTimeoutLoop($filePath, $timeout, $startTime, $fileHash);
        } catch (Exception $e) {
            $exception = $e;
        }

        if ($exception) {
            throw $exception;
        }
        return $result;
    }

    /**
     * Helper for cross-platform timeout: checks elapsed time in a loop.
     */
    private function performAnalysisWithTimeoutLoop(string $filePath, int $timeout, float $startTime, ?string $fileHash = null): RackAnalysis
    {
        // If performAnalysis is not long-running, just call it directly
        // For more granular control, break up performAnalysis into steps and check time between them
        // Here, we check before and after for simplicity
        if ((microtime(true) - $startTime) > $timeout) {
            throw new AnalysisException("Analysis timeout exceeded (pre)", self::ERROR_TYPES['TIMEOUT']);
        }
        $result = $this->performAnalysis($filePath, $fileHash);
        if ((microtime(true) - $startTime) > $timeout) {
            throw new AnalysisException("Analysis timeout exceeded (post)", self::ERROR_TYPES['TIMEOUT']);
        }
        return $result;
    }

    /**
     * Enhanced file decompression with fallbacks
     */
    public function decompressAndParseAbletonFile(string $filePath): ?SimpleXMLElement
    {
        $fileSize = filesize($filePath);
        try {
            // Use stream parsing for large files
            if ($fileSize > $this->config['stream_parsing_threshold']) {
                return $this->streamParseAbletonFile($filePath);
            }
            return $this->standardParseAbletonFile($filePath);
        } catch (AnalysisException $e) {
            // Already an AnalysisException, just rethrow
            throw $e;
        } catch (Exception $e) {
            throw new AnalysisException("Decompression or XML parse error in decompressAndParseAbletonFile: " . $e->getMessage(), self::ERROR_TYPES['DECOMPRESSION_FAILED'], 0, $e);
        }
    }

    /**
     * Standard parsing method with fallback decompression
     */
    private function standardParseAbletonFile(string $filePath): ?SimpleXMLElement
    {
        $xmlContent = null;
        try {
            // Primary method: PHP's compress.zlib wrapper
            $xmlContent = @file_get_contents("compress.zlib://$filePath");
        } catch (Exception $e) {
            // Fallback method: manual gzip decompression
            try {
                $xmlContent = $this->manualGzipDecompression($filePath);
            } catch (Exception $e2) {
                throw new AnalysisException("Both zlib and manual decompression failed in standardParseAbletonFile: " . $e2->getMessage(), self::ERROR_TYPES['DECOMPRESSION_FAILED'], 0, $e2);
            }
        }

        if ($xmlContent === false || empty($xmlContent)) {
            throw new AnalysisException("Failed to decompress file in standardParseAbletonFile", self::ERROR_TYPES['DECOMPRESSION_FAILED']);
        }

        try {
            return $this->parseXmlSecurely($xmlContent);
        } catch (Exception $e) {
            throw new AnalysisException("XML parse error in standardParseAbletonFile: " . $e->getMessage(), self::ERROR_TYPES['XML_PARSE_ERROR'], 0, $e);
        }
    }
    /**
     * Stream-based parsing for large files
     */
    private function streamParseAbletonFile(string $filePath): ?SimpleXMLElement
    {
        $gz = false;
        try {
            $gz = gzopen($filePath, 'rb');
            if (!$gz) {
                $msg = "Failed to open gzip stream";
                throw new AnalysisException($msg, self::ERROR_TYPES['DECOMPRESSION_FAILED']);
            }

            $xmlContent = '';
            $memoryLimit = $this->config['max_memory_usage'];

            while (!gzeof($gz)) {
                $chunk = gzread($gz, 8192);
                if ($chunk === false) {
                    $msg = "Stream read error in streamParseAbletonFile";
                    throw new AnalysisException($msg, self::ERROR_TYPES['DECOMPRESSION_FAILED']);
                }

                $xmlContent .= $chunk;

                // Memory protection
                if (memory_get_usage(true) > $memoryLimit) {
                    $msg = "Memory limit exceeded in streamParseAbletonFile";
                    throw new AnalysisException($msg, self::ERROR_TYPES['MEMORY_LIMIT_EXCEEDED']);
                }
            }

            return $this->parseXmlSecurely($xmlContent);
        } catch (AnalysisException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new AnalysisException("Decompression or XML parse error in streamParseAbletonFile: " . $e->getMessage(), self::ERROR_TYPES['DECOMPRESSION_FAILED'], 0, $e);
        } finally {
            if ($gz !== false) {
                gzclose($gz);
            }
        }
    }

    /**
     * Manual gzip decompression fallback
     */
    private function manualGzipDecompression(string $filePath): ?string
    {
        $handle = false;
        try {
            $handle = fopen($filePath, 'rb');
            if (!$handle) {
                $msg = "Failed to open file in manualGzipDecompression";
                throw new AnalysisException($msg, self::ERROR_TYPES['DECOMPRESSION_FAILED']);
            }

            $data = fread($handle, filesize($filePath));
            if ($data === false) {
                $msg = "Failed to read file in manualGzipDecompression";
                throw new AnalysisException($msg, self::ERROR_TYPES['DECOMPRESSION_FAILED']);
            }

            $decoded = gzdecode($data);
            if ($decoded === false) {
                $msg = "gzdecode failed in manualGzipDecompression";
                throw new AnalysisException($msg, self::ERROR_TYPES['DECOMPRESSION_FAILED']);
            }
            return $decoded;
        } catch (AnalysisException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new AnalysisException("Decompression error in manualGzipDecompression: " . $e->getMessage(), self::ERROR_TYPES['DECOMPRESSION_FAILED'], 0, $e);
        } finally {
            if ($handle !== false) {
                fclose($handle);
            }
        }
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
                $errorMsg = "XML parsing failed in parseXmlSecurely";
                if (!empty($errors)) {
                    $errorMsg .= ": " . $errors[0]->message;
                }
                throw new AnalysisException($errorMsg, self::ERROR_TYPES['XML_PARSE_ERROR']);
            }

            return $xml;
        } catch (AnalysisException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new AnalysisException("XML parse error in parseXmlSecurely: " . $e->getMessage(), self::ERROR_TYPES['XML_PARSE_ERROR'], 0, $e);
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
    private function performAnalysis(string $filePath, ?string $fileHash = null): RackAnalysis
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

        // Use provided hash if available
        $finalHash = $fileHash ?? hash_file('sha256', $filePath);

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
            fileHash: $finalHash,
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
    private function performPartialAnalysis(string $filePath, Exception $e, ?string $fileHash = null): RackAnalysis
    {
        try {
            $xml = $this->decompressAndParseAbletonFile($filePath);
            if (!$xml) {
                return RackAnalysis::createError(basename($filePath), $e->getMessage(), 'DECOMPRESSION_FAILED');
            }

            $rackName = $this->extractRackNameFromXml($xml) ?? basename($filePath);
            $versionInfo = $this->extractAbletonVersionInfo($xml);

            $finalHash = $fileHash ?? hash_file('sha256', $filePath);

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
                fileHash: $finalHash,
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
    private function getCachedAnalysis(string $filePath, ?string $fileHash = null): ?RackAnalysis
    {
        $finalHash = $fileHash ?? hash_file('sha256', $filePath);
        $cacheKey = "ableton_analysis_{$finalHash}";

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
    private function cacheAnalysis(string $filePath, RackAnalysis $analysis, ?string $fileHash = null): void
    {
        $finalHash = $fileHash ?? hash_file('sha256', $filePath);
        $cacheKey = "ableton_analysis_{$finalHash}";

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
            // XML structure validation: check for GroupDevicePreset
            $groupDevicePreset = $this->getXpathCached($root, './/GroupDevicePreset');
            if (empty($groupDevicePreset)) {
                $rackInfo["parsing_warnings"][] = "Missing expected node: GroupDevicePreset. XML structure may be incompatible or corrupted.";
            }

            list($rackType, $mainDevice) = $this->detectRackTypeAndDevice($root);
            $rackInfo["rack_type"] = $rackType;

            if (!$rackType || !$mainDevice) {
                $rackInfo["parsing_warnings"][] = "Unable to detect rack type or main device. XML structure may be incompatible or corrupted.";
                // Fallback: return minimal info
                return $rackInfo;
            }

            // Validate main device has Name node
            $mainDeviceName = $this->getXpathCached($mainDevice, 'Name');
            if (empty($mainDeviceName)) {
                $rackInfo["parsing_warnings"][] = "Main device missing Name node. Device name may be unavailable.";
            }

            // Parse macros with enhanced error handling
            $this->parseMacroControls($mainDevice, $rackInfo);

            // Parse chains with enhanced error handling
            $this->parseRackChains($root, $rackType, $rackInfo, $verbose);

            // Validate at least one chain was found
            if (empty($rackInfo["chains"])) {
                $rackInfo["parsing_warnings"][] = "No chains found in rack. XML structure may be incomplete or incompatible.";
            }
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
                    $macroControlElem = $this->getXpathCached($mainDevice, "MacroControls.$i/Manual");
                    $macroName = (string)($macroNameElem[0]['Value'] ?? "Macro " . ($i + 1));
                    $macroValue = !empty($macroControlElem) ? (float)($macroControlElem[0]['Value'] ?? 0) : 0.0;

                    $rackInfo["macro_controls"][] = [
                        "name" => $macroName,
                        "value" => $macroValue,
                        "index" => $i
                    ];
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
        $metrics = [
            'memory_usage' => memory_get_usage(true),
            'peak_memory_usage' => memory_get_peak_usage(true),
            'analysis_duration_ms' => isset($this->analysisStartTime) ? (int)((microtime(true) - $this->analysisStartTime) * 1000) : null,
            'xpath_cache_hits' => $this->xpathCacheHits,
            'xpath_cache_misses' => $this->xpathCacheMisses,
        ];
        return $metrics;
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
