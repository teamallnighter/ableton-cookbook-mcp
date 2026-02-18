<?php

/**
 * @OA\Schema(
 *     schema="BlogPost",
 *     type="object",
 *     title="BlogPost",
 *     description="Blog post model",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="title", type="string", example="Advanced Ableton Live Techniques"),
 *     @OA\Property(property="slug", type="string", example="advanced-ableton-live-techniques"),
 *     @OA\Property(property="excerpt", type="string", example="Learn advanced techniques to take your Ableton Live production to the next level"),
 *     @OA\Property(property="content", type="string", example="Full blog post content here..."),
 *     @OA\Property(property="featured_image_path", type="string", nullable=true),
 *     @OA\Property(property="featured", type="boolean", example=true),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="views_count", type="integer", example=250),
 *     @OA\Property(property="published_at", type="string", format="date-time"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(
 *         property="author",
 *         ref="#/components/schemas/User"
 *     ),
 *     @OA\Property(
 *         property="category",
 *         ref="#/components/schemas/BlogCategory"
 *     )
 * )
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\CommonMark\CommonMarkConverter;

class BlogPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'blog_category_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'featured_image_path',
        'published_at',
        'featured',
        'is_active',
        'meta',
        'views_count',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'featured' => 'boolean',
        'is_active' => 'boolean',
        'meta' => 'array',
        'views_count' => 'integer',
    ];

    /**
     * Get the author of the post
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the category of the post
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }

    /**
     * Get the newsletter for this post
     */
    public function newsletter()
    {
        return $this->hasOne(Newsletter::class);
    }

    /**
     * Scope for published posts
     */
    public function scopePublished($query)
    {
        return $query->where('is_active', true)
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now());
    }

    /**
     * Scope for featured posts
     */
    public function scopeFeatured($query)
    {
        return $query->where('featured', true);
    }

    /**
     * Scope for active posts
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Generate slug from title
     */
    public static function boot()
    {
        parent::boot();

        static::creating(function ($post) {
            if (empty($post->slug)) {
                $post->slug = Str::slug($post->title);
                
                // Ensure unique slug
                $originalSlug = $post->slug;
                $count = 1;
                while (static::where('slug', $post->slug)->exists()) {
                    $post->slug = $originalSlug . '-' . $count;
                    $count++;
                }
            }
        });

        static::updating(function ($post) {
            if ($post->isDirty('title') && $post->slug === Str::slug($post->getOriginal('title'))) {
                $post->slug = Str::slug($post->title);
                
                // Ensure unique slug
                $originalSlug = $post->slug;
                $count = 1;
                while (static::where('slug', $post->slug)->where('id', '!=', $post->id)->exists()) {
                    $post->slug = $originalSlug . '-' . $count;
                    $count++;
                }
            }
        });
    }

    /**
     * Get the featured image URL
     */
    public function getFeaturedImageUrlAttribute()
    {
        if ($this->featured_image_path) {
            return Storage::url($this->featured_image_path);
        }
        return null;
    }

    /**
     * Check if post is published
     */
    public function getIsPublishedAttribute()
    {
        return $this->is_active && 
               $this->published_at !== null && 
               $this->published_at->isPast();
    }

    /**
     * Get reading time estimate
     */
    public function getReadingTimeAttribute()
    {
        $words = str_word_count(strip_tags($this->content));
        $minutes = ceil($words / 200); // Average reading speed
        return $minutes;
    }

    /**
     * Increment view count
     */
    public function incrementViews()
    {
        $this->increment('views_count');
    }

    /**
     * Get related posts
     */
    public function getRelatedPosts($limit = 3)
    {
        return static::published()
            ->where('id', '!=', $this->id)
            ->where('blog_category_id', $this->blog_category_id)
            ->latest('published_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Convert content to HTML (handles both markdown and plain text)
     */
    public function getHtmlContentAttribute()
    {
        // Check if content contains HTML tags
        if (preg_match('/<[^>]+>/', $this->content)) {
            // Content appears to be HTML, return as-is
            return $this->content;
        }
        
        // Check if content contains markdown indicators
        if (preg_match('/[#*_`\[\]!]/', $this->content)) {
            // Content appears to be markdown, convert it
            $converter = new CommonMarkConverter([
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]);
            return $converter->convert($this->content ?: '');
        }
        
        // Plain text - convert line breaks to <br> tags
        return nl2br(e($this->content ?: ''));
    }

    /**
     * Get a truncated version of the content as HTML
     */
    public function getHtmlExcerptAttribute()
    {
        if ($this->excerpt) {
            $converter = new CommonMarkConverter([
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]);
            return $converter->convert($this->excerpt);
        }
        
        // If no excerpt, truncate content
        $truncated = Str::limit($this->content, 200);
        $converter = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
        return $converter->convert($truncated);
    }
}