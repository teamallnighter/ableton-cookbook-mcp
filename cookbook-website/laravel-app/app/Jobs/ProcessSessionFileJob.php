<?php

namespace App\Jobs;

use App\Models\Session;
use App\Services\AbletonSessionAnalyzer\AbletonSessionAnalyzer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class ProcessSessionFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 minutes timeout (sessions can be large)
    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Session $session
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(AbletonSessionAnalyzer $analyzer): void
    {
        Log::info("Starting session file processing", [
            'session_id' => $this->session->id,
            'file_path' => $this->session->file_path
        ]);

        try {
            // Update status to processing
            $this->session->update(['status' => 'processing']);

            // Get the file path on disk
            $filePath = Storage::disk('private')->path($this->session->file_path);
            
            if (!file_exists($filePath)) {
                throw new Exception("Session file not found: {$filePath}");
            }

            // Analyze the session file
            $analysisResult = $analyzer->analyzeSessionFile($filePath);

            // Update session with analysis results
            $updateData = [
                'status' => $analysisResult['success'] ? 'approved' : 'failed',
                'published_at' => $analysisResult['success'] ? now() : null,
            ];

            if ($analysisResult['success']) {
                // Extract analysis data
                $updateData = array_merge($updateData, [
                    'ableton_version' => $analysisResult['ableton_version'],
                    'tempo' => $analysisResult['tempo'],
                    'time_signature' => $analysisResult['time_signature'],
                    'key_signature' => $analysisResult['key_signature'],
                    'track_count' => $analysisResult['track_count'],
                    'scene_count' => $analysisResult['scene_count'],
                    'length_seconds' => $analysisResult['length_seconds'],
                    'tracks' => $analysisResult['tracks'] ?? [],
                    'scenes' => $analysisResult['scenes'] ?? [],
                    'embedded_racks' => $analysisResult['embedded_racks'] ?? [],
                    'embedded_presets' => $analysisResult['embedded_presets'] ?? [],
                    'embedded_samples' => $analysisResult['embedded_samples'] ?? [],
                    'embedded_assets' => $analysisResult['embedded_assets'] ?? [],
                    'routing_info' => $analysisResult['routing_info'] ?? [],
                    'automation_data' => $analysisResult['automation_data'] ?? [],
                    'version_details' => $analysisResult['version_details'] ?? [],
                    'parsing_errors' => [],
                    'parsing_warnings' => [],
                ]);

                Log::info("Session analysis completed successfully", [
                    'session_id' => $this->session->id,
                    'track_count' => $analysisResult['track_count'],
                    'scene_count' => $analysisResult['scene_count'],
                    'tempo' => $analysisResult['tempo'],
                    'embedded_racks_count' => count($analysisResult['embedded_racks'] ?? []),
                    'embedded_samples_count' => count($analysisResult['embedded_samples'] ?? [])
                ]);
            } else {
                // Store error information
                $updateData['processing_error'] = $analysisResult['error'] ?? 'Unknown analysis error';
                $updateData['parsing_errors'] = [$analysisResult['error'] ?? 'Analysis failed'];

                Log::error("Session analysis failed", [
                    'session_id' => $this->session->id,
                    'error' => $analysisResult['error']
                ]);
            }

            // Update the session record
            $this->session->update($updateData);

            // TODO: Generate session preview image/waveform if needed
            // TODO: Extract and process embedded samples for preview
            // TODO: Create session thumbnail from arrangement view
            // TODO: Send notification to user about completion
            // TODO: Update search index

        } catch (Exception $e) {
            Log::error("Session processing job failed", [
                'session_id' => $this->session->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Update session with error status
            $this->session->update([
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
        Log::error("Session processing job permanently failed", [
            'session_id' => $this->session->id,
            'error' => $exception->getMessage(),
        ]);

        // Update session with final failure status
        $this->session->update([
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
        return ['sessions', 'file-processing', "session:{$this->session->id}"];
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [60, 120, 300]; // Wait 1m, then 2m, then 5m between retries (sessions are complex)
    }
}