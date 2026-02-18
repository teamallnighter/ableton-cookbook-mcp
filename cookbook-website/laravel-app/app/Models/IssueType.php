<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IssueType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'allows_file_upload',
        'is_active',
    ];

    protected $casts = [
        'allows_file_upload' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getDisplayNameAttribute()
    {
        return ucfirst(str_replace('_', ' ', $this->name));
    }
}
