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
        Schema::create('collection_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            
            // Template identification
            $table->string('name'); // Template name
            $table->string('slug')->unique(); // URL-friendly identifier
            $table->uuid('uuid')->unique(); // Public UUID
            
            // Template metadata
            $table->text('description')->nullable(); // Template description
            $table->string('category')->nullable(); // Template category
            $table->json('tags')->nullable(); // Template tags
            $table->string('thumbnail_path')->nullable(); // Preview image
            
            // Template structure definition
            $table->json('structure_schema'); // JSON schema for collection structure
            $table->json('default_settings'); // Default collection settings
            $table->json('section_templates')->nullable(); // Pre-defined sections
            $table->json('item_templates')->nullable(); // Templates for items within collections
            
            // Template configuration
            $table->enum('template_type', ['basic', 'learning_path', 'showcase', 'tutorial_series', 'course', 'project'])->default('basic');
            $table->enum('complexity_level', ['simple', 'moderate', 'advanced', 'expert'])->default('simple');
            $table->integer('recommended_item_count_min')->nullable(); // Minimum recommended items
            $table->integer('recommended_item_count_max')->nullable(); // Maximum recommended items
            
            // Content guidance
            $table->json('content_guidelines'); // Guidelines for using this template
            $table->json('example_content')->nullable(); // Example content/items
            $table->text('setup_instructions')->nullable(); // How to set up collections using this template
            $table->json('best_practices')->nullable(); // Best practices for this template type
            
            // Visual and branding
            $table->json('visual_theme')->nullable(); // Color scheme, layout preferences
            $table->json('branding_options')->nullable(); // Available branding customizations
            $table->string('cover_image_template')->nullable(); // Template for cover images
            $table->json('layout_options')->nullable(); // Different layout configurations
            
            // Template features and capabilities
            $table->boolean('supports_learning_paths')->default(false);
            $table->boolean('supports_assessments')->default(false);
            $table->boolean('supports_collaboration')->default(false);
            $table->boolean('supports_media_rich_content')->default(false);
            $table->boolean('supports_gamification')->default(false);
            $table->json('feature_configuration')->nullable(); // Detailed feature settings
            
            // Usage and permissions
            $table->enum('visibility', ['public', 'private', 'organization_only'])->default('public');
            $table->enum('usage_license', ['free', 'attribution_required', 'commercial_license', 'custom'])->default('free');
            $table->text('license_terms')->nullable(); // Detailed license terms
            $table->decimal('license_cost', 10, 2)->nullable(); // Cost if commercial
            
            // Template versioning
            $table->string('version', 20)->default('1.0');
            $table->text('changelog')->nullable(); // Version change history
            $table->foreignId('parent_template_id')->nullable()->constrained('collection_templates')->nullOnDelete();
            $table->boolean('is_fork')->default(false); // Whether this is a fork of another template
            
            // Quality and curation
            $table->enum('status', ['draft', 'published', 'featured', 'deprecated', 'archived'])->default('draft');
            $table->boolean('is_curated')->default(false); // Officially curated template
            $table->boolean('is_featured')->default(false); // Featured in template gallery
            $table->integer('quality_score')->nullable(); // System-calculated quality score
            
            // Usage analytics
            $table->unsignedInteger('usage_count')->default(0); // How many times used
            $table->unsignedInteger('clone_count')->default(0); // How many times cloned/forked
            $table->unsignedInteger('view_count')->default(0); // Template page views
            $table->decimal('average_rating', 3, 2)->nullable(); // User ratings
            $table->unsignedInteger('rating_count')->default(0);
            
            // Community features
            $table->boolean('allows_community_contributions')->default(false);
            $table->json('contributors')->nullable(); // Array of contributor user IDs
            $table->boolean('accepts_feedback')->default(true);
            $table->json('community_tags')->nullable(); // Community-added tags
            
            // Customization options
            $table->json('customizable_fields'); // Which fields can be customized
            $table->json('required_fields'); // Which fields are required
            $table->json('field_validation_rules')->nullable(); // Validation for custom fields
            $table->json('conditional_logic')->nullable(); // Rules for showing/hiding fields
            
            // Integration capabilities
            $table->json('supported_integrations')->nullable(); // External services this template works with
            $table->json('webhook_templates')->nullable(); // Webhook configurations
            $table->json('api_configurations')->nullable(); // API integration settings
            
            // Educational features (for learning path templates)
            $table->json('learning_objectives_template')->nullable();
            $table->json('assessment_templates')->nullable();
            $table->json('progress_tracking_config')->nullable();
            $table->json('certification_config')->nullable();
            
            // Monetization (if applicable)
            $table->boolean('supports_paid_content')->default(false);
            $table->json('pricing_templates')->nullable(); // Templates for pricing structures
            $table->json('subscription_models')->nullable(); // Subscription options
            
            // Accessibility and compliance
            $table->json('accessibility_features')->nullable(); // Built-in accessibility features
            $table->boolean('gdpr_compliant')->default(true);
            $table->json('compliance_notes')->nullable(); // Compliance-related information
            
            // Template maintenance
            $table->timestamp('last_updated_at')->nullable();
            $table->foreignId('last_updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('requires_manual_review')->default(false); // For template updates
            $table->json('maintenance_schedule')->nullable(); // Planned maintenance/updates
            
            // Performance optimization
            $table->json('performance_hints')->nullable(); // Tips for optimal performance
            $table->integer('estimated_setup_time_minutes')->nullable();
            $table->json('resource_requirements')->nullable(); // System resource needs
            
            // Multi-language support
            $table->string('primary_language', 10)->default('en');
            $table->json('supported_languages')->nullable(); // Languages this template supports
            $table->json('localization_data')->nullable(); // Localized content
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['status', 'visibility', 'created_at']);
            $table->index(['template_type', 'complexity_level']);
            $table->index(['is_featured', 'is_curated', 'quality_score']);
            $table->index(['category', 'usage_count']);
            $table->index(['creator_id', 'status']);
            $table->index(['parent_template_id', 'is_fork']);
            $table->index(['slug']);
            $table->index(['uuid']);
            $table->index(['average_rating', 'rating_count']);
            $table->index(['usage_license', 'license_cost']);
            $table->index(['version', 'last_updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collection_templates');
    }
};