<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EnhancedCollection;
use App\Services\CollectionService;
use App\Services\CollectionDownloadManager;
use App\Http\Requests\CreateCollectionRequest;
use App\Http\Requests\UpdateCollectionRequest;
use App\Http\Resources\CollectionResource;
use App\Http\Resources\CollectionListResource;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * @OA\Tag(
 *     name="Enhanced Collections",
 *     description="Mixed-content collections and learning cookbooks"
 * )
 */
class EnhancedCollectionController extends Controller
{
    public function __construct(
        protected CollectionService $collectionService,
        protected CollectionDownloadManager $downloadManager
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/enhanced-collections",
     *     summary="List enhanced collections",
     *     description="Get a paginated list of enhanced collections with filtering and sorting options",
     *     operationId="getEnhancedCollections",
     *     tags={"Enhanced Collections"},
     *     @OA\Parameter(
     *         name="filter[collection_type]",
     *         in="query",
     *         description="Filter by collection type",
     *         @OA\Schema(type="string", enum={"genre_cookbook", "technique_masterclass", "artist_series", "quick_start_pack", "preset_library", "custom"})
     *     ),
     *     @OA\Parameter(
     *         name="filter[difficulty_level]",
     *         in="query",
     *         description="Filter by difficulty level",
     *         @OA\Schema(type="string", enum={"beginner", "intermediate", "advanced"})
     *     ),
     *     @OA\Parameter(
     *         name="filter[genre]",
     *         in="query",
     *         description="Filter by genre",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="filter[is_free]",
     *         in="query",
     *         description="Filter by free collections",
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="filter[is_featured]",
     *         in="query",
     *         description="Filter by featured collections",
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="filter[search]",
     *         in="query",
     *         description="Full-text search in title and description",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="Sort collections",
     *         @OA\Schema(type="string", enum={"created_at", "-created_at", "average_rating", "-average_rating", "downloads_count", "-downloads_count", "views_count", "-views_count"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/CollectionListResource")
     *             ),
     *             @OA\Property(property="links", type="object"),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $collections = QueryBuilder::for(EnhancedCollection::class)
            ->published()
            ->with(['user:id,name,avatar_path', 'tags'])
            ->allowedFilters([
                'collection_type',
                'difficulty_level',
                'genre',
                'is_free',
                'is_featured',
                'is_learning_path',
                'has_certificate',
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->whereFullText(['title', 'description'], $value);
                }),
                AllowedFilter::callback('user', function ($query, $value) {
                    $query->where('user_id', $value);
                }),
            ])
            ->allowedSorts([
                'created_at',
                'published_at',
                'average_rating',
                'downloads_count',
                'views_count',
                'saves_count',
                'completions_count',
                'title',
            ])
            ->defaultSort('-is_featured', '-average_rating')
            ->jsonPaginate();

        return CollectionListResource::collection($collections);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/enhanced-collections",
     *     summary="Create a new enhanced collection",
     *     description="Create a new enhanced collection for the authenticated user",
     *     operationId="createEnhancedCollection",
     *     tags={"Enhanced Collections"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", maxLength=255, example="My Progressive House Cookbook"),
     *             @OA\Property(property="description", type="string", example="A comprehensive guide to progressive house production"),
     *             @OA\Property(property="collection_type", type="string", enum={"genre_cookbook", "technique_masterclass", "artist_series", "quick_start_pack", "preset_library", "custom"}),
     *             @OA\Property(property="difficulty_level", type="string", enum={"beginner", "intermediate", "advanced"}),
     *             @OA\Property(property="genre", type="string", example="house"),
     *             @OA\Property(property="estimated_completion_time", type="number", format="float", example=5.5),
     *             @OA\Property(property="is_learning_path", type="boolean", example=false),
     *             @OA\Property(property="has_certificate", type="boolean", example=false),
     *             @OA\Property(property="required_packs", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="required_plugins", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="is_free", type="boolean", example=true),
     *             @OA\Property(property="collection_price", type="number", format="float", example=29.99)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Collection created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/EnhancedCollection")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(CreateCollectionRequest $request)
    {
        $collection = $this->collectionService->createCollection(
            $request->user(),
            $request->validated()
        );

        return new CollectionResource($collection);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/enhanced-collections/{id}",
     *     summary="Get enhanced collection details",
     *     description="Retrieve detailed information about a specific enhanced collection",
     *     operationId="getEnhancedCollection",
     *     tags={"Enhanced Collections"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Collection ID or UUID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="include",
     *         in="query",
     *         description="Include related data (items,user,tags,progress)",
     *         @OA\Schema(type="string", example="items,user,tags")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/EnhancedCollection")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Collection not found")
     * )
     */
    public function show(Request $request, string $id)
    {
        $collection = $this->findCollection($id);
        
        // Record view
        $this->collectionService->recordView($collection, $request->user());

        // Handle includes
        $includes = $request->get('include', '');
        $allowedIncludes = ['items', 'user', 'tags', 'progress'];
        $requestedIncludes = array_intersect(explode(',', $includes), $allowedIncludes);

        if (!empty($requestedIncludes)) {
            $collection->load($requestedIncludes);
        }

        // Load user progress if authenticated and requested
        if ($request->user() && in_array('progress', $requestedIncludes)) {
            $collection->user_progress = $collection->getUserProgress($request->user());
        }

        return new CollectionResource($collection);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/enhanced-collections/{id}",
     *     summary="Update enhanced collection",
     *     description="Update an existing enhanced collection (owner only)",
     *     operationId="updateEnhancedCollection",
     *     tags={"Enhanced Collections"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Collection ID or UUID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", maxLength=255),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="how_to_article", type="string"),
     *             @OA\Property(property="difficulty_level", type="string", enum={"beginner", "intermediate", "advanced"}),
     *             @OA\Property(property="estimated_completion_time", type="number", format="float"),
     *             @OA\Property(property="required_packs", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="required_plugins", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="is_free", type="boolean"),
     *             @OA\Property(property="collection_price", type="number", format="float")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Collection updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/EnhancedCollection")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Forbidden - Not the owner"),
     *     @OA\Response(response=404, description="Collection not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(UpdateCollectionRequest $request, string $id)
    {
        $collection = $this->findCollection($id);
        
        $this->authorize('update', $collection);

        $collection = $this->collectionService->updateCollection(
            $collection,
            $request->validated()
        );

        return new CollectionResource($collection);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/enhanced-collections/{id}",
     *     summary="Delete enhanced collection",
     *     description="Delete an enhanced collection (owner only)",
     *     operationId="deleteEnhancedCollection",
     *     tags={"Enhanced Collections"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Collection ID or UUID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response=204, description="Collection deleted successfully"),
     *     @OA\Response(response=403, description="Forbidden - Not the owner"),
     *     @OA\Response(response=404, description="Collection not found")
     * )
     */
    public function destroy(Request $request, string $id)
    {
        $collection = $this->findCollection($id);
        
        $this->authorize('delete', $collection);

        $collection->delete();

        return response()->noContent();
    }

    /**
     * @OA\Post(
     *     path="/api/v1/enhanced-collections/{id}/items",
     *     summary="Add item to enhanced collection",
     *     description="Add an item (rack, preset, session, etc.) to an enhanced collection",
     *     operationId="addEnhancedCollectionItem",
     *     tags={"Enhanced Collections"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Collection ID or UUID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="item_type", type="string", enum={"rack", "preset", "session", "bundle", "blog_post", "external_link"}),
     *             @OA\Property(property="item_id", type="integer", description="ID of the item (null for external links)"),
     *             @OA\Property(property="section", type="string", example="Basslines"),
     *             @OA\Property(property="chapter_title", type="string", example="Building Your First Bassline"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="learning_notes", type="string"),
     *             @OA\Property(property="is_required", type="boolean", example=true),
     *             @OA\Property(property="estimated_duration", type="number", format="float", example=2.5),
     *             @OA\Property(property="external_url", type="string", format="uri", description="For external links"),
     *             @OA\Property(property="external_type", type="string", enum={"youtube_video", "vimeo_video", "soundcloud_track", "spotify_playlist", "blog_article", "documentation", "website", "other"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Item added successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/CollectionItem")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Forbidden - Not the owner"),
     *     @OA\Response(response=404, description="Collection not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function addItem(Request $request, string $id)
    {
        $collection = $this->findCollection($id);
        
        $this->authorize('update', $collection);

        $request->validate([
            'item_type' => 'required|string|in:rack,preset,session,bundle,blog_post,external_link',
            'item_id' => 'nullable|integer|required_unless:item_type,external_link',
            'section' => 'nullable|string|max:255',
            'chapter_title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'learning_notes' => 'nullable|string',
            'is_required' => 'boolean',
            'estimated_duration' => 'nullable|numeric|min:0|max:100',
            'external_url' => 'required_if:item_type,external_link|nullable|url',
            'external_type' => 'required_if:item_type,external_link|nullable|string',
        ]);

        $itemTypeMap = [
            'rack' => \App\Models\Rack::class,
            'preset' => \App\Models\Preset::class,
            'session' => \App\Models\Session::class,
            'bundle' => \App\Models\Bundle::class,
            'blog_post' => \App\Models\BlogPost::class,
        ];

        $itemType = $request->item_type === 'external_link' 
            ? 'external' 
            : $itemTypeMap[$request->item_type];

        $item = $this->collectionService->addItemToCollection(
            $collection,
            $itemType,
            $request->item_id,
            $request->except(['item_type', 'item_id'])
        );

        return response()->json(['data' => $item], 201);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/enhanced-collections/{id}/items/{itemId}",
     *     summary="Remove item from enhanced collection",
     *     description="Remove an item from an enhanced collection",
     *     operationId="removeEnhancedCollectionItem",
     *     tags={"Enhanced Collections"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Collection ID or UUID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="itemId",
     *         in="path",
     *         required=true,
     *         description="Collection item ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=204, description="Item removed successfully"),
     *     @OA\Response(response=403, description="Forbidden - Not the owner"),
     *     @OA\Response(response=404, description="Collection or item not found")
     * )
     */
    public function removeItem(Request $request, string $id, int $itemId)
    {
        $collection = $this->findCollection($id);
        
        $this->authorize('update', $collection);

        $this->collectionService->removeItemFromCollection($collection, $itemId);

        return response()->noContent();
    }

    /**
     * @OA\Post(
     *     path="/api/v1/enhanced-collections/{id}/save",
     *     summary="Save/unsave enhanced collection",
     *     description="Toggle save status for an enhanced collection",
     *     operationId="toggleSaveEnhancedCollection",
     *     tags={"Enhanced Collections"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Collection ID or UUID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="folder", type="string", example="My Favorites"),
     *             @OA\Property(property="personal_notes", type="string"),
     *             @OA\Property(property="is_favorite", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Save status toggled",
     *         @OA\JsonContent(
     *             @OA\Property(property="saved", type="boolean", description="Whether collection is now saved"),
     *             @OA\Property(property="saves_count", type="integer", description="Total saves for this collection")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Collection not found")
     * )
     */
    public function toggleSave(Request $request, string $id)
    {
        $collection = $this->findCollection($id);

        $request->validate([
            'folder' => 'nullable|string|max:255',
            'personal_notes' => 'nullable|string',
            'is_favorite' => 'boolean',
        ]);

        $saved = $this->collectionService->toggleSaveCollection(
            $collection,
            $request->user(),
            $request->only(['folder', 'personal_notes', 'is_favorite'])
        );

        return response()->json([
            'saved' => $saved,
            'saves_count' => $collection->fresh()->saves_count,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/enhanced-collections/{id}/start",
     *     summary="Start enhanced collection",
     *     description="Start an enhanced collection and begin progress tracking",
     *     operationId="startEnhancedCollection",
     *     tags={"Enhanced Collections"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Collection ID or UUID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Collection started successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/UserProgress")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Collection not found")
     * )
     */
    public function start(Request $request, string $id)
    {
        $collection = $this->findCollection($id);

        $progress = $this->collectionService->startCollectionForUser(
            $collection,
            $request->user()
        );

        return response()->json(['data' => $progress->getProgressSummary()]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/enhanced-collections/{id}/download",
     *     summary="Create enhanced collection download",
     *     description="Create a download archive for an enhanced collection",
     *     operationId="downloadEnhancedCollection",
     *     tags={"Enhanced Collections"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Collection ID or UUID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="download_type", type="string", enum={"full_collection", "individual_item", "section", "custom_selection"}, example="full_collection"),
     *             @OA\Property(property="selected_items", type="array", @OA\Items(type="integer"), description="Item IDs for partial downloads"),
     *             @OA\Property(property="selected_sections", type="array", @OA\Items(type="string"), description="Section names for section downloads"),
     *             @OA\Property(property="format", type="string", enum={"zip"}, example="zip"),
     *             @OA\Property(property="include_metadata", type="boolean", example=true),
     *             @OA\Property(property="include_how_to", type="boolean", example=true),
     *             @OA\Property(property="organize_by_type", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=202,
     *         description="Download creation started",
     *         @OA\JsonContent(
     *             @OA\Property(property="download_token", type="string", description="Token to check download status"),
     *             @OA\Property(property="status", type="string", example="pending"),
     *             @OA\Property(property="message", type="string", example="Download is being prepared")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Collection not found")
     * )
     */
    public function download(Request $request, string $id)
    {
        $collection = $this->findCollection($id);

        $request->validate([
            'download_type' => 'string|in:full_collection,individual_item,section,custom_selection',
            'selected_items' => 'array|exists:collection_items,id',
            'selected_sections' => 'array',
            'format' => 'string|in:zip',
            'include_metadata' => 'boolean',
            'include_how_to' => 'boolean',
            'organize_by_type' => 'boolean',
        ]);

        $download = $this->downloadManager->createCollectionDownload(
            $collection,
            $request->user(),
            $request->all()
        );

        return response()->json([
            'download_token' => $download->download_token,
            'status' => $download->status,
            'message' => 'Download is being prepared',
        ], 202);
    }

    /**
     * Find collection by ID or UUID
     */
    protected function findCollection(string $id): EnhancedCollection
    {
        return EnhancedCollection::where('id', $id)
            ->orWhere('uuid', $id)
            ->published()
            ->firstOrFail();
    }
}