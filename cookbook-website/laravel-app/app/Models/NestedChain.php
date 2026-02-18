<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NestedChain extends Model
{
    use HasFactory;

    protected $fillable = [
        'rack_id',
        'chain_identifier',
        'xml_path',
        'parent_chain_id',
        'depth_level',
        'device_count',
        'is_empty',
        'chain_type',
        'devices',
        'parameters',
        'chain_metadata',
        'analyzed_at',
    ];

    protected $casts = [
        'is_empty' => 'boolean',
        'devices' => 'array',
        'parameters' => 'array',
        'chain_metadata' => 'array',
        'analyzed_at' => 'datetime',
    ];

    /**
     * Get the rack that owns the nested chain
     */
    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class);
    }

    /**
     * Get the parent chain (for hierarchical structure)
     */
    public function parentChain(): BelongsTo
    {
        return $this->belongsTo(NestedChain::class, 'parent_chain_id');
    }

    /**
     * Get the child chains
     */
    public function childChains(): HasMany
    {
        return $this->hasMany(NestedChain::class, 'parent_chain_id');
    }

    /**
     * Get all descendant chains (recursive)
     */
    public function descendants(): HasMany
    {
        return $this->childChains()->with('descendants');
    }

    /**
     * Scope for root chains (no parent)
     */
    public function scopeRootChains($query)
    {
        return $query->whereNull('parent_chain_id');
    }

    /**
     * Scope for chains at specific depth
     */
    public function scopeAtDepth($query, int $depth)
    {
        return $query->where('depth_level', $depth);
    }

    /**
     * Scope for non-empty chains
     */
    public function scopeNonEmpty($query)
    {
        return $query->where('is_empty', false);
    }

    /**
     * Scope for chains of specific type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('chain_type', $type);
    }

    /**
     * Scope for chains with devices
     */
    public function scopeWithDevices($query)
    {
        return $query->where('device_count', '>', 0);
    }

    /**
     * Get the hierarchical path of this chain
     */
    public function getHierarchicalPath(): string
    {
        $path = [$this->chain_identifier];
        $parent = $this->parentChain;

        while ($parent) {
            array_unshift($path, $parent->chain_identifier);
            $parent = $parent->parentChain;
        }

        return implode(' > ', $path);
    }

    /**
     * Check if this chain is constitutional compliant
     */
    public function isConstitutionalCompliant(): bool
    {
        // Constitutional requirement: ALL CHAINS must be included
        // A chain is compliant if it has been properly analyzed
        return $this->analyzed_at !== null &&
               $this->xml_path !== null &&
               $this->chain_identifier !== null;
    }

    /**
     * Get the total device count including descendants
     */
    public function getTotalDeviceCountAttribute(): int
    {
        return $this->device_count + $this->descendants->sum('device_count');
    }

    /**
     * Get the maximum depth of descendants
     */
    public function getMaxDescendantDepthAttribute(): int
    {
        $maxDepth = $this->depth_level;

        foreach ($this->descendants as $descendant) {
            $maxDepth = max($maxDepth, $descendant->depth_level);
        }

        return $maxDepth;
    }

    /**
     * Check if this chain has any devices
     */
    public function hasDevices(): bool
    {
        return $this->device_count > 0;
    }

    /**
     * Check if this chain has child chains
     */
    public function hasChildren(): bool
    {
        return $this->childChains()->exists();
    }

    /**
     * Get device names from the devices array
     */
    public function getDeviceNames(): array
    {
        if (!$this->devices) {
            return [];
        }

        return collect($this->devices)
            ->pluck('device_name')
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Get device types from the devices array
     */
    public function getDeviceTypes(): array
    {
        if (!$this->devices) {
            return [];
        }

        return collect($this->devices)
            ->pluck('device_type')
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }
}
