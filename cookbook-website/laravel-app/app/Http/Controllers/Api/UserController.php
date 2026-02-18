<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RackResource;
use App\Http\Resources\UserResource;
use App\Models\Rack;
use App\Models\User;
use App\Models\UserFollow;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Users",
 *     description="API Endpoints for user profiles and social features"
 * )
 */
class UserController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/users/{user}",
     *     summary="Get user profile",
     *     description="Retrieve detailed user profile information including follower counts",
     *     operationId="getUserProfile",
     *     tags={"Users"},
     *     @OA\Parameter(
     *         name="user",
     *         in="path",
     *         description="User ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User profile retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", example="john@example.com"),
     *                 @OA\Property(property="followers_count", type="integer", example=42),
     *                 @OA\Property(property="following_count", type="integer", example=15)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found"
     *     )
     * )
     */
    public function show(User $user)
    {
        $user->load(['followers', 'following']);
        
        return new UserResource($user);
    }

    public function racks(User $user, Request $request)
    {
        $racks = Rack::where('user_id', $user->id)
            ->when(!auth()->check() || auth()->id() !== $user->id, function($query) {
                $query->published();
            })
            ->with(['user:id,name,profile_photo_path', 'tags:id,name'])
            ->orderBy($request->sort_by ?? 'created_at', $request->sort_direction ?? 'desc')
            ->paginate($request->per_page ?? 20);

        return RackResource::collection($racks);
    }

    public function followers(User $user, Request $request)
    {
        $followers = $user->followers()
            ->select(['users.id', 'users.name', 'users.profile_photo_path', 'users.created_at'])
            ->paginate($request->per_page ?? 20);

        return UserResource::collection($followers);
    }

    public function following(User $user, Request $request)
    {
        $following = $user->following()
            ->select(['users.id', 'users.name', 'users.profile_photo_path', 'users.created_at'])
            ->paginate($request->per_page ?? 20);

        return UserResource::collection($following);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/users/{user}/follow",
     *     summary="Follow a user",
     *     description="Follow another user to see their content in your feed",
     *     operationId="followUser",
     *     tags={"Users"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="user",
     *         in="path",
     *         description="User ID to follow",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User followed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="User followed successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Authentication required"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Cannot follow yourself"
     *     )
     * )
     */
    public function follow(User $user): JsonResponse
    {
        if (!auth()->check()) {
            return response()->json(['message' => 'Authentication required'], 401);
        }

        if (auth()->id() === $user->id) {
            return response()->json(['message' => 'You cannot follow yourself'], 403);
        }

        $follow = UserFollow::firstOrCreate([
            'follower_id' => auth()->id(),
            'following_id' => $user->id
        ]);

        if ($follow->wasRecentlyCreated) {
            return response()->json(['message' => 'User followed successfully']);
        }

        return response()->json(['message' => 'Already following this user']);
    }

    public function unfollow(User $user): JsonResponse
    {
        if (!auth()->check()) {
            return response()->json(['message' => 'Authentication required'], 401);
        }

        $deleted = UserFollow::where('follower_id', auth()->id())
            ->where('following_id', $user->id)
            ->delete();

        if ($deleted) {
            return response()->json(['message' => 'User unfollowed successfully']);
        }

        return response()->json(['message' => 'You are not following this user'], 404);
    }

    public function feed(Request $request)
    {
        if (!auth()->check()) {
            return response()->json(['message' => 'Authentication required'], 401);
        }

        $followingIds = auth()->user()->following()->pluck('users.id');

        $racks = Rack::whereIn('user_id', $followingIds)
            ->with(['user:id,name,profile_photo_path', 'tags:id,name'])
            ->published()
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return RackResource::collection($racks);
    }

    public function notifications(Request $request)
    {
        if (!auth()->check()) {
            return response()->json(['message' => 'Authentication required'], 401);
        }

        $notifications = auth()->user()
            ->notifications()
            ->latest()
            ->paginate($request->per_page ?? 20);

        return response()->json($notifications);
    }
}