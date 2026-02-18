<?php

namespace App\Http\Controllers;

use App\Models\Rack;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RackAnnotationController extends Controller
{
    /**
     * Step 2: Show chain annotation interface
     */
    public function annotate(Rack $rack)
    {
        // Ensure user owns this rack and it's still pending
        if ($rack->user_id !== auth()->id()) {
            abort(403);
        }

        // If rack is already published, redirect to regular edit
        if ($rack->status === 'approved') {
            return redirect()->route('racks.edit', $rack);
        }

        return view('racks.annotate', compact('rack'));
    }

    /**
     * Step 2: Save chain annotations and proceed to metadata
     */
    public function saveAnnotations(Request $request, Rack $rack)
    {
        // Ensure user owns this rack
        if ($rack->user_id !== auth()->id()) {
            abort(403);
        }

        $request->validate([
            'chain_annotations' => 'nullable|array',
            'chain_annotations.*' => 'nullable|array',
            'chain_annotations.*.custom_name' => 'nullable|string|max:100'
        ]);

        // Update chain annotations
        $chainAnnotations = $request->chain_annotations ?? [];
        
        // Clean up empty annotations
        $chainAnnotations = array_filter($chainAnnotations, function($annotation) {
            return !empty($annotation['custom_name']);
        });

        $rack->update([
            'chain_annotations' => $chainAnnotations
        ]);

        return redirect()->route('racks.metadata', $rack);
    }

    /**
     * Step 3: Show metadata form
     */
    public function metadata(Rack $rack)
    {
        // Ensure user owns this rack and it's still pending
        if ($rack->user_id !== auth()->id()) {
            abort(403);
        }

        // If rack is already published, redirect to regular edit
        if ($rack->status === 'approved') {
            return redirect()->route('racks.edit', $rack);
        }

        // Get dynamic categories based on rack type
        $categories = $this->getCategoriesByRackType($rack->rack_type);
        
        return view('racks.metadata', compact('rack', 'categories'));
    }

    /**
     * Step 3: Save metadata and publish rack
     */
    public function publish(Request $request, Rack $rack)
    {
        // Ensure user owns this rack
        if ($rack->user_id !== auth()->id()) {
            abort(403);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
            'category' => 'required|string|max:100',
            'tags' => 'nullable|string|max:500',
            'is_public' => 'boolean'
        ]);

        // Update rack with metadata
        $rack->update([
            'title' => $request->title,
            'description' => $request->description,
            'category' => $request->category,
            'is_public' => $request->boolean('is_public', true),
            'status' => 'approved' // Publish the rack
        ]);

        // Handle tags (reuse from original controller)
        if ($request->tags) {
            $rack->tags()->detach(); // Clear existing tags
            $this->attachTags($rack, $request->tags);
        }

        return redirect()->route('racks.show', $rack)
            ->with('success', 'Your rack has been published successfully!');
    }

    /**
     * Get categories based on rack type
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
     * Attach tags to rack (copied from RackUploadController)
     */
    private function attachTags($rack, $tagString)
    {
        $tagNames = array_filter(array_map('trim', explode(',', $tagString)));
        
        foreach ($tagNames as $tagName) {
            if (strlen($tagName) > 2) { // Minimum tag length
                $tag = \App\Models\Tag::firstOrCreate([
                    'name' => $tagName,
                    'slug' => \Illuminate\Support\Str::slug($tagName)
                ]);
                
                $rack->tags()->attach($tag->id);
            }
        }
    }
}
