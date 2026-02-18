<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\BlogCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BlogAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('admin');
    }

    /**
     * Display a listing of blog posts
     */
    public function index(Request $request)
    {
        $query = BlogPost::with(['author', 'category'])
            ->latest();

        // Filter by category
        if ($request->filled('category')) {
            $query->where('blog_category_id', $request->category);
        }

        // Filter by status
        if ($request->filled('status')) {
            switch ($request->status) {
                case 'published':
                    $query->published();
                    break;
                case 'draft':
                    $query->where('published_at', null);
                    break;
                case 'scheduled':
                    $query->where('published_at', '>', now());
                    break;
            }
        }

        $posts = $query->paginate(15);
        $categories = BlogCategory::active()->ordered()->get();

        return view('admin.blog.index', compact('posts', 'categories'));
    }

    /**
     * Show the form for creating a new post
     */
    public function create()
    {
        $categories = BlogCategory::active()->ordered()->get();
        return view('admin.blog.create', compact('categories'));
    }

    /**
     * Store a newly created post
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'excerpt' => 'required|string|max:1000',
            'content' => 'required|string',
            'blog_category_id' => 'required|exists:blog_categories,id',
            'featured_image' => 'nullable|image|max:5120', // 5MB max
            'published_at' => 'nullable|date',
            'featured' => 'boolean',
            'is_active' => 'boolean',
            'meta_title' => 'nullable|string|max:60',
            'meta_description' => 'nullable|string|max:160',
            'action' => 'nullable|string|in:save_draft,publish_now,update',
        ]);

        // Handle different actions
        $action = $request->input('action', 'update');
        
        if ($action === 'save_draft') {
            $validated['published_at'] = null;
            $validated['is_active'] = false;
        } elseif ($action === 'publish_now') {
            $validated['published_at'] = now();
            $validated['is_active'] = true;
        }

        // Handle featured image upload
        if ($request->hasFile('featured_image')) {
            $validated['featured_image_path'] = $request->file('featured_image')
                ->store('blog-images', 'public');
        }

        // Set author
        $validated['user_id'] = auth()->id();

        // Handle meta data
        $validated['meta'] = [
            'title' => $request->meta_title,
            'description' => $request->meta_description,
        ];

        // Remove meta fields from main data
        unset($validated['meta_title'], $validated['meta_description'], $validated['action']);

        $post = BlogPost::create($validated);

        $message = match($action) {
            'save_draft' => 'Blog post saved as draft!',
            'publish_now' => 'Blog post published successfully!',
            default => 'Blog post created successfully!'
        };

        return redirect()->route('admin.blog.index')
            ->with('success', $message);
    }

    /**
     * Display the specified post
     */
    public function show(BlogPost $post)
    {
        $post->load(['author', 'category']);
        return view('admin.blog.show', compact('post'));
    }

    /**
     * Show the form for editing the specified post
     */
    public function edit(BlogPost $post)
    {
        $categories = BlogCategory::active()->ordered()->get();
        return view('admin.blog.edit', compact('post', 'categories'));
    }

    /**
     * Update the specified post
     */
    public function update(Request $request, BlogPost $post)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'excerpt' => 'required|string|max:1000',
            'content' => 'required|string',
            'blog_category_id' => 'required|exists:blog_categories,id',
            'featured_image' => 'nullable|image|max:5120',
            'published_at' => 'nullable|date',
            'featured' => 'boolean',
            'is_active' => 'boolean',
            'meta_title' => 'nullable|string|max:60',
            'meta_description' => 'nullable|string|max:160',
            'action' => 'nullable|string|in:save_draft,publish_now,update',
        ]);

        // Handle different actions
        $action = $request->input('action', 'update');
        
        if ($action === 'save_draft') {
            $validated['published_at'] = null;
            $validated['is_active'] = false;
        } elseif ($action === 'publish_now') {
            $validated['published_at'] = now();
            $validated['is_active'] = true;
        }

        // Handle featured image upload
        if ($request->hasFile('featured_image')) {
            // Delete old image
            if ($post->featured_image_path) {
                Storage::disk('public')->delete($post->featured_image_path);
            }
            
            $validated['featured_image_path'] = $request->file('featured_image')
                ->store('blog-images', 'public');
        }

        // Handle meta data
        $validated['meta'] = [
            'title' => $request->meta_title,
            'description' => $request->meta_description,
        ];

        // Remove meta fields from main data
        unset($validated['meta_title'], $validated['meta_description'], $validated['action']);

        $post->update($validated);

        $message = match($action) {
            'save_draft' => 'Blog post saved as draft!',
            'publish_now' => 'Blog post published successfully!',
            default => 'Blog post updated successfully!'
        };

        return redirect()->route('admin.blog.index')
            ->with('success', $message);
    }

    /**
     * Remove the specified post
     */
    public function destroy(BlogPost $post)
    {
        // Delete featured image
        if ($post->featured_image_path) {
            Storage::disk('public')->delete($post->featured_image_path);
        }

        $post->delete();

        return redirect()->route('admin.blog.index')
            ->with('success', 'Blog post deleted successfully!');
    }

    /**
     * Handle image uploads via drag and drop
     */
    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:5120'
        ]);

        $path = $request->file('image')->store('blog-images', 'public');
        $url = Storage::url($path);

        return response()->json([
            'success' => true,
            'url' => $url,
            'path' => $path
        ]);
    }

    /**
     * Toggle post featured status
     */
    public function toggleFeatured(BlogPost $post)
    {
        $post->update(['featured' => !$post->featured]);

        return response()->json([
            'success' => true,
            'featured' => $post->featured
        ]);
    }

    /**
     * Toggle post active status
     */
    public function toggleActive(BlogPost $post)
    {
        $post->update(['is_active' => !$post->is_active]);

        return response()->json([
            'success' => true,
            'is_active' => $post->is_active
        ]);
    }
}