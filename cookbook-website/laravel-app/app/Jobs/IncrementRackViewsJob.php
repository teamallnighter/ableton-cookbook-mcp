<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class IncrementRackViewsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $rackId)
    {
    }

    public function handle(): void
    {
        // Increment view count for rack
        \App\Models\Rack::where('id', $this->rackId)->increment('views_count');
    }
}
