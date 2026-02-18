<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\NewsletterMail;
use App\Models\BlogPost;
use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class NewsletterController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }

    public function index()
    {
        $newsletters = Newsletter::with(['blogPost', 'creator'])
            ->latest()
            ->paginate(15);

        $stats = [
            'total_newsletters' => Newsletter::count(),
            'sent_newsletters' => Newsletter::sent()->count(),
            'subscribers' => User::whereJsonContains('notification_preferences->newsletter', true)->count(),
            'draft_newsletters' => Newsletter::draft()->count(),
        ];

        return view('admin.newsletter.index', compact('newsletters', 'stats'));
    }

    public function create()
    {
        $blogPosts = BlogPost::published()
            ->doesntHave('newsletter')
            ->latest('published_at')
            ->limit(20)
            ->get();

        return view('admin.newsletter.create', compact('blogPosts'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
            'blog_post_id' => 'nullable|exists:blog_posts,id',
            'template_type' => 'required|in:blog_announcement,general,monthly_digest',
        ]);

        $newsletter = Newsletter::create([
            ...$validated,
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        return redirect()
            ->route('admin.newsletter.show', $newsletter)
            ->with('success', 'Newsletter created successfully!');
    }

    public function show(Newsletter $newsletter)
    {
        $newsletter->load(['blogPost', 'creator']);
        $subscriberCount = User::whereJsonContains('notification_preferences->newsletter', true)->count();

        return view('admin.newsletter.show', compact('newsletter', 'subscriberCount'));
    }

    public function edit(Newsletter $newsletter)
    {
        if ($newsletter->status === 'sent') {
            return redirect()
                ->route('admin.newsletter.show', $newsletter)
                ->with('error', 'Cannot edit sent newsletters.');
        }

        $blogPosts = BlogPost::published()
            ->where(function ($query) use ($newsletter) {
                $query->doesntHave('newsletter')
                    ->orWhere('id', $newsletter->blog_post_id);
            })
            ->latest('published_at')
            ->limit(20)
            ->get();

        return view('admin.newsletter.edit', compact('newsletter', 'blogPosts'));
    }

    public function update(Request $request, Newsletter $newsletter)
    {
        if ($newsletter->status === 'sent') {
            return redirect()
                ->route('admin.newsletter.show', $newsletter)
                ->with('error', 'Cannot edit sent newsletters.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
            'blog_post_id' => 'nullable|exists:blog_posts,id',
            'template_type' => 'required|in:blog_announcement,general,monthly_digest',
        ]);

        $newsletter->update($validated);

        return redirect()
            ->route('admin.newsletter.show', $newsletter)
            ->with('success', 'Newsletter updated successfully!');
    }

    public function preview(Newsletter $newsletter)
    {
        return view('emails.newsletter', [
            'newsletter' => $newsletter,
            'user' => auth()->user(),
            'unsubscribeUrl' => route('unsubscribe', ['token' => 'preview-token'])
        ]);
    }

    public function sendTest(Newsletter $newsletter)
    {
        try {
            Mail::to(auth()->user()->email)->send(new NewsletterMail($newsletter, auth()->user()));
            
            return response()->json([
                'success' => true,
                'message' => 'Test newsletter sent to ' . auth()->user()->email
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test newsletter: ' . $e->getMessage()
            ], 500);
        }
    }

    public function send(Newsletter $newsletter)
    {
        if ($newsletter->status === 'sent') {
            return redirect()
                ->route('admin.newsletter.show', $newsletter)
                ->with('error', 'Newsletter has already been sent.');
        }

        $subscribers = User::whereJsonContains('notification_preferences->newsletter', true)
            ->whereNotNull('email_verified_at')
            ->get();

        if ($subscribers->isEmpty()) {
            return redirect()
                ->route('admin.newsletter.show', $newsletter)
                ->with('error', 'No active subscribers found.');
        }

        $newsletter->update(['status' => 'sending']);

        $sentCount = 0;
        foreach ($subscribers as $subscriber) {
            try {
                Mail::to($subscriber->email)->send(new NewsletterMail($newsletter, $subscriber));
                $sentCount++;
            } catch (\Exception $e) {
                \Log::error('Newsletter send failed for user ' . $subscriber->id . ': ' . $e->getMessage());
            }
        }

        $newsletter->update([
            'status' => 'sent',
            'sent_at' => now(),
            'sent_count' => $sentCount
        ]);

        return redirect()
            ->route('admin.newsletter.show', $newsletter)
            ->with('success', "Newsletter sent successfully to {$sentCount} subscribers!");
    }

    public function destroy(Newsletter $newsletter)
    {
        if ($newsletter->status === 'sent') {
            return redirect()
                ->route('admin.newsletter.index')
                ->with('error', 'Cannot delete sent newsletters.');
        }

        $newsletter->delete();

        return redirect()
            ->route('admin.newsletter.index')
            ->with('success', 'Newsletter deleted successfully!');
    }

    public function createFromBlog(BlogPost $blogPost)
    {
        // Check if newsletter already exists for this blog post
        if ($blogPost->newsletter) {
            return redirect()
                ->route('admin.newsletter.show', $blogPost->newsletter)
                ->with('info', 'Newsletter already exists for this blog post.');
        }

        $newsletter = Newsletter::create([
            'title' => 'Blog Announcement: ' . $blogPost->title,
            'subject' => '📝 New Post: ' . $blogPost->title,
            'content' => $this->generateBlogNewsletterContent($blogPost),
            'blog_post_id' => $blogPost->id,
            'template_type' => 'blog_announcement',
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        return redirect()
            ->route('admin.newsletter.edit', $newsletter)
            ->with('success', 'Newsletter created from blog post! Review and customize before sending.');
    }

    private function generateBlogNewsletterContent(BlogPost $blogPost)
    {
        $excerpt = Str::limit(strip_tags($blogPost->content), 300);
        
        return "Hi {name},

We've just published a new blog post that we think you'll find interesting!

**{$blogPost->title}**

{$excerpt}

[Read the full post](" . route('blog.show', $blogPost->slug) . ")

Best regards,  
The Ableton Cookbook Team";
    }
}