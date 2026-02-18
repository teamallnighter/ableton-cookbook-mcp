<?php

namespace App\Services;

use App\Services\AbletonDrumRackAnalyzer\AbletonDrumRackAnalyzer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

/**
 * Laravel Service Wrapper for Drum Rack Analyzer
 * Provides easy integration with Laravel applications
 */
class DrumRackAnalyzerService
{
    /**
     * Analyze an uploaded drum rack file
     * 
     * @param string $filePath Path to the .adg file
     * @param array $options Analysis options
     * @return array Analysis results
     */
    public function analyzeDrumRack($filePath, $options = [])
    {
        try {
            // Validate file exists
            if (!file_exists($filePath)) {
                throw new Exception("Drum rack file not found: {$filePath}");
            }

            // Extract options
            $verbose = $options['verbose'] ?? false;
            $includePerformance = $options['include_performance'] ?? true;
            $exportJson = $options['export_json'] ?? false;
            $outputFolder = $options['output_folder'] ?? storage_path('app/drum-rack-analysis');

            Log::info("Starting drum rack analysis", [
                'file' => $filePath,
                'size' => filesize($filePath),
                'options' => $options
            ]);

            // Decompress and parse the file
            $xml = AbletonDrumRackAnalyzer::decompressAndParseAbletonFile($filePath);
            if (!$xml) {
                throw new Exception("Failed to decompress and parse drum rack file");
            }

            // Perform main analysis
            $analysisResult = AbletonDrumRackAnalyzer::parseDrumRackChainsAndDevices(
                $xml, 
                $filePath, 
                $verbose
            );

            // Add performance analysis if requested
            if ($includePerformance) {
                $analysisResult['performance_analysis'] = AbletonDrumRackAnalyzer::analyzeDrumRackPerformance($analysisResult);
            }

            // Add metadata
            $analysisResult['analysis_metadata'] = [
                'analyzed_at' => now()->toISOString(),
                'file_path' => $filePath,
                'file_size' => filesize($filePath),
                'analyzer_version' => 'drum-rack-v1.0',
                'processing_time_ms' => round((microtime(true) - LARAVEL_START) * 1000, 2)
            ];

            // Export to JSON if requested
            if ($exportJson) {
                if (!is_dir($outputFolder)) {
                    mkdir($outputFolder, 0755, true);
                }
                $jsonPath = AbletonDrumRackAnalyzer::exportDrumRackAnalysisToJson(
                    $analysisResult, 
                    $filePath, 
                    $outputFolder
                );
                $analysisResult['exported_json_path'] = $jsonPath;
            }

            Log::info("Drum rack analysis completed successfully", [
                'drum_rack_name' => $analysisResult['drum_rack_name'] ?? 'Unknown',
                'total_chains' => count($analysisResult['drum_chains'] ?? []),
                'active_pads' => $analysisResult['drum_statistics']['active_pads'] ?? 0
            ]);

            return [
                'success' => true,
                'data' => $analysisResult
            ];

        } catch (Exception $e) {
            Log::error("Drum rack analysis failed", [
                'file' => $filePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Analyze multiple drum rack files in batch
     * 
     * @param array $filePaths Array of file paths
     * @param array $options Analysis options
     * @return array Batch analysis results
     */
    public function analyzeDrumRackBatch($filePaths, $options = [])
    {
        $results = [];
        $successful = 0;
        $failed = 0;

        Log::info("Starting batch drum rack analysis", [
            'file_count' => count($filePaths),
            'options' => $options
        ]);

        foreach ($filePaths as $index => $filePath) {
            $result = $this->analyzeDrumRack($filePath, $options);
            $results[] = [
                'index' => $index,
                'file_path' => $filePath,
                'result' => $result
            ];

            if ($result['success']) {
                $successful++;
            } else {
                $failed++;
            }
        }

        return [
            'success' => $failed === 0,
            'summary' => [
                'total' => count($filePaths),
                'successful' => $successful,
                'failed' => $failed
            ],
            'results' => $results
        ];
    }

    /**
     * Find drum rack files in a directory
     * 
     * @param string $directory Directory to search
     * @return array Array of found .adg file paths
     */
    public function findDrumRackFiles($directory)
    {
        try {
            return AbletonDrumRackAnalyzer::findDrumRackFiles($directory);
        } catch (Exception $e) {
            Log::error("Error finding drum rack files", [
                'directory' => $directory,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Validate a drum rack file before analysis
     * 
     * @param string $filePath Path to file
     * @return array Validation result
     */
    public function validateDrumRackFile($filePath)
    {
        $validation = [
            'valid' => false,
            'errors' => [],
            'warnings' => [],
            'info' => []
        ];

        // Check file exists
        if (!file_exists($filePath)) {
            $validation['errors'][] = 'File does not exist';
            return $validation;
        }

        // Check file extension
        if (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) !== 'adg') {
            $validation['errors'][] = 'File must have .adg extension';
            return $validation;
        }

        // Check file size
        $fileSize = filesize($filePath);
        if ($fileSize > 100 * 1024 * 1024) { // 100MB
            $validation['errors'][] = 'File too large (max 100MB)';
            return $validation;
        }
        if ($fileSize < 100) { // 100 bytes
            $validation['errors'][] = 'File too small to be valid';
            return $validation;
        }

        $validation['info'][] = "File size: " . round($fileSize / 1024, 2) . " KB";

        // Try to decompress
        $xmlContent = @file_get_contents("compress.zlib://$filePath");
        if ($xmlContent === false) {
            $validation['errors'][] = 'Unable to decompress file - may not be a valid .adg file';
            return $validation;
        }

        // Try to parse XML
        $xml = @simplexml_load_string($xmlContent);
        if ($xml === false) {
            $validation['errors'][] = 'Invalid XML structure';
            return $validation;
        }

        // Check for drum rack indicators
        $drumRackFound = !empty($xml->xpath('.//DrumRack')) || !empty($xml->xpath('.//DrumGroupDevice'));
        if (!$drumRackFound) {
            $validation['warnings'][] = 'No drum rack detected - may be a different type of Ableton device';
        } else {
            $validation['info'][] = 'Drum rack detected';
        }

        $validation['valid'] = empty($validation['errors']);
        
        return $validation;
    }

    /**
     * Get supported drum rack file extensions
     * 
     * @return array Array of supported extensions
     */
    public function getSupportedExtensions()
    {
        return ['adg'];
    }

    /**
     * Get analysis statistics for a completed analysis
     * 
     * @param array $analysisResult Completed analysis result
     * @return array Statistics summary
     */
    public function getAnalysisStatistics($analysisResult)
    {
        if (!isset($analysisResult['data'])) {
            return null;
        }

        $data = $analysisResult['data'];
        
        return [
            'drum_rack_name' => $data['drum_rack_name'] ?? 'Unknown',
            'total_chains' => count($data['drum_chains'] ?? []),
            'active_chains' => array_reduce($data['drum_chains'] ?? [], function($count, $chain) {
                return $count + (empty($chain['devices']) ? 0 : 1);
            }, 0),
            'total_devices' => array_reduce($data['drum_chains'] ?? [], function($count, $chain) {
                return $count + count($chain['devices'] ?? []);
            }, 0),
            'macro_controls' => count($data['macro_controls'] ?? []),
            'ableton_version' => $data['ableton_version'] ?? 'Unknown',
            'complexity_score' => $data['performance_analysis']['complexity_score'] ?? null,
            'parsing_errors' => count($data['parsing_errors'] ?? []),
            'parsing_warnings' => count($data['parsing_warnings'] ?? []),
            'drum_statistics' => $data['drum_statistics'] ?? []
        ];
    }

    /**
     * Determine if a rack file is a drum rack based on analysis
     * 
     * @param string $filePath Path to the .adg file
     * @return bool True if file contains a drum rack
     */
    public function isDrumRack($filePath)
    {
        try {
            $xml = AbletonDrumRackAnalyzer::decompressAndParseAbletonFile($filePath);
            if (!$xml) {
                return false;
            }

            // Check for drum rack indicators
            $drumRackFound = !empty($xml->xpath('.//DrumRack')) || 
                             !empty($xml->xpath('.//DrumGroupDevice'));
            
            return $drumRackFound;
        } catch (Exception $e) {
            Log::error("Error checking if file is drum rack", [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}