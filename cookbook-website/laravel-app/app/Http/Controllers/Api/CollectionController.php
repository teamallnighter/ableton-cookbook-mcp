<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rack;
use App\Models\RackCollection;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CollectionController extends Controller
{
    public function index(Request $request)
    {
        if (!auth()->check()) {
            return response()->json(['message' => 'Authentication required'], 401);
        }

        $collections = RackCollection::where('user_id', auth()->id())
            ->withCount('racks')
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json($collections);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_public' => 'boolean'
        ]);

        if (!auth()->check()) {
            return response()->json(['message' => 'Authentication required'], 401);
        }

        $collection = RackCollection::create([
            'user_id' => auth()->id(),
            'name' => $request->name,
            'description' => $request->description,
            'is_public' => $request->boolean('is_public', false)
        ]);

        return response()->json([
            'message' => 'Collection created successfully',
            'collection' => $collection
        ], 201);
    }

    public function show(RackCollection $collection, Request $request)
    {
        // Check if user can view this collection
        if (!$collection->is_public && (!auth()->check() || auth()->id() !== $collection->user_id)) {
            return response()->json(['message' => 'Collection not found'], 404);
        }

        $collection->load([
            'user:id,name,profile_photo_path',
            'racks' => function($query) use ($request) {
                $query->with(['user:id,name,profile_photo_path', 'tags:id,name'])
                      ->published()
                      ->orderBy('pivot_created_at', 'desc');
            }
        ]);

        return response()->json($collection);
    }

    public function update(Request $request, RackCollection $collection): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_public' => 'boolean'
        ]);

        if (!auth()->check() || auth()->id() !== $collection->user_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $collection->update([
            'name' => $request->name,
            'description' => $request->description,
            'is_public' => $request->boolean('is_public', $collection->is_public)
        ]);

        return response()->json([
            'message' => 'Collection updated successfully',
            'collection' => $collection
        ]);
    }

    public function destroy(RackCollection $collection): JsonResponse
    {
        if (!auth()->check() || auth()->id() !== $collection->user_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $collection->delete();

        return response()->json(['message' => 'Collection deleted successfully']);
    }

    public function addRack(RackCollection $collection, Rack $rack): JsonResponse
    {
        if (!auth()->check() || auth()->id() !== $collection->user_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($collection->racks()->where('rack_id', $rack->id)->exists()) {
            return response()->json(['message' => 'Rack already in collection']);
        }

        $collection->racks()->attach($rack->id);

        return response()->json(['message' => 'Rack added to collection successfully']);
    }

    public function removeRack(RackCollection $collection, Rack $rack): JsonResponse
    {
        if (!auth()->check() || auth()->id() !== $collection->user_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $collection->racks()->detach($rack->id);

        return response()->json(['message' => 'Rack removed from collection successfully']);
    }
}