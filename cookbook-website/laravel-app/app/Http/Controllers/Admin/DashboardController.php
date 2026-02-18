<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Comment;
use App\Models\Issue;
use App\Models\Rack;
use App\Models\RackDownload;
use App\Models\RackRating;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }

    public function index()
    {
        $stats = $this->getOverviewStats();
        $charts = $this->getChartData();
        $recentActivity = $this->getRecentActivity();
        $topPerformers = $this->getTopPerformers();
        $issues = $this->getIssueStats();

        // Check if we should use the enhanced view
        $useEnhancedView = request()->get('enhanced', false) || auth()->user()->hasFeatureFlag('enhanced_admin_dashboard');

        if ($useEnhancedView) {
            return view('admin.dashboard.enhanced-main', compact(
                'stats',
                'charts', 
                'recentActivity',
                'topPerformers',
                'issues'
            ));
        }

        return view('admin.dashboard.index', compact(
            'stats',
            'charts', 
            'recentActivity',
            'topPerformers',
            'issues'
        ));
    }

    private function getOverviewStats()
    {
        $thirtyDaysAgo = Carbon::now()->subDays(30);
        
        return [
            'total_users' => User::count(),
            'new_users_30d' => User::where('created_at', '>=', $thirtyDaysAgo)->count(),
            'total_racks' => Rack::count(),
            'new_racks_30d' => Rack::where('created_at', '>=', $thirtyDaysAgo)->count(),
            'total_downloads' => RackDownload::count(),
            'downloads_30d' => RackDownload::where('created_at', '>=', $thirtyDaysAgo)->count(),
            'total_comments' => Comment::count(),
            'comments_30d' => Comment::where('created_at', '>=', $thirtyDaysAgo)->count(),
            'total_blog_posts' => BlogPost::count(),
            'published_blog_posts' => BlogPost::whereNotNull('published_at')->count(),
            'pending_issues' => Issue::whereIn('status', ['pending', 'in_review'])->count(),
            'urgent_issues' => Issue::where('priority', 'urgent')->whereIn('status', ['pending', 'in_review'])->count(),
        ];
    }

    private function getChartData()
    {
        $last30Days = collect(range(29, 0))->map(function ($daysBack) {
            return Carbon::now()->subDays($daysBack)->format('Y-m-d');
        });

        $downloads = RackDownload::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->groupBy('date')
            ->pluck('count', 'date');

        $newUsers = User::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->groupBy('date')
            ->pluck('count', 'date');

        $newRacks = Rack::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->groupBy('date')
            ->pluck('count', 'date');

        return [
            'dates' => $last30Days->values(),
            'downloads' => $last30Days->map(fn($date) => $downloads->get($date, 0))->values(),
            'users' => $last30Days->map(fn($date) => $newUsers->get($date, 0))->values(),
            'racks' => $last30Days->map(fn($date) => $newRacks->get($date, 0))->values(),
        ];
    }

    private function getRecentActivity()
    {
        $recentRacks = Rack::with('user')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn($rack) => [
                'type' => 'rack_upload',
                'title' => $rack->title,
                'user' => $rack->user->name,
                'created_at' => $rack->created_at,
                'url' => route('racks.show', $rack->id)
            ]);

        $recentComments = Comment::with(['user', 'rack'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn($comment) => [
                'type' => 'comment',
                'title' => 'Comment on ' . $comment->rack->title,
                'user' => $comment->user->name,
                'created_at' => $comment->created_at,
                'url' => route('racks.show', $comment->rack->id) . '#comment-' . $comment->id
            ]);

        $recentBlogPosts = BlogPost::with('user')
            ->whereNotNull('published_at')
            ->latest('published_at')
            ->limit(3)
            ->get()
            ->map(fn($post) => [
                'type' => 'blog_post',
                'title' => $post->title,
                'user' => $post->user->name,
                'created_at' => $post->published_at,
                'url' => route('blog.show', $post->slug)
            ]);

        return collect()
            ->concat($recentRacks)
            ->concat($recentComments)
            ->concat($recentBlogPosts)
            ->sortByDesc('created_at')
            ->take(10)
            ->values();
    }

    private function getTopPerformers()
    {
        $topRacksByDownloads = Rack::withCount('downloads')
            ->orderByDesc('downloads_count')
            ->limit(5)
            ->get()
            ->map(fn($rack) => [
                'title' => $rack->title,
                'metric' => number_format($rack->downloads_count) . ' downloads',
                'url' => route('racks.show', $rack->id)
            ]);

        $topRacksByRating = Rack::withAvg('ratings', 'rating')
            ->havingRaw('ratings_avg_rating IS NOT NULL')
            ->orderByDesc('ratings_avg_rating')
            ->limit(5)
            ->get()
            ->map(fn($rack) => [
                'title' => $rack->title,
                'metric' => number_format($rack->ratings_avg_rating, 1) . ' stars',
                'url' => route('racks.show', $rack->id)
            ]);

        $topUsers = User::withCount(['racks', 'downloads'])
            ->orderByDesc('racks_count')
            ->limit(5)
            ->get()
            ->map(fn($user) => [
                'title' => $user->name,
                'metric' => $user->racks_count . ' racks uploaded',
                'url' => route('profile.show', $user->username ?? $user->id)
            ]);

        return [
            'top_downloads' => $topRacksByDownloads,
            'top_rated' => $topRacksByRating,
            'top_uploaders' => $topUsers,
        ];
    }

    private function getIssueStats()
    {
        $issuesByStatus = Issue::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $issuesByType = Issue::selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type');

        $recentIssues = Issue::with('user')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn($issue) => [
                'id' => $issue->id,
                'title' => $issue->title,
                'type' => $issue->type,
                'priority' => $issue->priority,
                'status' => $issue->status,
                'user' => $issue->user->name,
                'created_at' => $issue->created_at,
                'url' => route('admin.issues.show', $issue->id)
            ]);

        return [
            'by_status' => $issuesByStatus,
            'by_type' => $issuesByType,
            'recent' => $recentIssues,
        ];
    }
}