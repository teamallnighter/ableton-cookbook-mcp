<?php

namespace App\Services;

class AbletonEditionDetector
{
    // Devices that require specific Ableton Live editions
    private array $suiteOnlyDevices = [
        'operator', 'collision', 'simpler', 'sampler', 'analogsynthesis',
        'operator2', 'tension', 'collision2', 'simpler2', 'sampler2'
    ];
    
    private array $standardOrHigherDevices = [
        'eq8', 'compressor2', 'autofilter', 'reverb', 'delay',
        'chorus', 'phaser', 'autopan', 'gate', 'overdrive', 'saturator'
    ];

    public function detectRequiredEdition(array $chains): string
    {
        $allDevices = $this->extractAllDevices($chains);
        
        // Check for Suite-only devices
        foreach ($allDevices as $device) {
            $deviceType = strtolower($device['type'] ?? '');
            
            if (in_array($deviceType, $this->suiteOnlyDevices)) {
                return 'suite';
            }
        }
        
        // Check for Standard or higher devices
        foreach ($allDevices as $device) {
            $deviceType = strtolower($device['type'] ?? '');
            
            if (in_array($deviceType, $this->standardOrHigherDevices)) {
                return 'standard';
            }
        }
        
        // If only basic devices, Intro is sufficient
        return 'intro';
    }
    
    private function extractAllDevices(array $chains): array
    {
        $devices = [];
        
        foreach ($chains as $chain) {
            foreach ($chain['devices'] ?? [] as $device) {
                $devices[] = $device;
                
                // Recursively extract devices from nested racks
                if (isset($device['chains'])) {
                    $nestedDevices = $this->extractAllDevices($device['chains']);
                    $devices = array_merge($devices, $nestedDevices);
                }
            }
        }
        
        return $devices;
    }
}