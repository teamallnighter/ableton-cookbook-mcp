<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('collection_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained('enhanced_collections')->cascadeOnDelete();
            $table->date('date');
            
            // Daily metrics
            $table->unsignedInteger('unique_visitors')->default(0);
            $table->unsignedInteger('total_page_views')->default(0);
            $table->unsignedInteger('unique_users')->default(0);
            $table->unsignedInteger('returning_users')->default(0);
            $table->unsignedInteger('new_users')->default(0);
            
            // Engagement metrics
            $table->decimal('average_session_duration', 8, 2)->default(0); // In minutes
            $table->decimal('bounce_rate', 5, 2)->default(0); // Percentage
            $table->unsignedInteger('total_sessions')->default(0);
            $table->decimal('pages_per_session', 5, 2)->default(0);
            
            // Collection-specific metrics
            $table->unsignedInteger('items_viewed')->default(0);
            $table->unsignedInteger('items_completed')->default(0);
            $table->unsignedInteger('collection_saves')->default(0);
            $table->unsignedInteger('collection_shares')->default(0);
            $table->unsignedInteger('downloads_initiated')->default(0);
            $table->unsignedInteger('downloads_completed')->default(0);
            
            // User actions
            $table->unsignedInteger('comments_posted')->default(0);
            $table->unsignedInteger('reviews_submitted')->default(0);
            $table->unsignedInteger('likes_given')->default(0);
            $table->unsignedInteger('follows_gained')->default(0);
            $table->unsignedInteger('unfollows')->default(0);
            
            // Learning path specific metrics
            $table->unsignedInteger('paths_started')->default(0);
            $table->unsignedInteger('paths_completed')->default(0);
            $table->unsignedInteger('certificates_earned')->default(0);
            $table->decimal('average_completion_rate', 5, 2)->default(0);
            $table->decimal('average_progress_score', 5, 2)->default(0);
            
            // Content performance
            $table->json('top_items_viewed')->nullable(); // Most viewed items and counts
            $table->json('top_exit_points')->nullable(); // Where users commonly leave
            $table->json('conversion_funnel')->nullable(); // View -> Save -> Download rates
            
            // Traffic sources
            $table->unsignedInteger('organic_traffic')->default(0);
            $table->unsignedInteger('direct_traffic')->default(0);
            $table->unsignedInteger('referral_traffic')->default(0);
            $table->unsignedInteger('social_traffic')->default(0);
            $table->unsignedInteger('search_traffic')->default(0);
            $table->json('referrer_breakdown')->nullable(); // Detailed referrer data
            
            // Device and technology metrics
            $table->json('device_breakdown')->nullable(); // Desktop, mobile, tablet
            $table->json('browser_breakdown')->nullable(); // Browser usage stats
            $table->json('os_breakdown')->nullable(); // Operating system stats
            $table->json('country_breakdown')->nullable(); // Geographic data
            
            // Performance metrics
            $table->decimal('average_load_time', 6, 2)->default(0); // In seconds
            $table->unsignedInteger('error_count')->default(0);
            $table->json('error_breakdown')->nullable(); // Types of errors
            
            // Revenue and monetization (if applicable)
            $table->decimal('revenue_generated', 10, 2)->default(0);
            $table->unsignedInteger('premium_upgrades')->default(0);
            $table->unsignedInteger('ad_clicks')->default(0);
            $table->decimal('ad_revenue', 10, 2)->default(0);
            
            // Social metrics
            $table->unsignedInteger('shares_facebook')->default(0);
            $table->unsignedInteger('shares_twitter')->default(0);
            $table->unsignedInteger('shares_linkedin')->default(0);
            $table->unsignedInteger('shares_other')->default(0);
            $table->unsignedInteger('embed_count')->default(0);
            
            // Search and discovery
            $table->json('search_keywords')->nullable(); // Keywords that led to collection
            $table->json('internal_search_terms')->nullable(); // What users searched for within collection
            $table->unsignedInteger('featured_impressions')->default(0); // Times shown as featured
            $table->unsignedInteger('recommendation_clicks')->default(0); // Clicks from recommendations
            
            // Quality metrics
            $table->decimal('user_satisfaction_score', 3, 2)->nullable(); // 1-5 scale
            $table->unsignedInteger('reported_issues')->default(0);
            $table->unsignedInteger('support_tickets')->default(0);
            $table->json('feedback_summary')->nullable(); // Aggregated user feedback
            
            // A/B testing metrics
            $table->json('experiment_data')->nullable(); // A/B test results for this collection
            
            // Cohort analysis data
            $table->json('user_cohort_data')->nullable(); // How different user cohorts interact
            
            // Predictive metrics
            $table->decimal('predicted_churn_risk', 5, 2)->nullable(); // Risk of users abandoning
            $table->decimal('growth_velocity', 8, 2)->nullable(); // Rate of growth
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['collection_id', 'date']);
            $table->index(['date', 'unique_visitors']);
            $table->index(['collection_id', 'total_page_views']);
            $table->index(['date', 'revenue_generated']);
            $table->index(['collection_id', 'average_completion_rate']);
            
            // Ensure unique daily records per collection
            $table->unique(['collection_id', 'date'], 'unique_collection_daily_analytics');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collection_analytics');
    }
};