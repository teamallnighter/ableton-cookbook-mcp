<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DrumRackAnalyzerController;

/*
|--------------------------------------------------------------------------
| Drum Rack Analyzer Routes
|--------------------------------------------------------------------------
|
| Routes for the Ableton Drum Rack Analyzer API
|
*/

Route::prefix('api/drum-rack-analyzer')->group(function () {
    
    // Basic analyzer information
    Route::get('info', [DrumRackAnalyzerController::class, 'info']);
    
    // File validation
    Route::post('validate', [DrumRackAnalyzerController::class, 'validate']);
    
    // Single file analysis
    Route::post('analyze', [DrumRackAnalyzerController::class, 'analyze']);
    
    // Analyze from URL
    Route::post('analyze-from-url', [DrumRackAnalyzerController::class, 'analyzeFromUrl']);
    
    // Batch analysis
    Route::post('analyze-batch', [DrumRackAnalyzerController::class, 'analyzeBatch']);
    
});

// Web interface routes (optional)
Route::prefix('drum-rack-analyzer')->group(function () {
    
    // Main analyzer page
    Route::get('/', function () {
        return view('drum-rack-analyzer.index');
    })->name('drum-rack-analyzer');
    
    // Upload page
    Route::get('/upload', function () {
        return view('drum-rack-analyzer.upload');
    })->name('drum-rack-analyzer.upload');
    
});