<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\DrumRackAnalyzerService;

class DrumRackAnalyzerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register the drum rack analyzer as a singleton
        $this->app->singleton(DrumRackAnalyzerService::class, function ($app) {
            return new DrumRackAnalyzerService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register config file
        $this->publishes([
            __DIR__.'/../../config/drum-rack-analyzer.php' => config_path('drum-rack-analyzer.php'),
        ], 'drum-rack-analyzer-config');

        // Load config
        $this->mergeConfigFrom(
            __DIR__.'/../../config/drum-rack-analyzer.php', 'drum-rack-analyzer'
        );
    }
}