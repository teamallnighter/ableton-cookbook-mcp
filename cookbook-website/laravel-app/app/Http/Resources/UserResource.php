<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->when($request->user()?->id === $this->id, $this->email),
            'profile_photo_path' => $this->profile_photo_path,
            'profile_photo_url' => $this->profile_photo_url,
            'bio' => $this->bio,
            'location' => $this->location,
            'website' => $this->website,
            'soundcloud_url' => $this->soundcloud_url,
            'bandcamp_url' => $this->bandcamp_url,
            'spotify_url' => $this->spotify_url,
            'youtube_url' => $this->youtube_url,
            'instagram_url' => $this->instagram_url,
            'twitter_url' => $this->twitter_url,
            'facebook_url' => $this->facebook_url,
            'stats' => [
                'followers_count' => $this->whenLoaded('followers', function() {
                    return $this->followers->count();
                }),
                'following_count' => $this->whenLoaded('following', function() {
                    return $this->following->count();
                }),
                'racks_count' => $this->whenCounted('racks'),
            ],
            'is_following' => $this->when(
                auth()->check() && auth()->id() !== $this->id,
                function() {
                    return auth()->user()->following()->where('following_id', $this->id)->exists();
                }
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}