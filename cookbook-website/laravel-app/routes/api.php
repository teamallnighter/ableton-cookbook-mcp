  <?php

  use App\Http\Controllers\Api\RackController;
  use App\Http\Controllers\Api\RackRatingController;
  use App\Http\Controllers\Api\UserController;
  use App\Http\Controllers\Api\CommentController;
  use App\Http\Controllers\Api\CollectionController;
  use App\Http\Controllers\Api\AuthController;
  use App\Http\Controllers\DrumRackAnalyzerController;
  use App\Http\Controllers\Api\MarkdownPreviewController;
  use App\Http\Controllers\Api\D2DiagramController;
  use App\Http\Controllers\Api\NestedChainAnalysisController;
  use App\Http\Controllers\Api\BatchReprocessController;
  use App\Http\Controllers\Api\ConstitutionalComplianceController;
  use Illuminate\Http\Request;
  use Illuminate\Support\Facades\Route;

  Route::get('/user', function (Request $request) {
      return $request->user();
  })->middleware('auth:sanctum');

  // Authentication routes for desktop apps
  Route::prefix('v1/auth')->group(function () {
      Route::post('/login', [AuthController::class, 'login']);
      Route::post('/logout', [AuthController::class,
  'logout'])->middleware('auth:sanctum');
      Route::get('/user', [AuthController::class,
  'user'])->middleware('auth:sanctum');
  });

  // Public routes with rate limiting
  Route::prefix('v1')->middleware(['throttle:60,1'])->group(function () {
      // Constitutional Compliance - Public information
      Route::prefix('compliance')->controller(ConstitutionalComplianceController::class)->group(function () {
          Route::get('/constitution', 'getConstitution');
          Route::get('/version-history', 'getVersionHistory');
      });
      // Racks - Public endpoints
      Route::get('/racks', [RackController::class, 'index']);
      Route::get('/racks/trending', [RackController::class, 'trending']);
      Route::get('/racks/featured', [RackController::class, 'featured']);
      Route::get('/racks/{rack}', [RackController::class, 'show']);
      Route::get('/racks/{rack}/how-to', [RackController::class, 'getHowTo']);

      // Drum Rack Analyzer - Public info endpoint
      Route::get('/drum-racks/info', [DrumRackAnalyzerController::class, 'info']);

      // D2 Diagrams - Public endpoints
      Route::prefix('diagrams')->controller(D2DiagramController::class)->group(function () {
          Route::get('/themes', 'getAvailableThemes');
          Route::get('/themes/{theme}/preview', 'getThemePreview');
          Route::get('/templates', 'getTemplates');
          Route::get('/database-schema', 'generateDatabaseSchemaDiagram');
      });

      // Markdown Preview - Public endpoints with rate limiting
      Route::prefix('markdown')->controller(MarkdownPreviewController::class)->group(function () {
          Route::post('/preview', 'preview')->middleware('throttle:60,1'); // 60 previews per minute
          Route::post('/validate', 'validateMarkdown')->middleware('throttle:30,1'); // 30 validations per minute
          Route::get('/syntax-help', 'syntaxHelp');
      });

      // Users - Public profiles
      Route::get('/users/{user}', [UserController::class, 'show']);
      Route::get('/users/{user}/racks', [UserController::class, 'racks']);
      Route::get('/users/{user}/followers', [UserController::class,
  'followers']);
      Route::get('/users/{user}/following', [UserController::class,
  'following']);
  });

  // Authenticated routes with stricter rate limiting
  Route::prefix('v1')->middleware(['auth:sanctum',
  'throttle:120,1'])->group(function () {
      // Racks - Authenticated actions with specific limits
      Route::post('/racks', [RackController::class,
  'store'])->middleware('throttle:5,1');
      Route::put('/racks/{rack}', [RackController::class, 'update']);
      Route::delete('/racks/{rack}', [RackController::class, 'destroy']);
      
      // Phase 3 Infrastructure API Endpoints
      Route::prefix('infrastructure')->controller(App\Http\Controllers\Api\InfrastructureController::class)->group(function () {
          Route::get('/feature-flags', 'getFeatureFlags');
          Route::get('/health', 'getSystemHealth');
          Route::get('/security', 'getSecurityMetrics');
          Route::get('/dashboard', 'getDashboardMetrics');
          Route::get('/accessibility', 'getAccessibilityMetrics');
      });
      Route::post('/racks/{rack}/download', [RackController::class,
  'download'])->middleware('throttle:30,1');
      Route::post('/racks/{rack}/like', [RackController::class,
  'toggleLike']);

      // How-to articles with auto-save throttling
      Route::put('/racks/{rack}/how-to', [RackController::class, 'updateHowTo'])->middleware('throttle:30,1');
      Route::delete('/racks/{rack}/how-to', [RackController::class, 'deleteHowTo']);

      // Rack Ratings
      Route::post('/racks/{rack}/rate', [RackRatingController::class,
  'store']);
      Route::put('/racks/{rack}/rate', [RackRatingController::class,
  'update']);
      Route::delete('/racks/{rack}/rate', [RackRatingController::class,
  'destroy']);

      // Comments
      Route::get('/racks/{rack}/comments', [CommentController::class,
  'index']);
      Route::post('/racks/{rack}/comments', [CommentController::class,
  'store']);
      Route::put('/comments/{comment}', [CommentController::class,
  'update']);
      Route::delete('/comments/{comment}', [CommentController::class,
  'destroy']);
      Route::post('/comments/{comment}/like', [CommentController::class,
  'toggleLike']);

      // User actions
      Route::post('/users/{user}/follow', [UserController::class,
  'follow']);
      Route::delete('/users/{user}/follow', [UserController::class,
  'unfollow']);
      Route::get('/user/feed', [UserController::class, 'feed']);
      Route::get('/user/notifications', [UserController::class,
  'notifications']);

      // Collections
      Route::get('/collections', [CollectionController::class, 'index']);
      Route::post('/collections', [CollectionController::class, 'store']);
      Route::get('/collections/{collection}', [CollectionController::class,
  'show']);
      Route::put('/collections/{collection}', [CollectionController::class,
  'update']);
      Route::delete('/collections/{collection}',
  [CollectionController::class, 'destroy']);
      Route::post('/collections/{collection}/racks/{rack}',
  [CollectionController::class, 'addRack']);
      Route::delete('/collections/{collection}/racks/{rack}',
  [CollectionController::class, 'removeRack']);

      // Drum Rack Analyzer - Authenticated endpoints
      Route::prefix('drum-racks')->group(function () {
          Route::post('/analyze', [DrumRackAnalyzerController::class, 'analyze'])->middleware('throttle:60,1');
          Route::post('/analyze-batch', [DrumRackAnalyzerController::class, 'analyzeBatch'])->middleware('throttle:10,1');
          Route::post('/validateDrumRack', [DrumRackAnalyzerController::class, 'validate']);
          Route::post('/detect', [DrumRackAnalyzerController::class, 'detect']);
      });

      // D2 Diagrams - Authenticated endpoints
      Route::prefix('diagrams')->controller(D2DiagramController::class)->group(function () {
          Route::post('/compare', 'generateComparisonDiagram')->middleware('throttle:10,1');
          Route::post('/templates', 'saveTemplate')->middleware('throttle:20,1');
      });

      // D2 Diagrams for Racks - Authenticated rack-specific diagrams
      Route::get('/racks/{rack}/diagram', [D2DiagramController::class, 'generateRackDiagram'])->middleware('throttle:30,1');

      // Enhanced Rack Analysis - RackController extensions
      Route::get('/racks/{rack}/analysis-status', [RackController::class, 'getAnalysisStatus']);
      Route::post('/racks/{rack}/trigger-analysis', [RackController::class, 'triggerAnalysis'])->middleware('throttle:10,1');

      // Nested Chain Analysis - Enhanced analysis endpoints
      Route::prefix('analysis')->controller(NestedChainAnalysisController::class)->group(function () {
          // Individual rack analysis
          Route::post('/racks/{uuid}/analyze-nested-chains', 'analyze')->middleware('throttle:60,1');
          Route::get('/racks/{uuid}/nested-chains', 'getHierarchy');
          Route::get('/racks/{uuid}/nested-chains/{chainId}', 'getChainDetails');
          Route::post('/racks/{uuid}/reanalyze-nested-chains', 'reanalyze')->middleware('throttle:60,1');
          Route::get('/racks/{uuid}/analysis-summary', 'getSummary');

          // Bulk operations (admin only)
          Route::get('/bulk-statistics', 'getBulkStatistics')->middleware(['can:viewAny,App\\Models\\Rack', 'throttle:30,1']);
      });

      // Batch Reprocessing - Enterprise batch operations
      Route::prefix('analysis')->controller(BatchReprocessController::class)->group(function () {
          Route::post('/batch-reprocess', 'submitBatch')->middleware('throttle:10,1'); // Max 10 batch submissions per minute
          Route::get('/batch-status/{batchId}', 'getBatchStatus')->middleware('throttle:60,1');
          Route::get('/batch-results/{batchId}', 'getBatchResults')->middleware('throttle:60,1');
          Route::get('/batch-history', 'getBatchHistory');
          Route::delete('/batch/{batchId}', 'cancelBatch')->middleware('throttle:30,1');
      });

      // Constitutional Compliance - Governance and compliance reporting
      Route::prefix('compliance')->controller(ConstitutionalComplianceController::class)->group(function () {
          // General compliance endpoints
          Route::get('/constitution', 'getConstitution');
          Route::get('/version-history', 'getVersionHistory');

          // Rack-specific compliance
          Route::post('/validate-rack/{uuid}', 'validateRack')->middleware('throttle:60,1');
          Route::get('/rack/{uuid}', 'getRackCompliance')->middleware('throttle:60,1');

          // System-wide compliance (admin only)
          Route::get('/system-status', 'getSystemCompliance')->middleware(['can:viewAny,App\\Models\\Rack', 'throttle:30,1']);
          Route::get('/report', 'getComplianceReport')->middleware(['can:viewAny,App\\Models\\Rack', 'throttle:30,1']);

          // Audit logging
          Route::post('/audit-log', 'logAuditEvent')->middleware('throttle:120,1');
      });
  });

