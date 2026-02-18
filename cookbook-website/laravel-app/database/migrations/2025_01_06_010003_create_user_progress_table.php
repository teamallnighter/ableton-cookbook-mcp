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
        Schema::create('user_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('collection_id')->constrained('enhanced_collections')->cascadeOnDelete();
            $table->foreignId('collection_item_id')->nullable()->constrained()->cascadeOnDelete();
            
            // Progress tracking
            $table->enum('status', ['not_started', 'in_progress', 'completed', 'paused', 'failed'])->default('not_started');
            $table->decimal('completion_percentage', 5, 2)->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            
            // Time tracking
            $table->unsignedInteger('total_time_minutes')->default(0);
            $table->unsignedInteger('active_time_minutes')->default(0); // Actual engaged time
            $table->json('session_times')->nullable(); // Detailed session tracking
            
            // Attempt tracking
            $table->unsignedInteger('attempt_count')->default(0);
            $table->unsignedInteger('current_attempt')->default(1);
            $table->json('attempt_history')->nullable(); // History of all attempts
            
            // Score and assessment
            $table->decimal('current_score', 5, 2)->nullable();
            $table->decimal('best_score', 5, 2)->nullable();
            $table->json('assessment_results')->nullable(); // Detailed assessment data
            $table->boolean('passed_assessment')->nullable();
            
            // Learning path specific
            $table->json('completed_items')->nullable(); // Array of completed item IDs
            $table->json('unlocked_items')->nullable(); // Array of unlocked item IDs
            $table->json('milestone_achievements')->nullable(); // Milestones reached
            $table->integer('current_position')->default(0); // Current position in path
            
            // User interaction data
            $table->json('bookmarks')->nullable(); // Bookmarked positions/items
            $table->json('notes')->nullable(); // User's personal notes
            $table->json('highlights')->nullable(); // Highlighted content
            $table->json('user_tags')->nullable(); // User's custom tags
            
            // Adaptive learning data
            $table->json('learning_preferences')->nullable(); // Preferred learning style
            $table->json('difficulty_adjustments')->nullable(); // System-made adjustments
            $table->json('performance_metrics')->nullable(); // Detailed performance data
            
            // Collaboration and social
            $table->json('shared_progress')->nullable(); // Progress shared with others
            $table->json('peer_comparisons')->nullable(); // How user compares to peers
            $table->boolean('allows_peer_visibility')->default(false);
            
            // Gamification progress
            $table->unsignedInteger('points_earned')->default(0);
            $table->json('badges_earned')->nullable(); // Array of earned badge IDs
            $table->unsignedInteger('streak_days')->default(0);
            $table->timestamp('last_streak_date')->nullable();
            
            // Engagement metrics
            $table->unsignedInteger('login_count')->default(0);
            $table->decimal('engagement_score', 5, 2)->nullable(); // Calculated engagement
            $table->json('interaction_patterns')->nullable(); // Usage pattern analysis
            
            // Feedback and quality
            $table->text('feedback_given')->nullable(); // User feedback about content
            $table->integer('content_rating')->nullable(); // 1-5 rating of content
            $table->boolean('would_recommend')->nullable();
            
            // System metadata
            $table->string('device_type')->nullable(); // Device used for learning
            $table->json('browser_data')->nullable(); // Browser/app information
            $table->json('location_data')->nullable(); // General location data
            
            // Resume functionality
            $table->json('resume_data')->nullable(); // Data needed to resume exactly where left off
            $table->string('last_section')->nullable(); // Last section/module accessed
            $table->integer('last_position')->nullable(); // Last position within content
            
            // Certificate and completion
            $table->boolean('certificate_earned')->default(false);
            $table->string('certificate_id')->nullable();
            $table->timestamp('certificate_issued_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['user_id', 'collection_id']);
            $table->index(['user_id', 'status', 'last_accessed_at']);
            $table->index(['collection_id', 'completion_percentage']);
            $table->index(['collection_item_id', 'status']);
            $table->index(['started_at', 'completed_at']);
            $table->index(['certificate_earned', 'certificate_issued_at']);
            $table->index('last_accessed_at');
            
            // Ensure unique progress per user per collection
            $table->unique(['user_id', 'collection_id'], 'unique_user_collection_progress');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_progress');
    }
};