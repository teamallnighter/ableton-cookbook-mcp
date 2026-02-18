<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rack;
use App\Models\RackRating;
use App\Notifications\RackRatedNotification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RackRatingController extends Controller
{
    public function store(Request $request, Rack $rack): JsonResponse
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5'
        ]);

        if (!auth()->check()) {
            return response()->json(['message' => 'Authentication required'], 401);
        }

        if (auth()->id() === $rack->user_id) {
            return response()->json(['message' => 'You cannot rate your own rack'], 403);
        }

        $rating = RackRating::updateOrCreate(
            [
                'rack_id' => $rack->id,
                'user_id' => auth()->id()
            ],
            [
                'rating' => $request->rating
            ]
        );

        // Update rack's cached rating statistics
        $this->updateRackRatingStats($rack);

        // Send notification to rack owner
        $rack->user->notify(new RackRatedNotification($rack, auth()->user(), $request->rating));

        return response()->json([
            'message' => 'Rating submitted successfully',
            'rating' => $rating->rating,
            'rack_stats' => [
                'average_rating' => $rack->fresh()->average_rating,
                'ratings_count' => $rack->fresh()->ratings_count
            ]
        ]);
    }

    public function update(Request $request, Rack $rack): JsonResponse
    {
        return $this->store($request, $rack);
    }

    public function destroy(Rack $rack): JsonResponse
    {
        if (!auth()->check()) {
            return response()->json(['message' => 'Authentication required'], 401);
        }

        $rating = RackRating::where('rack_id', $rack->id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$rating) {
            return response()->json(['message' => 'Rating not found'], 404);
        }

        $rating->delete();
        $this->updateRackRatingStats($rack);

        return response()->json(['message' => 'Rating removed successfully']);
    }

    private function updateRackRatingStats(Rack $rack): void
    {
        $ratings = RackRating::where('rack_id', $rack->id)->get();
        
        $rack->update([
            'average_rating' => $ratings->avg('rating') ?: 0,
            'ratings_count' => $ratings->count()
        ]);
    }
}