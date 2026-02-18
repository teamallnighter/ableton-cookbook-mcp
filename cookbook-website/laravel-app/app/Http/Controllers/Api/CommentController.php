<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rack;
use App\Models\RackComment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CommentController extends Controller
{
    public function index(Rack $rack, Request $request)
    {
        $comments = RackComment::where('rack_id', $rack->id)
            ->with(['user:id,name,profile_photo_path'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json($comments);
    }

    public function store(Request $request, Rack $rack): JsonResponse
    {
        $request->validate([
            'content' => 'required|string|max:1000'
        ]);

        if (!auth()->check()) {
            return response()->json(['message' => 'Authentication required'], 401);
        }

        $comment = RackComment::create([
            'rack_id' => $rack->id,
            'user_id' => auth()->id(),
            'content' => $request->content
        ]);

        $comment->load('user:id,name,profile_photo_path');

        return response()->json([
            'message' => 'Comment posted successfully',
            'comment' => $comment
        ], 201);
    }

    public function update(Request $request, RackComment $comment): JsonResponse
    {
        $request->validate([
            'content' => 'required|string|max:1000'
        ]);

        if (!auth()->check() || auth()->id() !== $comment->user_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $comment->update([
            'content' => $request->content
        ]);

        return response()->json([
            'message' => 'Comment updated successfully',
            'comment' => $comment
        ]);
    }

    public function destroy(RackComment $comment): JsonResponse
    {
        if (!auth()->check() || (auth()->id() !== $comment->user_id && auth()->id() !== $comment->rack->user_id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $comment->delete();

        return response()->json(['message' => 'Comment deleted successfully']);
    }

    public function toggleLike(RackComment $comment): JsonResponse
    {
        if (!auth()->check()) {
            return response()->json(['message' => 'Authentication required'], 401);
        }

        // This would require a comment_likes table to be implemented
        // For now, return a placeholder response
        return response()->json([
            'message' => 'Comment like functionality not yet implemented',
            'feature' => 'Coming soon'
        ]);
    }
}