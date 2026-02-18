<?php

namespace App\Console\Commands;

use App\Models\Rack;
use App\Models\Tag;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupTaggingSystem extends Command
{
    protected $signature = 'racks:cleanup-tagging';
    protected $description = 'Clean up the tagging system by properly categorizing racks and removing redundant tags';

    public function handle()
    {
        $this->info('Starting tagging system cleanup...');

        // Define category mappings based on existing tags
        $categoryMappings = [
            // Categories that should become proper categories
            'creative-effects' => 'Creative Effects',
            'creative effects' => 'Creative Effects',
            'distortion' => 'Distortion',
            'bit-crushing' => 'Distortion', // bit crushing is a type of distortion
            'channel-strip' => 'Mixing',
            'channel strip' => 'Mixing',
            'mixing' => 'Mixing',
            'modulation' => 'Modulation',
            'time' => 'Time',
            'delay' => 'Time',
            'chorus' => 'Modulation',
            
            // Instrument categories
            'drums' => 'Drums',
            'percussion' => 'Drums',
            'sub' => 'Bass',
            'low-end' => 'Bass',
            
            // Vocals/Processing
            'vocals' => 'Vocals',
            'filter' => 'Filter',
            'cutoff' => 'Filter',
        ];

        // Tags that should remain as tags (specific techniques/effects)
        $keepAsTags = [
            'bit crushing',
            'sweep', 
            'rhythm',
            'cutoff',
            'filter',
            'delay',
            'chorus',
        ];

        // Process each rack
        $racks = Rack::with('tags')->get();
        
        foreach ($racks as $rack) {
            $this->info("Processing rack: {$rack->title}");
            
            // Determine category based on tags
            $category = null;
            $tagsToRemove = [];
            $tagsToKeep = [];
            
            foreach ($rack->tags as $tag) {
                // Skip rack type tags (these shouldn't be tags)
                if (in_array($tag->slug, ['audio-effect', 'instrument', 'midi-effect'])) {
                    $tagsToRemove[] = $tag->id;
                    continue;
                }
                
                // Check if this tag should become a category
                if (isset($categoryMappings[$tag->slug]) || isset($categoryMappings[$tag->name])) {
                    if (!$category) { // Take the first category we find
                        $category = $categoryMappings[$tag->slug] ?? $categoryMappings[$tag->name];
                    }
                    $tagsToRemove[] = $tag->id;
                } else if (in_array($tag->name, $keepAsTags) || in_array($tag->slug, $keepAsTags)) {
                    // Keep as tag
                    $tagsToKeep[] = $tag->name;
                } else {
                    // For now, keep unknown tags but we might clean them later
                    $tagsToKeep[] = $tag->name;
                }
            }
            
            // Update the rack category
            if ($category) {
                $rack->update(['category' => $category]);
                $this->info("  - Set category to: {$category}");
            }
            
            // Remove tags that became categories or are rack types
            if (!empty($tagsToRemove)) {
                $rack->tags()->detach($tagsToRemove);
                $this->info("  - Removed " . count($tagsToRemove) . " tags");
            }
            
            // Show remaining tags
            if (!empty($tagsToKeep)) {
                $this->info("  - Keeping tags: " . implode(', ', $tagsToKeep));
            }
        }
        
        // Clean up unused tags
        $this->info('Cleaning up unused tags...');
        $unusedTags = Tag::doesntHave('racks')->get();
        foreach ($unusedTags as $tag) {
            $this->info("Removing unused tag: {$tag->name}");
            $tag->delete();
        }
        
        $this->info('Tagging system cleanup complete!');
        
        // Show summary
        $this->info('Summary:');
        $this->info('- Total racks: ' . Rack::count());
        $this->info('- Racks with categories: ' . Rack::whereNotNull('category')->count());
        $this->info('- Remaining tags: ' . Tag::count());
        $this->info('- Categories in use: ' . implode(', ', Rack::whereNotNull('category')->distinct()->pluck('category')->toArray()));
    }
}