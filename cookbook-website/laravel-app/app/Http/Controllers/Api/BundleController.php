<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bundle;
use App\Models\BundleItem;
use App\Http\Requests\StoreBundleRequest;
use App\Http\Requests\UpdateBundleRequest;
use App\Http\Requests\AddBundleItemRequest;
use App\Services\BundleManager\BundleManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * @OA\Tag(
 *     name="Bundles",
 *     description="Production bundles containing racks, presets, and sessions"
 * )
 */
class BundleController extends Controller
{
    public function __construct(
        private BundleManager $bundleManager
    ) {
        $this->middleware('auth:sanctum')->except(['index', 'show']);
        $this->middleware('throttle:60,1')->only(['index', 'show']);
        $this->middleware('throttle:30,1')->only(['store', 'update', 'destroy']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/bundles",
     *     summary="List bundles",
     *     description="Get a paginated list of published bundles with filtering and sorting options",
     *     operationId="getBundles",
     *     tags={"Bundles"},
     *     @OA\Parameter(
     *         name="filter[bundle_type]",
     *         in="query",
     *         description="Filter by bundle type",
     *         @OA\Schema(type="string", enum={"production", "template", "sample_pack", "tutorial", "remix_stems"})
     *     ),
     *     @OA\Parameter(
     *         name="filter[genre]",
     *         in="query",
     *         description="Filter by genre",
     *         @OA\Schema(type="string", example="house")
     *     ),
     *     @OA\Parameter(
     *         name="filter[difficulty_level]",
     *         in="query",
     *         description="Filter by difficulty level",
     *         @OA\Schema(type="string", enum={"beginner", "intermediate", "advanced"})
     *     ),
     *     @OA\Parameter(
     *         name="filter[is_free]",
     *         in="query",
     *         description="Filter by free/paid status",
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="Sort field",
     *         @OA\Schema(type="string", enum={"created_at", "updated_at", "downloads_count", "average_rating", "title", "total_items_count"})
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
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Bundle")),
     *             @OA\Property(property="links", type="object"),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $bundles = QueryBuilder::for(Bundle::class)
            ->published()
            ->with(['user:id,name', 'tags:id,name'])
            ->allowedFilters([
                AllowedFilter::exact('bundle_type'),
                AllowedFilter::exact('genre'),
                AllowedFilter::exact('difficulty_level'),
                AllowedFilter::exact('is_free'),
                AllowedFilter::partial('title'),
                'user.name'
            ])
            ->allowedSorts([
                'created_at',
                'updated_at', 
                'downloads_count',
                'average_rating',
                'title',
                'total_items_count'
            ])
            ->defaultSort('-created_at')
            ->paginate($request->get('per_page', 15))
            ->withQueryString();

        return response()->json($bundles);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/bundles",
     *     summary="Create a new bundle",
     *     description="Create a new production bundle",
     *     operationId="createBundle",
     *     tags={"Bundles"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(property="title", type="string", example="Progressive House Production Pack"),
     *                 @OA\Property(property="description", type="string", example="Complete progressive house production bundle"),
     *                 @OA\Property(property="bundle_type", type="string", enum={"production", "template", "sample_pack", "tutorial", "remix_stems"}),
     *                 @OA\Property(property="genre", type="string", example="house"),
     *                 @OA\Property(property="difficulty_level", type="string", enum={"beginner", "intermediate", "advanced"}),
     *                 @OA\Property(property="category", type="string", example="production"),
     *                 @OA\Property(property="is_free", type="boolean", example=true),
     *                 @OA\Property(property="bundle_price", type="number", format="float", example=29.99),
     *                 @OA\Property(property="tags", type="array", @OA\Items(type="string"), example={"house", "production", "template"})
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Bundle created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Bundle")
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(StoreBundleRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['user_id'] = auth()->id();

        // Create bundle
        $bundle = $this->bundleManager->createBundle($validated);

        // Attach tags if provided
        if (!empty($validated['tags'])) {
            $this->attachTags($bundle, $validated['tags']);
        }

        return response()->json($bundle->load(['user:id,name', 'tags:id,name']), 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/bundles/{bundle}",
     *     summary="Get a bundle",
     *     description="Get detailed information about a specific bundle",
     *     operationId="getBundle",
     *     tags={"Bundles"},
     *     @OA\Parameter(
     *         name="bundle",
     *         in="path",
     *         description="Bundle ID or UUID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(ref="#/components/schemas/Bundle")
     *     ),
     *     @OA\Response(response=404, description="Bundle not found")
     * )
     */
    public function show(string $bundle): JsonResponse
    {
        $bundle = Bundle::where('uuid', $bundle)
            ->orWhere('id', $bundle)
            ->published()
            ->with([
                'user:id,name,avatar_path',
                'tags:id,name',
                'items.bundlable',
                'comments.user:id,name,avatar_path'
            ])
            ->firstOrFail();

        // Increment view count
        $bundle->increment('views_count');

        return response()->json($bundle);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/bundles/{bundle}",
     *     summary="Update a bundle",
     *     description="Update bundle information (owner only)",
     *     operationId="updateBundle",
     *     tags={"Bundles"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="bundle",
     *         in="path",
     *         description="Bundle ID or UUID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(property="title", type="string", example="Updated Bundle Title"),
     *                 @OA\Property(property="description", type="string", example="Updated description"),
     *                 @OA\Property(property="bundle_type", type="string", example="production"),
     *                 @OA\Property(property="genre", type="string", example="house"),
     *                 @OA\Property(property="difficulty_level", type="string", example="intermediate"),
     *                 @OA\Property(property="how_to_article", type="string", example="# How to use this bundle\n\nDetailed instructions..."),
     *                 @OA\Property(property="tags", type="array", @OA\Items(type="string"), example={"house", "updated"})
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bundle updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Bundle")
     *     ),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Bundle not found")
     * )
     */
    public function update(UpdateBundleRequest $request, string $bundle): JsonResponse
    {
        $bundle = Bundle::where('uuid', $bundle)
            ->orWhere('id', $bundle)
            ->firstOrFail();

        $this->authorize('update', $bundle);

        $validated = $request->validated();

        // Update basic fields
        $updateData = array_filter($validated, fn($key) => in_array($key, [
            'title', 'description', 'bundle_type', 'genre', 'difficulty_level',
            'category', 'how_to_article', 'is_free', 'bundle_price',
            'allow_individual_downloads', 'require_full_download'
        ]), ARRAY_FILTER_USE_KEY);

        if (isset($updateData['title'])) {
            $updateData['slug'] = Str::slug($updateData['title']);
        }

        if (isset($updateData['how_to_article'])) {
            $updateData['how_to_updated_at'] = now();
        }

        $bundle->update($updateData);

        // Update tags if provided
        if (array_key_exists('tags', $validated)) {
            $this->attachTags($bundle, $validated['tags'] ?? []);
        }

        return response()->json($bundle->load(['user:id,name', 'tags:id,name']));
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/bundles/{bundle}",
     *     summary="Delete a bundle",
     *     description="Delete a bundle (owner only)",
     *     operationId="deleteBundle",
     *     tags={"Bundles"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="bundle",
     *         in="path",
     *         description="Bundle ID or UUID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response=204, description="Bundle deleted successfully"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Bundle not found")
     * )
     */
    public function destroy(string $bundle): JsonResponse
    {
        $bundle = Bundle::where('uuid', $bundle)
            ->orWhere('id', $bundle)
            ->firstOrFail();

        $this->authorize('delete', $bundle);

        // Delete archive if exists
        if ($bundle->archive_path) {
            \Illuminate\Support\Facades\Storage::disk('private')->delete($bundle->archive_path);
        }

        // Delete preview media
        if ($bundle->preview_image_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($bundle->preview_image_path);
        }

        if ($bundle->preview_audio_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($bundle->preview_audio_path);
        }

        if ($bundle->cover_image_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($bundle->cover_image_path);
        }

        $bundle->delete();

        return response()->json(null, 204);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/bundles/{bundle}/items",
     *     summary="Add item to bundle",
     *     description="Add a rack, preset, or session to a bundle",
     *     operationId="addBundleItem",
     *     tags={"Bundles"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="bundle",
     *         in="path",
     *         description="Bundle ID or UUID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(property="item_type", type="string", enum={"rack", "preset", "session"}),
     *                 @OA\Property(property="item_id", type="integer", example=1),
     *                 @OA\Property(property="position", type="integer", example=1),
     *                 @OA\Property(property="section", type="string", example="Intro"),
     *                 @OA\Property(property="notes", type="string", example="Use for the main lead line"),
     *                 @OA\Property(property="usage_instructions", type="string", example="Load into track 3"),
     *                 @OA\Property(property="is_required", type="boolean", example=false)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Item added successfully",
     *         @OA\JsonContent(ref="#/components/schemas/BundleItem")
     *     ),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Bundle or item not found")
     * )
     */
    public function addItem(AddBundleItemRequest $request, string $bundle): JsonResponse
    {
        $bundle = Bundle::where('uuid', $bundle)
            ->orWhere('id', $bundle)
            ->firstOrFail();

        $this->authorize('update', $bundle);

        $validated = $request->validated();

        $bundleItem = $this->bundleManager->addItemToBundle(
            $bundle,
            $validated['item_type'],
            $validated['item_id'],
            $validated['position'] ?? 0,
            $validated['section'] ?? null,
            $validated['notes'] ?? null,
            $validated['usage_instructions'] ?? null,
            $validated['is_required'] ?? false
        );

        return response()->json($bundleItem->load('bundlable'), 201);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/bundles/{bundle}/items/{item}",
     *     summary="Remove item from bundle",
     *     description="Remove an item from a bundle",
     *     operationId="removeBundleItem",
     *     tags={"Bundles"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="bundle",
     *         in="path",
     *         description="Bundle ID or UUID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="item",
     *         in="path",
     *         description="Bundle item ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=204, description="Item removed successfully"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Bundle or item not found")
     * )
     */
    public function removeItem(string $bundle, int $item): JsonResponse
    {
        $bundle = Bundle::where('uuid', $bundle)
            ->orWhere('id', $bundle)
            ->firstOrFail();

        $this->authorize('update', $bundle);

        $this->bundleManager->removeItemFromBundle($bundle, $item);

        return response()->json(null, 204);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/bundles/{bundle}/download",
     *     summary="Download a bundle",
     *     description="Get a temporary download URL for the complete bundle archive",
     *     operationId="downloadBundle",
     *     tags={"Bundles"},
     *     @OA\Parameter(
     *         name="bundle",
     *         in="path",
     *         description="Bundle ID or UUID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Download URL generated",
     *         @OA\JsonContent(
     *             @OA\Property(property="download_url", type="string", format="uri"),
     *             @OA\Property(property="expires_at", type="string", format="date-time"),
     *             @OA\Property(property="filename", type="string"),
     *             @OA\Property(property="archive_size", type="string"),
     *             @OA\Property(property="contents_summary", type="string")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Bundle not found")
     * )
     */
    public function download(string $bundle): JsonResponse
    {
        $bundle = Bundle::where('uuid', $bundle)
            ->orWhere('id', $bundle)
            ->published()
            ->firstOrFail();

        if (!$bundle->isReadyForDownload()) {
            return response()->json(['error' => 'Bundle not ready for download'], 400);
        }

        // Record download
        $this->bundleManager->recordBundleDownload($bundle, auth()->id());

        $downloadUrl = $this->bundleManager->getBundleDownloadUrl($bundle);
        $expiresAt = now()->addMinutes(10);

        return response()->json([
            'download_url' => $downloadUrl,
            'expires_at' => $expiresAt,
            'filename' => "bundle_{$bundle->slug}.zip",
            'archive_size' => $bundle->getFormattedArchiveSizeAttribute(),
            'contents_summary' => $bundle->getContentsSummaryAttribute(),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/bundles/{bundle}/items/{item}/download",
     *     summary="Download bundle item",
     *     description="Get a temporary download URL for an individual item in the bundle",
     *     operationId="downloadBundleItem",
     *     tags={"Bundles"},
     *     @OA\Parameter(
     *         name="bundle",
     *         in="path",
     *         description="Bundle ID or UUID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="item",
     *         in="path",
     *         description="Bundle item ID",
     *         required=true,
     *         @OA\Schema(type="integer")
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
     *     @OA\Response(response=400, description="Individual downloads not allowed"),
     *     @OA\Response(response=404, description="Bundle or item not found")
     * )
     */
    public function downloadItem(string $bundle, int $item): JsonResponse
    {
        $bundle = Bundle::where('uuid', $bundle)
            ->orWhere('id', $bundle)
            ->published()
            ->firstOrFail();

        $bundleItem = BundleItem::where('bundle_id', $bundle->id)
            ->where('id', $item)
            ->with('bundlable')
            ->firstOrFail();

        if (!$bundleItem->canDownloadIndividually()) {
            return response()->json(['error' => 'Individual downloads not allowed for this item'], 400);
        }

        // Record download
        $this->bundleManager->recordItemDownload($bundleItem, auth()->id());

        $downloadUrl = $this->bundleManager->getItemDownloadUrl($bundleItem);
        $expiresAt = now()->addMinutes(5);

        return response()->json([
            'download_url' => $downloadUrl,
            'expires_at' => $expiresAt,
            'filename' => $bundleItem->bundlable->original_filename,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/bundles/statistics",
     *     summary="Get bundle statistics",
     *     description="Get overall bundle platform statistics",
     *     operationId="getBundleStatistics",
     *     tags={"Bundles"},
     *     @OA\Response(
     *         response=200,
     *         description="Bundle statistics",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="total_bundles", type="integer"),
     *             @OA\Property(property="public_bundles", type="integer"),
     *             @OA\Property(property="featured_bundles", type="integer"),
     *             @OA\Property(property="total_downloads", type="integer"),
     *             @OA\Property(property="bundles_by_type", type="object")
     *         )
     *     )
     * )
     */
    public function statistics(): JsonResponse
    {
        $statistics = $this->bundleManager->getBundleStatistics();

        return response()->json($statistics);
    }

    /**
     * Attach tags to bundle
     */
    private function attachTags(Bundle $bundle, array $tagNames): void
    {
        $tagIds = [];

        foreach ($tagNames as $tagName) {
            $tag = \App\Models\Tag::firstOrCreate(['name' => strtolower(trim($tagName))]);
            $tagIds[] = $tag->id;
        }

        $bundle->tags()->sync($tagIds);
    }
}