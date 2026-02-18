<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateApiDocs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:generate-docs {--force : Force regeneration even if docs exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate API documentation with environment-specific server URLs';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Generating API documentation...');

        // First, generate the standard Swagger documentation
        $this->call('l5-swagger:generate');

        // Get the generated documentation path
        $docPath = storage_path('api-docs/api-docs.json');
        $publicDocPath = public_path('api-docs.json');

        if (!File::exists($docPath)) {
            $this->error('Failed to generate API documentation');
            return self::FAILURE;
        }

        // Read the generated documentation
        $docContent = json_decode(File::get($docPath), true);

        if (!$docContent) {
            $this->error('Failed to parse generated API documentation');
            return self::FAILURE;
        }

        // Update server configuration based on environment
        $appUrl = config('app.url', 'http://localhost:8000');
        $appEnv = config('app.env', 'local');

        // Configure servers based on environment
        if ($appEnv === 'production') {
            $docContent['servers'] = [
                [
                    'url' => $appUrl,
                    'description' => 'Production API Server'
                ]
            ];
        } else {
            $docContent['servers'] = [
                [
                    'url' => $appUrl,
                    'description' => 'Development API Server'
                ],
                [
                    'url' => 'https://ableton.recipes',
                    'description' => 'Production API Server'
                ]
            ];
        }

        // Save the updated documentation
        File::put($docPath, json_encode($docContent, JSON_PRETTY_PRINT));
        
        // Also update the public version
        File::put($publicDocPath, json_encode($docContent, JSON_PRETTY_PRINT));

        $this->info('API documentation generated successfully!');
        $this->line('Server URLs configured for environment: ' . $appEnv);
        
        foreach ($docContent['servers'] as $server) {
            $this->line('  - ' . $server['url'] . ' (' . $server['description'] . ')');
        }

        $this->line('Documentation available at: /api/docs');

        return self::SUCCESS;
    }
}