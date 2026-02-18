<?php

namespace App\Http\Controllers;

use App\Models\Issue;
use App\Models\IssueType;
use App\Models\IssueFileUpload;
use App\Models\Rack;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class IssueController extends Controller
{
    public function index(Request $request)
    {
        $query = Issue::with(['issueType', 'user', 'rack']);

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('issue_type_id', $request->type);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        $issues = $query->latest()->paginate(20);
        $issueTypes = IssueType::active()->get();

        return view('issues.index', compact('issues', 'issueTypes'));
    }

    public function create(Request $request)
    {
        $issueTypes = IssueType::active()->get();
        $racks = null;

        // If reporting a specific rack issue, get rack info
        if ($request->filled('rack_id')) {
            $racks = Rack::find($request->rack_id);
        }

        return view('issues.create', compact('issueTypes', 'racks'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'issue_type_id' => 'required|exists:issue_types,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'rack_id' => 'nullable|exists:racks,id',
            'submitter_name' => 'nullable|string|max:100',
            'submitter_email' => 'nullable|email',
            'priority' => Rule::in(['low', 'medium', 'high', 'urgent']),
            'rack_file' => 'nullable|file|mimes:adg,adv,als,zip|max:51200', // 50MB
            'rack_name' => 'nullable|string|max:255',
            'rack_description' => 'nullable|string',
            'ableton_version' => 'nullable|string|max:20',
            'tags' => 'nullable|string',
        ]);

        $data = $request->only([
            'issue_type_id', 'title', 'description', 'rack_id', 
            'submitter_name', 'submitter_email', 'priority'
        ]);

        // Set user_id if authenticated
        if (auth()->check()) {
            $data['user_id'] = auth()->id();
        }

        $issue = Issue::createWithNotification($data);

        // Handle file upload
        if ($request->hasFile('rack_file')) {
            $this->handleFileUpload($request, $issue);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'issue_id' => $issue->id,
                'message' => 'Issue submitted successfully!'
            ]);
        }

        return redirect()->route('issues.show', $issue->id)
            ->with('success', 'Issue submitted successfully!');
    }

    public function show(Issue $issue)
    {
        $issue->load(['issueType', 'user', 'rack', 'fileUploads', 'comments.user']);
        
        return view('issues.show', compact('issue'));
    }

    public function update(Request $request, Issue $issue)
    {
        // Admin only
        $this->authorize('update', $issue);

        $request->validate([
            'status' => Rule::in(['pending', 'in_review', 'approved', 'rejected', 'resolved']),
            'admin_notes' => 'nullable|string',
            'comment' => 'nullable|string',
        ]);

        $issue->updateStatusWithNotification(
            $request->status,
            $request->admin_notes,
            $request->comment
        );

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Issue updated successfully!');
    }

    public function addComment(Request $request, Issue $issue)
    {
        $request->validate([
            'comment_text' => 'required|string',
            'is_public' => 'boolean',
        ]);

        $comment = $issue->comments()->create([
            'comment_text' => $request->comment_text,
            'user_id' => auth()->id(),
            'is_admin_comment' => auth()->user()?->hasRole('admin') ?? false,
            'is_public' => $request->boolean('is_public', true),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'comment' => $comment]);
        }

        return back()->with('success', 'Comment added successfully!');
    }

    private function handleFileUpload(Request $request, Issue $issue)
    {
        $file = $request->file('rack_file');
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        
        // Generate unique filename
        $storedName = Str::random(20) . '_' . time() . '.' . $extension;
        
        // Store file
        $path = $file->storeAs('issue-uploads', $storedName, 'public');

        // Create database record
        $issue->fileUploads()->create([
            'original_filename' => $originalName,
            'stored_filename' => $storedName,
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'file_type' => $extension,
            'ableton_version' => $request->ableton_version,
            'rack_name' => $request->rack_name,
            'rack_description' => $request->rack_description,
            'tags' => $request->tags ? array_map('trim', explode(',', $request->tags)) : null,
        ]);
    }

    // Admin methods
    public function adminIndex(Request $request)
    {
        $this->authorize('viewAny', Issue::class);

        $query = Issue::with(['issueType', 'user', 'rack', 'fileUploads']);

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('issue_type_id', $request->type);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        $issues = $query->latest()->paginate(20);
        $issueTypes = IssueType::all();

        return view('admin.issues.index', compact('issues', 'issueTypes'));
    }

    public function adminShow(Issue $issue)
    {
        $this->authorize('view', $issue);
        
        $issue->load(['issueType', 'user', 'rack', 'fileUploads', 'comments.user']);
        
        return view('admin.issues.show', compact('issue'));
    }
}
