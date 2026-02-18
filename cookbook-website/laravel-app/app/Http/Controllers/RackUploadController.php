<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessRackFileJob;
use App\Models\Rack;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RackUploadController extends Controller
{
    // Middleware is now applied in routes/web.php

    public function create()
    {
        return view('racks.upload');
    }

    /**
     * Step 1: Handle file upload and start analysis
     */
    public function store(Request $request)
    {
        $request->validate([
            'rack_file' => 'required|file|adg_file|max:10240', // 10MB max
            'tos_acceptance' => 'required|accepted',
        ], [
            'tos_acceptance.required' => 'You must accept the Terms of Service to upload content.',
            'tos_acceptance.accepted' => 'You must accept the Terms of Service to upload content.',
        ]);

        try {
            // Handle file upload
            $file = $request->file('rack_file');
            $originalFilename = $file->getClientOriginalName();
            
            // Generate unique filename
            $hash = hash_file('sha256', $file->getPathname());
            $filename = Str::uuid() . '.adg';
            
            // Check for duplicate files
            $existingRack = Rack::where('file_hash', $hash)->first();
            if ($existingRack) {
                throw ValidationException::withMessages([
                    'rack_file' => 'This rack file has already been uploaded.'
                ]);
            }
            
            // Store the file
            $filePath = $file->storeAs('racks', $filename, 'private');
            
            // Create minimal rack record with pending status
            // Title will be extracted during analysis
            $rack = Rack::create([
                'uuid' => Str::uuid(),
                'user_id' => auth()->id(),
                'title' => pathinfo($originalFilename, PATHINFO_FILENAME), // Temporary title from filename
                'description' => '', // Will be filled in metadata step
                'slug' => Str::slug(pathinfo($originalFilename, PATHINFO_FILENAME)),
                'file_path' => $filePath,
                'file_hash' => $hash,
                'file_size' => $file->getSize(),
                'original_filename' => $originalFilename,
                'rack_type' => 'Unknown', // Default value, will be updated by job
                'status' => 'processing', // Changed from 'pending' to indicate analysis in progress
                'is_public' => true
            ]);
            
            // Dispatch background processing
            ProcessRackFileJob::dispatch($rack);
            
            // Redirect directly to metadata (parallel processing)
            return redirect()->route('racks.metadata', $rack)
                ->with('success', 'File uploaded successfully! You can enter metadata while we analyze your rack in the background.');
                
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['rack_file' => 'Failed to process rack file: ' . $e->getMessage()]);
        }
    }

    /**
     * Show analysis progress and redirect when complete
     */
    public function analysis(Rack $rack)
    {
        // Ensure user owns this rack
        if ($rack->user_id !== auth()->id()) {
            abort(403);
        }

        // If analysis is complete, redirect to annotation step
        if ($rack->status === 'pending') {
            return redirect()->route('racks.annotate', $rack);
        }

        // If there was an error during processing
        if ($rack->status === 'error') {
            return redirect()->route('racks.upload')
                ->withErrors(['rack_file' => 'There was an error analyzing your rack file. Please try again.']);
        }

        // Show analysis progress page
        return view('racks.analysis', compact('rack'));
    }
    
    // Methods moved to ProcessRackFileJob:
    // - countDevices()
    // - flattenDevices() 
    // - detectCategory()
    
    private function attachTags($rack, $tagString)
    {
        $tagNames = array_filter(array_map('trim', explode(',', $tagString)));
        
        foreach ($tagNames as $tagName) {
            if (strlen($tagName) > 2) { // Minimum tag length
                $tag = Tag::firstOrCreate([
                    'name' => $tagName,
                    'slug' => Str::slug($tagName)
                ]);
                
                $rack->tags()->attach($tag->id);
            }
        }
    }
    
    /**
     * Handle security scan completion callback
     */
    public function handleSecurityScanResult(Request $request)
    {
        // This would be called by the virus scanning job upon completion
        // to update the rack status and potentially trigger next steps
        
        $request->validate([
            'rack_id' => 'required|exists:racks,id',
            'scan_result' => 'required|array',
            'is_clean' => 'required|boolean'
        ]);
        
        $rack = Rack::findOrFail($request->rack_id);
        $scanResult = $request->scan_result;
        $isClean = $request->is_clean;
        
        Log::info('Processing security scan result', [
            'rack_id' => $rack->id,
            'is_clean' => $isClean,
            'user_id' => $rack->user_id
        ]);
        
        if ($isClean) {
            // File is clean, proceed with rack analysis
            $rack->update([
                'status' => 'processing',
                'processing_status' => 'analyzing',
                'security_status' => ScanStatus::CLEAN->value
            ]);
            
            // Now dispatch the rack processing job
            ProcessRackFileJob::dispatch($rack);
            
        } else {
            // File is infected, block processing
            $rack->update([
                'status' => 'security_blocked',
                'processing_status' => 'virus_detected', 
                'security_status' => ScanStatus::INFECTED->value
            ]);
            
            Log::warning('Rack file blocked due to security threat', [
                'rack_id' => $rack->id,
                'scan_result' => $scanResult,
                'user_id' => $rack->user_id
            ]);
        }
        
        return response()->json(['success' => true]);
    }
}
