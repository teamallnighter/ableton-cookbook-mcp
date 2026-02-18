<?php

namespace App\Console\Commands;

use App\Models\Rack;
use App\Services\AbletonRackAnalyzer\AbletonRackAnalyzer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ReanalyzeRacks extends Command
{
    protected $signature = 'racks:reanalyze {--id=* : Specific rack IDs to reanalyze} {--title=* : Specific rack titles to reanalyze} {--all : Reanalyze all racks}';
    protected $description = 'Re-analyze rack files to update chain and device structure';

    public function handle()
    {
        $this->info('Starting rack re-analysis...');
        
        // Determine which racks to process
        $query = Rack::query();
        
        if ($this->option('id')) {
            $query->whereIn('id', $this->option('id'));
        } elseif ($this->option('title')) {
            $titles = $this->option('title');
            $query->where(function($q) use ($titles) {
                foreach ($titles as $title) {
                    $q->orWhere('title', 'like', '%' . $title . '%');
                }
            });
        } elseif ($this->option('all')) {
            // Process all racks
        } else {
            $this->error('Please specify --all, --id, or --title option');
            return 1;
        }
        
        $racks = $query->get();
        $this->info('Found ' . $racks->count() . ' racks to re-analyze');
        
        $successCount = 0;
        $errorCount = 0;
        $bar = $this->output->createProgressBar($racks->count());
        
        foreach ($racks as $rack) {
            $bar->advance();
            
            try {
                // Get the file path
                $filePath = storage_path('app/private/' . $rack->file_path);
                
                if (!file_exists($filePath)) {
                    $this->error("\nFile not found for rack: {$rack->title}");
                    $errorCount++;
                    continue;
                }
                
                // Re-analyze the rack
                $xml = AbletonRackAnalyzer::decompressAndParseAbletonFile($filePath);
                if (!$xml) {
                    $this->error("\nFailed to parse file for rack: {$rack->title}");
                    $errorCount++;
                    continue;
                }
                
                // Get full rack info using the same logic as import
                $typeInfo = AbletonRackAnalyzer::detectRackTypeAndDevice($xml);
                $rackInfo = AbletonRackAnalyzer::parseChainsAndDevices($xml, $rack->original_filename);
                
                // Merge type info with rack info
                $rackInfo['type'] = $typeInfo['rack_type'] ?? null;
                $rackInfo['device_type'] = $typeInfo['device_type'] ?? null;
                
                // Calculate chain and device counts from the actual data
                $chainCount = count($rackInfo['chains'] ?? []);
                $deviceCount = 0;
                
                // Count all devices across all chains (including nested)
                if (!empty($rackInfo['chains'])) {
                    foreach ($rackInfo['chains'] as $chain) {
                        $deviceCount += count($chain['devices'] ?? []);
                        // Also count devices in nested chains
                        foreach ($chain['devices'] ?? [] as $device) {
                            if (isset($device['chains']) && is_array($device['chains'])) {
                                foreach ($device['chains'] as $nestedChain) {
                                    $deviceCount += count($nestedChain['devices'] ?? []);
                                }
                            }
                        }
                    }
                }
                
                // Update the rack with new analysis
                $oldChainCount = $rack->chain_count;
                $oldDeviceCount = $rack->device_count;
                
                $rack->update([
                    'chains' => $rackInfo['chains'] ?? [],
                    'chain_count' => $chainCount,
                    'devices' => $rackInfo['devices'] ?? [],
                    'device_count' => $deviceCount,
                    'macro_controls' => $rackInfo['macro_controls'] ?? [],
                    'parsing_errors' => $rackInfo['parsing_errors'] ?? [],
                    'parsing_warnings' => $rackInfo['parsing_warnings'] ?? []
                ]);
                
                // Log significant changes
                if ($oldChainCount != $rack->chain_count || $oldDeviceCount != $rack->device_count) {
                    $this->info("\n✓ {$rack->title}: Chains {$oldChainCount}→{$rack->chain_count}, Devices {$oldDeviceCount}→{$rack->device_count}");
                }
                
                $successCount++;
                
            } catch (\Exception $e) {
                $this->error("\nError processing rack {$rack->title}: " . $e->getMessage());
                $errorCount++;
            }
        }
        
        $bar->finish();
        
        $this->info("\n\nRe-analysis complete!");
        $this->info("✓ Successfully re-analyzed: $successCount racks");
        if ($errorCount > 0) {
            $this->error("✗ Failed to re-analyze: $errorCount racks");
        }
        
        return 0;
    }
}