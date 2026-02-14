<?php

namespace App\Services\AbletonDrumRackAnalyzer;

use Exception;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Illuminate\Support\Facades\Log;

/**
 * Ableton Drum Rack Analyzer - Specialized for Drum Racks
 * Analyzes Ableton drum rack files (.adg files) with focus on drum-specific features.
 * Based on AbletonRackAnalyzer-v3 but optimized for drum rack analysis.
 */
class AbletonDrumRackAnalyzer
{

    // Drum-specific device type mapping for display names
    private static $drumDeviceTypeMap = [
        // Drum Rack Core
        "DrumRack" => "Drum Rack",
        "DrumGroupDevice" => "Drum Rack",
        "InstrumentGroupDevice" => "Instrument Rack", // For nested racks
        
        // Drum Synthesizers (Live 12+ additions)
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
        "DSClang" => "DS Clang",
        "DSClap" => "DS Clap",
        "DSCymbal" => "DS Cymbal",
        "DSFM" => "DS FM",
        "DSHH" => "DS HH",
        "DSKick" => "DS Kick",
        "DSSnare" => "DS Snare",
        "DSTom" => "DS Tom",
        
        // Sample-based Instruments (common in drum racks)
        "Sampler" => "Sampler",
        "Simpler" => "Simpler",
        "Impulse" => "Impulse",
        "DrumSampler" => "Drum Sampler",
        
        // Common Audio Effects in Drum Processing
        "Compressor2" => "Compressor",
        "Compressor" => "Compressor",
        "DrumBuss" => "Drum Buss",
        "GlueCompressor" => "Glue Compressor",
        "MultibandDynamics" => "Multiband Dynamics",
        "Eq8" => "EQ Eight",
        "EQEight" => "EQ Eight",
        "FilterEQ3" => "EQ Three",
        "Eq3" => "EQ Three",
        "Saturator" => "Saturator",
        "Overdrive" => "Overdrive",
        "Gate" => "Gate",
        "Limiter" => "Limiter",
        "Utility" => "Utility",
        "Reverb" => "Reverb",
        "Delay" => "Delay",
        "Echo" => "Echo",
        "AutoFilter" => "Auto Filter",
        "Redux" => "Redux",
        "Erosion" => "Erosion",
        
        // Pan and Spatial Effects
        "AutoPan" => "Auto Pan",
        "SurroundPanner" => "Surround Panner",
        
        // Modulation Effects
        "LFO" => "LFO",
        "EnvelopeFollower" => "Envelope Follower",
        "Shaper" => "Shaper",
        
        // Pitch Effects
        "PitchHack" => "Pitch Hack",
        "Shifter" => "Shifter",
        "FrequencyShifter" => "Frequency Shifter",
        
        // Group Devices
        "AudioEffectGroupDevice" => "Audio Effect Rack",
        "MidiEffectGroupDevice" => "MIDI Effect Rack",
        
        // External Devices
        "ExternalInstrument" => "External Instrument",
        "ExternalAudioEffect" => "External Audio Effect",
        
        // MIDI Effects (common with drum programming)
        "Arpeggiator" => "Arpeggiator",
        "Scale" => "Scale",
        "Velocity" => "Velocity",
        "Random" => "Random",
        "NoteLength" => "Note Length",
        "Pitch" => "Pitch",
        
        // CV devices (Live 12)
        "CVInstrument" => "CV Instrument",
        "CVLFO" => "CV LFO",
        "CVUtility" => "CV Utility",
        
        // Legacy or variant naming
        "Operator" => "Operator",
        "Bass" => "Bass",
        "Analog" => "Analog",
        "Wavetable" => "Wavetable",
    ];

    // Drum rack specific branch preset type map
    private static $drumBranchTypeMap = [
        "DrumGroupDevice" => "InstrumentBranchPreset",
        "InstrumentGroupDevice" => "InstrumentBranchPreset",
        "AudioEffectGroupDevice" => "AudioEffectBranchPreset",
        "MidiEffectGroupDevice" => "MidiEffectBranchPreset"
    ];

    // Standard drum rack pad mapping (C1 = 36, etc.)
    private static $drumPadMapping = [
        36 => "C1 (Kick)",
        37 => "C#1", 38 => "D1 (Snare)", 39 => "D#1", 
        40 => "E1 (Snare Alt)", 41 => "F1", 42 => "F#1 (Hi-Hat Closed)", 43 => "G1",
        44 => "G#1 (Hi-Hat Pedal)", 45 => "A1", 46 => "A#1 (Hi-Hat Open)", 47 => "B1",
        48 => "C2", 49 => "C#2 (Crash)", 50 => "D2", 51 => "D#2 (Ride)",
        52 => "E2", 53 => "F2", 54 => "F#2", 55 => "G2",
        56 => "G#2", 57 => "A2 (Crash 2)", 58 => "A#2", 59 => "B2 (Ride 2)"
    ];

    /**
     * Decompress a gzip compressed Ableton file and parse its XML content.
     * Enhanced with drum rack specific validation.
     */
    public static function decompressAndParseAbletonFile($filePath)
    {
        try {
            // Check if file exists and is readable
            if (!file_exists($filePath)) {
                Log::error("Drum rack file does not exist: $filePath");
                return null;
            }

            if (!is_readable($filePath)) {
                Log::error("Drum rack file is not readable: $filePath");
                return null;
            }

            // Check file size (avoid extremely large files)
            $fileSize = filesize($filePath);
            if ($fileSize > 100 * 1024 * 1024) { // 100MB limit
                Log::warning("Drum rack file is too large ($fileSize bytes): $filePath");
                return null;
            } elseif ($fileSize < 100) { // Too small to be a valid drum rack
                Log::warning("Drum rack file is too small ($fileSize bytes): $filePath");
                return null;
            }

            // Try to decompress using gzfile
            $xmlContent = @file_get_contents("compress.zlib://$filePath");

            if ($xmlContent === false) {
                Log::error("Failed to decompress drum rack file: $filePath");
                return null;
            }

            // Basic XML validation
            if (empty($xmlContent)) {
                Log::error("Empty drum rack file after decompression: $filePath");
                return null;
            }

            // Check if it looks like XML
            $trimmedContent = ltrim($xmlContent);
            if (!str_starts_with($trimmedContent, '<?xml') && !str_starts_with($trimmedContent, '<')) {
                Log::error("Drum rack file does not appear to contain XML: $filePath");
                return null;
            }

            // Parse XML
            $xml = @simplexml_load_string($xmlContent);
            if ($xml === false) {
                Log::error("XML parsing error in drum rack file: $filePath");
                return null;
            }

            // Basic structure validation
            if (!$xml) {
                Log::error("Failed to parse XML root element in drum rack file: $filePath");
                return null;
            }

            // Check if it looks like an Ableton file
            $rootTag = $xml->getName();
            if (!in_array($rootTag, ['Ableton', 'GroupDevicePreset', 'PresetRef'])) {
                Log::warning("Root element '$rootTag' doesn't look like Ableton format in drum rack file: $filePath");
            }

            // Additional drum rack validation
            $drumRackFound = !empty($xml->xpath('.//DrumRack')) || !empty($xml->xpath('.//DrumGroupDevice'));
            if (!$drumRackFound) {
                Log::info("No drum rack detected in file, but proceeding with analysis: $filePath");
            }

            return $xml;
        } catch (Exception $e) {
            Log::error("Unexpected error processing drum rack file $filePath: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Detects and returns the type and main device element for a drum rack.
     * Enhanced to specifically detect drum racks first.
     */
    public static function detectDrumRackTypeAndDevice($root)
    {
        // First, specifically look for drum rack devices
        $drumRack = $root->xpath('.//DrumRack');
        if (!empty($drumRack)) {
            return ['DrumRack', $drumRack[0]];
        }

        // Look for DrumGroupDevice
        $drumGroupDevice = $root->xpath('.//DrumGroupDevice');
        if (!empty($drumGroupDevice)) {
            return ['DrumGroupDevice', $drumGroupDevice[0]];
        }

        // Find GroupDevicePreset with drum rack
        $groupPreset = $root->xpath('.//GroupDevicePreset');
        if (!empty($groupPreset)) {
            $device = $groupPreset[0]->xpath('Device');
            if (!empty($device)) {
                foreach ($device[0] as $child) {
                    $childName = $child->getName();
                    if (array_key_exists($childName, self::$drumBranchTypeMap)) {
                        return [$childName, $child];
                    }
                }
            }
        }

        // Fallback: look directly for known device types
        foreach (array_keys(self::$drumBranchTypeMap) as $rackType) {
            $device = $root->xpath(".//$rackType");
            if (!empty($device)) {
                return [$rackType, $device[0]];
            }
        }

        return [null, null];
    }

    /**
     * Map rack type to branch preset type for drum racks.
     */
    public static function getDrumBranchPresetType($rackType)
    {
        return self::$drumBranchTypeMap[$rackType] ?? null;
    }

    /**
     * Extract drum rack name from XML structure.
     */
    public static function extractDrumRackName($root)
    {
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
    public static function extractAbletonVersionInfo($root)
    {
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

        return $versionInfo;
    }

    /**
     * Main function to parse a drum rack structure and its chains/devices.
     * Enhanced with drum-specific analysis.
     */
    public static function parseDrumRackChainsAndDevices($root, $filename = null, $verbose = false)
    {
        // Extract drum rack name from XML structure first, fall back to filename
        $drumRackName = self::extractDrumRackName($root);
        if (!$drumRackName && $filename) {
            $drumRackName = pathinfo(basename($filename), PATHINFO_FILENAME);
        } elseif (!$drumRackName) {
            $drumRackName = "Unknown Drum Rack";
        }

        // Extract version information
        $versionInfo = self::extractAbletonVersionInfo($root);

        $drumRackInfo = [
            "drum_rack_name" => $drumRackName,
            "rack_type" => "drum_rack", // Always drum rack
            "use_case" => $drumRackName,
            "macro_controls" => [],
            "drum_chains" => [],
            "pad_mappings" => [],
            "sample_info" => [],
            "parsing_errors" => [],
            "parsing_warnings" => [],
            "ableton_version" => $versionInfo["ableton_version"],
            "version_details" => $versionInfo,
            "drum_statistics" => [
                "total_pads" => 0,
                "active_pads" => 0,
                "chained_pads" => 0,
                "sample_based_pads" => 0,
                "synthesized_pads" => 0
            ]
        ];

        list($rackType, $mainDevice) = self::detectDrumRackTypeAndDevice($root);
        if ($verbose) {
            Log::debug("Detected drum rack type: " . ($rackType ?? 'null'));
        }

        $drumRackInfo["detected_rack_type"] = $rackType;

        if (!$rackType) {
            $msg = "Unknown drum rack type - unable to detect DrumRack, DrumGroupDevice, or InstrumentGroupDevice";
            Log::error($msg);
            $drumRackInfo["parsing_errors"][] = $msg;
            return $drumRackInfo;
        }

        if ($mainDevice === null) {
            $msg = "Detected drum rack type $rackType but could not find main device element.";
            Log::error($msg);
            $drumRackInfo["parsing_errors"][] = $msg;
            return $drumRackInfo;
        }

        // Parse macro controls with error handling
        try {
            for ($i = 0; $i < 8; $i++) { // Drum racks typically use 8 macros
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

                            $drumRackInfo["macro_controls"][] = [
                                "name" => $macroName,
                                "value" => $macroValue,
                                "index" => $i
                            ];
                        }
                    }
                } catch (Exception $e) {
                    $errorMsg = "Error parsing macro control $i: " . $e->getMessage();
                    Log::warning($errorMsg);
                    $drumRackInfo["parsing_warnings"][] = $errorMsg;
                }
            }
        } catch (Exception $e) {
            $errorMsg = "Error parsing macro controls: " . $e->getMessage();
            Log::error($errorMsg);
            $drumRackInfo["parsing_errors"][] = $errorMsg;
        }

        // Process drum rack branch presets (drum chains)
        $branchPresetsElem = $root->xpath('.//GroupDevicePreset/BranchPresets');
        if (!empty($branchPresetsElem)) {
            $branchType = self::getDrumBranchPresetType($rackType);
            if ($branchType) {
                $branches = $branchPresetsElem[0]->xpath($branchType);
                if ($verbose) {
                    Log::debug("Found " . count($branches) . " $branchType elements in drum rack");
                }
                if (empty($branches)) {
                    $msg = "No drum chains found - expected $branchType elements";
                    Log::warning($msg);
                    $drumRackInfo["parsing_warnings"][] = $msg;
                }

                foreach ($branches as $idx => $branch) {
                    try {
                        $drumChain = self::parseSingleDrumChain($branch, $idx, $verbose, 0);
                        if ($drumChain) {
                            $drumRackInfo["drum_chains"][] = $drumChain;
                            
                            // Update statistics
                            $drumRackInfo["drum_statistics"]["total_pads"]++;
                            if (!empty($drumChain["devices"])) {
                                $drumRackInfo["drum_statistics"]["active_pads"]++;
                                if (count($drumChain["devices"]) > 1) {
                                    $drumRackInfo["drum_statistics"]["chained_pads"]++;
                                }
                                
                                // Analyze device types for statistics
                                foreach ($drumChain["devices"] as $device) {
                                    if (in_array($device["type"], ["Sampler", "Simpler", "Impulse", "DrumSampler"])) {
                                        $drumRackInfo["drum_statistics"]["sample_based_pads"]++;
                                        break;
                                    } elseif (in_array($device["type"], ["Kick", "Snare", "HiHat", "Cymbal", "Tom", "Clap", "BassDrum", "FMDrum", "Perc"])) {
                                        $drumRackInfo["drum_statistics"]["synthesized_pads"]++;
                                        break;
                                    }
                                }
                            }
                        } else {
                            $msgWarn = "Failed to parse drum chain " . ($idx + 1);
                            Log::warning($msgWarn);
                            $drumRackInfo["parsing_warnings"][] = $msgWarn;
                        }
                    } catch (Exception $e) {
                        $errorMsg = "Error parsing drum chain " . ($idx + 1) . ": " . $e->getMessage();
                        Log::error($errorMsg);
                        $drumRackInfo["parsing_errors"][] = $errorMsg;
                    }
                }
            } else {
                $msg = "Unknown branch preset type for drum rack type $rackType.";
                Log::error($msg);
                $drumRackInfo["parsing_errors"][] = $msg;
            }
        } else {
            if ($verbose) {
                Log::info("No BranchPresets element found in XML; drum rack may not contain chains.");
            }
        }

        return $drumRackInfo;
    }

    /**
     * Parse individual drum chain (enhanced for drum-specific features).
     */
    public static function parseSingleDrumChain($branchXml, $chainIndex = 0, $verbose = false, $depth = 0)
    {
        $drumChain = [
            "name" => "Drum Pad " . ($chainIndex + 1),
            "is_soloed" => false,
            "devices" => [],
            "chain_index" => $chainIndex,
            "drum_annotations" => [
                "description" => null,
                "drum_type" => null, // kick, snare, hi-hat, etc.
                "key_range" => null,
                "velocity_range" => null,
                "midi_note" => null,
                "pad_name" => null,
                "tags" => []
            ]
        ];

        // Parse chain name if available
        $nameElem = $branchXml->xpath('Name');
        if (!empty($nameElem)) {
            $nameVal = (string)($nameElem[0]['Value'] ?? '');
            if (!empty($nameVal)) {
                $drumChain["name"] = $nameVal;
                $drumChain["drum_annotations"]["pad_name"] = $nameVal;
            }
        }

        // Parse solo status
        $isSoloedElem = $branchXml->xpath('IsSoloed');
        if (!empty($isSoloedElem)) {
            $drumChain["is_soloed"] = (string)($isSoloedElem[0]['Value'] ?? 'false') === 'true';
        }

        // Parse key range for drum pads (critical for drum racks)
        $keyRangeElem = $branchXml->xpath('KeyRange');
        if (!empty($keyRangeElem)) {
            $minKey = $keyRangeElem[0]->xpath('Min');
            $maxKey = $keyRangeElem[0]->xpath('Max');
            if (!empty($minKey) && !empty($maxKey)) {
                $lowKey = (int)($minKey[0]['Value'] ?? 0);
                $highKey = (int)($maxKey[0]['Value'] ?? 127);
                
                $drumChain["drum_annotations"]["key_range"] = [
                    "low_key" => $lowKey,
                    "high_key" => $highKey
                ];
                
                // If it's a single key, record MIDI note and try to identify drum type
                if ($lowKey === $highKey) {
                    $drumChain["drum_annotations"]["midi_note"] = $lowKey;
                    if (isset(self::$drumPadMapping[$lowKey])) {
                        $drumChain["drum_annotations"]["drum_type"] = self::$drumPadMapping[$lowKey];
                    }
                }
            }
        }

        // Parse velocity range for drum sensitivity
        $velocityRangeElem = $branchXml->xpath('VelocityRange');
        if (!empty($velocityRangeElem)) {
            $minVel = $velocityRangeElem[0]->xpath('Min');
            $maxVel = $velocityRangeElem[0]->xpath('Max');
            if (!empty($minVel) && !empty($maxVel)) {
                $drumChain["drum_annotations"]["velocity_range"] = [
                    "low_vel" => (int)($minVel[0]['Value'] ?? 1),
                    "high_vel" => (int)($maxVel[0]['Value'] ?? 127)
                ];
            }
        }

        // Parse devices in drum chain
        $devicePresets = $branchXml->xpath('DevicePresets');
        if (!empty($devicePresets)) {
            foreach ($devicePresets[0] as $devicePreset) {
                $deviceElem = $devicePreset->xpath('Device');
                if (!empty($deviceElem)) {
                    foreach ($deviceElem[0] as $child) {
                        $deviceInfo = self::parseDrumDevice($child, $devicePreset, $depth + 1, $verbose);
                        if ($deviceInfo) {
                            $drumChain["devices"][] = $deviceInfo;
                        }
                    }
                }
            }
        }

        return $drumChain;
    }

    /**
     * Parse a device element in drum context (enhanced for drum-specific devices).
     */
    public static function parseDrumDevice($deviceElem, $parentPreset = null, $depth = 0, $verbose = false)
    {
        $deviceType = $deviceElem->getName();

        if ($depth > 10) {
            if ($verbose) {
                Log::warning("Max nesting depth reached at drum device $deviceType");
            }
            return null;
        }

        // Get the drum-specific device name from our mapping
        $standardDeviceName = self::$drumDeviceTypeMap[$deviceType] ?? $deviceType;

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
            "standard_name" => $standardDeviceName,
            "drum_context" => [
                "is_drum_synthesizer" => in_array($deviceType, ["Kick", "Snare", "HiHat", "Cymbal", "Tom", "Clap", "BassDrum", "FMDrum", "Perc", "DSKick", "DSSnare", "DSHH", "DSCymbal", "DSTom", "DSClap", "DSFM", "DSAnalog"]),
                "is_sampler" => in_array($deviceType, ["Sampler", "Simpler", "Impulse", "DrumSampler"]),
                "is_drum_effect" => in_array($deviceType, ["DrumBuss", "GlueCompressor", "Compressor", "Compressor2"]),
            ]
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

        // Handle nested racks within drum racks
        if (in_array($deviceType, ["AudioEffectGroupDevice", "InstrumentGroupDevice", "MidiEffectGroupDevice", "DrumGroupDevice"])) {
            // Search for branch presets within current device
            $nestedChains = [];

            $branchPresets = $deviceElem->xpath('BranchPresets');
            if (empty($branchPresets) && $parentPreset !== null && $parentPreset->getName() === "GroupDevicePreset") {
                $branchPresets = $parentPreset->xpath('BranchPresets');
                if ($verbose && !empty($branchPresets)) {
                    Log::debug("Found BranchPresets at parent level for drum device $deviceType");
                }
            }

            if (!empty($branchPresets)) {
                $nestedType = self::getDrumBranchPresetType($deviceType);
                $nestedBranches = $branchPresets[0]->xpath($nestedType);
                if ($verbose) {
                    Log::debug("Found " . count($nestedBranches) . " $nestedType in nested drum device $deviceType");
                }
                foreach ($nestedBranches as $idx => $branch) {
                    $nestedChain = self::parseSingleDrumChain($branch, $idx, $verbose, $depth + 1);
                    if ($nestedChain) {
                        $nestedChains[] = $nestedChain;
                    }
                }
            } else {
                if ($verbose) {
                    Log::info("No BranchPresets found for nested drum device $deviceType");
                }
            }

            $deviceInfo["chains"] = $nestedChains;
        }

        return $deviceInfo;
    }

    /**
     * Export drum rack analysis to JSON file.
     */
    public static function exportDrumRackAnalysisToJson($drumRackInfo, $originalPath, $outputFolder = ".")
    {
        try {
            $filenameBase = pathinfo(basename($originalPath), PATHINFO_FILENAME);
            $outputPath = rtrim($outputFolder, '/') . "/{$filenameBase}_drum_analysis.json";
            $jsonData = json_encode($drumRackInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            if (file_put_contents($outputPath, $jsonData) !== false) {
                Log::info("Drum rack analysis exported to: $outputPath");
                return $outputPath;
            } else {
                Log::error("Error writing drum rack JSON file: $outputPath");
                return null;
            }
        } catch (Exception $e) {
            Log::error("Error exporting drum rack analysis: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Find all .adg files in a directory tree recursively.
     */
    public static function findDrumRackFiles($directory)
    {
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

    /**
     * Analyze drum rack for performance insights.
     */
    public static function analyzeDrumRackPerformance($drumRackInfo)
    {
        $performance = [
            "complexity_score" => 0,
            "recommendations" => [],
            "warnings" => [],
            "optimization_opportunities" => []
        ];

        // Calculate complexity based on chains and devices
        $totalDevices = 0;
        $heavyDevices = 0;
        $chainCount = count($drumRackInfo["drum_chains"] ?? []);

        foreach ($drumRackInfo["drum_chains"] ?? [] as $chain) {
            $deviceCount = count($chain["devices"] ?? []);
            $totalDevices += $deviceCount;

            foreach ($chain["devices"] ?? [] as $device) {
                // Count resource-intensive devices
                if (in_array($device["type"], ["ConvolutionReverb", "HybridReverb", "Wavetable", "Operator"])) {
                    $heavyDevices++;
                }
            }
        }

        // Calculate complexity score (0-100)
        $performance["complexity_score"] = min(100, ($chainCount * 2) + ($totalDevices * 1.5) + ($heavyDevices * 10));

        // Generate recommendations
        if ($performance["complexity_score"] > 70) {
            $performance["recommendations"][] = "High complexity drum rack - consider freezing to audio for better performance";
        }

        if ($heavyDevices > 3) {
            $performance["recommendations"][] = "Multiple CPU-intensive devices detected - consider using simpler alternatives";
        }

        if ($chainCount > 32) {
            $performance["recommendations"][] = "Large number of drum chains - consider splitting into multiple racks";
        }

        return $performance;
    }
}