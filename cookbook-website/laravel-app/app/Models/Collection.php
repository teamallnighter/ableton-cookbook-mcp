<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Collection extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'slug',
        'is_public',
        'racks_count',
        'followers_count',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    /**
     * Get the user who owns this collection
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the racks in this collection
     */
    public function racks(): BelongsToMany
    {
        return $this->belongsToMany(Rack::class, 'collection_racks')
            ->withPivot('position', 'notes')
            ->withTimestamps()
            ->orderBy('pivot_position');
    }
}