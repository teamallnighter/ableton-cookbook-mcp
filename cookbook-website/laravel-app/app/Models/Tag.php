<?php

/**
 * @OA\Schema(
 *     schema="Tag",
 *     type="object",
 *     title="Tag",
 *     description="Tag model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="bass"),
 *     @OA\Property(property="slug", type="string", example="bass"),
 *     @OA\Property(property="description", type="string", nullable=true)
 * )
 */


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'usage_count',
    ];

    /**
     * Get the racks that have this tag
     */
    public function racks(): BelongsToMany
    {
        return $this->belongsToMany(Rack::class, 'rack_tags');
    }

    /**
     * Scope for popular tags
     */
    public function scopePopular($query, int $limit = 10)
    {
        return $query->orderBy('usage_count', 'desc')->limit($limit);
    }
}