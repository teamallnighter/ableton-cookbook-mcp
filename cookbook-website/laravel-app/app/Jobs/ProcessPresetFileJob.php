<?php

namespace App\Jobs;

use App\Models\Preset;
use App\Services\AbletonPresetAnalyzer\AbletonPresetAnalyzer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class ProcessPresetFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes timeout
    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Preset $preset
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(AbletonPresetAnalyzer $analyzer): void
    {
        Log::info("Starting preset file processing", [
            'preset_id' => $this->preset->id,
            'file_path' => $this->preset->file_path
        ]);

        try {
            // Update status to processing
            $this->preset->update(['status' => 'processing']);

            // Get the file path on disk
            $filePath = Storage::disk('private')->path($this->preset->file_path);
            
            if (!file_exists($filePath)) {
                throw new Exception("Preset file not found: {$filePath}");
            }

            // Analyze the preset file
            $analysisResult = $analyzer->analyzePresetFile($filePath);

            // Update preset with analysis results
            $updateData = [
                'status' => $analysisResult['success'] ? 'approved' : 'failed',
                'published_at' => $analysisResult['success'] ? now() : null,
            ];

            if ($analysisResult['success']) {
                // Extract analysis data
                $updateData = array_merge($updateData, [
                    'device_name' => $analysisResult['device_name'],
                    'device_type' => $analysisResult['device_type'],
                    'preset_type' => $analysisResult['preset_type'],
                    'ableton_version' => $analysisResult['ableton_version'],
                    'parameters' => $analysisResult['parameters'] ?? [],
                    'macro_mappings' => $analysisResult['macro_mappings'] ?? [],
                    'version_details' => $analysisResult['version_details'] ?? [],
                    'parsing_errors' => [],
                    'parsing_warnings' => [],
                ]);

                Log::info("Preset analysis completed successfully", [
                    'preset_id' => $this->preset->id,
                    'device_name' => $analysisResult['device_name'],
                    'preset_type' => $analysisResult['preset_type']
                ]);
            } else {
                // Store error information
                $updateData['processing_error'] = $analysisResult['error'] ?? 'Unknown analysis error';
                $updateData['parsing_errors'] = [$analysisResult['error'] ?? 'Analysis failed'];

                Log::error("Preset analysis failed", [
                    'preset_id' => $this->preset->id,
                    'error' => $analysisResult['error']
                ]);
            }

            // Update the preset record
            $this->preset->update($updateData);

            // TODO: Generate preview image if needed
            // TODO: Send notification to user about completion
            // TODO: Update search index

        } catch (Exception $e) {
            Log::error("Preset processing job failed", [
                'preset_id' => $this->preset->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Update preset with error status
            $this->preset->update([
                'status' => 'failed',
                'processing_error' => $e->getMessage(),
                'parsing_errors' => [$e->getMessage()],
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error("Preset processing job permanently failed", [
            'preset_id' => $this->preset->id,
            'error' => $exception->getMessage(),
        ]);

        // Update preset with final failure status
        $this->preset->update([
            'status' => 'failed',
            'processing_error' => 'Processing failed after maximum retries: ' . $exception->getMessage(),
        ]);

        // TODO: Send failure notification to user
        // TODO: Alert administrators about persistent failures
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return ['presets', 'file-processing', "preset:{$this->preset->id}"];
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [30, 60, 120]; // Wait 30s, then 60s, then 120s between retries
    }
}