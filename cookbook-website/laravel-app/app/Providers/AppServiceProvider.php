<?php

namespace App\Providers;

use App\Services\SeoService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Event;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register SEO Service as singleton
        $this->app->singleton(SeoService::class, function ($app) {
            return new SeoService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS in production
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        // Database query logging for performance monitoring
        if (app()->environment('local', 'staging')) {
            DB::listen(function ($query) {
                if ($query->time > 100) { // Log slow queries > 100ms
                    Log::warning('Slow Query Detected', [
                        'sql' => $query->sql,
                        'bindings' => $query->bindings,
                        'time' => $query->time . 'ms',
                        'connection' => $query->connectionName,
                    ]);
                }
            });
        }

        // Share SEO service with all views
        View::share('seoService', app(SeoService::class));

        // Track user logins
        Event::listen(
            \Illuminate\Auth\Events\Login::class,
            function ($event) {
                $event->user->update(['last_login_at' => now()]);
            }
        );
        
        // Add view composers for SEO data
        View::composer('*', function ($view) {
            // Add default structured data for website
            if (!$view->offsetExists('structuredData')) {
                $view->with('structuredData', app(SeoService::class)->getStructuredData('website'));
            }
        });
    }
}
