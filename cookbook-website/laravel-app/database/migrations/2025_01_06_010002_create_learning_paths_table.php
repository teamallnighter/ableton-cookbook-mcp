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
        Schema::create('learning_paths', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained('enhanced_collections')->cascadeOnDelete();
            
            // Learning path structure
            $table->string('path_type', 50)->default('sequential'); // sequential, branching, adaptive
            $table->json('structure')->nullable(); // Complex path structure definition
            
            // Learning objectives
            $table->json('learning_objectives')->nullable(); // What learners will achieve
            $table->json('prerequisites_skills')->nullable(); // Required skills to start
            $table->json('skills_gained')->nullable(); // Skills gained upon completion
            
            // Path configuration
            $table->boolean('allows_skipping')->default(false);
            $table->boolean('requires_order')->default(true);
            $table->integer('minimum_score')->nullable(); // Minimum score to progress
            $table->integer('estimated_hours')->nullable();
            
            // Certification and completion
            $table->boolean('awards_certificate')->default(false);
            $table->string('certificate_template')->nullable();
            $table->json('completion_requirements')->nullable();
            
            // Adaptive learning
            $table->boolean('is_adaptive')->default(false);
            $table->json('adaptation_rules')->nullable(); // Rules for adapting based on performance
            
            // Difficulty progression
            $table->enum('difficulty_curve', ['flat', 'gradual', 'steep', 'mixed'])->default('gradual');
            $table->json('milestone_rewards')->nullable(); // Rewards at key milestones
            
            // Assessment integration
            $table->boolean('includes_assessments')->default(false);
            $table->json('assessment_config')->nullable();
            $table->integer('passing_threshold')->nullable(); // Percentage needed to pass
            
            // Progress tracking settings
            $table->boolean('tracks_time')->default(true);
            $table->boolean('tracks_attempts')->default(true);
            $table->boolean('allows_retries')->default(true);
            $table->integer('max_retries')->nullable();
            
            // Collaboration features
            $table->boolean('supports_cohorts')->default(false);
            $table->boolean('enables_peer_review')->default(false);
            $table->boolean('includes_discussions')->default(false);
            
            // Gamification
            $table->json('point_system')->nullable(); // Points awarded for various actions
            $table->json('achievement_badges')->nullable(); // Available badges
            $table->boolean('has_leaderboard')->default(false);
            
            // Content delivery
            $table->enum('pacing', ['self_paced', 'instructor_led', 'cohort_based'])->default('self_paced');
            $table->json('schedule_settings')->nullable(); // For scheduled paths
            
            // Analytics and optimization
            $table->decimal('average_completion_rate', 5, 2)->nullable();
            $table->integer('average_completion_time_hours')->nullable();
            $table->json('drop_off_points')->nullable(); // Where people commonly drop off
            $table->json('optimization_suggestions')->nullable();
            
            // Path versioning
            $table->string('version', 20)->default('1.0');
            $table->text('version_notes')->nullable();
            $table->foreignId('previous_version_id')->nullable()->constrained('learning_paths')->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['collection_id', 'path_type']);
            $table->index(['is_adaptive', 'pacing']);
            $table->index(['awards_certificate', 'includes_assessments']);
            $table->index('version');
            $table->index('previous_version_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_paths');
    }
};