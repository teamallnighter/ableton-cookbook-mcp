<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RackResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'title' => $this->title,
            'description' => $this->description,
            'slug' => $this->slug,
            'rack_type' => $this->rack_type,
            'category' => $this->category,
            'user' => new UserResource($this->whenLoaded('user')),
            'tags' => $this->whenLoaded('tags', function() {
                return $this->tags->map(function($tag) {
                    return [
                        'id' => $tag->id,
                        'name' => $tag->name,
                        'slug' => $tag->slug
                    ];
                });
            }),
            'stats' => [
                'average_rating' => round($this->average_rating, 2),
                'ratings_count' => $this->ratings_count,
                'downloads_count' => $this->downloads_count,
                'views_count' => $this->views_count,
                'favorites_count' => $this->whenCounted('favorites'),
            ],
            'metadata' => [
                'device_count' => $this->device_count,
                'chain_count' => $this->chain_count,
                'ableton_version' => $this->ableton_version,
                'ableton_edition' => $this->ableton_edition,
                'file_size' => $this->file_size,
                'original_filename' => $this->original_filename,
            ],
            'user_interactions' => $this->when(auth()->check(), function() {
                return [
                    'is_favorited' => $this->relationLoaded('userFavorites') 
                        ? $this->userFavorites->isNotEmpty()
                        : false,
                    'user_rating' => $this->relationLoaded('userRating')
                        ? $this->userRating?->rating
                        : null,
                ];
            }),
            'permissions' => [
                'can_edit' => auth()->check() && auth()->id() === $this->user_id,
                'can_delete' => auth()->check() && auth()->id() === $this->user_id,
                'can_download' => true, // Racks are publicly downloadable
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'published_at' => $this->published_at,
        ];
    }
}