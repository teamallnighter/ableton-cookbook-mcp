<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\DrumRackAnalyzer\DrumRackAnalyzerService;

class DrumRackAnalyzerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(DrumRackAnalyzerService::class, function ($app) {
            return new DrumRackAnalyzerService();
        });

        $this->app->alias(DrumRackAnalyzerService::class, 'drum-rack-analyzer');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration file
        $this->publishes([
            __DIR__.'/../../config/drum-rack-analyzer.php' => config_path('drum-rack-analyzer.php'),
        ], 'config');

        // Load routes
        $this->loadRoutesFrom(__DIR__.'/../../routes/drum-rack-analyzer.php');
    }
}