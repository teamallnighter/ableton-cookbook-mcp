<?php

namespace App\Console\Commands;

use App\Models\Rack;
use App\Services\AbletonEditionDetector;
use App\Services\AbletonRackAnalyzer\AbletonRackAnalyzer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class UpdateRackEditions extends Command
{
    protected $signature = 'racks:update-editions {--force : Force update all racks}';
    protected $description = 'Update rack editions based on their device requirements';

    public function handle()
    {
        $force = $this->option('force');
        
        $query = Rack::query();
        if (!$force) {
            $query->whereNull('ableton_edition');
        }
        
        $racks = $query->get();
        $this->info("Processing {$racks->count()} racks...");
        
        $updated = 0;
        $errors = 0;
        
        foreach ($racks as $rack) {
            try {
                $this->line("Processing: {$rack->title}");
                
                // Get devices from stored data or re-analyze if needed
                $devices = $this->getDevicesFromRack($rack);
                
                if (empty($devices)) {
                    $this->warn("  No devices found - skipping");
                    continue;
                }
                
                $requiredEdition = AbletonEditionDetector::detectRequiredEdition($devices);
                
                if ($force || is_null($rack->ableton_edition)) {
                    $rack->update(['ableton_edition' => $requiredEdition]);
                    $this->info("  Updated to: {$requiredEdition}");
                    $updated++;
                } else {
                    $this->line("  Already has edition: {$rack->ableton_edition}");
                }
                
            } catch (\Exception $e) {
                $this->error("  Error processing {$rack->title}: " . $e->getMessage());
                $errors++;
            }
        }
        
        $this->info("\nCompleted! Updated: {$updated}, Errors: {$errors}");
    }
    
    private function getDevicesFromRack(Rack $rack): array
    {
        // First try to get devices from stored JSON
        if ($rack->devices) {
            $devices = json_decode($rack->devices, true);
            if (is_array($devices) && !empty($devices)) {
                return $devices;
            }
        }
        
        // If no stored devices, try to re-analyze the .adg file
        if ($rack->file_path && Storage::disk('private')->exists($rack->file_path)) {
            try {
                $filePath = Storage::disk('private')->path($rack->file_path);
                $xml = AbletonRackAnalyzer::decompressAndParseAbletonFile($filePath);
                $analysis = AbletonRackAnalyzer::parseChainsAndDevices($xml, $rack->title, false);
                
                if ($analysis && isset($analysis['chains']) && is_array($analysis['chains'])) {
                    // Extract devices from chains
                    $devices = [];
                    foreach ($analysis['chains'] as $chain) {
                        if (isset($chain['devices']) && is_array($chain['devices'])) {
                            $devices = array_merge($devices, $chain['devices']);
                        }
                    }
                    
                    if (!empty($devices)) {
                        // Update the rack with device data for future use
                        $rack->update(['devices' => json_encode($devices)]);
                        return $devices;
                    }
                }
            } catch (\Exception $e) {
                $this->warn("  Could not analyze file: " . $e->getMessage());
            }
        }
        
        return [];
    }
}
