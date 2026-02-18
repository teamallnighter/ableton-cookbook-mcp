<?php

namespace App\Http\Controllers;

use App\Models\Rack;
use App\Models\Tag;
use App\Jobs\ProcessRackFileJob;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class RackEditController extends Controller
{
    public function edit(Rack $rack)
    {
        // Check if user owns this rack
        if (auth()->id() !== $rack->user_id) {
            abort(403, 'Unauthorized');
        }

        // Redirect to the first step of the edit process
        return redirect()->route('racks.edit.upload', $rack);
    }

    public function editUpload(Rack $rack)
    {
        // Check if user owns this rack
        if (auth()->id() !== $rack->user_id) {
            abort(403, 'Unauthorized');
        }

        return view('racks.edit.upload', compact('rack'));
    }

    public function processUpload(Request $request, Rack $rack)
    {
        // Check if user owns this rack
        if (auth()->id() !== $rack->user_id) {
            abort(403, 'Unauthorized');
        }

        // If no file uploaded, skip to annotation step
        if (!$request->hasFile('rack_file')) {
            return redirect()->route('racks.edit.annotate', $rack);
        }

        $request->validate([
            'rack_file' => 'required|file|adg_file|max:10240'
        ]);

        try {
            // Delete old file if exists
            if ($rack->file_path && Storage::disk('private')->exists($rack->file_path)) {
                Storage::disk('private')->delete($rack->file_path);
            }

            // Store new file
            $file = $request->file('rack_file');
            $originalFilename = $file->getClientOriginalName();
            $hash = hash_file('sha256', $file->getRealPath());
            $filename = time() . '_' . $hash . '.adg';
            $filePath = $file->storeAs('racks', $filename, 'private');

            // Update rack with new file info and reset status
            $rack->update([
                'file_path' => $filePath,
                'file_hash' => $hash,
                'file_size' => $file->getSize(),
                'original_filename' => $originalFilename,
                'status' => 'processing',
                'chains' => null, // Will be re-analyzed
                'device_count' => 0,
                'chain_count' => 0,
                'chain_annotations' => null // Reset annotations since structure changed
            ]);

            // Re-analyze the new rack file
            ProcessRackFileJob::dispatch($rack);

            // Redirect to analysis waiting
            return redirect()->route('racks.edit.analysis', $rack)
                ->with('success', 'New rack file uploaded! Re-analyzing structure...');

        } catch (\Exception $e) {
            return back()->withErrors(['rack_file' => 'Failed to process rack file: ' . $e->getMessage()]);
        }
    }

    public function editAnalysis(Rack $rack)
    {
        // Check if user owns this rack
        if (auth()->id() !== $rack->user_id) {
            abort(403, 'Unauthorized');
        }

        // If analysis is complete, redirect to annotation step
        if ($rack->status === 'pending') {
            return redirect()->route('racks.edit.annotate', $rack);
        }

        // If there was an error during processing
        if ($rack->status === 'error') {
            return redirect()->route('racks.edit.upload', $rack)
                ->withErrors(['rack_file' => 'There was an error analyzing your rack file. Please try again.']);
        }

        // Show analysis progress page
        return view('racks.edit.analysis', compact('rack'));
    }

    public function editAnnotate(Rack $rack)
    {
        // Check if user owns this rack
        if (auth()->id() !== $rack->user_id) {
            abort(403, 'Unauthorized');
        }

        return view('racks.edit.annotate', compact('rack'));
    }

    public function saveAnnotations(Request $request, Rack $rack)
    {
        // Check if user owns this rack
        if (auth()->id() !== $rack->user_id) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'chain_annotations' => 'nullable|array',
            'chain_annotations.*.custom_name' => 'nullable|string|max:100',
            'chain_annotations.*.note' => 'nullable|string|max:500'
        ]);

        $rack->update([
            'chain_annotations' => $request->chain_annotations
        ]);

        return redirect()->route('racks.edit.metadata', $rack);
    }

    public function editMetadata(Rack $rack)
    {
        // Check if user owns this rack
        if (auth()->id() !== $rack->user_id) {
            abort(403, 'Unauthorized');
        }

        $categories = [
            'Distortion',
            'Modulation', 
            'Time',
            'Mixing',
            'Instruments',
            'Drums',
            'Vocal',
            'Guitar',
            'Bass',
            'Creative',
            'Mastering'
        ];

        return view('racks.edit.metadata', compact('rack', 'categories'));
    }

    public function update(Request $request, Rack $rack)
    {
        // Check if user owns this rack
        if (auth()->id() !== $rack->user_id) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
            'category' => 'nullable|string|max:50',
            'tags' => 'nullable|string|max:500',
            'how_to_article' => 'nullable|string|max:100000'
        ]);

        // Update rack
        $rack->update([
            'title' => $request->title,
            'slug' => Str::slug($request->title),
            'description' => $request->description,
            'category' => $request->category,
            'how_to_article' => $request->how_to_article,
            'how_to_updated_at' => $request->how_to_article ? now() : null
        ]);

        // Update tags
        if ($request->tags) {
            // Detach old tags
            $rack->tags()->detach();
            
            // Attach new tags
            $tagNames = array_filter(array_map('trim', explode(',', $request->tags)));
            foreach ($tagNames as $tagName) {
                if (strlen($tagName) > 2) {
                    $tag = Tag::firstOrCreate([
                        'name' => $tagName,
                        'slug' => Str::slug($tagName)
                    ]);
                    $rack->tags()->attach($tag->id);
                }
            }
        } else {
            // Remove all tags if none provided
            $rack->tags()->detach();
        }

        return redirect()->route('racks.show', $rack)
            ->with('success', 'Rack updated successfully!');
    }
}
