<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

// Health check for Railway
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'app' => config('app.name'),
        'env' => config('app.env')
    ]);
});


Route::get('/', function () {
    try {
        $recentBlogPosts = \App\Models\BlogPost::with(['author', 'category'])
            ->published()
            ->latest('published_at')
            ->take(3)
            ->get();
            
        return view('racks', compact('recentBlogPosts'));
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'View loading failed',
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
})->name('home');

Route::get('/racks/{rack}', function (App\Models\Rack $rack) {
    return view('rack-show', ['rack' => $rack]);
})->name('racks.show');

Route::get('/profile', function () {
    return view('profile', ['user' => auth()->user()]);
})->middleware('auth')->name('profile');

Route::get('/users/{user}', function (App\Models\User $user) {
    return view('profile', ['user' => $user]);
})->name('users.show');

// Legal Pages Routes (Public)
Route::prefix('legal')->name('legal.')->controller(App\Http\Controllers\LegalController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/terms', 'terms')->name('terms');
    Route::get('/privacy', 'privacy')->name('privacy');
    Route::get('/copyright', 'copyright')->name('copyright');
    Route::get('/cookies', 'cookies')->name('cookies');
});

// Upload routes (require authentication)
Route::middleware('auth')->group(function () {
    Route::get('/upload', [App\Http\Controllers\RackUploadController::class, 'create'])->name('racks.upload');
    Route::post('/upload', [App\Http\Controllers\RackUploadController::class, 'store'])->name('racks.store');
    
    // Edit routes (require authentication and ownership)
    // Edit routes (require authentication and ownership) - Multi-step process
    Route::get("/racks/{rack}/edit", [App\Http\Controllers\RackEditController::class, "edit"])->name("racks.edit");
    Route::get("/racks/{rack}/edit/upload", [App\Http\Controllers\RackEditController::class, "editUpload"])->name("racks.edit.upload");
    Route::post("/racks/{rack}/edit/upload", [App\Http\Controllers\RackEditController::class, "processUpload"])->name("racks.edit.upload.process");
    Route::get("/racks/{rack}/edit/analysis", [App\Http\Controllers\RackEditController::class, "editAnalysis"])->name("racks.edit.analysis");
    Route::get("/racks/{rack}/edit/annotate", [App\Http\Controllers\RackEditController::class, "editAnnotate"])->name("racks.edit.annotate");
    Route::post("/racks/{rack}/edit/annotate", [App\Http\Controllers\RackEditController::class, "saveAnnotations"])->name("racks.edit.annotate.save");
    Route::get("/racks/{rack}/edit/metadata", [App\Http\Controllers\RackEditController::class, "editMetadata"])->name("racks.edit.metadata");
    Route::put("/racks/{rack}", [App\Http\Controllers\RackEditController::class, "update"])->name("racks.update");
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    // Demo Routes
    Route::get('/demos/rack-tree-vertical', function () {
        return view('demos.rack-tree-vertical');
    })->name('demos.rack-tree-vertical');

    Route::get('/demos/rack-tree-horizontal', function () {
        return view('demos.rack-tree-horizontal');
    })->name('demos.rack-tree-horizontal');
});

// Blog Routes (Public)
Route::prefix('blog')->name('blog.')->group(function () {
    Route::get('/', [App\Http\Controllers\BlogController::class, 'index'])->name('index');
    Route::get('/search', [App\Http\Controllers\BlogController::class, 'search'])->name('search');
    Route::get('/category/{slug}', [App\Http\Controllers\BlogController::class, 'category'])->name('category');
    Route::get('/rss', [App\Http\Controllers\BlogController::class, 'rss'])->name('rss');
    Route::get('/sitemap.xml', [App\Http\Controllers\BlogController::class, 'sitemap'])->name('sitemap');
    Route::get('/{slug}', [App\Http\Controllers\BlogController::class, 'show'])->name('show');
});

// Blog Admin Routes (Require authentication and admin role)
Route::middleware(['auth', 'admin'])->prefix('admin/blog')->name('admin.blog.')->group(function () {
    // Blog Posts
    Route::get('/', [App\Http\Controllers\Admin\BlogAdminController::class, 'index'])->name('index');
    Route::get('/create', [App\Http\Controllers\Admin\BlogAdminController::class, 'create'])->name('create');
    Route::post('/', [App\Http\Controllers\Admin\BlogAdminController::class, 'store'])->name('store');
    Route::get('/{post}', [App\Http\Controllers\Admin\BlogAdminController::class, 'show'])->name('show');
    Route::get('/{post}/edit', [App\Http\Controllers\Admin\BlogAdminController::class, 'edit'])->name('edit');
    Route::put('/{post}', [App\Http\Controllers\Admin\BlogAdminController::class, 'update'])->name('update');
    Route::delete('/{post}', [App\Http\Controllers\Admin\BlogAdminController::class, 'destroy'])->name('destroy');
    
    // AJAX Routes
    Route::post('/upload-image', [App\Http\Controllers\Admin\BlogAdminController::class, 'uploadImage'])->name('upload-image');
    Route::post('/{post}/toggle-featured', [App\Http\Controllers\Admin\BlogAdminController::class, 'toggleFeatured'])->name('toggle-featured');
    Route::post('/{post}/toggle-active', [App\Http\Controllers\Admin\BlogAdminController::class, 'toggleActive'])->name('toggle-active');

    // Blog Categories
    Route::prefix('categories')->name('categories.')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\BlogCategoryController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\Admin\BlogCategoryController::class, 'create'])->name('create');
        Route::post('/', [App\Http\Controllers\Admin\BlogCategoryController::class, 'store'])->name('store');
        Route::get('/{category}/edit', [App\Http\Controllers\Admin\BlogCategoryController::class, 'edit'])->name('edit');
        Route::put('/{category}', [App\Http\Controllers\Admin\BlogCategoryController::class, 'update'])->name('update');
        Route::delete('/{category}', [App\Http\Controllers\Admin\BlogCategoryController::class, 'destroy'])->name('destroy');
        Route::post('/{category}/toggle-active', [App\Http\Controllers\Admin\BlogCategoryController::class, 'toggleActive'])->name('toggle-active');
    });
});

// SEO Routes
Route::get('/sitemap.xml', [App\Http\Controllers\SitemapController::class, 'index'])->name('sitemap.index');
Route::get('/sitemap-static.xml', [App\Http\Controllers\SitemapController::class, 'static'])->name('sitemap.static');
Route::get('/sitemap-racks.xml', [App\Http\Controllers\SitemapController::class, 'racks'])->name('sitemap.racks');
Route::get('/sitemap-users.xml', [App\Http\Controllers\SitemapController::class, 'users'])->name('sitemap.users');

// SEO Webhook Routes (for automated sitemap refresh)
Route::prefix('seo')->group(function () {
    Route::post('/refresh-sitemap', [App\Http\Controllers\SeoWebhookController::class, 'refreshSitemap'])->name('seo.refresh');
    Route::get('/ping', [App\Http\Controllers\SeoWebhookController::class, 'ping'])->name('seo.ping');
});

// Multi-step upload workflow
Route::middleware('auth')->group(function () {
    // Legacy analysis route (kept for backward compatibility)
    Route::get('/racks/{rack}/analysis', [App\Http\Controllers\RackUploadController::class, 'analysis'])->name('racks.analysis');
    
    // New parallel processing workflow
    Route::get('/racks/{rack}/metadata', [App\Http\Controllers\RackMetadataController::class, 'create'])->name('racks.metadata');
    Route::post('/racks/{rack}/metadata', [App\Http\Controllers\RackMetadataController::class, 'store'])->name('racks.metadata.store');
    
    // AJAX endpoints for real-time updates
    Route::post('/racks/{rack}/auto-save', [App\Http\Controllers\RackMetadataController::class, 'autoSave'])->name('racks.auto-save');
    Route::get('/racks/{rack}/status', [App\Http\Controllers\RackMetadataController::class, 'status'])->name('racks.status');
    Route::post('/racks/{rack}/preview-how-to', [App\Http\Controllers\RackMetadataController::class, 'previewHowTo'])->name('racks.preview-how-to');
    
    // Auto-save conflict resolution and recovery routes
    Route::get('/racks/{rack}/conflicts', [App\Http\Controllers\RackMetadataController::class, 'getConflicts'])->name('racks.conflicts');
    Route::post('/racks/{rack}/resolve-conflicts', [App\Http\Controllers\RackMetadataController::class, 'resolveConflicts'])->name('racks.resolve-conflicts');
    Route::post('/racks/{rack}/auto-resolve-conflicts', [App\Http\Controllers\RackMetadataController::class, 'autoResolveConflicts'])->name('racks.auto-resolve-conflicts');
    Route::post('/racks/{rack}/connection-recovery', [App\Http\Controllers\RackMetadataController::class, 'handleConnectionRecovery'])->name('racks.connection-recovery');
    
    // Job management and monitoring routes (Priority 1 fixes)
    Route::get('/racks/{rack}/progress', [App\Http\Controllers\RackMetadataController::class, 'progress'])->name('racks.progress');
    Route::post('/racks/{rack}/retry', [App\Http\Controllers\RackMetadataController::class, 'retry'])->name('racks.retry');
    Route::get('/racks/{rack}/job-details', [App\Http\Controllers\RackMetadataController::class, 'jobDetails'])->name('racks.job-details');
    
    // How-to image management routes
    Route::prefix('/racks/{rack}/how-to-images')->name('racks.how-to-images.')->controller(App\Http\Controllers\HowToImageController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'upload')->name('upload');
        Route::post('/preview', 'preview')->name('preview');
        Route::delete('/{filename}', 'delete')->name('delete');
        Route::post('/batch-delete', 'batchDelete')->name('batch-delete');
    });
    
    // Navigation routes
    Route::post('/racks/{rack}/proceed-to-annotation', [App\Http\Controllers\RackMetadataController::class, 'proceedToAnnotation'])->name('racks.proceed-to-annotation');
    Route::post('/racks/{rack}/quick-publish', [App\Http\Controllers\RackMetadataController::class, 'quickPublish'])->name('racks.quick-publish');
    
    // Annotation workflow (unchanged)
    Route::get('/racks/{rack}/annotate', [App\Http\Controllers\RackAnnotationController::class, 'annotate'])->name('racks.annotate');
    Route::post('/racks/{rack}/annotate', [App\Http\Controllers\RackAnnotationController::class, 'saveAnnotations'])->name('racks.annotate.save');
    Route::post('/racks/{rack}/publish', [App\Http\Controllers\RackAnnotationController::class, 'publish'])->name('racks.publish');
});

// Issue Reporting System Routes
Route::prefix('issues')->name('issues.')->group(function () {
    // Public routes
    Route::get('/', [App\Http\Controllers\IssueController::class, 'index'])->name('index');
    Route::get('/create', [App\Http\Controllers\IssueController::class, 'create'])->name('create');
    Route::post('/', [App\Http\Controllers\IssueController::class, 'store'])->name('store');
    Route::get('/{issue}', [App\Http\Controllers\IssueController::class, 'show'])->name('show');
    
    // Authenticated routes
    Route::middleware('auth')->group(function () {
        Route::post('/{issue}/comments', [App\Http\Controllers\IssueController::class, 'addComment'])->name('comments.store');
    });
});

// Admin dashboard and management routes
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    // Base admin route - redirect to analytics dashboard
    Route::get('/', function () {
        return redirect()->route('admin.analytics.dashboard');
    });
    
    // Main admin dashboard
    Route::get('/dashboard', [App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');
    
    // Enhanced Analytics Dashboard Routes
    Route::prefix('analytics')->name('analytics.')->controller(App\Http\Controllers\Admin\EnhancedDashboardController::class)->group(function () {
        Route::get('/', 'index')->name('dashboard');
        Route::get('/api', 'api')->name('api');
        Route::get('/real-time', 'realTime')->name('real-time');
        Route::get('/section/{section}', 'analyticsSection')->name('section');
        Route::post('/export', 'export')->name('export');
        Route::get('/alerts', 'alerts')->name('alerts');
        Route::get('/benchmarks', 'benchmarks')->name('benchmarks');
        
        // Detailed analytics endpoints
        Route::prefix('racks')->name('racks.')->group(function () {
            Route::get('/', 'rackAnalytics')->name('index');
            Route::get('/trends', 'rackAnalytics')->defaults('section', 'trends')->name('trends');
            Route::get('/categories', 'rackAnalytics')->defaults('section', 'categories')->name('categories');
            Route::get('/devices', 'rackAnalytics')->defaults('section', 'devices')->name('devices');
            Route::get('/engagement', 'rackAnalytics')->defaults('section', 'engagement')->name('engagement');
            Route::get('/performance', 'rackAnalytics')->defaults('section', 'performance')->name('performance');
            Route::get('/processing', 'rackAnalytics')->defaults('section', 'processing')->name('processing');
        });
        
        Route::prefix('email')->name('email.')->group(function () {
            Route::get('/', 'emailAnalytics')->name('index');
            Route::get('/newsletter', 'emailAnalytics')->defaults('section', 'newsletter')->name('newsletter');
            Route::get('/transactional', 'emailAnalytics')->defaults('section', 'transactional')->name('transactional');
            Route::get('/deliverability', 'emailAnalytics')->defaults('section', 'deliverability')->name('deliverability');
            Route::get('/trends', 'emailAnalytics')->defaults('section', 'trends')->name('trends');
            Route::get('/subscribers', 'emailAnalytics')->defaults('section', 'subscribers')->name('subscribers');
            Route::get('/automation', 'emailAnalytics')->defaults('section', 'automation')->name('automation');
        });
    });
    
    // Issue management routes
    Route::prefix('issues')->name('issues.')->group(function () {
        Route::get('/', [App\Http\Controllers\IssueController::class, 'adminIndex'])->name('index');
        Route::get('/{issue}', [App\Http\Controllers\IssueController::class, 'adminShow'])->name('show');
        Route::patch('/{issue}', [App\Http\Controllers\IssueController::class, 'update'])->name('update');
    });

    // Newsletter management routes
    Route::prefix('newsletter')->name('newsletter.')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\NewsletterController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\Admin\NewsletterController::class, 'create'])->name('create');
        Route::post('/', [App\Http\Controllers\Admin\NewsletterController::class, 'store'])->name('store');
        Route::get('/{newsletter}', [App\Http\Controllers\Admin\NewsletterController::class, 'show'])->name('show');
        Route::get('/{newsletter}/edit', [App\Http\Controllers\Admin\NewsletterController::class, 'edit'])->name('edit');
        Route::put('/{newsletter}', [App\Http\Controllers\Admin\NewsletterController::class, 'update'])->name('update');
        Route::get('/{newsletter}/preview', [App\Http\Controllers\Admin\NewsletterController::class, 'preview'])->name('preview');
        Route::post('/{newsletter}/test', [App\Http\Controllers\Admin\NewsletterController::class, 'sendTest'])->name('test');
        Route::post('/{newsletter}/send', [App\Http\Controllers\Admin\NewsletterController::class, 'send'])->name('send');
        Route::delete('/{newsletter}', [App\Http\Controllers\Admin\NewsletterController::class, 'destroy'])->name('destroy');
        Route::post('/blog/{blogPost}', [App\Http\Controllers\Admin\NewsletterController::class, 'createFromBlog'])->name('create-from-blog');
    });
    
    // Phase 3 Infrastructure: Feature Flags Management
    Route::prefix('feature-flags')->name('feature-flags.')->controller(App\Http\Controllers\Admin\FeatureFlagController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/api', 'api')->name('api');
        Route::post('/', 'store')->name('store');
        Route::put('/{flagName}', 'update')->name('update');
        Route::delete('/{flagName}', 'destroy')->name('destroy');
        Route::post('/bulk-update', 'bulkUpdate')->name('bulk-update');
        Route::post('/{flagName}/toggle', 'toggle')->name('toggle');
        Route::get('/analytics', 'analytics')->name('analytics');
        Route::get('/export', 'export')->name('export');
    });
    
    // Phase 3 Infrastructure: Monitoring Dashboard
    Route::prefix('monitoring')->name('monitoring.')->controller(App\Http\Controllers\Admin\MonitoringDashboardController::class)->group(function () {
        Route::get('/', 'index')->name('dashboard');
        Route::get('/api', 'api')->name('api');
        Route::get('/system-health', 'systemHealth')->name('system-health');
        Route::get('/performance', 'performanceMetrics')->name('performance');
        Route::get('/security', 'securityMetrics')->name('security');
        Route::get('/users', 'userAnalytics')->name('users');
        Route::get('/content', 'contentMetrics')->name('content');
        Route::get('/infrastructure', 'infrastructureStatus')->name('infrastructure');
        Route::get('/alerts', 'activeAlerts')->name('alerts');
        Route::get('/uptime', 'uptimeStats')->name('uptime');
    });
});

// Quick report routes for specific racks
Route::get('/racks/{rack}/report', [App\Http\Controllers\IssueController::class, 'create'])
    ->name('racks.report');

// Temporary public demo routes for testing
Route::get('/public-demos/rack-tree-vertical', function () {
    return view('demos.rack-tree-vertical');
})->name('public-demos.rack-tree-vertical');

Route::get('/public-demos/rack-tree-horizontal', function () {
    return view('demos.rack-tree-horizontal');
})->name('public-demos.rack-tree-horizontal');

// About Page
Route::get('/about', function () {
    $seoMetaTags = app('App\Services\SeoService')->getMetaTags([
        'title' => 'About - Ableton Cookbook',
        'description' => 'Learn about Ableton Cookbook, a community platform for sharing Ableton Live racks and music production workflows.',
        'keywords' => 'about ableton cookbook, music production community, ableton live racks, workflow sharing',
    ]);
    
    return view('about', compact('seoMetaTags'));
})->name('about');

