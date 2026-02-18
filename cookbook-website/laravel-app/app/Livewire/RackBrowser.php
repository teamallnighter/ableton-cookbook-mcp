<?php

namespace App\Livewire;

use App\Models\Rack;
use App\Models\RackFavorite;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class RackBrowser extends Component
{
    use WithPagination;

    public $search = '';
    public $selectedRackType = '';
    public $selectedCategory = '';
    public $selectedEdition = '';
    public $sortBy = 'created_at';
    public $sortDirection = 'desc';
    public $perPage = 12;

    public $rackTypes = [
        'AudioEffectGroupDevice' => 'Audio Effects',
        'InstrumentGroupDevice' => 'Instruments', 
        'MidiEffectGroupDevice' => 'MIDI Effects'
    ];

    public $abletonEditions = [
        'intro' => 'Live Intro',
        'standard' => 'Live Standard',
        'suite' => 'Live Suite'
    ];

    public $sortOptions = [
        'created_at' => 'Newest',
        'downloads_count' => 'Most Downloaded',
        'average_rating' => 'Highest Rated',
        'views_count' => 'Most Viewed'
    ];

    public function updating($field, $value)
    {
        if (in_array($field, ['search', 'selectedRackType', 'selectedCategory', 'selectedEdition', 'sortBy', 'sortDirection'])) {
            $this->resetPage();
        }
        
        // Reset category when rack type changes
        if ($field === 'selectedRackType') {
            $this->selectedCategory = '';
        }
    }
    
    public function updated($field, $value)
    {
        // Force a re-render when any filter changes
        if (in_array($field, ['search', 'selectedRackType', 'selectedCategory', 'selectedEdition', 'sortBy', 'sortDirection'])) {
            // This ensures the component re-renders
        }
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->selectedRackType = '';
        $this->selectedCategory = '';
        $this->selectedEdition = '';
        $this->sortBy = 'created_at';
        $this->sortDirection = 'desc';
        $this->resetPage();
    }
    
    public function testSearch()
    {
        $this->search = 'bass';
        session()->flash('message', 'Test search set to: ' . $this->search);
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
    }

    /**
     * Get categories based on rack type - same logic as in metadata form
     */
    private function getCategoriesByRackType($rackType)
    {
        return match($rackType) {
            'AudioEffectGroupDevice' => [
                'dynamics' => 'Dynamics',
                'time-based' => 'Time Based', 
                'modulation' => 'Modulation',
                'spectral' => 'Spectral',
                'filters' => 'Filters',
                'creative-effects' => 'Creative Effects',
                'utility' => 'Utility',
                'mixing' => 'Mixing',
                'distortion' => 'Distortion'
            ],
            'InstrumentGroupDevice' => [
                'drums' => 'Drums',
                'samplers' => 'Samplers',
                'synths' => 'Synths',
                'bass' => 'Bass',
                'fx' => 'FX'
            ],
            'MidiEffectGroupDevice' => [
                'arpeggiators-sequencers' => 'Arpeggiators & Sequencers',
                'music-theory' => 'Music Theory',
                'other' => 'Other'
            ],
            default => [
                'other' => 'Other'
            ]
        };
    }

    /**
     * Get available categories based on selected rack type
     */
    public function getAvailableCategories()
    {
        if ($this->selectedRackType) {
            // Return categories for selected rack type
            return $this->getCategoriesByRackType($this->selectedRackType);
        }
        
        // Return all categories from all rack types
        $allCategories = [];
        foreach (['AudioEffectGroupDevice', 'InstrumentGroupDevice', 'MidiEffectGroupDevice'] as $rackType) {
            $allCategories = array_merge($allCategories, $this->getCategoriesByRackType($rackType));
        }
        
        // Remove duplicates and sort
        $allCategories = array_unique($allCategories);
        asort($allCategories);
        
        return $allCategories;
    }

    public function render()
    {
        $query = Rack::query()
            ->with(['user:id,name,profile_photo_path', 'tags'])
            ->published()
            // Only select essential columns for list view - avoid heavy JSON columns
            ->select([
                'id', 'uuid', 'title', 'slug', 'user_id', 'rack_type', 'category',
                'average_rating', 'ratings_count', 'downloads_count', 'views_count',
                'created_at', 'published_at', 'description', 'ableton_edition'
            ]);

        // Search
        if ($this->search) {
            $query->where(function($q) {
                $q->where('title', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%')
                  ->orWhereHas('tags', function($tagQuery) {
                      $tagQuery->where('name', 'like', '%' . $this->search . '%');
                  });
            });
        }

        // Filter by rack type
        if ($this->selectedRackType) {
            $query->where('rack_type', $this->selectedRackType);
        }

        // Filter by category
        if ($this->selectedCategory) {
            $query->where('category', $this->selectedCategory);
        }

        // Filter by Ableton edition
        if ($this->selectedEdition) {
            // Filter to show racks that work with the selected edition or lower
            // intro: only intro racks
            // standard: intro and standard racks
            // suite: all racks
            if ($this->selectedEdition === 'intro') {
                $query->where('ableton_edition', 'intro');
            } elseif ($this->selectedEdition === 'standard') {
                $query->whereIn('ableton_edition', ['intro', 'standard']);
            }
            // suite shows all racks, so no filter needed
        }

        // Sorting
        if ($this->sortDirection === 'desc') {
            $query->orderByDesc($this->sortBy);
        } else {
            $query->orderBy($this->sortBy);
        }

        // Add subquery for favorites to eliminate N+1 queries
        if (auth()->check()) {
            $query->addSelect([
                'is_favorited' => RackFavorite::select(DB::raw(1))
                    ->whereColumn('rack_id', 'racks.id')
                    ->where('user_id', auth()->id())
                    ->limit(1)
            ]);
        }
        
        $racks = $query->paginate($this->perPage);
        
        // Transform favorite status for display
        if (auth()->check()) {
            $racks->getCollection()->transform(function ($rack) {
                $rack->is_favorited_by_user = (bool) $rack->is_favorited;
                return $rack;
            });
        } else {
            $racks->getCollection()->transform(function ($rack) {
                $rack->is_favorited_by_user = false;
                return $rack;
            });
        }
        
        // Get dynamic categories based on current filters
        $categories = $this->getAvailableCategories();

        return view('livewire.rack-browser', [
            'racks' => $racks,
            'categories' => $categories
        ]);
    }
}
