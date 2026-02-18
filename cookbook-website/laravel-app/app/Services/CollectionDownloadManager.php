<?php

namespace App\Services;

use App\Models\EnhancedCollection;
use App\Models\CollectionDownload;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Service for managing collection downloads with organized folder structures
 */
class CollectionDownloadManager
{
    protected string $downloadsDisk = 'private';
    protected string $downloadPath = 'downloads/collections';
    protected int $downloadExpirationHours = 24;

    public function __construct(
        protected CollectionAnalyticsService $analyticsService
    ) {}

    /**
     * Create download for collection
     */
    public function createCollectionDownload(
        EnhancedCollection $collection,
        ?User $user = null,
        array $options = []
    ): CollectionDownload {
        $download = CollectionDownload::create([
            'user_id' => $user?->id,
            'downloadable_type' => EnhancedCollection::class,
            'downloadable_id' => $collection->id,
            'download_token' => Str::random(64),
            'download_type' => $options['download_type'] ?? 'full_collection',
            'selected_items' => $options['selected_items'] ?? null,
            'selected_sections' => $options['selected_sections'] ?? null,
            'format' => $options['format'] ?? 'zip',
            'include_metadata' => $options['include_metadata'] ?? true,
            'include_how_to' => $options['include_how_to'] ?? true,
            'organize_by_type' => $options['organize_by_type'] ?? true,
            'folder_structure_preference' => $options['folder_structure'] ?? null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'expires_at' => now()->addHours($this->downloadExpirationHours),
            'status' => 'pending',
        ]);

        // Queue the archive creation
        dispatch(new \App\Jobs\CreateCollectionArchiveJob($download));

        return $download;
    }

    /**
     * Process collection download (called by job)
     */
    public function processCollectionDownload(CollectionDownload $download): void
    {
        try {
            $download->update(['status' => 'processing']);

            $collection = $download->downloadable;
            
            // Create archive based on download type
            $archivePath = match ($download->download_type) {
                'full_collection' => $this->createFullCollectionArchive($collection, $download),
                'individual_item' => $this->createIndividualItemArchive($collection, $download),
                'section' => $this->createSectionArchive($collection, $download),
                'custom_selection' => $this->createCustomSelectionArchive($collection, $download),
                default => throw new \Exception('Invalid download type'),
            };

            // Update download record
            $archiveSize = Storage::disk($this->downloadsDisk)->size($archivePath);
            $archiveHash = md5_file(Storage::disk($this->downloadsDisk)->path($archivePath));

            $download->update([
                'status' => 'ready',
                'archive_path' => $archivePath,
                'archive_name' => basename($archivePath),
                'archive_size' => $archiveSize,
                'archive_hash' => $archiveHash,
            ]);

            // Record analytics
            $this->analyticsService->recordDownload($collection, $download);

        } catch (\Exception $e) {
            $download->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Create full collection archive
     */
    protected function createFullCollectionArchive(
        EnhancedCollection $collection,
        CollectionDownload $download
    ): string {
        $zip = new ZipArchive();
        $archiveName = $this->sanitizeFilename($collection->title) . '_' . now()->format('Y-m-d') . '.zip';
        $archivePath = $this->downloadPath . '/' . $archiveName;
        $fullPath = Storage::disk($this->downloadsDisk)->path($archivePath);

        // Ensure directory exists
        Storage::disk($this->downloadsDisk)->makeDirectory($this->downloadPath);

        if ($zip->open($fullPath, ZipArchive::CREATE) !== TRUE) {
            throw new \Exception('Cannot create zip file');
        }

        // Create folder structure
        $baseFolderName = $this->sanitizeFilename($collection->title);
        
        // Add collection metadata
        if ($download->include_metadata) {
            $this->addCollectionMetadata($zip, $collection, $baseFolderName);
        }

        // Add how-to article
        if ($download->include_how_to && $collection->hasHowToArticle()) {
            $this->addHowToArticle($zip, $collection, $baseFolderName);
        }

        // Add items organized by type or section
        if ($download->organize_by_type) {
            $this->addItemsOrganizedByType($zip, $collection, $baseFolderName);
        } else {
            $this->addItemsOrganizedBySection($zip, $collection, $baseFolderName);
        }

        $zip->close();

        return $archivePath;
    }

    /**
     * Create archive for individual item
     */
    protected function createIndividualItemArchive(
        EnhancedCollection $collection,
        CollectionDownload $download
    ): string {
        $itemIds = $download->selected_items ?? [];
        $items = $collection->items()->whereIn('id', $itemIds)->with('collectable')->get();

        if ($items->isEmpty()) {
            throw new \Exception('No items found for download');
        }

        $zip = new ZipArchive();
        $archiveName = $this->sanitizeFilename($collection->title) . '_items_' . now()->format('Y-m-d') . '.zip';
        $archivePath = $this->downloadPath . '/' . $archiveName;
        $fullPath = Storage::disk($this->downloadsDisk)->path($archivePath);

        Storage::disk($this->downloadsDisk)->makeDirectory($this->downloadPath);

        if ($zip->open($fullPath, ZipArchive::CREATE) !== TRUE) {
            throw new \Exception('Cannot create zip file');
        }

        $baseFolderName = $this->sanitizeFilename($collection->title) . '/Selected Items';

        foreach ($items as $item) {
            $this->addItemToArchive($zip, $item, $baseFolderName);
        }

        $zip->close();

        return $archivePath;
    }

    /**
     * Create archive for specific sections
     */
    protected function createSectionArchive(
        EnhancedCollection $collection,
        CollectionDownload $download
    ): string {
        $sections = $download->selected_sections ?? [];
        $items = $collection->items()->whereIn('section', $sections)->with('collectable')->get();

        if ($items->isEmpty()) {
            throw new \Exception('No items found in selected sections');
        }

        $zip = new ZipArchive();
        $archiveName = $this->sanitizeFilename($collection->title) . '_sections_' . now()->format('Y-m-d') . '.zip';
        $archivePath = $this->downloadPath . '/' . $archiveName;
        $fullPath = Storage::disk($this->downloadsDisk)->path($archivePath);

        Storage::disk($this->downloadsDisk)->makeDirectory($this->downloadPath);

        if ($zip->open($fullPath, ZipArchive::CREATE) !== TRUE) {
            throw new \Exception('Cannot create zip file');
        }

        $baseFolderName = $this->sanitizeFilename($collection->title);

        // Group items by section
        $itemsBySection = $items->groupBy('section');

        foreach ($itemsBySection as $section => $sectionItems) {
            $sectionFolderName = $baseFolderName . '/' . $this->sanitizeFilename($section ?? 'Uncategorized');
            
            foreach ($sectionItems as $item) {
                $this->addItemToArchive($zip, $item, $sectionFolderName);
            }
        }

        $zip->close();

        return $archivePath;
    }

    /**
     * Create custom selection archive
     */
    protected function createCustomSelectionArchive(
        EnhancedCollection $collection,
        CollectionDownload $download
    ): string {
        // This could handle complex custom selections with specific organization
        return $this->createIndividualItemArchive($collection, $download);
    }

    /**
     * Add collection metadata to archive
     */
    protected function addCollectionMetadata(ZipArchive $zip, EnhancedCollection $collection, string $basePath): void
    {
        $metadata = [
            'Collection Information' => [
                'Title' => $collection->title,
                'Type' => $collection->collection_type_display,
                'Difficulty' => $collection->difficulty_level,
                'Creator' => $collection->user->name,
                'Items Count' => $collection->items_count,
                'Estimated Time' => $collection->formatted_completion_time,
                'Created' => $collection->created_at->format('Y-m-d H:i:s'),
            ],
        ];

        if ($collection->genre) {
            $metadata['Collection Information']['Genre'] = $collection->genre;
        }

        if ($collection->required_packs) {
            $metadata['Requirements']['Ableton Packs'] = implode(', ', $collection->required_packs);
        }

        if ($collection->required_plugins) {
            $metadata['Requirements']['Plugins'] = implode(', ', $collection->required_plugins);
        }

        if ($collection->min_ableton_version) {
            $metadata['Requirements']['Min Ableton Version'] = $collection->min_ableton_version;
        }

        // Create README content
        $readmeContent = $this->generateReadmeContent($collection, $metadata);
        
        $zip->addFromString($basePath . '/README.md', $readmeContent);
        $zip->addFromString($basePath . '/collection_info.json', json_encode($metadata, JSON_PRETTY_PRINT));
    }

    /**
     * Add how-to article to archive
     */
    protected function addHowToArticle(ZipArchive $zip, EnhancedCollection $collection, string $basePath): void
    {
        $markdownContent = $collection->how_to_article;
        $htmlContent = $collection->html_how_to;

        $zip->addFromString($basePath . '/How-To-Guide.md', $markdownContent);
        
        if ($htmlContent) {
            $htmlTemplate = $this->generateHtmlTemplate($collection->title, $htmlContent);
            $zip->addFromString($basePath . '/How-To-Guide.html', $htmlTemplate);
        }
    }

    /**
     * Add items organized by type
     */
    protected function addItemsOrganizedByType(ZipArchive $zip, EnhancedCollection $collection, string $basePath): void
    {
        $itemsByType = $collection->items()->with('collectable')->get()->groupBy('collectable_type');

        foreach ($itemsByType as $type => $items) {
            $typeName = $this->getTypeDisplayName($type);
            $typeFolderPath = $basePath . '/' . $typeName;

            foreach ($items as $item) {
                $this->addItemToArchive($zip, $item, $typeFolderPath);
            }
        }
    }

    /**
     * Add items organized by section
     */
    protected function addItemsOrganizedBySection(ZipArchive $zip, EnhancedCollection $collection, string $basePath): void
    {
        $itemsBySection = $collection->items()->with('collectable')->get()->groupBy('section');

        foreach ($itemsBySection as $section => $items) {
            $sectionName = $this->sanitizeFilename($section ?? 'Uncategorized');
            $sectionFolderPath = $basePath . '/' . $sectionName;

            foreach ($items as $item) {
                $this->addItemToArchive($zip, $item, $sectionFolderPath);
            }
        }
    }

    /**
     * Add individual item to archive
     */
    protected function addItemToArchive(ZipArchive $zip, $item, string $basePath): void
    {
        // Handle external links
        if ($item->isExternalLink()) {
            $linkContent = $this->generateLinkFile($item);
            $zip->addFromString($basePath . '/' . $item->getDownloadFilename() . '.url', $linkContent);
            return;
        }

        // Handle physical files
        $sourcePath = $item->getDownloadPath();
        
        if (!$sourcePath || !Storage::exists($sourcePath)) {
            // Create a notice file if item is not available
            $noticeContent = "Item not available: {$item->item_title}\nReason: File not found or not downloadable";
            $zip->addFromString($basePath . '/' . $item->getDownloadFilename() . '.txt', $noticeContent);
            return;
        }

        $fileName = $item->download_filename ?: $item->getDownloadFilename();
        $targetPath = $basePath . '/' . $fileName;

        // Add file to archive
        $zip->addFile(Storage::path($sourcePath), $targetPath);

        // Add item metadata if requested
        if ($item->description || $item->learning_notes) {
            $metadataContent = $this->generateItemMetadata($item);
            $metadataFileName = pathinfo($fileName, PATHINFO_FILENAME) . '_info.md';
            $zip->addFromString($basePath . '/' . $metadataFileName, $metadataContent);
        }
    }

    /**
     * Generate README content
     */
    protected function generateReadmeContent(EnhancedCollection $collection, array $metadata): string
    {
        $content = "# {$collection->title}\n\n";
        $content .= "{$collection->description}\n\n";

        $content .= "## Collection Information\n\n";
        foreach ($metadata['Collection Information'] as $key => $value) {
            $content .= "- **{$key}**: {$value}\n";
        }

        if (isset($metadata['Requirements'])) {
            $content .= "\n## Requirements\n\n";
            foreach ($metadata['Requirements'] as $key => $value) {
                $content .= "- **{$key}**: {$value}\n";
            }
        }

        $content .= "\n## Contents\n\n";
        
        $sections = $collection->getSections();
        if (!empty($sections)) {
            foreach ($sections as $section) {
                $sectionItems = $collection->items()->where('section', $section)->get();
                $content .= "### {$section}\n\n";
                
                foreach ($sectionItems as $item) {
                    $content .= "- {$item->item_title}";
                    if ($item->description) {
                        $content .= " - {$item->description}";
                    }
                    $content .= "\n";
                }
                $content .= "\n";
            }
        } else {
            foreach ($collection->items as $item) {
                $content .= "- {$item->item_title}";
                if ($item->description) {
                    $content .= " - {$item->description}";
                }
                $content .= "\n";
            }
        }

        $content .= "\n---\n\n";
        $content .= "Downloaded from Ableton Cookbook\n";
        $content .= "Generated on: " . now()->format('Y-m-d H:i:s') . "\n";

        return $content;
    }

    /**
     * Generate HTML template for how-to guide
     */
    protected function generateHtmlTemplate(string $title, string $htmlContent): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title} - How To Guide</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; max-width: 800px; margin: 0 auto; padding: 20px; }
        h1, h2, h3 { color: #333; }
        code { background: #f4f4f4; padding: 2px 4px; border-radius: 3px; }
        pre { background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto; }
        blockquote { border-left: 4px solid #ddd; margin: 0; padding-left: 20px; color: #666; }
        img { max-width: 100%; height: auto; }
    </style>
</head>
<body>
    <h1>{$title} - How To Guide</h1>
    {$htmlContent}
    <hr>
    <p><small>Downloaded from Ableton Cookbook on " . now()->format('Y-m-d') . "</small></p>
</body>
</html>
HTML;
    }

    /**
     * Generate link file for external URLs
     */
    protected function generateLinkFile($item): string
    {
        $title = $item->item_title;
        $url = $item->external_url;
        $description = $item->item_description ?: '';

        return <<<LINK
[InternetShortcut]
URL={$url}
IconIndex=0

; {$title}
; {$description}
LINK;
    }

    /**
     * Generate item metadata
     */
    protected function generateItemMetadata($item): string
    {
        $content = "# {$item->item_title}\n\n";
        
        if ($item->description) {
            $content .= "**Description**: {$item->description}\n\n";
        }

        if ($item->learning_notes) {
            $content .= "## Learning Notes\n\n{$item->learning_notes}\n\n";
        }

        if ($item->estimated_duration) {
            $content .= "**Estimated Time**: {$item->formatted_duration}\n\n";
        }

        return $content;
    }

    /**
     * Get download URL for completed archive
     */
    public function getDownloadUrl(CollectionDownload $download): string
    {
        return route('collections.download.serve', ['token' => $download->download_token]);
    }

    /**
     * Serve download file
     */
    public function serveDownload(string $token): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $download = CollectionDownload::where('download_token', $token)
            ->where('status', 'ready')
            ->where('expires_at', '>', now())
            ->firstOrFail();

        // Record download
        $download->update([
            'status' => 'downloaded',
            'downloaded_at' => now(),
        ]);

        $download->increment('download_attempts');

        // Update collection download count
        $download->downloadable->recordDownload($download->user, $download->ip_address);

        return Storage::disk($this->downloadsDisk)->download(
            $download->archive_path,
            $download->archive_name
        );
    }

    /**
     * Clean up expired downloads
     */
    public function cleanupExpiredDownloads(): int
    {
        $expiredDownloads = CollectionDownload::where('expires_at', '<', now())
            ->where('status', '!=', 'downloaded')
            ->get();

        $count = 0;
        
        foreach ($expiredDownloads as $download) {
            if ($download->archive_path && Storage::disk($this->downloadsDisk)->exists($download->archive_path)) {
                Storage::disk($this->downloadsDisk)->delete($download->archive_path);
            }
            
            $download->update(['status' => 'expired']);
            $count++;
        }

        return $count;
    }

    /**
     * Get type display name
     */
    protected function getTypeDisplayName(string $type): string
    {
        $typeMap = [
            'App\\Models\\Rack' => 'Racks',
            'App\\Models\\Preset' => 'Presets',
            'App\\Models\\Session' => 'Sessions',
            'App\\Models\\Bundle' => 'Bundles',
            'App\\Models\\BlogPost' => 'Articles',
        ];

        return $typeMap[$type] ?? 'Items';
    }

    /**
     * Sanitize filename for download
     */
    protected function sanitizeFilename(string $filename): string
    {
        // Remove or replace invalid filename characters
        $filename = preg_replace('/[\/\\\:\*\?\"\<\>\|]/', '-', $filename);
        $filename = preg_replace('/\s+/', ' ', $filename);
        $filename = trim($filename, ' .-');
        
        return substr($filename, 0, 200); // Limit length
    }
}