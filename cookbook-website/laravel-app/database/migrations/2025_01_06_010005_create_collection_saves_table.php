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
        Schema::create('collection_saves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('collection_id')->constrained('enhanced_collections')->cascadeOnDelete();
            
            // Save metadata
            $table->enum('save_type', ['bookmark', 'favorite', 'watch_later', 'archive'])->default('bookmark');
            $table->text('personal_note')->nullable(); // User's personal note about why they saved it
            $table->json('custom_tags')->nullable(); // User's personal tags for organization
            
            // Organization features
            $table->string('folder_name')->nullable(); // User-defined folder/category
            $table->integer('sort_order')->default(0); // Custom ordering within folder
            $table->boolean('is_private')->default(true); // Whether save is visible to others
            
            // Notification preferences
            $table->boolean('notify_on_updates')->default(false);
            $table->boolean('notify_on_new_items')->default(false);
            $table->boolean('notify_on_comments')->default(false);
            $table->json('notification_preferences')->nullable(); // Detailed notification settings
            
            // Interaction tracking
            $table->timestamp('last_viewed_at')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->json('viewing_history')->nullable(); // History of when user accessed
            
            // Smart features
            $table->boolean('auto_download_new_items')->default(false);
            $table->json('download_preferences')->nullable(); // What to auto-download
            $table->boolean('track_progress')->default(true);
            
            // Sharing and collaboration
            $table->boolean('allows_sharing')->default(false);
            $table->string('share_token')->nullable()->unique(); // For sharing with others
            $table->timestamp('share_expires_at')->nullable();
            $table->json('share_permissions')->nullable(); // What sharers can do
            
            // Reminder and scheduling
            $table->timestamp('remind_at')->nullable(); // When to remind user
            $table->enum('reminder_frequency', ['never', 'daily', 'weekly', 'monthly'])->default('never');
            $table->boolean('reminder_sent')->default(false);
            $table->timestamp('last_reminder_sent_at')->nullable();
            
            // Analytics and insights
            $table->json('save_context')->nullable(); // How/why user found and saved this
            $table->string('referrer_url')->nullable(); // Where user came from when saving
            $table->string('device_type')->nullable(); // Device used when saving
            
            // Quality and feedback
            $table->integer('user_rating')->nullable(); // User's personal rating (1-5)
            $table->text('feedback_for_creator')->nullable(); // Feedback user wants to give creator
            $table->boolean('recommended_to_others')->default(false);
            
            // Sync and backup
            $table->json('sync_data')->nullable(); // Data for syncing across devices
            $table->timestamp('last_synced_at')->nullable();
            $table->boolean('backup_included')->default(true);
            
            // Advanced organization
            $table->json('custom_metadata')->nullable(); // User-defined metadata
            $table->string('color_code', 7)->nullable(); // Color for visual organization
            $table->integer('priority_level')->default(3); // 1-5 priority level
            
            // Learning integration
            $table->boolean('add_to_learning_plan')->default(false);
            $table->timestamp('planned_start_date')->nullable();
            $table->timestamp('target_completion_date')->nullable();
            $table->enum('learning_priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            
            // Collaboration features
            $table->json('shared_with_users')->nullable(); // Array of user IDs this is shared with
            $table->boolean('allow_collaborative_notes')->default(false);
            $table->json('collaborative_data')->nullable(); // Shared notes, highlights, etc.
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['user_id', 'save_type', 'created_at']);
            $table->index(['user_id', 'folder_name', 'sort_order']);
            $table->index(['collection_id', 'is_private']);
            $table->index(['remind_at', 'reminder_sent']);
            $table->index(['notify_on_updates', 'collection_id']);
            $table->index(['last_viewed_at', 'user_id']);
            $table->index('share_token');
            $table->index(['auto_download_new_items', 'user_id']);
            
            // Ensure unique saves per user per collection
            $table->unique(['user_id', 'collection_id'], 'unique_user_collection_save');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collection_saves');
    }
};