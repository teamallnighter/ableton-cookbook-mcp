<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RackRating extends Model
{
    use HasFactory;

    protected $fillable = [
        'rack_id',
        'user_id',
        'rating',
        'review',
        'is_verified_purchase',
        'helpful_count',
    ];

    protected $casts = [
        'is_verified_purchase' => 'boolean',
    ];

    /**
     * Get the rack that was rated
     */
    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class);
    }

    /**
     * Get the user who made the rating
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}