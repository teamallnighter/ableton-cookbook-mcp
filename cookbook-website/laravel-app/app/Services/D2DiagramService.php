<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class D2DiagramService
{
    /**
     * Configuration cache
     */
    private ?array $config = null;

    /**
     * Get D2 configuration with environment awareness
     */
    private function getConfig(): array
    {
        if ($this->config === null) {
            $this->config = [
                'binary_path' => config('d2.binary_path', '/usr/local/bin/d2'),
                'temp_path' => config('d2.temp_path', sys_get_temp_dir()),
                'timeout' => config('d2.timeout', 10),
                'cache_enabled' => config('d2.cache_enabled', true),
                'cache_ttl' => config('d2.cache_ttl', 3600),
                'enabled' => config('d2.enabled', true),
                'use_system_path' => config('d2.use_system_path', app()->environment('local')),
            ];
        }
        return $this->config;
    }

    /**
     * Get the D2 binary path based on environment
     */
    private function getD2Binary(): string
    {
        $config = $this->getConfig();

        if ($config['use_system_path']) {
            // Development: Use system PATH
            return 'd2';
        }

        // Production: Use explicit path
        return $config['binary_path'];
    }

    /**
     * Get temp directory for D2 operations
     */
    private function getTempDirectory(): string
    {
        $config = $this->getConfig();
        $tempPath = $config['temp_path'];

        // Ensure the directory exists
        if (!is_dir($tempPath)) {
            mkdir($tempPath, 0755, true);
        }

        return $tempPath;
    }

    /**
     * Check if D2 is available and working
     */
    public function isAvailable(): bool
    {
        try {
            $command = $this->getD2Binary() . ' --version 2>&1';
            exec($command, $output, $returnCode);
            return $returnCode === 0;
        } catch (Exception $e) {
            Log::error('D2 availability check failed: ' . $e->getMessage());
            return false;
        }
    }
    public function generateRackDiagram(array $rackData, array $options = []): string
    {
        $rackName = $rackData['title'] ?? 'Unknown Rack';
        
        // Get actual chain and device data from rack file
        $chainData = $this->getChainData($rackData);
        
        $d2 = "direction: right\n";
        $d2 .= "# {$rackName} Rack\n\n";
        
        if (empty($chainData)) {
            // Fallback for racks without chain data
            $d2 .= "rack: {$rackName} {\n";
            $d2 .= "  style.fill: '#ff6b6b'\n";
            $d2 .= "}\n";
        } else {
            // Create diagram with parallel chains
            $d2 .= "rack: {$rackName} {\n";
            
            // Add each chain
            foreach ($chainData as $i => $chain) {
                $chainName = $this->sanitizeName($chain['name'] ?? "Chain_" . ($i + 1));
                $devices = $chain['devices'] ?? [];
                
                $d2 .= "  {$chainName}: " . ($chain['name'] ?? "Chain " . ($i + 1)) . " {\n";
                
                if (empty($devices)) {
                    $d2 .= "    empty: Empty\n";
                } else {
                    // Track device name counts to make them unique
                    $deviceCounts = [];
                    $uniqueDeviceNames = [];

                    // First pass: create unique identifiers for each device
                    foreach ($devices as $index => $device) {
                        $baseName = $this->sanitizeName($device['name'] ?? 'Unknown');

                        // Count occurrences of this device name
                        if (!isset($deviceCounts[$baseName])) {
                            $deviceCounts[$baseName] = 0;
                        }
                        $deviceCounts[$baseName]++;

                        // Create unique identifier by appending index if there are duplicates
                        if ($deviceCounts[$baseName] > 1) {
                            $uniqueName = $baseName . '_' . $deviceCounts[$baseName];
                        } else {
                            // Check if we'll have duplicates later
                            $duplicateCount = 0;
                            foreach ($devices as $checkDevice) {
                                if (($checkDevice['name'] ?? 'Unknown') === ($device['name'] ?? 'Unknown')) {
                                    $duplicateCount++;
                                }
                            }
                            $uniqueName = $duplicateCount > 1 ? $baseName . '_1' : $baseName;
                        }

                        $uniqueDeviceNames[$index] = $uniqueName;
                    }

                    // Second pass: create the diagram with unique names and handle nested racks
                    $prevDevice = null;
                    foreach ($devices as $index => $device) {
                        $deviceName = $uniqueDeviceNames[$index];
                        $displayName = $device['name'] ?? 'Unknown';

                        // Check if this is a nested rack
                        $isNestedRack = isset($device['is_nested_chain']) && $device['is_nested_chain'] === true;

                        if ($isNestedRack) {
                            // Create a container for the nested rack
                            $d2 .= "    {$deviceName}: {$displayName} {\n";
                            $d2 .= "      style.fill: '#f39c12'\n";
                            $d2 .= "      style.stroke: '#e67e22'\n";
                            $d2 .= "      style.stroke-width: 2\n";

                            // If we have device information for the nested rack, show it
                            if (isset($device['device_count']) && $device['device_count'] > 0) {
                                $d2 .= "      devices: \"{$device['device_count']} devices\"\n";
                            } else {
                                $d2 .= "      content: \"Nested Rack\"\n";
                            }

                            $d2 .= "    }\n";
                        } else {
                            // Regular device
                            $d2 .= "    {$deviceName}: \"{$displayName}\"\n";
                        }

                        if ($prevDevice) {
                            $d2 .= "    {$prevDevice} -> {$deviceName}\n";
                        }
                        $prevDevice = $deviceName;
                    }
                }
                $d2 .= "  }\n";
            }
            
            $d2 .= "}\n\n";
            
            // Add input/output outside the rack (since they're not in JSON)
            $d2 .= "# Signal Flow\n";
            $d2 .= "input: Rack Input {\n";
            $d2 .= "  style.fill: '#2ecc71'\n";
            $d2 .= "}\n\n";
            $d2 .= "output: Rack Output {\n";
            $d2 .= "  style.fill: '#e74c3c'\n";
            $d2 .= "}\n\n";
            
            foreach ($chainData as $i => $chain) {
                $chainName = $this->sanitizeName($chain['name'] ?? "Chain_" . ($i + 1));
                $d2 .= "input -> rack.{$chainName}\n";
                $d2 .= "rack.{$chainName} -> output\n";
            }
        }
        
        return $d2;
    }
    
    private function getChainData(array $rackData): array
    {
        // Try to get from already parsed data (this should now contain Enhanced Analysis data)
        if (!empty($rackData['chains']) && is_array($rackData['chains'])) {
            Log::info('D2 Diagram: Using provided chain data', [
                'chain_count' => count($rackData['chains']),
                'chains' => array_map(function($chain) {
                    return [
                        'name' => $chain['name'] ?? 'Unknown',
                        'device_count' => count($chain['devices'] ?? []),
                        'has_devices' => !empty($chain['devices'])
                    ];
                }, $rackData['chains'])
            ]);
            return $rackData['chains'];
        }

        // If no chains in rack data, try to analyze the file
        if (!empty($rackData['uuid'])) {
            Log::info('D2 Diagram: Falling back to file analysis for UUID: ' . $rackData['uuid']);
            try {
                $rack = \App\Models\Rack::where('uuid', $rackData['uuid'])->first();
                if ($rack && $rack->file_path) {
                    $filePath = storage_path('app/private/' . $rack->file_path);
                    if (file_exists($filePath)) {
                        // First decompress and get XML
                        $xml = \App\Services\AbletonRackAnalyzer\AbletonRackAnalyzer::decompressAndParseAbletonFile($filePath);
                        if ($xml) {
                            // Then parse the chains and devices
                            $result = \App\Services\AbletonRackAnalyzer\AbletonRackAnalyzer::parseChainsAndDevices($xml, $filePath);
                            $chains = $result['chains'] ?? [];
                            Log::info('D2 Diagram: File analysis resulted in chains', [
                                'chain_count' => count($chains)
                            ]);
                            return $chains;
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed to analyze rack file for D2 diagram: ' . $e->getMessage());
            }
        }

        Log::warning('D2 Diagram: No chain data available', [
            'has_rack_chains' => !empty($rackData['chains']),
            'has_uuid' => !empty($rackData['uuid']),
            'rack_data_keys' => array_keys($rackData)
        ]);

        return [];
    }
    
    private function getDeviceData(array $rackData): array
    {
        // Try to get from already parsed data
        if (!empty($rackData['devices'])) {
            return $rackData['devices'];
        }
        
        // If no devices in rack data, try to analyze the file
        if (!empty($rackData['uuid'])) {
            try {
                $rack = \App\Models\Rack::where('uuid', $rackData['uuid'])->first();
                if ($rack && $rack->file_path) {
                    $filePath = storage_path('app/private/' . $rack->file_path);
                    if (file_exists($filePath)) {
                        // First decompress and get XML
                        $xml = \App\Services\AbletonRackAnalyzer\AbletonRackAnalyzer::decompressAndParseAbletonFile($filePath);
                        if ($xml) {
                            // Then parse the chains and devices
                            $result = \App\Services\AbletonRackAnalyzer\AbletonRackAnalyzer::parseChainsAndDevices($xml, $filePath);
                            return $result['chains'][0]['devices'] ?? [];
                        }
                    }
                }
            } catch (\Exception $e) {
                \Log::error("Failed to analyze rack file: " . $e->getMessage());
            }
        }
        
        return [];
    }
    
    private function sanitizeName(string $name): string
    {
        // Convert device name to valid D2 identifier
        return preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
    }
    
    private function getDeviceColor(string $deviceName): string
    {
        $name = strtolower($deviceName);
        
        if (strpos($name, 'eq') !== false) return '#96CEB4';
        if (strpos($name, 'compressor') !== false) return '#45B7D1';
        if (strpos($name, 'reverb') !== false) return '#48CAE4';
        if (strpos($name, 'delay') !== false) return '#FECA57';
        if (strpos($name, 'distortion') !== false || strpos($name, 'overdrive') !== false) return '#FF9F43';
        if (strpos($name, 'filter') !== false) return '#A55EEA';
        if (strpos($name, 'bass') !== false || strpos($name, 'operator') !== false) return '#FF6B6B';
        
        return '#4ECDC4'; // Default device color
    }

    public function generateDrumRackDiagram(array $rackData, array $options = []): string
    {
        $rackName = $rackData['title'] ?? 'Unknown Drum Rack';
        
        $d2 = "# {$rackName} Drum Rack\n\n";
        $d2 .= "drumrack: {$rackName} {\n";
        $d2 .= "  style.fill: '#4ecdc4'\n";
        $d2 .= "}\n";
        
        return $d2;
    }

    public function generateAsciiDiagram(array $rackData): string
    {
        $rackName = $rackData['title'] ?? 'Unknown Rack';
        $chainData = $this->getChainData($rackData);

        if (empty($chainData)) {
            return "┌─────────────────────────────────────┐\n│  {$rackName}\n│  (No devices found)\n└─────────────────────────────────────┘";
        }

        $ascii = '';
        $ascii .= "┌─ {$rackName} " . str_repeat('─', max(0, 40 - strlen($rackName))) . "┐\n";

        foreach ($chainData as $chainIndex => $chain) {
            $chainName = $chain['name'] ?? "Chain " . ($chainIndex + 1);
            $devices = $chain['devices'] ?? [];

            $ascii .= "│\n";
            $ascii .= "├─ {$chainName}:\n";

            if (empty($devices)) {
                $ascii .= "│   (Empty)\n";
            } else {
                foreach ($devices as $deviceIndex => $device) {
                    $deviceName = $device['name'] ?? 'Unknown Device';
                    $isLast = ($deviceIndex === count($devices) - 1);
                    $connector = $isLast ? '└──' : '├──';
                    $arrow = $isLast ? '' : ' ↓';

                    // Check if this is a nested rack
                    $isNestedRack = isset($device['is_nested_chain']) && $device['is_nested_chain'] === true;

                    if ($isNestedRack) {
                        $deviceCount = isset($device['device_count']) ? " ({$device['device_count']} devices)" : '';
                        $ascii .= "│   {$connector} 📁 {$deviceName}{$deviceCount} [NESTED RACK]{$arrow}\n";
                    } else {
                        $ascii .= "│   {$connector} {$deviceName}{$arrow}\n";
                    }
                }
            }
        }

        $ascii .= "└" . str_repeat('─', 44) . "┘";

        return $ascii;
    }

    public function renderDiagram(string $d2Code, string $format = 'svg'): ?string
    {
        $config = $this->getConfig();

        // Check if D2 is enabled
        if (!$config['enabled']) {
            Log::warning('D2 rendering requested but D2 is disabled');
            return null;
        }

        // Check cache if enabled
        if ($config['cache_enabled']) {
            $cacheKey = 'd2:' . md5($d2Code . $format);
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                Log::debug('D2 diagram served from cache', ['key' => $cacheKey]);
                return $cached;
            }
        }

        try {
            $tempDir = $this->getTempDirectory();
            $tempFile = tempnam($tempDir, 'd2_');
            file_put_contents($tempFile . '.d2', $d2Code);

            $d2Binary = $this->getD2Binary();
            $timeout = $config['timeout'];

            // Handle ASCII format specially - use stdout
            if ($format === 'ascii') {
                $command = "timeout {$timeout} {$d2Binary} --layout=elk --stdout-format ascii {$tempFile}.d2 - 2>&1";

                exec($command, $output, $returnCode);
                unlink($tempFile . '.d2');

                if ($returnCode === 0) {
                    // Remove the "success:" line that D2 adds at the end
                    $result = implode("\n", $output);
                    $result = preg_replace('/\nsuccess:.*$/m', '', $result);
                    $result = trim($result);

                    // Cache the result
                    if ($config['cache_enabled'] && $result) {
                        Cache::put($cacheKey, $result, $config['cache_ttl']);
                    }

                    return $result;
                } elseif ($returnCode === 124) {
                    Log::error('D2 rendering timeout', ['format' => $format, 'timeout' => $timeout]);
                }
            } else {
                // Handle other formats with file output
                $outputFile = $tempFile . '.' . $format;
                $command = "timeout {$timeout} {$d2Binary} --layout=elk {$tempFile}.d2 {$outputFile} 2>&1";

                exec($command, $output, $returnCode);

                if ($returnCode === 0 && file_exists($outputFile)) {
                    $result = file_get_contents($outputFile);
                    unlink($tempFile . '.d2');
                    unlink($outputFile);

                    // Cache the result
                    if ($config['cache_enabled'] && $result) {
                        Cache::put($cacheKey, $result, $config['cache_ttl']);
                    }

                    return $result;
                } elseif ($returnCode === 124) {
                    Log::error('D2 rendering timeout', ['format' => $format, 'timeout' => $timeout]);
                }

                if (file_exists($tempFile . '.d2')) {
                    unlink($tempFile . '.d2');
                }
            }

            Log::error('D2 rendering failed', [
                'return_code' => $returnCode,
                'output' => $output,
                'format' => $format,
                'binary' => $d2Binary
            ]);

            return null;
        } catch (Exception $e) {
            Log::error('D2 rendering failed: ' . $e->getMessage());
            return null;
        }
    }
}