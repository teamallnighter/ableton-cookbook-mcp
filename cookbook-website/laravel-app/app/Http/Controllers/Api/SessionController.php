<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Session;
use App\Http\Requests\StoreSessionRequest;
use App\Http\Requests\UpdateSessionRequest;
use App\Services\AbletonSessionAnalyzer\AbletonSessionAnalyzer;
use App\Jobs\ProcessSessionFileJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * @OA\Tag(
 *     name="Sessions",
 *     description="Ableton Live session files management"
 * )
 */
class SessionController extends Controller
{
    public function __construct(
        private AbletonSessionAnalyzer $sessionAnalyzer
    ) {
        $this->middleware('auth:sanctum')->except(['index', 'show']);
        $this->middleware('throttle:60,1')->only(['index', 'show']);
        $this->middleware('throttle:30,1')->only(['store', 'update', 'destroy']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/sessions",
     *     summary="List sessions",
     *     description="Get a paginated list of published sessions with filtering and sorting options",
     *     operationId="getSessions",
     *     tags={"Sessions"},
     *     @OA\Parameter(
     *         name="filter[category]",
     *         in="query",
     *         description="Filter by category",
     *         @OA\Schema(type="string", enum={"production", "template", "remix", "stems", "loop_pack", "sample_pack", "tutorial"})
     *     ),
     *     @OA\Parameter(
     *         name="filter[genre]",
     *         in="query",
     *         description="Filter by genre",
     *         @OA\Schema(type="string", example="house")
     *     ),
     *     @OA\Parameter(
     *         name="filter[tempo]",
     *         in="query",
     *         description="Filter by tempo range (e.g., 120-128)",
     *         @OA\Schema(type="string", example="120-128")
     *     ),
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="Sort field",
     *         @OA\Schema(type="string", enum={"created_at", "updated_at", "downloads_count", "average_rating", "title", "tempo"})
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
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Session")),
     *             @OA\Property(property="links", type="object"),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $sessions = QueryBuilder::for(Session::class)
            ->published()
            ->with(['user:id,name', 'tags:id,name'])
            ->allowedFilters([
                AllowedFilter::exact('category'),
                AllowedFilter::exact('genre'),
                AllowedFilter::partial('title'),
                AllowedFilter::callback('tempo', function ($query, $value) {
                    if (strpos($value, '-') !== false) {
                        [$min, $max] = explode('-', $value);
                        $query->whereBetween('tempo', [(int)$min, (int)$max]);
                    } else {
                        $query->where('tempo', (int)$value);
                    }
                }),
                'user.name'
            ])
            ->allowedSorts([
                'created_at',
                'updated_at', 
                'downloads_count',
                'average_rating',
                'title',
                'tempo',
                'track_count'
            ])
            ->defaultSort('-created_at')
            ->paginate($request->get('per_page', 15))
            ->withQueryString();

        return response()->json($sessions);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/sessions",
     *     summary="Create a new session",
     *     description="Upload and create a new Ableton Live session",
     *     operationId="createSession",
     *     tags={"Sessions"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="title", type="string", example="Progressive House Template"),
     *                 @OA\Property(property="description", type="string", example="Complete progressive house production template"),
     *                 @OA\Property(property="category", type="string", example="template"),
     *                 @OA\Property(property="genre", type="string", example="house"),
     *                 @OA\Property(property="file", type="string", format="binary", description="Session file (.als)"),
     *                 @OA\Property(property="tags", type="array", @OA\Items(type="string"), example={"house", "template", "progressive"})
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Session created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Session")
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(StoreSessionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $file = $request->file('file');

        // Store file
        $filePath = $this->storeSessionFile($file);
        
        // Create session record
        $session = Session::create([
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
            'genre' => $validated['genre'] ?? null,
            'status' => 'pending',
        ]);

        // Attach tags if provided
        if (!empty($validated['tags'])) {
            $this->attachTags($session, $validated['tags']);
        }

        // Queue background analysis
        ProcessSessionFileJob::dispatch($session);

        return response()->json($session->load(['user:id,name', 'tags:id,name']), 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/sessions/{session}",
     *     summary="Get a session",
     *     description="Get detailed information about a specific session",
     *     operationId="getSession",
     *     tags={"Sessions"},
     *     @OA\Parameter(
     *         name="session",
     *         in="path",
     *         description="Session ID or UUID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(ref="#/components/schemas/Session")
     *     ),
     *     @OA\Response(response=404, description="Session not found")
     * )
     */
    public function show(string $session): JsonResponse
    {
        $session = Session::where('uuid', $session)
            ->orWhere('id', $session)
            ->published()
            ->with([
                'user:id,name,avatar_path',
                'tags:id,name',
                'comments.user:id,name,avatar_path'
            ])
            ->firstOrFail();

        // Increment view count
        $session->increment('views_count');

        return response()->json($session);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/sessions/{session}",
     *     summary="Update a session",
     *     description="Update session information (owner only)",
     *     operationId="updateSession",
     *     tags={"Sessions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="session",
     *         in="path",
     *         description="Session ID or UUID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(property="title", type="string", example="Updated Session Title"),
     *                 @OA\Property(property="description", type="string", example="Updated description"),
     *                 @OA\Property(property="category", type="string", example="template"),
     *                 @OA\Property(property="genre", type="string", example="house"),
     *                 @OA\Property(property="how_to_article", type="string", example="# How to use this session\n\nDetailed instructions..."),
     *                 @OA\Property(property="tags", type="array", @OA\Items(type="string"), example={"house", "updated"})
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Session updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/Session")
     *     ),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Session not found")
     * )
     */
    public function update(UpdateSessionRequest $request, string $session): JsonResponse
    {
        $session = Session::where('uuid', $session)
            ->orWhere('id', $session)
            ->firstOrFail();

        $this->authorize('update', $session);

        $validated = $request->validated();

        // Update basic fields
        $updateData = array_filter($validated, fn($key) => in_array($key, [
            'title', 'description', 'category', 'genre', 'how_to_article'
        ]), ARRAY_FILTER_USE_KEY);

        if (isset($updateData['title'])) {
            $updateData['slug'] = Str::slug($updateData['title']);
        }

        if (isset($updateData['how_to_article'])) {
            $updateData['how_to_updated_at'] = now();
        }

        $session->update($updateData);

        // Update tags if provided
        if (array_key_exists('tags', $validated)) {
            $this->attachTags($session, $validated['tags'] ?? []);
        }

        return response()->json($session->load(['user:id,name', 'tags:id,name']));
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/sessions/{session}",
     *     summary="Delete a session",
     *     description="Delete a session (owner only)",
     *     operationId="deleteSession",
     *     tags={"Sessions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="session",
     *         in="path",
     *         description="Session ID or UUID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response=204, description="Session deleted successfully"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Session not found")
     * )
     */
    public function destroy(string $session): JsonResponse
    {
        $session = Session::where('uuid', $session)
            ->orWhere('id', $session)
            ->firstOrFail();

        $this->authorize('delete', $session);

        // Delete file from storage
        if ($session->file_path) {
            Storage::disk('private')->delete($session->file_path);
        }

        // Delete preview media
        if ($session->preview_image_path) {
            Storage::disk('public')->delete($session->preview_image_path);
        }

        if ($session->preview_audio_path) {
            Storage::disk('public')->delete($session->preview_audio_path);
        }

        $session->delete();

        return response()->json(null, 204);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/sessions/{session}/download",
     *     summary="Download a session",
     *     description="Get a temporary download URL for the session file",
     *     operationId="downloadSession",
     *     tags={"Sessions"},
     *     @OA\Parameter(
     *         name="session",
     *         in="path",
     *         description="Session ID or UUID",
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
     *             @OA\Property(property="estimated_assets_size", type="string"),
     *             @OA\Property(property="embedded_content", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Session not found")
     * )
     */
    public function download(string $session): JsonResponse
    {
        $session = Session::where('uuid', $session)
            ->orWhere('id', $session)
            ->published()
            ->firstOrFail();

        // Record download
        $session->recordDownload(auth()->user());

        $downloadUrl = $session->getDownloadUrl();
        $expiresAt = now()->addMinutes(5);

        return response()->json([
            'download_url' => $downloadUrl,
            'expires_at' => $expiresAt,
            'filename' => $session->original_filename,
            'estimated_assets_size' => $session->getEstimatedAssetsSize(),
            'embedded_content' => [
                'racks_count' => $session->getEmbeddedRacksCountAttribute(),
                'presets_count' => $session->getEmbeddedPresetsCountAttribute(),
                'samples_count' => $session->getEmbeddedSamplesCountAttribute(),
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/sessions/{session}/embedded-assets",
     *     summary="Get embedded assets",
     *     description="Get detailed information about assets embedded in the session",
     *     operationId="getSessionEmbeddedAssets",
     *     tags={"Sessions"},
     *     @OA\Parameter(
     *         name="session",
     *         in="path",
     *         description="Session ID or UUID",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Embedded assets information",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="embedded_racks", type="array"),
     *             @OA\Property(property="embedded_presets", type="array"),
     *             @OA\Property(property="embedded_samples", type="array"),
     *             @OA\Property(property="embedded_assets", type="array")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Session not found")
     * )
     */
    public function embeddedAssets(string $session): JsonResponse
    {
        $session = Session::where('uuid', $session)
            ->orWhere('id', $session)
            ->published()
            ->firstOrFail();

        return response()->json([
            'embedded_racks' => $session->embedded_racks ?? [],
            'embedded_presets' => $session->embedded_presets ?? [],
            'embedded_samples' => $session->embedded_samples ?? [],
            'embedded_assets' => $session->embedded_assets ?? [],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/sessions/categories",
     *     summary="Get session categories",
     *     description="Get available session categories",
     *     operationId="getSessionCategories",
     *     tags={"Sessions"},
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
        $categories = AbletonSessionAnalyzer::getSessionCategories();

        return response()->json(['categories' => $categories]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/sessions/genres",
     *     summary="Get session genres",
     *     description="Get available session genres",
     *     operationId="getSessionGenres",
     *     tags={"Sessions"},
     *     @OA\Response(
     *         response=200,
     *         description="Available genres",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="genres", type="object")
     *         )
     *     )
     * )
     */
    public function genres(): JsonResponse
    {
        $genres = AbletonSessionAnalyzer::getGenres();

        return response()->json(['genres' => $genres]);
    }

    /**
     * Store session file in private storage
     */
    private function storeSessionFile(UploadedFile $file): string
    {
        $filename = Str::uuid() . '.als';
        $path = "sessions/{$filename}";

        Storage::disk('private')->putFileAs('sessions', $file, $filename);

        return $path;
    }

    /**
     * Attach tags to session
     */
    private function attachTags(Session $session, array $tagNames): void
    {
        $tagIds = [];

        foreach ($tagNames as $tagName) {
            $tag = \App\Models\Tag::firstOrCreate(['name' => strtolower(trim($tagName))]);
            $tagIds[] = $tag->id;
        }

        $session->tags()->sync($tagIds);
    }
}