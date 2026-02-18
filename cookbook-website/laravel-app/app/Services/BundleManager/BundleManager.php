<?php

namespace App\Services\BundleManager;

use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\Rack;
use App\Models\Preset;
use App\Models\Session;
use Exception;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Bundle Manager Service
 * Handles creation, management, and packaging of production bundles
 * that can contain racks, presets, and sessions together.
 */
class BundleManager
{
    private const ARCHIVE_DISK = 'private';
    private const MAX_ARCHIVE_SIZE = 500 * 1024 * 1024; // 500MB max bundle size
    private const BUNDLE_STRUCTURE = [
        'racks' => 'Racks',
        'presets' => 'Presets', 
        'sessions' => 'Sessions',
        'documentation' => 'Documentation',
        'samples' => 'Samples',
        'media' => 'Media'
    ];

    /**
     * Create a new bundle with items
     */
    public function createBundle(array $bundleData, array $items = []): Bundle
    {
        try {
            Log::info("Creating new bundle", ['title' => $bundleData['title']]);

            // Create the bundle
            $bundle = Bundle::create([
                'uuid' => Str::uuid(),
                'user_id' => $bundleData['user_id'],
                'title' => $bundleData['title'],
                'description' => $bundleData['description'] ?? null,
                'slug' => Str::slug($bundleData['title']),
                'bundle_type' => $bundleData['bundle_type'] ?? 'production',
                'genre' => $bundleData['genre'] ?? null,
                'category' => $bundleData['category'] ?? null,
                'difficulty_level' => $bundleData['difficulty_level'] ?? 'intermediate',
                'allow_individual_downloads' => $bundleData['allow_individual_downloads'] ?? true,
                'is_free' => $bundleData['is_free'] ?? true,
                'status' => 'draft',
            ]);

            // Add items to the bundle
            if (!empty($items)) {
                $this->addItemsToBundle($bundle, $items);
            }

            // Update counters
            $this->updateBundleCounters($bundle);

            Log::info("Bundle created successfully", ['bundle_id' => $bundle->id]);
            return $bundle;

        } catch (Exception $e) {
            Log::error("Failed to create bundle", [
                'error' => $e->getMessage(),
                'data' => $bundleData
            ]);
            throw $e;
        }
    }

    /**
     * Add items to a bundle
     */
    public function addItemsToBundle(Bundle $bundle, array $items): void
    {
        foreach ($items as $item) {
            $this->addItemToBundle(
                $bundle,
                $item['type'], // 'rack', 'preset', or 'session'
                $item['id'],
                $item['position'] ?? 0,
                $item['section'] ?? null,
                $item['notes'] ?? null,
                $item['usage_instructions'] ?? null,
                $item['is_required'] ?? false
            );
        }
    }

    /**
     * Add a single item to a bundle
     */
    public function addItemToBundle(
        Bundle $bundle,
        string $itemType,
        int $itemId,
        int $position = 0,
        ?string $section = null,
        ?string $notes = null,
        ?string $usageInstructions = null,
        bool $isRequired = false
    ): BundleItem {
        // Validate item type and get model
        $model = $this->getModelForType($itemType);
        $item = $model::findOrFail($itemId);

        // Check if user owns the item or it's public
        if ($item->user_id !== $bundle->user_id && !$item->is_public) {
            throw new Exception("Cannot add private item to bundle");
        }

        // Create bundle item
        $bundleItem = BundleItem::create([
            'bundle_id' => $bundle->id,
            'bundlable_type' => get_class($item),
            'bundlable_id' => $item->id,
            'position' => $position,
            'section' => $section,
            'notes' => $notes,
            'usage_instructions' => $usageInstructions,
            'is_required' => $isRequired,
        ]);

        // Update bundle counters
        $this->updateBundleCounters($bundle);

        // Mark archive as outdated
        $this->markArchiveOutdated($bundle);

        return $bundleItem;
    }

    /**
     * Remove item from bundle
     */
    public function removeItemFromBundle(Bundle $bundle, int $bundleItemId): void
    {
        $bundleItem = BundleItem::where('bundle_id', $bundle->id)
            ->where('id', $bundleItemId)
            ->firstOrFail();

        $bundleItem->delete();

        // Update counters
        $this->updateBundleCounters($bundle);

        // Mark archive as outdated
        $this->markArchiveOutdated($bundle);
    }

    /**
     * Reorder bundle items
     */
    public function reorderBundleItems(Bundle $bundle, array $itemPositions): void
    {
        foreach ($itemPositions as $itemId => $position) {
            BundleItem::where('bundle_id', $bundle->id)
                ->where('id', $itemId)
                ->update(['position' => $position]);
        }

        // Mark archive as outdated
        $this->markArchiveOutdated($bundle);
    }

    /**
     * Build archive for bundle download
     */
    public function buildBundleArchive(Bundle $bundle, bool $forceRebuild = false): string
    {
        try {
            Log::info("Building bundle archive", ['bundle_id' => $bundle->id]);

            // Check if archive exists and is current
            if (!$forceRebuild && $this->isArchiveCurrent($bundle)) {
                Log::info("Using existing archive", ['bundle_id' => $bundle->id]);
                return $bundle->archive_path;
            }

            // Generate archive filename
            $archiveName = "bundle_{$bundle->uuid}.zip";
            $archivePath = "bundles/archives/{$archiveName}";
            $tempPath = storage_path("app/temp/{$archiveName}");

            // Ensure temp directory exists
            $tempDir = dirname($tempPath);
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            // Create ZIP archive
            $zip = new ZipArchive();
            if ($zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
                throw new Exception("Cannot create ZIP archive: {$tempPath}");
            }

            // Add bundle documentation
            $this->addBundleDocumentation($zip, $bundle);

            // Add bundle items by type
            $this->addRacksToArchive($zip, $bundle);
            $this->addPresetsToArchive($zip, $bundle);
            $this->addSessionsToArchive($zip, $bundle);

            // Add media files
            $this->addMediaToArchive($zip, $bundle);

            // Close the ZIP file
            $zip->close();

            // Check file size
            $archiveSize = filesize($tempPath);
            if ($archiveSize > self::MAX_ARCHIVE_SIZE) {
                unlink($tempPath);
                throw new Exception("Bundle archive exceeds maximum size limit");
            }

            // Move to permanent storage
            $archiveContent = file_get_contents($tempPath);
            Storage::disk(self::ARCHIVE_DISK)->put($archivePath, $archiveContent);
            unlink($tempPath);

            // Update bundle record
            $bundle->update([
                'archive_path' => $archivePath,
                'archive_hash' => hash('sha256', $archiveContent),
                'archive_size' => $archiveSize,
                'archive_updated_at' => now(),
            ]);

            Log::info("Bundle archive created successfully", [
                'bundle_id' => $bundle->id,
                'archive_size' => $archiveSize
            ]);

            return $archivePath;

        } catch (Exception $e) {
            Log::error("Failed to build bundle archive", [
                'bundle_id' => $bundle->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get download URL for bundle archive
     */
    public function getBundleDownloadUrl(Bundle $bundle): string
    {
        // Build archive if it doesn't exist or is outdated
        if (!$this->isArchiveCurrent($bundle)) {
            $this->buildBundleArchive($bundle);
        }

        // Generate temporary signed URL
        return Storage::disk(self::ARCHIVE_DISK)->temporaryUrl(
            $bundle->archive_path,
            now()->addMinutes(10)
        );
    }

    /**
     * Get download URL for individual bundle item
     */
    public function getItemDownloadUrl(BundleItem $bundleItem): string
    {
        $item = $bundleItem->bundlable;
        
        // Check if individual downloads are allowed
        if (!$bundleItem->is_downloadable_individually) {
            throw new Exception("Individual download not allowed for this item");
        }

        // Generate temporary signed URL for the item
        return Storage::disk('private')->temporaryUrl(
            $item->file_path,
            now()->addMinutes(5)
        );
    }

    /**
     * Record bundle download
     */
    public function recordBundleDownload(Bundle $bundle, ?int $userId = null): void
    {
        // Increment download counter
        $bundle->increment('downloads_count');

        // Could add detailed download tracking here if needed
        Log::info("Bundle download recorded", [
            'bundle_id' => $bundle->id,
            'user_id' => $userId
        ]);
    }

    /**
     * Record individual item download
     */
    public function recordItemDownload(BundleItem $bundleItem, ?int $userId = null): void
    {
        // Increment individual download counter
        $bundleItem->increment('individual_downloads_count');

        // Also record download on the actual item
        $item = $bundleItem->bundlable;
        if (method_exists($item, 'recordDownload')) {
            $item->recordDownload($userId ? \App\Models\User::find($userId) : null);
        }

        Log::info("Bundle item download recorded", [
            'bundle_item_id' => $bundleItem->id,
            'user_id' => $userId
        ]);
    }

    /**
     * Update bundle counters
     */
    private function updateBundleCounters(Bundle $bundle): void
    {
        $counters = BundleItem::where('bundle_id', $bundle->id)
            ->selectRaw('
                bundlable_type,
                COUNT(*) as count
            ')
            ->groupBy('bundlable_type')
            ->get()
            ->keyBy('bundlable_type');

        $racksCount = $counters[Rack::class]->count ?? 0;
        $presetsCount = $counters[Preset::class]->count ?? 0;
        $sessionsCount = $counters[Session::class]->count ?? 0;

        $bundle->update([
            'racks_count' => $racksCount,
            'presets_count' => $presetsCount,
            'sessions_count' => $sessionsCount,
            'total_items_count' => $racksCount + $presetsCount + $sessionsCount,
        ]);
    }

    /**
     * Mark archive as outdated
     */
    private function markArchiveOutdated(Bundle $bundle): void
    {
        if ($bundle->archive_path) {
            $bundle->update([
                'archive_updated_at' => null
            ]);
        }
    }

    /**
     * Check if archive is current
     */
    private function isArchiveCurrent(Bundle $bundle): bool
    {
        if (!$bundle->archive_path || !$bundle->archive_updated_at) {
            return false;
        }

        // Check if archive file exists
        if (!Storage::disk(self::ARCHIVE_DISK)->exists($bundle->archive_path)) {
            return false;
        }

        // Archive is current if it was updated after the bundle was last modified
        return $bundle->archive_updated_at >= $bundle->updated_at;
    }

    /**
     * Add bundle documentation to archive
     */
    private function addBundleDocumentation(ZipArchive $zip, Bundle $bundle): void
    {
        // Create README content
        $readme = $this->generateBundleReadme($bundle);
        $zip->addFromString('README.md', $readme);

        // Add how-to article if present
        if ($bundle->how_to_article) {
            $zip->addFromString('TUTORIAL.md', $bundle->how_to_article);
        }

        // Add bundle info JSON
        $bundleInfo = [
            'title' => $bundle->title,
            'description' => $bundle->description,
            'type' => $bundle->bundle_type,
            'genre' => $bundle->genre,
            'difficulty' => $bundle->difficulty_level,
            'created_at' => $bundle->created_at->toISOString(),
            'items_count' => $bundle->total_items_count,
            'creator' => $bundle->user->name ?? 'Unknown',
        ];
        $zip->addFromString('bundle-info.json', json_encode($bundleInfo, JSON_PRETTY_PRINT));
    }

    /**
     * Add racks to archive
     */
    private function addRacksToArchive(ZipArchive $zip, Bundle $bundle): void
    {
        $rackItems = BundleItem::where('bundle_id', $bundle->id)
            ->where('bundlable_type', Rack::class)
            ->with('bundlable')
            ->orderBy('position')
            ->get();

        if ($rackItems->isEmpty()) return;

        foreach ($rackItems as $item) {
            $rack = $item->bundlable;
            $filename = self::BUNDLE_STRUCTURE['racks'] . '/' . $this->sanitizeFilename($rack->title) . '.adg';
            
            if (Storage::disk('private')->exists($rack->file_path)) {
                $content = Storage::disk('private')->get($rack->file_path);
                $zip->addFromString($filename, $content);
            }
        }
    }

    /**
     * Add presets to archive
     */
    private function addPresetsToArchive(ZipArchive $zip, Bundle $bundle): void
    {
        $presetItems = BundleItem::where('bundle_id', $bundle->id)
            ->where('bundlable_type', Preset::class)
            ->with('bundlable')
            ->orderBy('position')
            ->get();

        if ($presetItems->isEmpty()) return;

        foreach ($presetItems as $item) {
            $preset = $item->bundlable;
            $filename = self::BUNDLE_STRUCTURE['presets'] . '/' . $this->sanitizeFilename($preset->title) . '.adv';
            
            if (Storage::disk('private')->exists($preset->file_path)) {
                $content = Storage::disk('private')->get($preset->file_path);
                $zip->addFromString($filename, $content);
            }
        }
    }

    /**
     * Add sessions to archive
     */
    private function addSessionsToArchive(ZipArchive $zip, Bundle $bundle): void
    {
        $sessionItems = BundleItem::where('bundle_id', $bundle->id)
            ->where('bundlable_type', Session::class)
            ->with('bundlable')
            ->orderBy('position')
            ->get();

        if ($sessionItems->isEmpty()) return;

        foreach ($sessionItems as $item) {
            $session = $item->bundlable;
            $filename = self::BUNDLE_STRUCTURE['sessions'] . '/' . $this->sanitizeFilename($session->title) . '.als';
            
            if (Storage::disk('private')->exists($session->file_path)) {
                $content = Storage::disk('private')->get($session->file_path);
                $zip->addFromString($filename, $content);
            }
        }
    }

    /**
     * Add media files to archive
     */
    private function addMediaToArchive(ZipArchive $zip, Bundle $bundle): void
    {
        // Add bundle cover image
        if ($bundle->cover_image_path && Storage::disk('public')->exists($bundle->cover_image_path)) {
            $extension = pathinfo($bundle->cover_image_path, PATHINFO_EXTENSION);
            $zip->addFile(
                Storage::disk('public')->path($bundle->cover_image_path),
                self::BUNDLE_STRUCTURE['media'] . "/cover.{$extension}"
            );
        }

        // Add preview audio
        if ($bundle->preview_audio_path && Storage::disk('public')->exists($bundle->preview_audio_path)) {
            $extension = pathinfo($bundle->preview_audio_path, PATHINFO_EXTENSION);
            $zip->addFile(
                Storage::disk('public')->path($bundle->preview_audio_path),
                self::BUNDLE_STRUCTURE['media'] . "/preview.{$extension}"
            );
        }
    }

    /**
     * Generate bundle README content
     */
    private function generateBundleReadme(Bundle $bundle): string
    {
        $readme = "# {$bundle->title}\n\n";
        
        if ($bundle->description) {
            $readme .= "{$bundle->description}\n\n";
        }

        $readme .= "## Bundle Contents\n\n";
        
        if ($bundle->racks_count > 0) {
            $readme .= "- **{$bundle->racks_count} Racks** - Ableton Live device racks (.adg files)\n";
        }
        
        if ($bundle->presets_count > 0) {
            $readme .= "- **{$bundle->presets_count} Presets** - Device presets (.adv files)\n";
        }
        
        if ($bundle->sessions_count > 0) {
            $readme .= "- **{$bundle->sessions_count} Sessions** - Ableton Live sessions (.als files)\n";
        }

        $readme .= "\n## Bundle Information\n\n";
        $readme .= "- **Type**: " . ucfirst(str_replace('_', ' ', $bundle->bundle_type)) . "\n";
        
        if ($bundle->genre) {
            $readme .= "- **Genre**: {$bundle->genre}\n";
        }
        
        if ($bundle->difficulty_level) {
            $readme .= "- **Difficulty**: " . ucfirst($bundle->difficulty_level) . "\n";
        }

        $readme .= "- **Created**: {$bundle->created_at->format('F j, Y')}\n";
        
        if ($bundle->user) {
            $readme .= "- **Creator**: {$bundle->user->name}\n";
        }

        $readme .= "\n## How to Use\n\n";
        $readme .= "1. Extract this archive to a location of your choice\n";
        $readme .= "2. Import .adg files (racks) by dragging them into Ableton Live\n";
        $readme .= "3. Import .adv files (presets) by placing them in your User Library\n";
        $readme .= "4. Open .als files (sessions) directly in Ableton Live\n";

        if ($bundle->how_to_article) {
            $readme .= "\nSee TUTORIAL.md for detailed instructions and tips.\n";
        }

        $readme .= "\n---\n";
        $readme .= "*Downloaded from Ableton Cookbook*\n";

        return $readme;
    }

    /**
     * Get model class for item type
     */
    private function getModelForType(string $type): string
    {
        return match($type) {
            'rack' => Rack::class,
            'preset' => Preset::class,  
            'session' => Session::class,
            default => throw new Exception("Unknown item type: {$type}")
        };
    }

    /**
     * Sanitize filename for archive
     */
    private function sanitizeFilename(string $filename): string
    {
        // Remove or replace invalid characters
        $filename = preg_replace('/[^a-zA-Z0-9\s\-_.]/', '', $filename);
        $filename = preg_replace('/\s+/', '_', $filename);
        $filename = trim($filename, '_-');
        
        return $filename ?: 'untitled';
    }

    /**
     * Get bundle statistics
     */
    public function getBundleStatistics(): array
    {
        return [
            'total_bundles' => Bundle::count(),
            'public_bundles' => Bundle::where('is_public', true)->count(),
            'featured_bundles' => Bundle::where('is_featured', true)->count(),
            'total_downloads' => Bundle::sum('downloads_count'),
            'bundles_by_type' => Bundle::selectRaw('bundle_type, COUNT(*) as count')
                ->groupBy('bundle_type')
                ->pluck('count', 'bundle_type')
                ->toArray(),
        ];
    }
}