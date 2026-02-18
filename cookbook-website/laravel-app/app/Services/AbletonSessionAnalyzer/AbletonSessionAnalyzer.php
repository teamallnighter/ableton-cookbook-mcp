<?php

namespace App\Services\AbletonSessionAnalyzer;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Ableton Session Analyzer - Pure PHP
 * Analyzes Ableton session files (.als files) by decompressing and parsing XML structure.
 * Detects embedded racks, presets, samples, and provides comprehensive session metadata.
 */
class AbletonSessionAnalyzer
{
    /**
     * Analyze an Ableton session file
     * 
     * @param string $filePath Path to the .als file
     * @return array Analysis results
     * @throws Exception If file cannot be processed
     */
    public function analyzeSessionFile(string $filePath): array
    {
        try {
            // Validate file exists and is readable
            if (!file_exists($filePath) || !is_readable($filePath)) {
                throw new Exception("Session file not found or not readable: {$filePath}");
            }

            // Get file info
            $fileSize = filesize($filePath);
            $fileName = basename($filePath);

            Log::info("Starting session analysis", [
                'file' => $fileName,
                'size' => $fileSize,
                'path' => $filePath
            ]);

            // Decompress and parse the session
            $xmlContent = $this->decompressSessionFile($filePath);
            $parsedData = $this->parseSessionXml($xmlContent);

            // Extract comprehensive analysis data
            $analysis = [
                'file_info' => [
                    'filename' => $fileName,
                    'size' => $fileSize,
                    'hash' => hash_file('sha256', $filePath),
                ],
                'session_metadata' => $this->extractSessionMetadata($parsedData),
                'track_analysis' => $this->analyzeTrackStructure($parsedData),
                'embedded_racks' => $this->detectEmbeddedRacks($parsedData),
                'embedded_presets' => $this->detectEmbeddedPresets($parsedData),
                'audio_clips' => $this->analyzeAudioClips($parsedData),
                'midi_clips' => $this->analyzeMidiClips($parsedData),
                'external_dependencies' => $this->detectExternalDependencies($parsedData),
                'automation_analysis' => $this->analyzeAutomation($parsedData),
                'arrangement_structure' => $this->analyzeArrangementStructure($parsedData),
                'compatibility' => $this->analyzeCompatibility($parsedData),
            ];

            Log::info("Session analysis completed", [
                'tracks' => $analysis['track_analysis']['total_tracks'] ?? 0,
                'embedded_racks' => count($analysis['embedded_racks']),
                'embedded_presets' => count($analysis['embedded_presets']),
                'audio_clips' => count($analysis['audio_clips']),
                'duration' => $analysis['session_metadata']['duration_seconds'] ?? 0
            ]);

            return $analysis;

        } catch (Exception $e) {
            Log::error("Session analysis failed", [
                'file' => $filePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Decompress .als file (gzipped XML)
     */
    private function decompressSessionFile(string $filePath): string
    {
        try {
            $compressedData = file_get_contents($filePath);
            if ($compressedData === false) {
                throw new Exception("Cannot read session file");
            }

            // .als files are gzipped XML
            $xmlContent = gzuncompress($compressedData);
            if ($xmlContent === false) {
                throw new Exception("Cannot decompress session file - may not be a valid .als file");
            }

            return $xmlContent;

        } catch (Exception $e) {
            throw new Exception("Decompression failed: " . $e->getMessage());
        }
    }

    /**
     * Parse the decompressed XML content
     */
    private function parseSessionXml(string $xmlContent): \SimpleXMLElement
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
     * Extract session metadata
     */
    private function extractSessionMetadata(\SimpleXMLElement $xml): array
    {
        $metadata = [
            'bpm' => null,
            'time_signature' => '4/4',
            'duration_seconds' => null,
            'sample_rate' => 44100,
            'bit_depth' => 16,
            'ableton_version' => null,
            'created_date' => null,
            'last_modified' => null,
            'author' => null,
            'comments' => null,
        ];

        try {
            // Extract tempo (BPM)
            $tempoResult = $xml->xpath('//MasterTrack//Tempo//@Value');
            if (!empty($tempoResult)) {
                $metadata['bpm'] = (float) $tempoResult[0];
            }

            // Extract time signature
            $timeSigResult = $xml->xpath('//TimeSignature//@Numerator');
            $timeSigDenomResult = $xml->xpath('//TimeSignature//@Denominator');
            if (!empty($timeSigResult) && !empty($timeSigDenomResult)) {
                $numerator = (int) $timeSigResult[0];
                $denominator = (int) $timeSigDenomResult[0];
                $metadata['time_signature'] = "{$numerator}/{$denominator}";
            }

            // Extract session duration
            $durationResult = $xml->xpath('//Arrangement//@Duration');
            if (!empty($durationResult)) {
                $metadata['duration_seconds'] = (float) $durationResult[0];
            }

            // Extract sample rate and bit depth
            $sampleRateResult = $xml->xpath('//SampleRate//@Value');
            if (!empty($sampleRateResult)) {
                $metadata['sample_rate'] = (int) $sampleRateResult[0];
            }

            $bitDepthResult = $xml->xpath('//BitDepth//@Value');
            if (!empty($bitDepthResult)) {
                $metadata['bit_depth'] = (int) $bitDepthResult[0];
            }

            // Extract Ableton version
            $versionResult = $xml->xpath('//@Version');
            if (!empty($versionResult)) {
                $metadata['ableton_version'] = (string) $versionResult[0];
            }

            // Extract creation info
            $createdResult = $xml->xpath('//@CreationDate');
            if (!empty($createdResult)) {
                $metadata['created_date'] = (string) $createdResult[0];
            }

            $modifiedResult = $xml->xpath('//@LastModified');
            if (!empty($modifiedResult)) {
                $metadata['last_modified'] = (string) $modifiedResult[0];
            }

            // Extract author/creator
            $authorResult = $xml->xpath('//@Author');
            if (!empty($authorResult)) {
                $metadata['author'] = (string) $authorResult[0];
            }

            // Extract session comments
            $commentsResult = $xml->xpath('//Annotation//@Text');
            if (!empty($commentsResult)) {
                $metadata['comments'] = (string) $commentsResult[0];
            }

        } catch (Exception $e) {
            Log::warning("Session metadata extraction failed", ['error' => $e->getMessage()]);
        }

        return array_filter($metadata, fn($value) => $value !== null);
    }

    /**
     * Analyze track structure
     */
    private function analyzeTrackStructure(\SimpleXMLElement $xml): array
    {
        $trackAnalysis = [
            'total_tracks' => 0,
            'audio_tracks' => 0,
            'midi_tracks' => 0,
            'return_tracks' => 0,
            'group_tracks' => 0,
            'track_details' => []
        ];

        try {
            // Analyze main tracks
            $tracks = $xml->xpath('//Track');
            foreach ($tracks as $index => $track) {
                $trackInfo = $this->analyzeIndividualTrack($track, $index);
                $trackAnalysis['track_details'][] = $trackInfo;
                
                // Count track types
                switch ($trackInfo['type']) {
                    case 'audio':
                        $trackAnalysis['audio_tracks']++;
                        break;
                    case 'midi':
                        $trackAnalysis['midi_tracks']++;
                        break;
                    case 'return':
                        $trackAnalysis['return_tracks']++;
                        break;
                    case 'group':
                        $trackAnalysis['group_tracks']++;
                        break;
                }
            }

            // Analyze return tracks separately
            $returnTracks = $xml->xpath('//ReturnTrack');
            foreach ($returnTracks as $index => $track) {
                $trackInfo = $this->analyzeIndividualTrack($track, $index, 'return');
                $trackAnalysis['track_details'][] = $trackInfo;
                $trackAnalysis['return_tracks']++;
            }

            $trackAnalysis['total_tracks'] = count($trackAnalysis['track_details']);

        } catch (Exception $e) {
            Log::warning("Track structure analysis failed", ['error' => $e->getMessage()]);
        }

        return $trackAnalysis;
    }

    /**
     * Analyze individual track
     */
    private function analyzeIndividualTrack(\SimpleXMLElement $track, int $index, string $forceType = null): array
    {
        $trackInfo = [
            'index' => $index,
            'name' => $this->getTrackName($track),
            'type' => $forceType ?? $this->determineTrackType($track),
            'color' => $this->getTrackColor($track),
            'is_muted' => $this->isTrackMuted($track),
            'is_soloed' => $this->isTrackSoloed($track),
            'devices' => $this->getTrackDevices($track),
            'clips_count' => $this->getTrackClipsCount($track),
            'has_automation' => $this->trackHasAutomation($track),
        ];

        return $trackInfo;
    }

    /**
     * Detect embedded racks within the session
     */
    private function detectEmbeddedRacks(\SimpleXMLElement $xml): array
    {
        $embeddedRacks = [];

        try {
            // Look for rack devices (grouped devices)
            $rackDevices = $xml->xpath('//DeviceChain//GroupDevice | //DeviceChain//RackDevice');
            
            foreach ($rackDevices as $rack) {
                $rackInfo = [
                    'type' => 'rack',
                    'name' => $this->getDeviceName($rack),
                    'location' => $this->getDeviceLocation($rack),
                    'devices' => $this->getRackDevices($rack),
                    'macros' => $this->getRackMacros($rack),
                    'chains' => $this->getRackChains($rack),
                    'hash' => $this->calculateRackHash($rack), // For duplicate detection
                ];

                $embeddedRacks[] = $rackInfo;
            }

        } catch (Exception $e) {
            Log::warning("Embedded racks detection failed", ['error' => $e->getMessage()]);
        }

        return $embeddedRacks;
    }

    /**
     * Detect embedded presets within the session
     */
    private function detectEmbeddedPresets(\SimpleXMLElement $xml): array
    {
        $embeddedPresets = [];

        try {
            // Look for devices with custom presets
            $devices = $xml->xpath('//DeviceChain//Device');
            
            foreach ($devices as $device) {
                $presetInfo = $this->extractDevicePresetInfo($device);
                
                if ($presetInfo && !$this->isDefaultPreset($presetInfo)) {
                    $presetInfo['location'] = $this->getDeviceLocation($device);
                    $embeddedPresets[] = $presetInfo;
                }
            }

        } catch (Exception $e) {
            Log::warning("Embedded presets detection failed", ['error' => $e->getMessage()]);
        }

        return $embeddedPresets;
    }

    /**
     * Analyze audio clips in the session
     */
    private function analyzeAudioClips(\SimpleXMLElement $xml): array
    {
        $audioClips = [];

        try {
            $clips = $xml->xpath('//AudioClip');
            
            foreach ($clips as $clip) {
                $clipInfo = [
                    'name' => (string) ($clip['Name'] ?? 'Unnamed Clip'),
                    'file_path' => $this->getClipFilePath($clip),
                    'duration' => $this->getClipDuration($clip),
                    'start_time' => $this->getClipStartTime($clip),
                    'loop_info' => $this->getClipLoopInfo($clip),
                    'warp_info' => $this->getClipWarpInfo($clip),
                    'sample_rate' => $this->getClipSampleRate($clip),
                    'channels' => $this->getClipChannels($clip),
                ];

                $audioClips[] = $clipInfo;
            }

        } catch (Exception $e) {
            Log::warning("Audio clips analysis failed", ['error' => $e->getMessage()]);
        }

        return $audioClips;
    }

    /**
     * Analyze MIDI clips in the session
     */
    private function analyzeMidiClips(\SimpleXMLElement $xml): array
    {
        $midiClips = [];

        try {
            $clips = $xml->xpath('//MidiClip');
            
            foreach ($clips as $clip) {
                $clipInfo = [
                    'name' => (string) ($clip['Name'] ?? 'Unnamed MIDI Clip'),
                    'duration' => $this->getClipDuration($clip),
                    'start_time' => $this->getClipStartTime($clip),
                    'note_count' => $this->getMidiClipNoteCount($clip),
                    'key_signature' => $this->getMidiClipKeySignature($clip),
                    'velocity_range' => $this->getMidiClipVelocityRange($clip),
                ];

                $midiClips[] = $clipInfo;
            }

        } catch (Exception $e) {
            Log::warning("MIDI clips analysis failed", ['error' => $e->getMessage()]);
        }

        return $midiClips;
    }

    /**
     * Detect external dependencies (plugins, samples, etc.)
     */
    private function detectExternalDependencies(\SimpleXMLElement $xml): array
    {
        $dependencies = [
            'external_plugins' => [],
            'missing_samples' => [],
            'max_for_live_devices' => [],
        ];

        try {
            // Detect external/third-party plugins
            $externalPlugins = $xml->xpath('//ExternalPlugin | //ThirdPartyPlugin');
            foreach ($externalPlugins as $plugin) {
                $dependencies['external_plugins'][] = [
                    'name' => (string) ($plugin['Name'] ?? 'Unknown Plugin'),
                    'version' => (string) ($plugin['Version'] ?? 'Unknown'),
                    'vendor' => (string) ($plugin['Vendor'] ?? 'Unknown'),
                    'format' => (string) ($plugin['Format'] ?? 'VST'),
                ];
            }

            // Detect missing samples
            $missingRefs = $xml->xpath('//FileRef[@Missing="true"]');
            foreach ($missingRefs as $ref) {
                $dependencies['missing_samples'][] = [
                    'path' => (string) ($ref['Path'] ?? 'Unknown'),
                    'name' => (string) ($ref['Name'] ?? 'Unknown'),
                    'type' => (string) ($ref['Type'] ?? 'Unknown'),
                ];
            }

            // Detect Max for Live devices
            $maxDevices = $xml->xpath('//MxDevice | //MaxDevice');
            foreach ($maxDevices as $device) {
                $dependencies['max_for_live_devices'][] = [
                    'name' => $this->getDeviceName($device),
                    'path' => (string) ($device['Path'] ?? 'Unknown'),
                ];
            }

        } catch (Exception $e) {
            Log::warning("External dependencies detection failed", ['error' => $e->getMessage()]);
        }

        return $dependencies;
    }

    /**
     * Analyze automation data
     */
    private function analyzeAutomation(\SimpleXMLElement $xml): array
    {
        $automation = [
            'has_automation' => false,
            'automated_parameters' => 0,
            'automation_lanes' => [],
        ];

        try {
            // Look for automation envelopes
            $envelopes = $xml->xpath('//AutomationEnvelope');
            
            $automation['has_automation'] = count($envelopes) > 0;
            $automation['automated_parameters'] = count($envelopes);

            foreach ($envelopes as $envelope) {
                $automation['automation_lanes'][] = [
                    'parameter' => (string) ($envelope['Target'] ?? 'Unknown'),
                    'points' => $this->getAutomationPoints($envelope),
                ];
            }

        } catch (Exception $e) {
            Log::warning("Automation analysis failed", ['error' => $e->getMessage()]);
        }

        return $automation;
    }

    /**
     * Analyze arrangement structure
     */
    private function analyzeArrangementStructure(\SimpleXMLElement $xml): array
    {
        $structure = [
            'sections' => [],
            'markers' => [],
            'total_bars' => 0,
        ];

        try {
            // Look for arrangement markers/sections
            $markers = $xml->xpath('//Locator');
            foreach ($markers as $marker) {
                $structure['markers'][] = [
                    'name' => (string) ($marker['Name'] ?? 'Unnamed'),
                    'time' => (float) ($marker['Time'] ?? 0),
                ];
            }

            // Calculate total bars from arrangement duration and time signature
            $duration = $xml->xpath('//Arrangement//@Duration');
            $bpm = $xml->xpath('//Tempo//@Value');
            
            if (!empty($duration) && !empty($bpm)) {
                $durationSeconds = (float) $duration[0];
                $beatsPerMinute = (float) $bpm[0];
                $beatsPerSecond = $beatsPerMinute / 60;
                $totalBeats = $durationSeconds * $beatsPerSecond;
                $structure['total_bars'] = ceil($totalBeats / 4); // Assuming 4/4 time
            }

        } catch (Exception $e) {
            Log::warning("Arrangement structure analysis failed", ['error' => $e->getMessage()]);
        }

        return $structure;
    }

    /**
     * Analyze compatibility requirements
     */
    private function analyzeCompatibility(\SimpleXMLElement $xml): array
    {
        return [
            'min_ableton_version' => $this->extractMinAbletonVersion($xml),
            'max_for_live_required' => $this->sessionRequiresMaxForLive($xml),
            'external_plugin_count' => count($xml->xpath('//ExternalPlugin')),
            'missing_sample_count' => count($xml->xpath('//FileRef[@Missing="true"]')),
        ];
    }

    // Helper methods for track analysis
    private function getTrackName(\SimpleXMLElement $track): string
    {
        return (string) ($track['Name'] ?? $track['DisplayName'] ?? 'Unnamed Track');
    }

    private function determineTrackType(\SimpleXMLElement $track): string
    {
        // Determine track type based on XML structure
        $nodeName = $track->getName();
        
        switch ($nodeName) {
            case 'AudioTrack':
                return 'audio';
            case 'MidiTrack':
                return 'midi';
            case 'ReturnTrack':
                return 'return';
            case 'GroupTrack':
                return 'group';
            default:
                return 'unknown';
        }
    }

    private function getTrackColor(\SimpleXMLElement $track): ?string
    {
        $colorResult = $track->xpath('.//@Color');
        return !empty($colorResult) ? (string) $colorResult[0] : null;
    }

    private function isTrackMuted(\SimpleXMLElement $track): bool
    {
        $mutedResult = $track->xpath('.//@Muted');
        return !empty($mutedResult) && (string) $mutedResult[0] === 'true';
    }

    private function isTrackSoloed(\SimpleXMLElement $track): bool
    {
        $soloedResult = $track->xpath('.//@Solo');
        return !empty($soloedResult) && (string) $soloedResult[0] === 'true';
    }

    private function getTrackDevices(\SimpleXMLElement $track): array
    {
        $devices = [];
        $deviceElements = $track->xpath('.//Device');
        
        foreach ($deviceElements as $device) {
            $devices[] = [
                'type' => $this->getDeviceType($device),
                'name' => $this->getDeviceName($device),
                'is_enabled' => $this->isDeviceEnabled($device),
            ];
        }

        return $devices;
    }

    private function getTrackClipsCount(\SimpleXMLElement $track): int
    {
        $clips = $track->xpath('.//AudioClip | .//MidiClip');
        return count($clips);
    }

    private function trackHasAutomation(\SimpleXMLElement $track): bool
    {
        $automation = $track->xpath('.//AutomationEnvelope');
        return count($automation) > 0;
    }

    private function getDeviceName(\SimpleXMLElement $device): string
    {
        return (string) ($device['Name'] ?? $device['DisplayName'] ?? 'Unknown Device');
    }

    private function getDeviceType(\SimpleXMLElement $device): string
    {
        return (string) ($device['Type'] ?? 'Unknown');
    }

    private function getDeviceLocation(\SimpleXMLElement $device): string
    {
        // Try to determine which track this device is on
        $trackName = 'Unknown Track';
        
        // Walk up the XML tree to find the parent track
        $current = $device;
        while ($current) {
            $parent = $current->xpath('..')[0] ?? null;
            if ($parent && in_array($parent->getName(), ['Track', 'AudioTrack', 'MidiTrack', 'ReturnTrack'])) {
                $trackName = $this->getTrackName($parent);
                break;
            }
            $current = $parent;
        }

        return "Track: {$trackName}";
    }

    private function isDeviceEnabled(\SimpleXMLElement $device): bool
    {
        $enabledResult = $device->xpath('.//@On');
        return empty($enabledResult) || (string) $enabledResult[0] === 'true';
    }

    private function getRackDevices(\SimpleXMLElement $rack): array
    {
        $devices = [];
        $deviceElements = $rack->xpath('.//Device');
        
        foreach ($deviceElements as $device) {
            $devices[] = [
                'type' => $this->getDeviceType($device),
                'name' => $this->getDeviceName($device),
            ];
        }

        return $devices;
    }

    private function getRackMacros(\SimpleXMLElement $rack): array
    {
        $macros = [];
        $macroElements = $rack->xpath('.//MacroControls//RemoteableSlot');
        
        foreach ($macroElements as $index => $macro) {
            $macros[] = [
                'index' => $index,
                'name' => (string) ($macro['Name'] ?? "Macro " . ($index + 1)),
                'value' => (float) ($macro['Value'] ?? 0),
            ];
        }

        return $macros;
    }

    private function getRackChains(\SimpleXMLElement $rack): array
    {
        $chains = [];
        $chainElements = $rack->xpath('.//DeviceChain');
        
        foreach ($chainElements as $index => $chain) {
            $chains[] = [
                'index' => $index,
                'name' => (string) ($chain['Name'] ?? "Chain " . ($index + 1)),
                'devices' => $this->getTrackDevices($chain),
            ];
        }

        return $chains;
    }

    private function calculateRackHash(\SimpleXMLElement $rack): string
    {
        // Create a hash based on rack structure for duplicate detection
        $rackString = $rack->asXML();
        return hash('md5', $rackString);
    }

    private function extractDevicePresetInfo(\SimpleXMLElement $device): ?array
    {
        $presetName = $this->getDeviceName($device);
        $deviceType = $this->getDeviceType($device);
        
        if ($presetName && $deviceType) {
            return [
                'device_type' => $deviceType,
                'preset_name' => $presetName,
                'parameters' => $this->getDeviceParameters($device),
            ];
        }

        return null;
    }

    private function isDefaultPreset(array $presetInfo): bool
    {
        // Simple heuristic to detect default presets
        $defaultNames = ['Init', 'Default', 'Basic', 'Empty'];
        
        foreach ($defaultNames as $defaultName) {
            if (stripos($presetInfo['preset_name'], $defaultName) !== false) {
                return true;
            }
        }

        return false;
    }

    private function getDeviceParameters(\SimpleXMLElement $device): array
    {
        $parameters = [];
        $paramElements = $device->xpath('.//Parameter');
        
        foreach ($paramElements as $param) {
            $name = (string) ($param['Name'] ?? '');
            $value = (float) ($param['Value'] ?? 0);
            
            if ($name) {
                $parameters[$name] = $value;
            }
        }

        return $parameters;
    }

    private function getClipFilePath(\SimpleXMLElement $clip): ?string
    {
        $fileRefResult = $clip->xpath('.//FileRef/@Path');
        return !empty($fileRefResult) ? (string) $fileRefResult[0] : null;
    }

    private function getClipDuration(\SimpleXMLElement $clip): ?float
    {
        $durationResult = $clip->xpath('.//@Duration');
        return !empty($durationResult) ? (float) $durationResult[0] : null;
    }

    private function getClipStartTime(\SimpleXMLElement $clip): ?float
    {
        $startResult = $clip->xpath('.//@StartTime');
        return !empty($startResult) ? (float) $startResult[0] : null;
    }

    private function getClipLoopInfo(\SimpleXMLElement $clip): array
    {
        return [
            'is_looped' => !empty($clip->xpath('.//@Loop')),
            'loop_start' => $this->getXPathFloat($clip, './/@LoopStart'),
            'loop_end' => $this->getXPathFloat($clip, './/@LoopEnd'),
        ];
    }

    private function getClipWarpInfo(\SimpleXMLElement $clip): array
    {
        return [
            'is_warped' => !empty($clip->xpath('.//@Warped')),
            'warp_mode' => $this->getXPathString($clip, './/@WarpMode'),
        ];
    }

    private function getClipSampleRate(\SimpleXMLElement $clip): ?int
    {
        $result = $clip->xpath('.//@SampleRate');
        return !empty($result) ? (int) $result[0] : null;
    }

    private function getClipChannels(\SimpleXMLElement $clip): ?int
    {
        $result = $clip->xpath('.//@Channels');
        return !empty($result) ? (int) $result[0] : null;
    }

    private function getMidiClipNoteCount(\SimpleXMLElement $clip): int
    {
        $notes = $clip->xpath('.//Note');
        return count($notes);
    }

    private function getMidiClipKeySignature(\SimpleXMLElement $clip): ?string
    {
        $result = $clip->xpath('.//@KeySignature');
        return !empty($result) ? (string) $result[0] : null;
    }

    private function getMidiClipVelocityRange(\SimpleXMLElement $clip): array
    {
        $velocities = [];
        $notes = $clip->xpath('.//Note/@Velocity');
        
        foreach ($notes as $velocity) {
            $velocities[] = (int) $velocity;
        }

        return [
            'min' => !empty($velocities) ? min($velocities) : 0,
            'max' => !empty($velocities) ? max($velocities) : 127,
            'average' => !empty($velocities) ? array_sum($velocities) / count($velocities) : 64,
        ];
    }

    private function getAutomationPoints(\SimpleXMLElement $envelope): int
    {
        $points = $envelope->xpath('.//AutomationPoint');
        return count($points);
    }

    private function extractMinAbletonVersion(\SimpleXMLElement $xml): string
    {
        $versionResult = $xml->xpath('//@MinVersion | //@Version');
        return !empty($versionResult) ? (string) $versionResult[0] : '9.0';
    }

    private function sessionRequiresMaxForLive(\SimpleXMLElement $xml): bool
    {
        $maxDevices = $xml->xpath('//MxDevice | //MaxDevice');
        return count($maxDevices) > 0;
    }

    // Utility helper methods
    private function getXPathFloat(\SimpleXMLElement $element, string $xpath): ?float
    {
        $result = $element->xpath($xpath);
        return !empty($result) ? (float) $result[0] : null;
    }

    private function getXPathString(\SimpleXMLElement $element, string $xpath): ?string
    {
        $result = $element->xpath($xpath);
        return !empty($result) ? (string) $result[0] : null;
    }

    /**
     * Get supported session file extensions
     */
    public static function getSupportedExtensions(): array
    {
        return ['als'];
    }

    /**
     * Validate if file is a supported session format
     */
    public static function isValidSessionFile(string $filePath): bool
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        return in_array($extension, self::getSupportedExtensions());
    }
}