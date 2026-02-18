<?php

namespace App\Services;

use App\Models\Rack;
use App\Models\User;
use App\Services\AbletonRackAnalyzer\AbletonRackAnalyzer;
use App\Services\DrumRackAnalyzerService;
use App\Services\D2DiagramService;
use App\Services\EnhancedRackAnalysisService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;

class RackProcessingService
{
    protected AbletonRackAnalyzer $analyzer;
    protected DrumRackAnalyzerService $drumAnalyzer;
    protected D2DiagramService $d2Service;
    protected EnhancedRackAnalysisService $enhancedAnalysisService;

    public function __construct(
        DrumRackAnalyzerService $drumAnalyzer,
        D2DiagramService $d2Service,
        EnhancedRackAnalysisService $enhancedAnalysisService
    ) {
        $this->analyzer = new AbletonRackAnalyzer();
        $this->drumAnalyzer = $drumAnalyzer;
        $this->d2Service = $d2Service;
        $this->enhancedAnalysisService = $enhancedAnalysisService;
    }

    /**
     * Process an uploaded rack file
     */
    public function processRack(UploadedFile $file, User $user, array $metadata = []): Rack
    {
        return DB::transaction(function () use ($file, $user, $metadata) {
            // 1. Store file securely
            $fileInfo = $this->storeRackFile($file);
            
            // 2. Create database record with pending status
            $rack = Rack::create([
                'uuid' => Str::uuid(),
                'user_id' => $user->id,
                'title' => $metadata['title'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'description' => $metadata['description'] ?? null,
                'slug' => $this->generateUniqueSlug($metadata['title'] ?? $file->getClientOriginalName()),
                'file_path' => $fileInfo['path'],
                'file_hash' => $fileInfo['hash'],
                'file_size' => $fileInfo['size'],
                'original_filename' => $file->getClientOriginalName(),
                'rack_type' => 'AudioEffectGroupDevice', // Default, will be updated after analysis
                'status' => 'processing',
                'is_public' => $metadata['is_public'] ?? true,
            ]);
            
            // 3. Process the rack file with analyzer
            try {
                // First check if it's a drum rack
                $isDrumRack = $this->drumAnalyzer->isDrumRack($fileInfo['full_path']);
                
                if ($isDrumRack) {
                    // Use specialized drum rack analyzer
                    $analysisResult = $this->analyzeDrumRackFile($fileInfo['full_path']);
                    
                    if ($analysisResult) {
                        // Update rack with drum rack analysis results
                        $rack->update([
                            'rack_type' => 'drum_rack',
                            'category' => 'drums',
                            'device_count' => $this->countDrumRackDevices($analysisResult),
                            'chain_count' => count($analysisResult['drum_chains'] ?? []),
                            'ableton_version' => $analysisResult['ableton_version'] ?? null,
                            'macro_controls' => $analysisResult['macro_controls'] ?? [],
                            'devices' => $this->convertDrumChainsToDevices($analysisResult['drum_chains'] ?? []),
                            'chains' => $this->convertDrumChainsToChains($analysisResult['drum_chains'] ?? []),
                            'version_details' => $analysisResult['version_details'] ?? [],
                            'parsing_errors' => $analysisResult['parsing_errors'] ?? [],
                            'parsing_warnings' => $analysisResult['parsing_warnings'] ?? [],
                            'status' => empty($analysisResult['parsing_errors']) ? 'approved' : 'pending',
                            'published_at' => empty($analysisResult['parsing_errors']) ? now() : null,
                        ]);
                        
                        // Auto-tag drum racks
                        $this->attachTags($rack, ['drums', 'percussion', 'rhythm']);
                    }
                } else {
                    // Use general rack analyzer
                    $analysisResult = $this->analyzeRackFile($fileInfo['full_path']);
                    
                    if ($analysisResult) {
                        // Update rack with analysis results
                        $rack->update([
                            'rack_type' => $analysisResult['rack_type'] ?? null,
                            'device_count' => count($analysisResult['chains'][0]['devices'] ?? []),
                            'chain_count' => count($analysisResult['chains'] ?? []),
                            'ableton_version' => $analysisResult['ableton_version'] ?? null,
                            'macro_controls' => $analysisResult['macro_controls'] ?? [],
                            'devices' => $analysisResult['chains'] ?? [],
                            'chains' => $analysisResult['chains'] ?? [],
                            'version_details' => $analysisResult['version_details'] ?? [],
                            'parsing_errors' => $analysisResult['parsing_errors'] ?? [],
                            'parsing_warnings' => $analysisResult['parsing_warnings'] ?? [],
                            'analysis_complete' => true,
                            'status' => empty($analysisResult['parsing_errors']) ? 'approved' : 'pending',
                            'published_at' => empty($analysisResult['parsing_errors']) ? now() : null,
                        ]);
                    }
                }
                
                // Process additional tags if provided
                if (!empty($metadata['tags'])) {
                    $this->attachTags($rack, $metadata['tags']);
                }
                
                // Generate D2 diagrams for the analyzed rack
                $this->generateRackDiagrams($rack, $analysisResult ?? [], $isDrumRack ?? false);

                // Perform enhanced nested chain analysis (constitutional requirement)
                $this->performEnhancedAnalysis($rack);

            } catch (Exception $e) {
                $rack->update([
                    'status' => 'failed',
                    'processing_error' => $e->getMessage(),
                ]);
                
                throw $e;
            }
            
            return $rack->fresh();
        });
    }

    /**
     * Analyze a rack file using AbletonRackAnalyzer
     */
    protected function analyzeRackFile(string $filePath): ?array
    {
        $xml = AbletonRackAnalyzer::decompressAndParseAbletonFile($filePath);
        
        if (!$xml) {
            throw new Exception('Failed to decompress or parse the .adg file');
        }
        
        return AbletonRackAnalyzer::parseChainsAndDevices($xml, $filePath);
    }

    /**
     * Analyze a drum rack file using DrumRackAnalyzerService
     */
    protected function analyzeDrumRackFile(string $filePath): ?array
    {
        $result = $this->drumAnalyzer->analyzeDrumRack($filePath, [
            'include_performance' => true,
            'verbose' => false
        ]);
        
        return $result['success'] ? $result['data'] : null;
    }

    /**
     * Count total devices in drum rack chains
     */
    protected function countDrumRackDevices(array $drumRackData): int
    {
        $count = 0;
        foreach ($drumRackData['drum_chains'] ?? [] as $chain) {
            $count += count($chain['devices'] ?? []);
        }
        return $count;
    }

    /**
     * Convert drum chains to standard devices format for backward compatibility
     */
    protected function convertDrumChainsToDevices(array $drumChains): array
    {
        $devices = [];
        foreach ($drumChains as $chain) {
            foreach ($chain['devices'] ?? [] as $device) {
                $devices[] = [
                    'type' => $device['type'] ?? 'Unknown',
                    'name' => $device['name'] ?? 'Unknown Device',
                    'standard_name' => $device['standard_name'] ?? '',
                    'is_on' => $device['is_on'] ?? true,
                    'drum_context' => $device['drum_context'] ?? []
                ];
            }
        }
        return $devices;
    }

    /**
     * Convert drum chains to standard chains format for backward compatibility
     */
    protected function convertDrumChainsToChains(array $drumChains): array
    {
        $chains = [];
        foreach ($drumChains as $chain) {
            $chains[] = [
                'name' => $chain['name'] ?? 'Drum Chain',
                'devices' => $chain['devices'] ?? [],
                'is_soloed' => $chain['is_soloed'] ?? false,
                'chain_index' => $chain['chain_index'] ?? 0,
                'annotations' => [
                    'tags' => $chain['drum_annotations']['tags'] ?? [],
                    'purpose' => $chain['drum_annotations']['drum_type'] ?? null,
                    'key_range' => $chain['drum_annotations']['key_range'] ?? null,
                    'description' => $chain['drum_annotations']['description'] ?? null,
                    'velocity_range' => $chain['drum_annotations']['velocity_range'] ?? null,
                    'midi_note' => $chain['drum_annotations']['midi_note'] ?? null,
                ]
            ];
        }
        return $chains;
    }

    /**
     * Store rack file securely
     */
    protected function storeRackFile(UploadedFile $file): array
    {
        $uuid = Str::uuid();
        $extension = $file->getClientOriginalExtension();
        $path = "racks/{$uuid}.{$extension}";
        
        // Store in private storage
        $storedPath = Storage::disk('private')->putFileAs('racks', $file, "{$uuid}.{$extension}");
        
        // Get full path for analysis
        $fullPath = Storage::disk('private')->path($storedPath);
        
        return [
            'path' => $storedPath,
            'full_path' => $fullPath,
            'hash' => hash_file('sha256', $fullPath),
            'size' => $file->getSize(),
        ];
    }

    /**
     * Generate unique slug for rack
     */
    protected function generateUniqueSlug(string $title): string
    {
        $slug = Str::slug($title);
        $count = 1;
        
        while (Rack::where('slug', $slug)->exists()) {
            $slug = Str::slug($title) . '-' . $count;
            $count++;
        }
        
        return $slug;
    }

    /**
     * Attach tags to rack
     */
    protected function attachTags(Rack $rack, array $tags): void
    {
        $tagIds = [];
        
        foreach ($tags as $tagName) {
            $tag = \App\Models\Tag::firstOrCreate(
                ['slug' => Str::slug($tagName)],
                ['name' => $tagName]
            );
            
            $tagIds[] = $tag->id;
            $tag->increment('usage_count');
        }
        
        $rack->tags()->sync($tagIds);
    }

    /**
     * Check if rack file is duplicate
     */
    public function isDuplicate(string $fileHash): ?Rack
    {
        return Rack::where('file_hash', $fileHash)
            ->where('status', '!=', 'failed')
            ->first();
    }

    /**
     * Generate D2 diagrams for analyzed rack
     */
    protected function generateRackDiagrams(Rack $rack, array $analysisData, bool $isDrumRack): void
    {
        try {
            // Prepare data for D2 transformation
            $rackData = [
                'uuid' => $rack->uuid,
                'title' => $rack->title,
                'rack_type' => $rack->rack_type,
                'devices' => $rack->devices,
                'chains' => $rack->chains,
                'macro_controls' => $rack->macro_controls,
                'analysis' => $analysisData
            ];
            
            if ($isDrumRack) {
                // Generate drum rack specific diagram
                $this->d2Service->generateDrumRackDiagram($rackData, [
                    'style' => 'sketch',
                    'include_tooltips' => true,
                    'show_performance' => true
                ]);
            } else {
                // Generate general rack diagram  
                $this->d2Service->generateRackDiagram($rackData, [
                    'style' => 'technical',
                    'include_tooltips' => true,
                    'show_chains' => true
                ]);
            }
            
            \Log::info("Generated D2 diagrams for rack: {$rack->uuid}");
            
        } catch (Exception $e) {
            \Log::error("Failed to generate D2 diagrams for rack {$rack->uuid}: " . $e->getMessage());
            // Don't fail the entire rack processing if diagram generation fails
        }
    }
    
    /**
     * Get D2 diagram for a rack
     */
    public function getRackDiagram(Rack $rack, string $style = 'sketch', string $format = 'svg'): ?string
    {
        try {
            $rackData = [
                'uuid' => $rack->uuid,
                'title' => $rack->title,
                'rack_type' => $rack->rack_type,
                'devices' => $rack->devices,
                'chains' => $rack->chains,
                'macro_controls' => $rack->macro_controls
            ];
            
            $isDrumRack = $rack->rack_type === 'drum_rack' || $rack->category === 'drums';
            
            // Generate D2 code
            $d2Code = '';
            if ($isDrumRack) {
                $d2Code = $this->d2Service->generateDrumRackDiagram($rackData, [
                    'style' => $style,
                    'format' => $format,
                    'include_tooltips' => true
                ]);
            } else {
                $d2Code = $this->d2Service->generateRackDiagram($rackData, [
                    'style' => $style, 
                    'format' => $format,
                    'include_tooltips' => true
                ]);
            }
            
            // Render to requested format using D2
            return $this->d2Service->renderDiagram($d2Code, $format);
            
        } catch (Exception $e) {
            \Log::error("Failed to get D2 diagram for rack {$rack->uuid}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Generate ASCII diagram for rack (perfect for README files)
     */
    public function getRackAsciiDiagram(Rack $rack): ?string
    {
        return $this->getRackDiagram($rack, 'minimal', 'ascii');
    }

    /**
     * Generate preview for rack (placeholder for future implementation)
     */
    public function generatePreview(Rack $rack): void
    {
        // Future: Generate audio preview
        // This would require integration with audio processing tools
        // or user-uploaded preview files
    }

    /**
     * Perform enhanced nested chain analysis (constitutional requirement)
     */
    protected function performEnhancedAnalysis(Rack $rack): void
    {
        try {
            // Constitutional requirement: ALL CHAINS must be detected and analyzed
            $enhancedResult = $this->enhancedAnalysisService->analyzeRack($rack);

            if ($enhancedResult['analysis_complete']) {
                Log::info('Enhanced nested chain analysis completed during upload', [
                    'rack_uuid' => $rack->uuid,
                    'constitutional_compliant' => $enhancedResult['constitutional_compliant'],
                    'nested_chains_detected' => $enhancedResult['nested_chains_detected'] ?? 0,
                    'analysis_duration_ms' => $enhancedResult['analysis_duration_ms'] ?? 0
                ]);
            } else {
                Log::warning('Enhanced analysis failed during upload but rack processing continues', [
                    'rack_uuid' => $rack->uuid,
                    'error' => $enhancedResult['error'] ?? 'Unknown error'
                ]);
            }
        } catch (Exception $e) {
            // Don't fail the entire upload if enhanced analysis fails
            Log::error('Enhanced analysis failed during upload processing', [
                'rack_uuid' => $rack->uuid,
                'error' => $e->getMessage()
            ]);
        }
    }
}