<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RackDownload extends Model
{
    use HasFactory;

    protected $fillable = [
        'rack_id',
        'user_id',
        'ip_address',
        'user_agent',
        'download_token',
        'downloaded_at',
    ];

    protected $casts = [
        'downloaded_at' => 'datetime',
    ];

    /**
     * Get the rack that was downloaded
     */
    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class);
    }

    /**
     * Get the user who downloaded the rack
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}