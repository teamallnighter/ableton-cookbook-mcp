<?php

namespace App\Console\Commands;

use App\Services\D2DiagramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class D2HealthCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'd2:health {--detailed : Show detailed output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check D2 diagram service health and configuration';

    /**
     * Execute the console command.
     */
    public function handle(D2DiagramService $d2Service): int
    {
        $this->info('🔍 D2 Diagram Service Health Check');
        $this->newLine();

        $checks = [];
        $allPassed = true;

        // 1. Check configuration
        $this->info('Checking configuration...');
        $config = config('d2');

        if ($config) {
            $checks['Configuration'] = '✅ Loaded';
            if ($this->option('detailed')) {
                $this->table(
                    ['Setting', 'Value'],
                    [
                        ['Enabled', $config['enabled'] ? 'Yes' : 'No'],
                        ['Binary Path', $config['binary_path']],
                        ['Use System Path', $config['use_system_path'] ? 'Yes' : 'No'],
                        ['Temp Path', $config['temp_path']],
                        ['Timeout', $config['timeout'] . ' seconds'],
                        ['Cache Enabled', $config['cache_enabled'] ? 'Yes' : 'No'],
                        ['Cache TTL', $config['cache_ttl'] . ' seconds'],
                    ]
                );
            }
        } else {
            $checks['Configuration'] = '❌ Not found';
            $allPassed = false;
        }

        // 2. Check if D2 is enabled
        if (!config('d2.enabled')) {
            $this->warn('⚠️  D2 is disabled in configuration');
            $checks['Service Status'] = '⚠️  Disabled';
        } else {
            $checks['Service Status'] = '✅ Enabled';
        }

        // 3. Check D2 binary availability
        $this->info('Checking D2 binary...');
        if ($d2Service->isAvailable()) {
            $checks['D2 Binary'] = '✅ Available';

            // Get version
            $binaryPath = config('d2.use_system_path') ? 'd2' : config('d2.binary_path');
            exec($binaryPath . ' --version 2>&1', $versionOutput);
            if (!empty($versionOutput)) {
                $version = implode(' ', $versionOutput);
                $this->info("  Version: {$version}");
            }
        } else {
            $checks['D2 Binary'] = '❌ Not available';
            $allPassed = false;
            $this->error('  D2 binary not found or not executable');
            $this->line('  Install D2 with: curl -fsSL https://d2lang.com/install.sh | sh -s --');
        }

        // 4. Check temp directory
        $this->info('Checking temp directory...');
        $tempPath = config('d2.temp_path', storage_path('app/temp/d2'));

        if (!is_dir($tempPath)) {
            // Try to create it
            if (@mkdir($tempPath, 0755, true)) {
                $checks['Temp Directory'] = '✅ Created';
                $this->info("  Created temp directory: {$tempPath}");
            } else {
                $checks['Temp Directory'] = '❌ Cannot create';
                $allPassed = false;
                $this->error("  Failed to create: {$tempPath}");
            }
        } elseif (is_writable($tempPath)) {
            $checks['Temp Directory'] = '✅ Writable';
            if ($this->option('detailed')) {
                $this->info("  Path: {$tempPath}");
            }
        } else {
            $checks['Temp Directory'] = '❌ Not writable';
            $allPassed = false;
            $this->error("  Directory not writable: {$tempPath}");
        }

        // 5. Check cache connection
        $this->info('Checking cache...');
        if (config('d2.cache_enabled')) {
            try {
                Cache::put('d2:health:test', 'test', 10);
                if (Cache::get('d2:health:test') === 'test') {
                    $checks['Cache'] = '✅ Working';
                    Cache::forget('d2:health:test');
                } else {
                    $checks['Cache'] = '⚠️  Read issue';
                }
            } catch (\Exception $e) {
                $checks['Cache'] = '❌ Not working';
                if ($this->option('detailed')) {
                    $this->error('  ' . $e->getMessage());
                }
            }
        } else {
            $checks['Cache'] = '⚠️  Disabled';
        }

        // 6. Test diagram generation
        if ($d2Service->isAvailable() && config('d2.enabled')) {
            $this->info('Testing diagram generation...');

            $testD2 = "Test: Hello World";
            $testDiagram = $d2Service->renderDiagram($testD2, 'svg');

            if ($testDiagram && str_contains($testDiagram, '<svg')) {
                $checks['Diagram Generation'] = '✅ Working';
                $size = strlen($testDiagram);
                $this->info("  Generated SVG ({$size} bytes)");

                // Test ASCII generation
                $testAscii = $d2Service->renderDiagram($testD2, 'ascii');
                if ($testAscii) {
                    $checks['ASCII Generation'] = '✅ Working';
                    if ($this->option('detailed')) {
                        $this->info("  ASCII output:");
                        $this->line($testAscii);
                    }
                } else {
                    $checks['ASCII Generation'] = '❌ Failed';
                }
            } else {
                $checks['Diagram Generation'] = '❌ Failed';
                $allPassed = false;
            }
        }

        // 7. Check web server user permissions (if not in CLI context)
        if (function_exists('posix_getpwuid')) {
            $this->info('Checking permissions...');
            $currentUser = posix_getpwuid(posix_geteuid())['name'];
            $this->info("  Current user: {$currentUser}");

            if ($currentUser === 'www-data') {
                $checks['Web User'] = '✅ Running as www-data';
            } else {
                $checks['Web User'] = '⚠️  Not www-data (' . $currentUser . ')';
                if (app()->environment('production')) {
                    $this->warn('  In production, ensure www-data can execute D2');
                }
            }
        }

        // Display summary
        $this->newLine();
        $this->info('📊 Health Check Summary:');
        $this->table(['Check', 'Status'], array_map(fn($k, $v) => [$k, $v], array_keys($checks), array_values($checks)));

        // Overall status
        $this->newLine();
        if ($allPassed) {
            $this->info('✅ All health checks passed! D2 is ready for use.');
            return Command::SUCCESS;
        } else {
            $this->error('❌ Some health checks failed. Please review the issues above.');

            // Provide helpful next steps
            $this->newLine();
            $this->info('Next steps:');
            $this->line('1. Run the deployment script: sudo bash scripts/deploy-d2-ubuntu.sh');
            $this->line('2. Add D2 configuration to your .env file');
            $this->line('3. Clear Laravel cache: php artisan config:clear');
            $this->line('4. Run this health check again');

            return Command::FAILURE;
        }
    }
}