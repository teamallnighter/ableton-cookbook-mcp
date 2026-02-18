<?php

namespace App\Livewire;

use App\Models\User;
use App\Models\Rack;
use App\Models\RackFavorite;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class UserProfile extends Component
{
    use WithPagination;

    public User $user;
    public $activeTab = 'uploads';
    public $isOwnProfile = false;

    public function mount(User $user)
    {
        $this->user = $user;
        $this->isOwnProfile = auth()->check() && auth()->id() === (int) $user->id;
        
        // Default to uploads for both own profile and others
        $this->activeTab = 'uploads';
    }

    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function toggleFavorite($rackId)
    {
        if (!auth()->check()) {
            session()->flash('error', 'You must be logged in to favorite racks.');
            return;
        }

        $favorite = RackFavorite::where('rack_id', $rackId)
            ->where('user_id', auth()->id())
            ->first();

        if ($favorite) {
            $favorite->delete();
            session()->flash('success', 'Removed from favorites!');
        } else {
            RackFavorite::create([
                'rack_id' => $rackId,
                'user_id' => auth()->id()
            ]);
            session()->flash('success', 'Added to favorites!');
        }
        
        // Clear cached user stats when favorites change
        Cache::forget("user_stats_{$this->user->id}");
    }

    public function render()
    {
        $racks = collect();
        
        if ($this->activeTab === 'uploads') {
            $query = Rack::where('user_id', $this->user->id)
                ->with(['user:id,name', 'tags'])
                ->select([
                    'id', 'uuid', 'title', 'slug', 'user_id', 'rack_type', 'category',
                    'average_rating', 'ratings_count', 'downloads_count', 'views_count',
                    'created_at', 'published_at', 'description'
                ])
                ->when($this->isOwnProfile, function($query) {
                    // Show all racks for own profile (including drafts/pending)
                    return $query;
                }, function($query) {
                    // Only show published racks for other profiles
                    return $query->published();
                });
            
            // Add subquery for favorites to eliminate N+1 queries
            if (auth()->check()) {
                $query->addSelect([
                    'is_favorited' => RackFavorite::select(DB::raw(1))
                        ->whereColumn('rack_id', 'racks.id')
                        ->where('user_id', auth()->id())
                        ->limit(1)
                ]);
            }
            
            $racks = $query->orderBy('created_at', 'desc')->paginate(12);
        } elseif ($this->activeTab === 'favorites') {
            // Only show favorites for own profile or if user wants them public
            if ($this->isOwnProfile) {
                $query = Rack::join('rack_favorites', 'racks.id', '=', 'rack_favorites.rack_id')
                    ->where('rack_favorites.user_id', $this->user->id)
                    ->with(['user:id,name', 'tags'])
                    ->published()
                    ->select([
                        'racks.id', 'racks.uuid', 'racks.title', 'racks.slug', 'racks.user_id', 
                        'racks.rack_type', 'racks.category', 'racks.average_rating', 'racks.ratings_count', 
                        'racks.downloads_count', 'racks.views_count', 'racks.created_at', 
                        'racks.published_at', 'racks.description'
                    ]);
                
                // Add subquery for favorites (will always be true for this query but consistent structure)
                if (auth()->check()) {
                    $query->addSelect([
                        'is_favorited' => RackFavorite::select(DB::raw(1))
                            ->whereColumn('rack_id', 'racks.id')
                            ->where('user_id', auth()->id())
                            ->limit(1)
                    ]);
                }
                
                $racks = $query->orderBy('rack_favorites.created_at', 'desc')->paginate(12);
            }
        }

        // Transform favorite status for display (now using subquery result)
        if (auth()->check() && $racks->count() > 0) {
            $racks->getCollection()->transform(function ($rack) {
                $rack->is_favorited_by_user = (bool) ($rack->is_favorited ?? false);
                return $rack;
            });
        } else {
            $racks->getCollection()->transform(function ($rack) {
                $rack->is_favorited_by_user = false;
                return $rack;
            });
        }

        // Get user stats with optimized single query and caching
        $stats = Cache::remember("user_stats_{$this->user->id}", 600, function () {
            // Use a single query to get all stats at once
            $rackStats = Rack::where('user_id', $this->user->id)
                ->published()
                ->selectRaw('
                    COUNT(*) as total_uploads,
                    COALESCE(SUM(downloads_count), 0) as total_downloads,
                    COALESCE(SUM(views_count), 0) as total_views,
                    COALESCE(AVG(average_rating), 0) as average_rating
                ')
                ->first();
                
            $totalFavorites = RackFavorite::join('racks', 'rack_favorites.rack_id', '=', 'racks.id')
                ->where('racks.user_id', $this->user->id)
                ->count();
            
            return [
                'total_uploads' => $rackStats->total_uploads ?: 0,
                'total_downloads' => $rackStats->total_downloads ?: 0,
                'total_views' => $rackStats->total_views ?: 0,
                'total_favorites' => $totalFavorites,
                'average_rating' => $rackStats->average_rating ?: 0,
            ];
        });

        return view('livewire.user-profile', [
            'racks' => $racks,
            'stats' => $stats
        ]);
    }
}
