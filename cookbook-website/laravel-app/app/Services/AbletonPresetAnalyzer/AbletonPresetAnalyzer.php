<?php

namespace App\Services\AbletonPresetAnalyzer;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Ableton Preset Analyzer - Pure PHP
 * Analyzes Ableton device preset files (.adv files) by decompressing and parsing XML structure.
 * Extracts device parameters, macro mappings, and metadata.
 */
class AbletonPresetAnalyzer
{
    // Device type mapping - reuse from existing rack analyzer but focused on presets
    private static $deviceTypeMap = [
        // Instruments
        "Analog" => "Analog",
        "Bass" => "Bass",
        "BeatBox" => "BeatBox", 
        "Collision" => "Collision",
        "Corpus" => "Corpus",
        "Cymbals" => "Cymbals",
        "Drum" => "Drum Kit",
        "DrumMachine" => "Drum Machine",
        "DrumRack" => "Drum Rack",
        "ElectricPiano" => "Electric Piano",
        "Finger" => "Finger",
        "GrandPiano" => "Grand Piano",
        "GroovePool" => "Groove Pool",
        "HiHat" => "Hi-Hat",
        "Kick" => "Kick",
        "MidiArp" => "MIDI Arp",
        "MidiChord" => "MIDI Chord",
        "MidiEcho" => "MIDI Echo", 
        "MidiNoteLength" => "MIDI Note Length",
        "MidiPitcher" => "MIDI Pitcher",
        "MidiRandom" => "MIDI Random",
        "MidiScale" => "MIDI Scale",
        "MidiVelocity" => "MIDI Velocity",
        "Operator" => "Operator",
        "OriginalSimpler" => "Original Simpler",
        "Pad" => "Pad",
        "PercussionRack" => "Percussion Rack", 
        "Ride" => "Ride",
        "Simpler" => "Simpler",
        "Snare" => "Snare",
        "StringStudio" => "String Studio",
        "Tom" => "Tom",
        "UltraAnalog" => "Ultra Analog",
        "Wavetable" => "Wavetable",
        "MxDeviceInstrument" => "Max for Live Instrument",
        
        // Audio Effects (same as in rack analyzer)
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
        "Delay" => "Delay",
        "DrumBuss" => "Drum Buss",
        "DynamicTube" => "Dynamic Tube",
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
        "Overdrive" => "Overdrive",
        "Pedal" => "Pedal",
        "Phaser" => "Phaser",
        "PhaserFlanger" => "Phaser-Flanger",
        "Flanger" => "Flanger",
        "Redux" => "Redux",
        "Resonators" => "Resonators",
        "Reverb" => "Reverb",
        "Roar" => "Roar",
        "Saturator" => "Saturator",
        "Shaper" => "Shaper",
        "Shifter" => "Shifter",
        "FrequencyShifter" => "Frequency Shifter",
        "SpectralResonator" => "Spectral Resonator",
        "SpectralTime" => "Spectral Time",
        "Spectrum" => "Spectrum",
        "Tuner" => "Tuner",
        "Utility" => "Utility",
        "VinylDistortion" => "Vinyl Distortion",
        "Vocoder" => "Vocoder",
        "ConvolutionReverb" => "Convolution Reverb",
        "ColorLimiter" => "Color Limiter",
        "MxDeviceAudioEffect" => "Max for Live Effect",
    ];

    // Device categories for classification
    private static $deviceCategories = [
        // Instruments 
        'Analog' => 'synth',
        'Bass' => 'synth', 
        'Collision' => 'synth',
        'Drum Kit' => 'drums',
        'Drum Rack' => 'drums',
        'Electric Piano' => 'keys',
        'Grand Piano' => 'keys',
        'Operator' => 'synth',
        'Simpler' => 'sampler',
        'Wavetable' => 'synth',
        
        // Effects
        'Compressor' => 'dynamics',
        'EQ Eight' => 'eq',
        'EQ Three' => 'eq',
        'Reverb' => 'reverb',
        'Delay' => 'delay',
        'Echo' => 'delay',
        'Chorus-Ensemble' => 'modulation',
        'Phaser' => 'modulation',
        'Flanger' => 'modulation',
        'Auto Filter' => 'filter',
        'Saturator' => 'distortion',
        'Overdrive' => 'distortion',
    ];

    /**
     * Analyze an Ableton preset file
     * 
     * @param string $filePath Path to the .adv file
     * @return array Analysis results
     * @throws Exception If file cannot be processed
     */
    public function analyzePresetFile(string $filePath): array
    {
        try {
            // Validate file exists and is readable
            if (!file_exists($filePath) || !is_readable($filePath)) {
                throw new Exception("Preset file not found or not readable: {$filePath}");
            }

            // Get file info
            $fileSize = filesize($filePath);
            $fileName = basename($filePath);

            Log::info("Starting preset analysis", [
                'file' => $fileName,
                'size' => $fileSize,
                'path' => $filePath
            ]);

            // Decompress and parse the preset
            $xmlContent = $this->decompressPresetFile($filePath);
            $parsedData = $this->parsePresetXml($xmlContent);

            // Extract analysis data
            $analysis = [
                'file_info' => [
                    'filename' => $fileName,
                    'size' => $fileSize,
                    'hash' => hash_file('sha256', $filePath),
                ],
                'device_info' => $this->extractDeviceInfo($parsedData),
                'parameters' => $this->extractParameters($parsedData),
                'macros' => $this->extractMacros($parsedData),
                'metadata' => $this->extractMetadata($parsedData),
                'compatibility' => $this->analyzeCompatibility($parsedData),
                'sonic_analysis' => $this->analyzeSonicCharacteristics($parsedData),
            ];

            Log::info("Preset analysis completed", [
                'device_type' => $analysis['device_info']['type'] ?? 'unknown',
                'parameter_count' => count($analysis['parameters']),
                'macro_count' => count($analysis['macros'])
            ]);

            return $analysis;

        } catch (Exception $e) {
            Log::error("Preset analysis failed", [
                'file' => $filePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Decompress .adv file (gzipped XML)
     */
    private function decompressPresetFile(string $filePath): string
    {
        try {
            $compressedData = file_get_contents($filePath);
            if ($compressedData === false) {
                throw new Exception("Cannot read preset file");
            }

            // .adv files are gzipped XML
            $xmlContent = gzuncompress($compressedData);
            if ($xmlContent === false) {
                throw new Exception("Cannot decompress preset file - may not be a valid .adv file");
            }

            return $xmlContent;

        } catch (Exception $e) {
            throw new Exception("Decompression failed: " . $e->getMessage());
        }
    }

    /**
     * Parse the decompressed XML content
     */
    private function parsePresetXml(string $xmlContent): \SimpleXMLElement
    {
        try {
            // Suppress libxml errors to handle malformed XML gracefully
            libxml_use_internal_errors(true);

            $xml = simplexml_load_string($xmlContent, 'SimpleXMLElement', LIBXML_NOCDATA);
            
            if ($xml === false) {
                $errors = libxml_get_errors();
                $errorMessage = "XML parsing failed";
                if (!empty($errors)) {
                    $errorMessage .= ": " . $errors[0]->message;
                }
                throw new Exception($errorMessage);
            }

            return $xml;

        } catch (Exception $e) {
            throw new Exception("XML parsing failed: " . $e->getMessage());
        }
    }

    /**
     * Extract device information
     */
    private function extractDeviceInfo(\SimpleXMLElement $xml): array
    {
        $deviceInfo = [
            'type' => 'unknown',
            'name' => 'Unknown Device',
            'category' => 'other',
            'is_instrument' => false,
            'is_effect' => false,
        ];

        try {
            // Look for device type in various locations
            $deviceType = $this->findDeviceType($xml);
            
            if ($deviceType) {
                $deviceInfo['type'] = $deviceType;
                $deviceInfo['name'] = self::$deviceTypeMap[$deviceType] ?? $deviceType;
                $deviceInfo['category'] = self::$deviceCategories[$deviceInfo['name']] ?? 'other';
                
                // Determine if it's an instrument or effect
                $deviceInfo['is_instrument'] = $this->isInstrumentDevice($deviceType);
                $deviceInfo['is_effect'] = !$deviceInfo['is_instrument'];
            }

            // Look for device name/title
            $deviceName = $this->findDeviceName($xml);
            if ($deviceName) {
                $deviceInfo['preset_name'] = $deviceName;
            }

        } catch (Exception $e) {
            Log::warning("Device info extraction failed", ['error' => $e->getMessage()]);
        }

        return $deviceInfo;
    }

    /**
     * Find device type from XML structure
     */
    private function findDeviceType(\SimpleXMLElement $xml): ?string
    {
        // Common paths where device type is stored
        $possiblePaths = [
            '//DevicePreset/@Name',
            '//PluginDesc/@Name', 
            '//Device/@Type',
            '//@DeviceType',
        ];

        foreach ($possiblePaths as $path) {
            $result = $xml->xpath($path);
            if (!empty($result)) {
                $deviceType = (string) $result[0];
                if (isset(self::$deviceTypeMap[$deviceType])) {
                    return $deviceType;
                }
            }
        }

        return null;
    }

    /**
     * Find device/preset name
     */
    private function findDeviceName(\SimpleXMLElement $xml): ?string
    {
        $possiblePaths = [
            '//DevicePreset/@Name',
            '//Name/@Value',
            '//DisplayName/@Value',
            '//@PresetName',
        ];

        foreach ($possiblePaths as $path) {
            $result = $xml->xpath($path);
            if (!empty($result)) {
                return (string) $result[0];
            }
        }

        return null;
    }

    /**
     * Extract device parameters
     */
    private function extractParameters(\SimpleXMLElement $xml): array
    {
        $parameters = [];

        try {
            // Look for parameter values
            $paramPaths = [
                '//Parameter',
                '//Param', 
                '//DeviceParameter',
                '//Value'
            ];

            foreach ($paramPaths as $path) {
                $params = $xml->xpath($path);
                foreach ($params as $param) {
                    $name = $this->getParameterName($param);
                    $value = $this->getParameterValue($param);
                    
                    if ($name && $value !== null) {
                        $parameters[$name] = [
                            'value' => $value,
                            'type' => $this->getParameterType($param),
                            'min' => $this->getParameterMin($param),
                            'max' => $this->getParameterMax($param),
                        ];
                    }
                }
            }

        } catch (Exception $e) {
            Log::warning("Parameter extraction failed", ['error' => $e->getMessage()]);
        }

        return $parameters;
    }

    /**
     * Extract macro mappings
     */
    private function extractMacros(\SimpleXMLElement $xml): array
    {
        $macros = [];

        try {
            // Look for macro control mappings
            $macroElements = $xml->xpath('//MacroControls//RemoteableSlot');
            
            foreach ($macroElements as $index => $macro) {
                $macroData = [
                    'index' => $index,
                    'name' => (string) ($macro['Name'] ?? "Macro " . ($index + 1)),
                    'value' => (float) ($macro['Value'] ?? 0),
                    'mappings' => []
                ];

                // Look for parameter mappings
                $mappings = $macro->xpath('.//ModulationTarget');
                foreach ($mappings as $mapping) {
                    $macroData['mappings'][] = [
                        'parameter' => (string) ($mapping['Parameter'] ?? 'Unknown'),
                        'min' => (float) ($mapping['Min'] ?? 0),
                        'max' => (float) ($mapping['Max'] ?? 1),
                    ];
                }

                $macros[] = $macroData;
            }

        } catch (Exception $e) {
            Log::warning("Macro extraction failed", ['error' => $e->getMessage()]);
        }

        return $macros;
    }

    /**
     * Extract general metadata
     */
    private function extractMetadata(\SimpleXMLElement $xml): array
    {
        $metadata = [];

        try {
            // Look for creation info
            $metadata['ableton_version'] = $this->extractAbletonVersion($xml);
            $metadata['creation_date'] = $this->extractCreationDate($xml);
            $metadata['modification_date'] = $this->extractModificationDate($xml);
            $metadata['author'] = $this->extractAuthor($xml);
            
            // Device-specific metadata
            $metadata['sample_rate'] = $this->extractSampleRate($xml);
            $metadata['polyphony'] = $this->extractPolyphony($xml);
            $metadata['voices'] = $this->extractVoiceCount($xml);

        } catch (Exception $e) {
            Log::warning("Metadata extraction failed", ['error' => $e->getMessage()]);
        }

        return array_filter($metadata); // Remove null values
    }

    /**
     * Analyze compatibility requirements
     */
    private function analyzeCompatibility(\SimpleXMLElement $xml): array
    {
        return [
            'min_ableton_version' => $this->extractAbletonVersion($xml) ?? '9.0',
            'max_for_live_required' => $this->requiresMaxForLive($xml),
            'plugin_dependencies' => $this->extractPluginDependencies($xml),
            'cpu_usage_estimate' => $this->estimateCpuUsage($xml),
        ];
    }

    /**
     * Analyze sonic characteristics for search/discovery
     */
    private function analyzeSonicCharacteristics(\SimpleXMLElement $xml): array
    {
        $characteristics = [];

        try {
            // Analyze parameter values to infer sonic qualities
            $parameters = $this->extractParameters($xml);
            
            // Example heuristics (can be expanded)
            if (isset($parameters['Resonance']) && $parameters['Resonance']['value'] > 0.7) {
                $characteristics[] = 'resonant';
            }
            
            if (isset($parameters['Drive']) && $parameters['Drive']['value'] > 0.5) {
                $characteristics[] = 'aggressive';
            }
            
            if (isset($parameters['Attack']) && $parameters['Attack']['value'] < 0.1) {
                $characteristics[] = 'punchy';
            }

            // Add more sophisticated analysis as needed

        } catch (Exception $e) {
            Log::warning("Sonic analysis failed", ['error' => $e->getMessage()]);
        }

        return $characteristics;
    }

    // Helper methods for parameter extraction
    private function getParameterName(\SimpleXMLElement $param): ?string
    {
        return (string) ($param['Name'] ?? $param['ParameterName'] ?? null);
    }

    private function getParameterValue(\SimpleXMLElement $param)
    {
        $value = $param['Value'] ?? $param['ParameterValue'] ?? null;
        return $value !== null ? (float) $value : null;
    }

    private function getParameterType(\SimpleXMLElement $param): string
    {
        return (string) ($param['Type'] ?? 'float');
    }

    private function getParameterMin(\SimpleXMLElement $param): ?float
    {
        $min = $param['Min'] ?? $param['MinValue'] ?? null;
        return $min !== null ? (float) $min : null;
    }

    private function getParameterMax(\SimpleXMLElement $param): ?float
    {
        $max = $param['Max'] ?? $param['MaxValue'] ?? null;
        return $max !== null ? (float) $max : null;
    }

    private function extractAbletonVersion(\SimpleXMLElement $xml): ?string
    {
        $versionPaths = [
            '//@Version',
            '//AbletonVersion/@Value',
            '//CreatedBy/@Version'
        ];

        foreach ($versionPaths as $path) {
            $result = $xml->xpath($path);
            if (!empty($result)) {
                return (string) $result[0];
            }
        }

        return null;
    }

    private function extractCreationDate(\SimpleXMLElement $xml): ?string
    {
        $result = $xml->xpath('//@CreationDate');
        return !empty($result) ? (string) $result[0] : null;
    }

    private function extractModificationDate(\SimpleXMLElement $xml): ?string
    {
        $result = $xml->xpath('//@ModificationDate');
        return !empty($result) ? (string) $result[0] : null;
    }

    private function extractAuthor(\SimpleXMLElement $xml): ?string
    {
        $authorPaths = [
            '//@Author',
            '//Creator/@Value',
            '//Author/@Value'
        ];

        foreach ($authorPaths as $path) {
            $result = $xml->xpath($path);
            if (!empty($result)) {
                return (string) $result[0];
            }
        }

        return null;
    }

    private function extractSampleRate(\SimpleXMLElement $xml): ?int
    {
        $result = $xml->xpath('//@SampleRate');
        return !empty($result) ? (int) $result[0] : null;
    }

    private function extractPolyphony(\SimpleXMLElement $xml): ?int
    {
        $result = $xml->xpath('//@Polyphony');
        return !empty($result) ? (int) $result[0] : null;
    }

    private function extractVoiceCount(\SimpleXMLElement $xml): ?int
    {
        $result = $xml->xpath('//@Voices');
        return !empty($result) ? (int) $result[0] : null;
    }

    private function requiresMaxForLive(\SimpleXMLElement $xml): bool
    {
        $maxDevices = $xml->xpath('//MxDevice | //MaxDevice');
        return !empty($maxDevices);
    }

    private function extractPluginDependencies(\SimpleXMLElement $xml): array
    {
        $dependencies = [];
        
        $plugins = $xml->xpath('//ExternalPlugin | //ThirdPartyPlugin');
        foreach ($plugins as $plugin) {
            $name = (string) ($plugin['Name'] ?? 'Unknown Plugin');
            $version = (string) ($plugin['Version'] ?? 'Unknown');
            $dependencies[] = [
                'name' => $name,
                'version' => $version,
                'required' => true
            ];
        }

        return $dependencies;
    }

    private function estimateCpuUsage(\SimpleXMLElement $xml): string
    {
        // Simple heuristics for CPU usage estimation
        $complexity = 0;
        
        // Count parameters (more parameters = more complexity)
        $params = $xml->xpath('//Parameter | //Param');
        $complexity += count($params) * 0.1;
        
        // Check for CPU-intensive devices
        $deviceType = $this->findDeviceType($xml);
        $highCpuDevices = ['Wavetable', 'Operator', 'ConvolutionReverb', 'HybridReverb'];
        
        if ($deviceType && in_array($deviceType, $highCpuDevices)) {
            $complexity += 2;
        }

        // Check for Max for Live (usually higher CPU)
        if ($this->requiresMaxForLive($xml)) {
            $complexity += 1;
        }

        // Categorize
        if ($complexity > 2) {
            return 'high';
        } elseif ($complexity > 1) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    private function isInstrumentDevice(string $deviceType): bool
    {
        $instruments = [
            'Analog', 'Bass', 'Collision', 'Drum Kit', 'Drum Rack', 
            'Electric Piano', 'Grand Piano', 'Operator', 'Simpler', 
            'Wavetable', 'MxDeviceInstrument'
        ];
        
        return in_array($deviceType, $instruments);
    }

    /**
     * Get supported preset file extensions
     */
    public static function getSupportedExtensions(): array
    {
        return ['adv'];
    }

    /**
     * Validate if file is a supported preset format
     */
    public static function isValidPresetFile(string $filePath): bool
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        return in_array($extension, self::getSupportedExtensions());
    }
}