<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Preset;
use App\Http\Requests\StorePresetRequest;
use App\Http\Requests\UpdatePresetRequest;
use App\Services\AbletonPresetAnalyzer\AbletonPresetAnalyzer;
use App\Jobs\ProcessPresetFileJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * @OA\Tag(
 *     name="Presets",
 *     description="Ableton Live device presets management"
 * )
 */
class PresetController extends Controller
{
    public function __construct(
        private AbletonPresetAnalyzer $presetAnalyzer
    ) {
        $this->middleware('auth:sanctum')->except(['index', 'show']);
        $this->middleware('throttle:60,1')->only(['index', 'show']);
        $this->middleware('throttle:30,1')->only(['store', 'update', 'destroy']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/presets",
     *     summary="List presets",
     *     description="Get a paginated list of published presets with filtering and sorting options",
     *     operationId="getPresets",
     *     tags={"Presets"},
     *     @OA\Parameter(
     *         name="filter[preset_type]",
     *         in="query",
     *         description="Filter by preset type",
     *         @OA\Schema(type="string", enum={"instrument", "audio_effect", "midi_effect"})
     *     ),
     *     @OA\Parameter(
     *         name="filter[device_name]",
     *         in="query",
     *         description="Filter by device name",
     *         @OA\Schema(type="string", example="Wavetable")
     *     ),
     *     @OA\Parameter(
     *         name="filter[category]",
     *         in="query",
     *         description="Filter by category",
     *         @OA\Schema(type="string", example="lead")
     *     ),
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="Sort field",
     *         @OA\Schema(type="string", enum={"created_at", "updated_at", "downloads_count", "average_rating", "title"})
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Preset")),
     *             @OA\Property(property="links", type="object"),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $presets = QueryBuilder::for(Preset::class)
            ->published()
            ->with(['user:id,name', 'tags:id,name'])
            ->allowedFilters([
                AllowedFilter::exact('preset_type'),
                AllowedFilter::exact('device_name'),
                AllowedFilter::exact('category'),
                AllowedFilter::partial('title'),
                'user.name'
            ])
            ->allowedSorts([
                'created_at',
                'updated_at', 
                'downloads_count',
                'average_rating',
                'title'
            ])
            ->defaultSort('-created_at')
            ->paginate($request->get('per_page', 15))
            ->withQueryString();

        return response()->json($presets);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/presets",
     *     summary="Create a new preset",
     *     description="Upload and create a new Ableton device preset",
     *     operationId="createPreset",
     *     tags={"Presets"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="title", type="string", example="Epic Lead Preset"),
     *                 @OA\Property(property="description", type="string", example="A powerful lead preset for Wavetable"),
     *                 @OA\Property(property="category", type="string", example="lead"),
     *                 @OA\Property(property="file", type="string", format="binary", description="Preset file (.adv)"),
     *                 @OA\Property(property="tags", type="array", @OA\Items(type="string"), example={"lead", "wavetable", "electronic"})
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Preset created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Preset")
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(StorePresetRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $file = $request->file('file');

        // Store file
        $filePath = $this->storePresetFile($file);
        
        // Create preset record
        $preset = Preset::create([
            'uuid' => Str::uuid(),
            'user_id' => auth()->id(),
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'slug' => Str::slug($validated['title']),
            'file_path' => $filePath,
            'file_hash' => hash_file('sha256', $file->getPathname()),
            'file_size' => $file->getSize(),
            'original_filename' => $file->getClientOriginalName(),
            'category' => $validated['category'] ?? null,
            'status' => 'pending',
        ]);

        // Attach tags if provided
        if (!empty($validated['tags'])) {
            $this->attachTags($preset, $validated['tags']);
        }

        // Queue background analysis
        ProcessPresetFileJob::dispatch($preset);

        return response()->json($preset->load(['user:id,name', 'tags:id,name']), 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/presets/{preset}",
     *     summary="Get a preset",
     *     description="Get detailed information about a specific preset",
     *     operationId="getPreset",
     *     tags={"Presets"},
     *     @OA\Parameter(
     *         name="preset",
     *         in="path",
     *         description="Preset ID or UUID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(ref="#/components/schemas/Preset")
     *     ),
     *     @OA\Response(response=404, description="Preset not found")
     * )
     */
    public function show(string $preset): JsonResponse
    {
        $preset = Preset::where('uuid', $preset)
            ->orWhere('id', $preset)
            ->published()
            ->with([
                'user:id,name,avatar_path',
                'tags:id,name',
                'comments.user:id,name,avatar_path'
            ])
            ->firstOrFail();

        // Increment view count
        $preset->increment('views_count');

        return response()->json($preset);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/presets/{preset}",
     *     summary="Update a preset",
     *     description="Update preset information (owner only)",
     *     operationId="updatePreset",
     *     tags={"Presets"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="preset",
     *         in="path",
     *         description="Preset ID or UUID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(property="title", type="string", example="Updated Lead Preset"),
     *                 @OA\Property(property="description", type="string", example="Updated description"),
     *                 @OA\Property(property="category", type="string", example="lead"),
     *                 @OA\Property(property="how_to_article", type="string", example="# How to use this preset\n\nDetailed instructions..."),
     *                 @OA\Property(property="tags", type="array", @OA\Items(type="string"), example={"lead", "updated"})
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Preset updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Preset")
     *     ),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Preset not found")
     * )
     */
    public function update(UpdatePresetRequest $request, string $preset): JsonResponse
    {
        $preset = Preset::where('uuid', $preset)
            ->orWhere('id', $preset)
            ->firstOrFail();

        $this->authorize('update', $preset);

        $validated = $request->validated();

        // Update basic fields
        $updateData = array_filter($validated, fn($key) => in_array($key, [
            'title', 'description', 'category', 'how_to_article'
        ]), ARRAY_FILTER_USE_KEY);

        if (isset($updateData['title'])) {
            $updateData['slug'] = Str::slug($updateData['title']);
        }

        if (isset($updateData['how_to_article'])) {
            $updateData['how_to_updated_at'] = now();
        }

        $preset->update($updateData);

        // Update tags if provided
        if (array_key_exists('tags', $validated)) {
            $this->attachTags($preset, $validated['tags'] ?? []);
        }

        return response()->json($preset->load(['user:id,name', 'tags:id,name']));
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/presets/{preset}",
     *     summary="Delete a preset",
     *     description="Delete a preset (owner only)",
     *     operationId="deletePreset",
     *     tags={"Presets"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="preset",
     *         in="path",
     *         description="Preset ID or UUID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response=204, description="Preset deleted successfully"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Preset not found")
     * )
     */
    public function destroy(string $preset): JsonResponse
    {
        $preset = Preset::where('uuid', $preset)
            ->orWhere('id', $preset)
            ->firstOrFail();

        $this->authorize('delete', $preset);

        // Delete file from storage
        if ($preset->file_path) {
            Storage::disk('private')->delete($preset->file_path);
        }

        // Delete preview media
        if ($preset->preview_image_path) {
            Storage::disk('public')->delete($preset->preview_image_path);
        }

        if ($preset->preview_audio_path) {
            Storage::disk('public')->delete($preset->preview_audio_path);
        }

        $preset->delete();

        return response()->json(null, 204);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/presets/{preset}/download",
     *     summary="Download a preset",
     *     description="Get a temporary download URL for the preset file",
     *     operationId="downloadPreset",
     *     tags={"Presets"},
     *     @OA\Parameter(
     *         name="preset",
     *         in="path",
     *         description="Preset ID or UUID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Download URL generated",
     *         @OA\JsonContent(
     *             @OA\Property(property="download_url", type="string", format="uri"),
     *             @OA\Property(property="expires_at", type="string", format="date-time"),
     *             @OA\Property(property="filename", type="string")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Preset not found")
     * )
     */
    public function download(string $preset): JsonResponse
    {
        $preset = Preset::where('uuid', $preset)
            ->orWhere('id', $preset)
            ->published()
            ->firstOrFail();

        // Record download
        $preset->recordDownload(auth()->user());

        $downloadUrl = $preset->getDownloadUrl();
        $expiresAt = now()->addMinutes(5);

        return response()->json([
            'download_url' => $downloadUrl,
            'expires_at' => $expiresAt,
            'filename' => $preset->original_filename,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/presets/categories",
     *     summary="Get preset categories",
     *     description="Get available preset categories",
     *     operationId="getPresetCategories",
     *     tags={"Presets"},
     *     @OA\Response(
     *         response=200,
     *         description="Available categories",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="categories", type="object")
     *         )
     *     )
     * )
     */
    public function categories(): JsonResponse
    {
        $categories = AbletonPresetAnalyzer::getPresetCategories();

        return response()->json(['categories' => $categories]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/presets/device-types",
     *     summary="Get device types",
     *     description="Get available device types that presets can be created for",
     *     operationId="getPresetDeviceTypes",
     *     tags={"Presets"},
     *     @OA\Response(
     *         response=200,
     *         description="Available device types",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="device_types", type="array", @OA\Items(type="string"))
     *         )
     *     )
     * )
     */
    public function deviceTypes(): JsonResponse
    {
        // Get unique device names from existing presets
        $deviceTypes = Preset::published()
            ->whereNotNull('device_name')
            ->distinct()
            ->pluck('device_name')
            ->sort()
            ->values();

        return response()->json(['device_types' => $deviceTypes]);
    }

    /**
     * Store preset file in private storage
     */
    private function storePresetFile(UploadedFile $file): string
    {
        $filename = Str::uuid() . '.adv';
        $path = "presets/{$filename}";

        Storage::disk('private')->putFileAs('presets', $file, $filename);

        return $path;
    }

    /**
     * Attach tags to preset
     */
    private function attachTags(Preset $preset, array $tagNames): void
    {
        $tagIds = [];

        foreach ($tagNames as $tagName) {
            $tag = \App\Models\Tag::firstOrCreate(['name' => strtolower(trim($tagName))]);
            $tagIds[] = $tag->id;
        }

        $preset->tags()->sync($tagIds);
    }
}