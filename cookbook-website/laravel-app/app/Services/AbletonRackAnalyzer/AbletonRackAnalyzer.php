<?php

namespace App\Services\AbletonRackAnalyzer;

use Exception;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Illuminate\Support\Facades\Log;

/**
 * Ableton Rack Analyzer V3 - PHP Port
 * Analyzes Ableton rack files (.adg files) by decompressing and parsing XML structure.
 * Ported from Python to PHP while maintaining functionality.
 */
class AbletonRackAnalyzer {
    
    // Comprehensive device type mapping for display names
    private static $deviceTypeMap = [
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
        "ConvolutionReverb" => "Convolution Reverb",
        "ConvolutionReverbPro" => "Convolution Reverb Pro",
        "InMeasurementDevice" => "IR Measurement Device",
        "ColorLimiter" => "Color Limiter",
        "GatedDelay" => "Gated Delay",
        "PitchHack" => "Pitch Hack",
        "ReEnveloper" => "Re-Enveloper",
        "SpectralBlur" => "Spectral Blur",
        "VectorDelay" => "Vector Delay",
        "VectorFade" => "Vector Fade",
        "ArrangementLooper" => "Arrangement Looper",
        "Performer" => "Performer",
        "Prearranger" => "Prearranger",
        "VectorGrain" => "Vector Grain",
        "SurroundPanner" => "Surround Panner",
        
        // Additional audio effects
        "SimpleDelay" => "Simple Delay",
        "PingPongDelay" => "Ping Pong Delay",
        "AutoFilter2" => "Auto Filter",
        "Chorus2" => "Chorus",
        "Phaser2" => "Phaser",
        "FilterEQ" => "Filter EQ",
        "EQFilter" => "Filter EQ",
        "BitCrusher" => "Redux",
        "Bitcrusher" => "Redux",
        "Distortion" => "Overdrive",
        "StereoImager" => "Utility",
        "Stereo" => "Utility",
        "Limiter2" => "Limiter",
        
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
        "DSClang" => "DS Clang",
        "DSClap" => "DS Clap",
        "DSCymbal" => "DS Cymbal",
        "DSFM" => "DS FM",
        "DSHH" => "DS HH",
        "DSKick" => "DS Kick",
        "DSSnare" => "DS Snare",
        "DSTom" => "DS Tom",
        "InstrumentGroupDevice" => "Instrument Rack",
        "MidiEffectGroupDevice" => "MIDI Effect Rack",
        "DrumGroupDevice" => "Drum Rack",
        "Treee" => "Tree Tone",
        "VectorFM" => "Vector FM",
        
        // Drum synthesizers (Live 12+ additions)
        "BassDrum" => "Bass Drum",
        "Clap" => "Clap",
        "Cymbal" => "Cymbal",
        "FMDrum" => "FM Drum",
        "HiHat" => "Hi Hat",
        "Kick" => "Kick",
        "Perc" => "Perc",
        "Snare" => "Snare",
        "Tom" => "Tom",
        "DSAnalog" => "DS Analog",
        "DSDrum" => "DS Drum",
        "DSPenta" => "DS Penta",
        
        // MIDI Effects
        "Arpeggiator" => "Arpeggiator",
        "Arpeggiate" => "Arpeggiator",
        "BouncyNotes" => "Bouncy Notes",
        "CCControl" => "CC Control",
        "Chord" => "Chord",
        "EnvelopeMidi" => "Envelope MIDI",
        "ExpressionControl" => "Expression Control",
        "ExpressiveChords" => "Expressive Chords",
        "MelodicSteps" => "Melodic Steps",
        "Microtuner" => "Microtuner",
        "MidiEffectRack" => "MIDI Effect Rack",
        "MidiMonitor" => "MIDI Monitor",
        "MPEControl" => "MPE Control",
        "NoteEcho" => "Note Echo",
        "NoteLength" => "Note Length",
        "Pitch" => "Pitch",
        "Random" => "Random",
        "RhythmicSteps" => "Rhythmic Steps",
        "Scale" => "Scale",
        "ShaperMidi" => "Shaper MIDI",
        "StepSequencer" => "SQ Sequencer",
        "StepArp" => "Step Arp",
        "Velocity" => "Velocity",
        
        // Additional MIDI effects
        "Connect" => "Connect",
        "Ornament" => "Ornament",
        "Quantize" => "Quantize",
        "Recombine" => "Recombine",
        "Rhythm" => "Rhythm",
        "Seed" => "Seed",
        "Shape" => "Shape",
        "Stacks" => "Stacks",
        "Strum" => "Strum",
        "TimeSpan" => "Time Span",
        "TimeWarp" => "Time Warp",
        "Pattern" => "Pattern",
        "MidiEuclideanGenerator" => "MIDI Euclidean Generator",
        "MidiVelocityShaper" => "MIDI Velocity Shaper",
        
        // CV devices (Live 12)
        "CVClockIn" => "CV Clock In",
        "CVClockOut" => "CV Clock Out",
        "CVEnvelopeFollower" => "CV Envelope Follower",
        "CVInstrument" => "CV Instrument",
        "CVLFO" => "CV LFO",
        "CVShaper" => "CV Shaper",
        "CVUtility" => "CV Utility",
        
        // Legacy or variant naming
        "Operator2" => "Operator",
        "Bass2" => "Bass",
        "Collision2" => "Collision",
        "Simpler2" => "Simpler",
        "Sampler2" => "Sampler"
    ];

    // Branch preset type map for each rack type
    private static $branchTypeMap = [
        "AudioEffectGroupDevice" => "AudioEffectBranchPreset",
        "InstrumentGroupDevice" => "InstrumentBranchPreset",
        "MidiEffectGroupDevice" => "MidiEffectBranchPreset"
    ];

    /**
     * Decompress a gzip compressed Ableton file and parse its XML content.
     */
    public static function decompressAndParseAbletonFile($filePath) {
        try {
            // Check if file exists and is readable
            if (!file_exists($filePath)) {
                Log::error("File does not exist: $filePath");
                return null;
            }
            
            if (!is_readable($filePath)) {
                Log::error("File is not readable: $filePath");
                return null;
            }
            
            // Check file size (avoid extremely large files)
            $fileSize = filesize($filePath);
            if ($fileSize > 100 * 1024 * 1024) { // 100MB limit
                Log::warning("File is too large ($fileSize bytes): $filePath");
                return null;
            } elseif ($fileSize < 100) { // Too small to be a valid rack
                Log::warning("File is too small ($fileSize bytes): $filePath");
                return null;
            }
            
            // Try to decompress using gzfile
            $xmlContent = @file_get_contents("compress.zlib://$filePath");
            
            if ($xmlContent === false) {
                Log::error("Failed to decompress gzip file: $filePath");
                return null;
            }
            
            // Basic XML validation
            if (empty($xmlContent)) {
                Log::error("Empty file after decompression: $filePath");
                return null;
            }
            
            // Check if it looks like XML
            $trimmedContent = ltrim($xmlContent);
            if (!str_starts_with($trimmedContent, '<?xml') && !str_starts_with($trimmedContent, '<')) {
                Log::error("File does not appear to contain XML: $filePath");
                return null;
            }
            
            // Parse XML
            $xml = @simplexml_load_string($xmlContent);
            if ($xml === false) {
                Log::error("XML parsing error in $filePath");
                return null;
            }
            
            // Basic structure validation
            if (!$xml) {
                Log::error("Failed to parse XML root element: $filePath");
                return null;
            }
            
            // Check if it looks like an Ableton file
            $rootTag = $xml->getName();
            if (!in_array($rootTag, ['Ableton', 'GroupDevicePreset', 'PresetRef'])) {
                Log::warning("Root element '$rootTag' doesn't look like Ableton format: $filePath");
            }
            
            return $xml;
            
        } catch (Exception $e) {
            Log::error("Unexpected error processing $filePath: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Detects and returns the type and main device element for a rack.
     */
    public static function detectRackTypeAndDevice($root) {
        // Find GroupDevicePreset
        $groupPreset = $root->xpath('.//GroupDevicePreset');
        if (!empty($groupPreset)) {
            $device = $groupPreset[0]->xpath('Device');
            if (!empty($device)) {
                foreach ($device[0] as $child) {
                    $childName = $child->getName();
                    if (array_key_exists($childName, self::$branchTypeMap)) {
                        return [$childName, $child];
                    }
                }
            }
        }

        // Fallback: look directly for known device types
        foreach (array_keys(self::$branchTypeMap) as $rackType) {
            $device = $root->xpath(".//$rackType");
            if (!empty($device)) {
                return [$rackType, $device[0]];
            }
        }
        
        return [null, null];
    }

    /**
     * Map rack type to branch preset type.
     */
    public static function getBranchPresetType($rackType) {
        return self::$branchTypeMap[$rackType] ?? null;
    }

    /**
     * Extract rack name from XML structure.
     */
    public static function extractRackNameFromXml($root) {
        // Try to find name in GroupDevicePreset
        $groupPreset = $root->xpath('.//GroupDevicePreset');
        if (!empty($groupPreset)) {
            // Check for Name element in preset
            $nameElem = $groupPreset[0]->xpath('Name');
            if (!empty($nameElem) && isset($nameElem[0]['Value'])) {
                $name = trim((string)$nameElem[0]['Value']);
                if (!empty($name)) {
                    return $name;
                }
            }
            
            // Check for UserName in the main device
            $device = $groupPreset[0]->xpath('Device');
            if (!empty($device)) {
                foreach ($device[0] as $child) {
                    $userNameElem = $child->xpath('UserName');
                    if (!empty($userNameElem) && isset($userNameElem[0]['Value'])) {
                        $name = trim((string)$userNameElem[0]['Value']);
                        if (!empty($name)) {
                            return $name;
                        }
                    }
                }
            }
        }
        
        // Try to find name in root level
        $nameElem = $root->xpath('.//Name');
        if (!empty($nameElem) && isset($nameElem[0]['Value'])) {
            $name = trim((string)$nameElem[0]['Value']);
            if (!empty($name)) {
                return $name;
            }
        }
        
        return null;
    }

    /**
     * Extract Ableton Live version information from XML structure.
     */
    public static function extractAbletonVersionInfo($root) {
        $versionInfo = [
            "ableton_version" => null,
            "major_version" => null,
            "minor_version" => null,
            "build_number" => null,
            "revision" => null
        ];
        
        // Try to find version in root Ableton element attributes first
        if ($root->getName() === "Ableton") {
            $attrs = $root->attributes();
            if (isset($attrs['MajorVersion'])) {
                $versionInfo["major_version"] = (int)$attrs['MajorVersion'];
            }
            if (isset($attrs['MinorVersion'])) {
                $versionInfo["minor_version"] = (int)$attrs['MinorVersion'];
            }
            if (isset($attrs['BuildNumber'])) {
                $versionInfo["build_number"] = (int)$attrs['BuildNumber'];
            }
            if (isset($attrs['Revision'])) {
                $versionInfo["revision"] = (int)$attrs['Revision'];
            }
        }
        
        // Try to find version in Ableton child element
        $abletonElem = $root->xpath('.//Ableton');
        if (!empty($abletonElem) && $versionInfo["major_version"] === null) {
            $attrs = $abletonElem[0]->attributes();
            if (isset($attrs['MajorVersion'])) {
                $versionInfo["major_version"] = (int)$attrs['MajorVersion'];
            }
            if (isset($attrs['MinorVersion'])) {
                $versionInfo["minor_version"] = (int)$attrs['MinorVersion'];
            }
            
            // Check child elements
            $majorElem = $abletonElem[0]->xpath('MajorVersion');
            if (!empty($majorElem) && $versionInfo["major_version"] === null) {
                $versionInfo["major_version"] = (int)($majorElem[0]['Value'] ?? 0);
            }
            
            $minorElem = $abletonElem[0]->xpath('MinorVersion');
            if (!empty($minorElem) && $versionInfo["minor_version"] === null) {
                $versionInfo["minor_version"] = (int)($minorElem[0]['Value'] ?? 0);
            }
            
            $buildElem = $abletonElem[0]->xpath('BuildNumber');
            if (!empty($buildElem) && $versionInfo["build_number"] === null) {
                $versionInfo["build_number"] = (int)($buildElem[0]['Value'] ?? 0);
            }
            
            // Construct full version string
            if ($versionInfo["major_version"] !== null) {
                $versionParts = [$versionInfo["major_version"]];
                if ($versionInfo["minor_version"] !== null) {
                    $versionParts[] = $versionInfo["minor_version"];
                    if ($versionInfo["revision"] !== null) {
                        $versionParts[] = $versionInfo["revision"];
                    }
                }
                $versionInfo["ableton_version"] = implode('.', $versionParts);
            }
        }
        
        // Fallback: try to find version in other common locations
        if (!$versionInfo["ableton_version"]) {
            $attrs = $root->attributes();
            if (isset($attrs['Version'])) {
                $versionInfo["ableton_version"] = (string)$attrs['Version'];
            }
            
            // Check for Creator attribute which sometimes contains version info
            if (isset($attrs['Creator']) && strpos((string)$attrs['Creator'], 'Ableton Live') !== false) {
                // Try to extract version from creator string
                if (preg_match('/(\d+\.\d+(?:\.\d+)?)/', (string)$attrs['Creator'], $matches)) {
                    $versionInfo["ableton_version"] = $matches[1];
                }
            }
        }
        
        return $versionInfo;
    }

    /**
     * Main function to parse a rack structure and its chains/devices.
     */
    public static function parseChainsAndDevices($root, $filename = null, $verbose = false) {
        // Extract rack name from XML structure first, fall back to filename
        $rackName = self::extractRackNameFromXml($root);
        if (!$rackName && $filename) {
            $rackName = pathinfo(basename($filename), PATHINFO_FILENAME);
        } elseif (!$rackName) {
            $rackName = "Unknown";
        }

        // Extract version information
        $versionInfo = self::extractAbletonVersionInfo($root);
        
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

        list($rackType, $mainDevice) = self::detectRackTypeAndDevice($root);
        if ($verbose) {
            Log::debug("Detected rack type: " . ($rackType ?? 'null'));
        }

        $rackInfo["rack_type"] = $rackType;

        if (!$rackType) {
            $msg = "Unknown rack type - unable to detect AudioEffectGroupDevice, InstrumentGroupDevice, or MidiEffectGroupDevice";
            Log::error($msg);
            $rackInfo["parsing_errors"][] = $msg;
            return $rackInfo;
        }

        if ($mainDevice === null) {
            $msg = "Detected rack type $rackType but could not find main device element.";
            Log::error($msg);
            $rackInfo["parsing_errors"][] = $msg;
            return $rackInfo;
        }

        // Parse macro controls with error handling
        try {
            for ($i = 0; $i < 16; $i++) {
                try {
                    $macroNameElem = $mainDevice->xpath("MacroDisplayNames.$i");
                    if (!empty($macroNameElem)) {
                        $macroName = (string)($macroNameElem[0]['Value'] ?? "Macro " . ($i + 1));
                        if ($macroName && $macroName !== "Macro " . ($i + 1)) { // Only listed if named
                            $macroControlElem = $mainDevice->xpath("MacroControls.$i/Manual");
                            $macroValue = 0.0;
                            if (!empty($macroControlElem)) {
                                $macroValue = (float)($macroControlElem[0]['Value'] ?? 0);
                            }
                            
                            $rackInfo["macro_controls"][] = [
                                "name" => $macroName,
                                "value" => $macroValue,
                                "index" => $i
                            ];
                        }
                    }
                } catch (Exception $e) {
                    $errorMsg = "Error parsing macro control $i: " . $e->getMessage();
                    Log::warning($errorMsg);
                    $rackInfo["parsing_warnings"][] = $errorMsg;
                }
            }
        } catch (Exception $e) {
            $errorMsg = "Error parsing macro controls: " . $e->getMessage();
            Log::error($errorMsg);
            $rackInfo["parsing_errors"][] = $errorMsg;
        }

        // Process branch presets (chains)
        $branchPresetsElem = $root->xpath('.//GroupDevicePreset/BranchPresets');
        if (!empty($branchPresetsElem)) {
            $branchType = self::getBranchPresetType($rackType);
            if ($branchType) {
                $branches = $branchPresetsElem[0]->xpath($branchType);
                if ($verbose) {
                    Log::debug("Found " . count($branches) . " $branchType elements");
                }
                if (empty($branches)) {
                    $msg = "No chains found - expected $branchType elements";
                    Log::warning($msg);
                    $rackInfo["parsing_warnings"][] = $msg;
                }

                foreach ($branches as $idx => $branch) {
                    try {
                        $chain = self::parseSingleChainBranch($branch, $idx, $verbose, 0);
                        if ($chain) {
                            $rackInfo["chains"][] = $chain;
                        } else {
                            $msgWarn = "Failed to parse chain " . ($idx + 1);
                            Log::warning($msgWarn);
                            $rackInfo["parsing_warnings"][] = $msgWarn;
                        }
                    } catch (Exception $e) {
                        $errorMsg = "Error parsing chain " . ($idx + 1) . ": " . $e->getMessage();
                        Log::error($errorMsg);
                        $rackInfo["parsing_errors"][] = $errorMsg;
                    }
                }
            } else {
                $msg = "Unknown branch preset type for rack type $rackType.";
                Log::error($msg);
                $rackInfo["parsing_errors"][] = $msg;
            }
        } else {
            if ($verbose) {
                Log::info("No BranchPresets element found in XML; rack may not contain chains.");
            }
        }

        return $rackInfo;
    }

    /**
     * Parse individual chain branch (audio, instrument, midi effect).
     */
    public static function parseSingleChainBranch($branchXml, $chainIndex = 0, $verbose = false, $depth = 0) {
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

        // Parse chain name if available
        $nameElem = $branchXml->xpath('Name');
        if (!empty($nameElem)) {
            $nameVal = (string)($nameElem[0]['Value'] ?? '');
            if (!empty($nameVal)) {
                $chain["name"] = $nameVal;
            }
        }

        // Parse solo status
        $isSoloedElem = $branchXml->xpath('IsSoloed');
        if (!empty($isSoloedElem)) {
            $chain["is_soloed"] = (string)($isSoloedElem[0]['Value'] ?? 'false') === 'true';
        }
        
        // Parse key range for instrument racks
        $keyRangeElem = $branchXml->xpath('KeyRange');
        if (!empty($keyRangeElem)) {
            $minKey = $keyRangeElem[0]->xpath('Min');
            $maxKey = $keyRangeElem[0]->xpath('Max');
            if (!empty($minKey) && !empty($maxKey)) {
                $chain["annotations"]["key_range"] = [
                    "low_key" => (int)($minKey[0]['Value'] ?? 0),
                    "high_key" => (int)($maxKey[0]['Value'] ?? 127)
                ];
            }
        }
        
        // Parse velocity range for instrument racks
        $velocityRangeElem = $branchXml->xpath('VelocityRange');
        if (!empty($velocityRangeElem)) {
            $minVel = $velocityRangeElem[0]->xpath('Min');
            $maxVel = $velocityRangeElem[0]->xpath('Max');
            if (!empty($minVel) && !empty($maxVel)) {
                $chain["annotations"]["velocity_range"] = [
                    "low_vel" => (int)($minVel[0]['Value'] ?? 1),
                    "high_vel" => (int)($maxVel[0]['Value'] ?? 127)
                ];
            }
        }

        // Parse devices
        $devicePresets = $branchXml->xpath('DevicePresets');
        if (!empty($devicePresets)) {
            foreach ($devicePresets[0] as $devicePreset) {
                $deviceElem = $devicePreset->xpath('Device');
                if (!empty($deviceElem)) {
                    foreach ($deviceElem[0] as $child) {
                        $deviceInfo = self::parseDevice($child, $devicePreset, $depth + 1, $verbose);
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
     * Parse a device element recursively, including nested racks.
     */
    public static function parseDevice($deviceElem, $parentPreset = null, $depth = 0, $verbose = false) {
        $deviceType = $deviceElem->getName();

        if ($depth > 10) {
            if ($verbose) {
                Log::warning("Max nesting depth reached at $deviceType");
            }
            return null;
        }

        // Get the standard device name from our mapping
        $standardDeviceName = self::$deviceTypeMap[$deviceType] ?? $deviceType;

        // Check for custom name (UserName) - this is what the user renamed the device to
        $userNameElem = $deviceElem->xpath('UserName');
        $customName = null;
        if (!empty($userNameElem) && isset($userNameElem[0]['Value'])) {
            $customName = trim((string)$userNameElem[0]['Value']);
        }

        // Determine display name and preset name
        if ($customName && !empty($customName) && $customName !== $standardDeviceName) {
            // User has renamed the device, use custom name for display
            $displayName = $customName;
            $presetName = $standardDeviceName; // Original device name becomes preset
        } else {
            // No custom name or same as standard, use standard name
            $displayName = $standardDeviceName;
            $presetName = null;
        }

        $deviceInfo = [
            "type" => $deviceType,
            "name" => $displayName,
            "is_on" => true,
            "standard_name" => $standardDeviceName
        ];
        
        // Only add preset_name if it's different from display name
        if ($presetName) {
            $deviceInfo["preset_name"] = $presetName;
        }

        // Device on/off status
        $onElem = $deviceElem->xpath('On/Manual');
        if (!empty($onElem)) {
            $deviceInfo["is_on"] = (string)($onElem[0]['Value'] ?? 'true') === 'true';
        }

        // Handle nested racks (effect, instrument, MIDI effect groups)
        if (in_array($deviceType, ["AudioEffectGroupDevice", "InstrumentGroupDevice", "MidiEffectGroupDevice"])) {
            // Search for branch presets within current device
            $nestedChains = [];

            $branchPresets = $deviceElem->xpath('BranchPresets');
            if (empty($branchPresets) && $parentPreset !== null && $parentPreset->getName() === "GroupDevicePreset") {
                $branchPresets = $parentPreset->xpath('BranchPresets');
                if ($verbose && !empty($branchPresets)) {
                    Log::debug("Found BranchPresets at parent level for $deviceType");
                }
            }

            if (!empty($branchPresets)) {
                $nestedType = self::getBranchPresetType($deviceType);
                $nestedBranches = $branchPresets[0]->xpath($nestedType);
                if ($verbose) {
                    Log::debug("Found " . count($nestedBranches) . " $nestedType in nested $deviceType");
                }
                foreach ($nestedBranches as $idx => $branch) {
                    $nestedChain = self::parseSingleChainBranch($branch, $idx, $verbose, $depth + 1);
                    if ($nestedChain) {
                        $nestedChains[] = $nestedChain;
                    }
                }
            } else {
                if ($verbose) {
                    Log::info("No BranchPresets found for nested $deviceType");
                }
            }

            $deviceInfo["chains"] = $nestedChains;
        }

        return $deviceInfo;
    }

    /**
     * Export analysis to JSON file.
     */
    public static function exportAnalysisToJson($rackInfo, $originalPath, $outputFolder = ".") {
        try {
            $filenameBase = pathinfo(basename($originalPath), PATHINFO_FILENAME);
            $outputPath = rtrim($outputFolder, '/') . "/{$filenameBase}_analysis.json";
            $jsonData = json_encode($rackInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
            if (file_put_contents($outputPath, $jsonData) !== false) {
                Log::info("Analysis exported to: $outputPath");
                return $outputPath;
            } else {
                Log::error("Error writing JSON file: $outputPath");
                return null;
            }
        } catch (Exception $e) {
            Log::error("Error exporting analysis: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Find all .adg files in a directory tree recursively.
     */
    public static function findAdgFiles($directory) {
        $adgFiles = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'adg') {
                $adgFiles[] = $file->getRealPath();
            }
        }
        
        return $adgFiles;
    }
}