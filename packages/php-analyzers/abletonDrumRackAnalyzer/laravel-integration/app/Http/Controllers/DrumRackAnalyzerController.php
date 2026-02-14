<?php

namespace App\Http\Controllers;

use App\Services\DrumRackAnalyzerService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Exception;

class DrumRackAnalyzerController extends Controller
{
    protected $drumRackAnalyzer;

    public function __construct(DrumRackAnalyzerService $drumRackAnalyzer)
    {
        $this->drumRackAnalyzer = $drumRackAnalyzer;
    }

    /**
     * Upload and analyze a drum rack file
     */
    public function analyze(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:102400|mimes:adg', // 100MB max
            'options' => 'sometimes|array',
            'options.verbose' => 'sometimes|boolean',
            'options.include_performance' => 'sometimes|boolean',
            'options.export_json' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('file');
            
            // Store uploaded file temporarily
            $tempPath = $file->store('temp/drum-racks');
            $fullPath = Storage::path($tempPath);

            // Validate the file
            $validation = $this->drumRackAnalyzer->validateDrumRackFile($fullPath);
            if (!$validation['valid']) {
                Storage::delete($tempPath);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid drum rack file',
                    'validation_errors' => $validation['errors'],
                    'warnings' => $validation['warnings']
                ], 400);
            }

            // Perform analysis
            $options = $request->input('options', []);
            $result = $this->drumRackAnalyzer->analyzeDrumRack($fullPath, $options);

            // Clean up temp file
            Storage::delete($tempPath);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Analysis failed',
                    'error' => $result['error']
                ], 500);
            }

            // Get statistics for response
            $statistics = $this->drumRackAnalyzer->getAnalysisStatistics($result);

            return response()->json([
                'success' => true,
                'message' => 'Drum rack analyzed successfully',
                'statistics' => $statistics,
                'validation' => $validation,
                'data' => $result['data']
            ]);

        } catch (Exception $e) {
            // Clean up temp file if it exists
            if (isset($tempPath)) {
                Storage::delete($tempPath);
            }

            return response()->json([
                'success' => false,
                'message' => 'Server error during analysis',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Analyze drum rack from URL
     */
    public function analyzeFromUrl(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'url' => 'required|url',
            'options' => 'sometimes|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $url = $request->input('url');
            
            // Download file from URL
            $fileContents = file_get_contents($url);
            if ($fileContents === false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to download file from URL'
                ], 400);
            }

            // Store temporarily
            $tempPath = 'temp/drum-racks/' . uniqid() . '.adg';
            Storage::put($tempPath, $fileContents);
            $fullPath = Storage::path($tempPath);

            // Validate and analyze
            $validation = $this->drumRackAnalyzer->validateDrumRackFile($fullPath);
            if (!$validation['valid']) {
                Storage::delete($tempPath);
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid drum rack file from URL',
                    'validation_errors' => $validation['errors']
                ], 400);
            }

            $options = $request->input('options', []);
            $result = $this->drumRackAnalyzer->analyzeDrumRack($fullPath, $options);

            Storage::delete($tempPath);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Analysis failed',
                    'error' => $result['error']
                ], 500);
            }

            $statistics = $this->drumRackAnalyzer->getAnalysisStatistics($result);

            return response()->json([
                'success' => true,
                'message' => 'Drum rack analyzed successfully',
                'statistics' => $statistics,
                'data' => $result['data']
            ]);

        } catch (Exception $e) {
            if (isset($tempPath)) {
                Storage::delete($tempPath);
            }

            return response()->json([
                'success' => false,
                'message' => 'Server error during analysis',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Batch analyze multiple drum rack files
     */
    public function analyzeBatch(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'files' => 'required|array|max:10', // Limit to 10 files
            'files.*' => 'required|file|max:102400|mimes:adg',
            'options' => 'sometimes|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $files = $request->file('files');
            $tempPaths = [];
            $filePaths = [];

            // Store all files temporarily
            foreach ($files as $file) {
                $tempPath = $file->store('temp/drum-racks');
                $tempPaths[] = $tempPath;
                $filePaths[] = Storage::path($tempPath);
            }

            // Perform batch analysis
            $options = $request->input('options', []);
            $result = $this->drumRackAnalyzer->analyzeDrumRackBatch($filePaths, $options);

            // Clean up temp files
            foreach ($tempPaths as $tempPath) {
                Storage::delete($tempPath);
            }

            return response()->json([
                'success' => $result['success'],
                'message' => $result['success'] ? 'Batch analysis completed successfully' : 'Batch analysis completed with errors',
                'summary' => $result['summary'],
                'results' => $result['results']
            ]);

        } catch (Exception $e) {
            // Clean up temp files
            if (isset($tempPaths)) {
                foreach ($tempPaths as $tempPath) {
                    Storage::delete($tempPath);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Server error during batch analysis',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Validate drum rack file without analyzing
     */
    public function validate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:102400'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('file');
            
            // Store temporarily
            $tempPath = $file->store('temp/drum-racks');
            $fullPath = Storage::path($tempPath);

            // Validate
            $validation = $this->drumRackAnalyzer->validateDrumRackFile($fullPath);

            // Clean up
            Storage::delete($tempPath);

            return response()->json([
                'success' => true,
                'message' => 'File validation completed',
                'validation' => $validation
            ]);

        } catch (Exception $e) {
            if (isset($tempPath)) {
                Storage::delete($tempPath);
            }

            return response()->json([
                'success' => false,
                'message' => 'Server error during validation',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get analyzer information
     */
    public function info(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'analyzer' => [
                'name' => 'Ableton Drum Rack Analyzer',
                'version' => '1.0.0',
                'supported_extensions' => $this->drumRackAnalyzer->getSupportedExtensions(),
                'max_file_size' => '100MB',
                'features' => [
                    'drum_chain_analysis',
                    'device_detection',
                    'performance_analysis',
                    'macro_control_detection',
                    'pad_mapping',
                    'velocity_range_analysis',
                    'batch_processing'
                ]
            ]
        ]);
    }
}