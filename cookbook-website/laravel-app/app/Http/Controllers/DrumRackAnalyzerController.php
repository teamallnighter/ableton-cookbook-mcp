<?php

namespace App\Http\Controllers;

use App\Services\DrumRackAnalyzerService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Controller for handling drum rack analysis operations.
 * Provides API endpoints for analyzing Ableton drum rack files.
 */
class DrumRackAnalyzerController extends Controller
{
    protected DrumRackAnalyzerService $drumRackAnalyzer;

    public function __construct(DrumRackAnalyzerService $drumRackAnalyzer)
    {
        $this->drumRackAnalyzer = $drumRackAnalyzer;
        
        // Apply authentication middleware for protected operations
        $this->middleware('auth:sanctum')->except(['info']);
        
        // Apply rate limiting
        $this->middleware('throttle:60,1')->only(['analyze', 'analyzeFromUrl']);
        $this->middleware('throttle:10,1')->only(['analyzeBatch']);
    }

    /**
     * Upload and analyze a drum rack file
     * 
     * @OA\Post(
     *     path="/api/v1/drum-racks/analyze",
     *     tags={"Drum Rack Analysis"},
     *     summary="Analyze uploaded drum rack file",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="file", type="string", format="binary", description="Drum rack .adg file"),
     *                 @OA\Property(property="options[verbose]", type="boolean", description="Enable verbose output"),
     *                 @OA\Property(property="options[include_performance]", type="boolean", description="Include performance analysis"),
     *                 @OA\Property(property="options[export_json]", type="boolean", description="Export analysis to JSON file")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Analysis completed successfully"),
     *     @OA\Response(response=400, description="Invalid file"),
     *     @OA\Response(response=422, description="Validation failed"),
     *     @OA\Response(response=500, description="Analysis failed")
     * )
     */
    public function analyze(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:102400', // 100MB max
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
            
            // Check file extension
            if (strtolower($file->getClientOriginalExtension()) !== 'adg') {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid file type. Only .adg files are supported.'
                ], 400);
            }
            
            // Store uploaded file temporarily
            $tempPath = $file->store('temp/drum-racks');
            $fullPath = Storage::path($tempPath);

            Log::info('Drum rack analysis started', [
                'user_id' => auth()->id(),
                'filename' => $file->getClientOriginalName(),
                'size' => $file->getSize()
            ]);

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
            $options = array_merge(config('drum-rack-analyzer.analysis'), $request->input('options', []));
            $result = $this->drumRackAnalyzer->analyzeDrumRack($fullPath, $options);

            // Clean up temp file
            Storage::delete($tempPath);

            if (!$result['success']) {
                Log::error('Drum rack analysis failed', [
                    'user_id' => auth()->id(),
                    'error' => $result['error'],
                    'filename' => $file->getClientOriginalName()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Analysis failed',
                    'error' => $result['error']
                ], 500);
            }

            // Get statistics for response
            $statistics = $this->drumRackAnalyzer->getAnalysisStatistics($result);

            Log::info('Drum rack analysis completed', [
                'user_id' => auth()->id(),
                'drum_rack_name' => $statistics['drum_rack_name'],
                'active_pads' => $statistics['drum_statistics']['active_pads'] ?? 0,
                'complexity_score' => $statistics['complexity_score']
            ]);

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

            Log::error('Drum rack analysis exception', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Server error during analysis',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Batch analyze multiple drum rack files
     * 
     * @OA\Post(
     *     path="/api/v1/drum-racks/analyze-batch",
     *     tags={"Drum Rack Analysis"},
     *     summary="Analyze multiple drum rack files in batch",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="files[]", type="array", @OA\Items(type="string", format="binary"), description="Array of drum rack .adg files (max 5)"),
     *                 @OA\Property(property="options[verbose]", type="boolean", description="Enable verbose output")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Batch analysis completed"),
     *     @OA\Response(response=422, description="Validation failed")
     * )
     */
    public function analyzeBatch(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'files' => 'required|array|max:5', // Limit to 5 files for performance
            'files.*' => 'required|file|max:102400',
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

            // Validate all files are .adg first
            foreach ($files as $file) {
                if (strtolower($file->getClientOriginalExtension()) !== 'adg') {
                    return response()->json([
                        'success' => false,
                        'message' => 'All files must be .adg format'
                    ], 400);
                }
            }

            // Store all files temporarily
            foreach ($files as $file) {
                $tempPath = $file->store('temp/drum-racks');
                $tempPaths[] = $tempPath;
                $filePaths[] = Storage::path($tempPath);
            }

            Log::info('Drum rack batch analysis started', [
                'user_id' => auth()->id(),
                'file_count' => count($files)
            ]);

            // Perform batch analysis
            $options = array_merge(config('drum-rack-analyzer.analysis'), $request->input('options', []));
            $result = $this->drumRackAnalyzer->analyzeDrumRackBatch($filePaths, $options);

            // Clean up temp files
            foreach ($tempPaths as $tempPath) {
                Storage::delete($tempPath);
            }

            Log::info('Drum rack batch analysis completed', [
                'user_id' => auth()->id(),
                'successful' => $result['summary']['successful'],
                'failed' => $result['summary']['failed']
            ]);

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

            Log::error('Drum rack batch analysis exception', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Server error during batch analysis',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Validate drum rack file without analyzing
     * 
     * @OA\Post(
     *     path="/api/v1/drum-racks/validate",
     *     tags={"Drum Rack Analysis"},
     *     summary="Validate drum rack file format",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="file", type="string", format="binary", description="Drum rack .adg file to validate")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Validation completed"),
     *     @OA\Response(response=422, description="Validation failed")
     * )
     */
    public function validateDrumRack(Request $request): JsonResponse
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
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Check if a file is a drum rack
     * 
     * @OA\Post(
     *     path="/api/v1/drum-racks/detect",
     *     tags={"Drum Rack Analysis"},
     *     summary="Detect if file contains a drum rack",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="file", type="string", format="binary", description=".adg file to check")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Detection completed"),
     *     @OA\Response(response=422, description="Validation failed")
     * )
     */
    public function detect(Request $request): JsonResponse
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

            // Check if it's a drum rack
            $isDrumRack = $this->drumRackAnalyzer->isDrumRack($fullPath);

            // Clean up
            Storage::delete($tempPath);

            return response()->json([
                'success' => true,
                'message' => 'Detection completed',
                'is_drum_rack' => $isDrumRack,
                'filename' => $file->getClientOriginalName()
            ]);

        } catch (Exception $e) {
            if (isset($tempPath)) {
                Storage::delete($tempPath);
            }

            return response()->json([
                'success' => false,
                'message' => 'Server error during detection',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get analyzer information and capabilities
     * 
     * @OA\Get(
     *     path="/api/v1/drum-racks/info",
     *     tags={"Drum Rack Analysis"},
     *     summary="Get drum rack analyzer information",
     *     @OA\Response(response=200, description="Analyzer information retrieved")
     * )
     */
    public function info(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'analyzer' => [
                'name' => 'Ableton Drum Rack Analyzer',
                'version' => '1.0.0',
                'type' => 'specialized',
                'supported_extensions' => $this->drumRackAnalyzer->getSupportedExtensions(),
                'max_file_size' => config('drum-rack-analyzer.validation.max_file_size'),
                'max_batch_files' => 5,
                'features' => [
                    'drum_chain_analysis',
                    'device_detection',
                    'performance_analysis',
                    'macro_control_detection',
                    'pad_mapping_analysis',
                    'velocity_range_analysis',
                    'midi_note_mapping',
                    'drum_type_identification',
                    'sample_vs_synthesis_detection',
                    'complexity_scoring',
                    'batch_processing',
                    'drum_rack_validation'
                ],
                'drum_specific_features' => [
                    'pad_mappings' => 'Analyze MIDI note to pad mappings (C1=36, etc.)',
                    'drum_types' => 'Identify kick, snare, hi-hat, etc. from pad positions',
                    'sample_analysis' => 'Detect sample-based vs synthesized drums',
                    'performance_scoring' => 'CPU complexity analysis for drum racks',
                    'velocity_sensitivity' => 'Analyze velocity ranges for drum dynamics'
                ]
            ],
            'integration' => [
                'complements_general_analyzer' => true,
                'auto_detection' => config('drum-rack-analyzer.integration.auto_detect_drum_racks'),
                'fallback_available' => config('drum-rack-analyzer.integration.fallback_to_general_analyzer')
            ]
        ]);
    }
}