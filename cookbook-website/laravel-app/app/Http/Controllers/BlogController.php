<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\BlogCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BlogController extends Controller
{
    /**
     * Display a listing of blog posts
     */
    public function index(Request $request)
    {
        $query = BlogPost::with(['author', 'category'])
            ->published()
            ->latest('published_at');

        // Filter by category
        if ($request->filled('category')) {
            $category = BlogCategory::where('slug', $request->category)->firstOrFail();
            $query->where('blog_category_id', $category->id);
        }

        $posts = $query->paginate(12);
        $categories = Cache::remember('blog.categories', 3600, function() {
            return BlogCategory::active()
                ->withCount('publishedPosts')
                ->ordered()
                ->get();
        });

        $featuredPosts = Cache::remember('blog.featured', 1800, function() {
            return BlogPost::published()
                ->featured()
                ->with(['author', 'category'])
                ->latest('published_at')
                ->take(3)
                ->get();
        });

        return view('blog.index', compact('posts', 'categories', 'featuredPosts'));
    }

    /**
     * Display the specified post
     */
    public function show($slug)
    {
        $post = BlogPost::with(['author', 'category'])
            ->where('slug', $slug)
            ->published()
            ->firstOrFail();

        // Increment view count
        $post->incrementViews();

        // Get related posts
        $relatedPosts = $post->getRelatedPosts();

        // Get categories for sidebar
        $categories = Cache::remember('blog.categories', 3600, function() {
            return BlogCategory::active()
                ->withCount('publishedPosts')
                ->ordered()
                ->get();
        });

        return view('blog.show', compact('post', 'relatedPosts', 'categories'));
    }

    /**
     * Display posts by category
     */
    public function category($slug)
    {
        $category = BlogCategory::where('slug', $slug)
            ->active()
            ->firstOrFail();

        $posts = BlogPost::with(['author', 'category'])
            ->published()
            ->where('blog_category_id', $category->id)
            ->latest('published_at')
            ->paginate(12);

        $categories = Cache::remember('blog.categories', 3600, function() {
            return BlogCategory::active()
                ->withCount('publishedPosts')
                ->ordered()
                ->get();
        });

        return view('blog.category', compact('category', 'posts', 'categories'));
    }

    /**
     * Search blog posts
     */
    public function search(Request $request)
    {
        $query = $request->get('q');
        
        $request->validate([
            'q' => 'required|string|min:2|max:100'
        ]);

        $posts = BlogPost::with(['author', 'category'])
            ->published()
            ->where(function($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('excerpt', 'like', "%{$query}%")
                  ->orWhere('content', 'like', "%{$query}%");
            })
            ->latest('published_at')
            ->paginate(12)
            ->appends(['q' => $query]);

        $categories = Cache::remember('blog.categories', 3600, function() {
            return BlogCategory::active()
                ->withCount('publishedPosts')
                ->ordered()
                ->get();
        });

        return view('blog.search', compact('posts', 'query', 'categories'));
    }

    /**
     * Generate RSS feed
     */
    public function rss()
    {
        $posts = BlogPost::with(['author', 'category'])
            ->published()
            ->latest('published_at')
            ->take(20)
            ->get();

        return response()->view('blog.rss', compact('posts'))
            ->header('Content-Type', 'application/rss+xml');
    }

    /**
     * Generate sitemap
     */
    public function sitemap()
    {
        $posts = BlogPost::published()
            ->select('slug', 'updated_at')
            ->latest('published_at')
            ->get();

        return response()->view('blog.sitemap', compact('posts'))
            ->header('Content-Type', 'application/xml');
    }
}