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
        Schema::create('collection_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('collection_id')->constrained('enhanced_collections')->cascadeOnDelete();
            
            // Review core data
            $table->integer('rating'); // 1-5 star rating
            $table->string('title')->nullable(); // Optional review title
            $table->text('content'); // Review content
            $table->json('pros')->nullable(); // Array of positive points
            $table->json('cons')->nullable(); // Array of negative points
            
            // Review categories and detailed ratings
            $table->integer('content_quality_rating')->nullable(); // 1-5 rating
            $table->integer('organization_rating')->nullable(); // 1-5 rating
            $table->integer('usefulness_rating')->nullable(); // 1-5 rating
            $table->integer('difficulty_rating')->nullable(); // 1-5 rating (appropriateness)
            $table->integer('presentation_rating')->nullable(); // 1-5 rating
            
            // Review metadata
            $table->enum('review_type', ['general', 'detailed', 'expert', 'quick'])->default('general');
            $table->json('tags')->nullable(); // User-defined tags for the review
            $table->boolean('is_verified_purchase')->default(false); // If user actually used/downloaded
            $table->boolean('completed_collection')->default(false); // User completed the collection
            
            // User context
            $table->enum('user_experience_level', ['beginner', 'intermediate', 'advanced', 'expert'])->nullable();
            $table->string('user_use_case')->nullable(); // How they used the collection
            $table->integer('time_spent_hours')->nullable(); // Time spent with collection
            
            // Review status and moderation
            $table->enum('status', ['draft', 'published', 'under_review', 'approved', 'rejected', 'flagged'])->default('published');
            $table->text('moderation_notes')->nullable(); // Admin notes
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            
            // Community interaction
            $table->unsignedInteger('helpful_count')->default(0); // "Was this helpful?" votes
            $table->unsignedInteger('unhelpful_count')->default(0);
            $table->unsignedInteger('total_votes')->default(0);
            $table->decimal('helpfulness_ratio', 5, 2)->default(0); // helpful/(helpful+unhelpful)
            
            // Response and follow-up
            $table->text('creator_response')->nullable(); // Collection creator's response
            $table->timestamp('creator_responded_at')->nullable();
            $table->boolean('user_acknowledged_response')->default(false);
            $table->text('user_follow_up')->nullable(); // User's follow-up after creator response
            
            // Review quality indicators
            $table->boolean('is_featured_review')->default(false); // Highlighted by admins
            $table->integer('quality_score')->nullable(); // System-calculated quality (1-100)
            $table->json('quality_indicators')->nullable(); // What makes this review high quality
            $table->boolean('verified_reviewer')->default(false); // Verified/trusted reviewer
            
            // Media attachments
            $table->json('attachments')->nullable(); // Screenshots, videos, etc.
            $table->json('media_metadata')->nullable(); // Metadata about attached media
            
            // Update tracking
            $table->boolean('is_updated')->default(false);
            $table->text('update_reason')->nullable(); // Why review was updated
            $table->json('version_history')->nullable(); // History of changes
            $table->timestamp('last_updated_at')->nullable();
            
            // Spam and abuse prevention
            $table->unsignedInteger('report_count')->default(0); // Number of times reported
            $table->json('report_reasons')->nullable(); // Reasons for reports
            $table->boolean('is_spam')->default(false); // Flagged as spam
            $table->decimal('spam_score', 5, 2)->nullable(); // Automated spam detection score
            
            // Analytics and insights
            $table->string('referrer_source')->nullable(); // How user came to write review
            $table->json('interaction_data')->nullable(); // How user interacted before reviewing
            $table->string('device_type')->nullable(); // Device used to write review
            $table->integer('writing_time_minutes')->nullable(); // Time spent writing
            
            // Collection version context
            $table->string('collection_version_reviewed')->nullable(); // Version of collection reviewed
            $table->boolean('review_outdated')->default(false); // Collection updated significantly since review
            $table->timestamp('collection_last_updated_when_reviewed')->nullable();
            
            // Incentives and rewards
            $table->boolean('reward_eligible')->default(true); // Eligible for review rewards
            $table->integer('points_awarded')->default(0); // Gamification points
            $table->json('badges_earned')->nullable(); // Badges earned for this review
            
            // Language and accessibility
            $table->string('language', 10)->default('en'); // Language of review
            $table->json('translations')->nullable(); // Auto-generated translations
            $table->boolean('contains_sensitive_content')->default(false);
            $table->json('content_warnings')->nullable(); // Any content warnings
            
            // Business intelligence
            $table->json('sentiment_analysis')->nullable(); // AI sentiment analysis results
            $table->json('topic_extraction')->nullable(); // Key topics mentioned
            $table->json('feature_mentions')->nullable(); // Specific features mentioned
            
            // Review authenticity
            $table->string('authenticity_score')->nullable(); // Score indicating review authenticity
            $table->json('authenticity_signals')->nullable(); // What indicates authenticity
            $table->boolean('manual_verification_required')->default(false);
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['collection_id', 'status', 'created_at']);
            $table->index(['user_id', 'status', 'created_at']);
            $table->index(['rating', 'status', 'helpful_count']);
            $table->index(['is_featured_review', 'quality_score']);
            $table->index(['status', 'moderated_at']);
            $table->index(['helpful_count', 'total_votes']);
            $table->index(['is_spam', 'report_count']);
            $table->index(['review_outdated', 'collection_version_reviewed'], 'idx_review_outdated_version');
            $table->index(['verified_reviewer', 'is_verified_purchase']);
            $table->index(['completed_collection', 'user_experience_level']);
            
            // Ensure unique review per user per collection
            $table->unique(['user_id', 'collection_id'], 'unique_user_collection_review');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collection_reviews');
    }
};