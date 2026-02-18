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
        Schema::create('enhanced_collections', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            // Basic information
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('slug')->unique();
            
            // Collection metadata
            $table->enum('type', ['manual', 'smart', 'learning_path'])->default('manual');
            $table->enum('visibility', ['public', 'unlisted', 'private'])->default('private');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            
            // Smart collection criteria (JSON)
            $table->json('smart_criteria')->nullable();
            
            // Collection settings
            $table->boolean('allows_collaboration')->default(false);
            $table->boolean('allows_comments')->default(true);
            $table->boolean('allows_downloads')->default(true);
            $table->json('collaboration_settings')->nullable();
            
            // Ordering and display
            $table->enum('default_sort', ['manual', 'created_at', 'title', 'popularity'])->default('manual');
            $table->enum('display_mode', ['grid', 'list', 'timeline'])->default('grid');
            
            // Collection cover and branding
            $table->string('cover_image_path')->nullable();
            $table->json('theme_settings')->nullable();
            
            // Tags and categorization
            $table->json('tags')->nullable();
            $table->string('category')->nullable();
            $table->integer('difficulty_level')->nullable(); // 1-5 scale
            
            // Statistics (denormalized for performance)
            $table->unsignedInteger('items_count')->default(0);
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('downloads_count')->default(0);
            $table->unsignedInteger('saves_count')->default(0);
            $table->unsignedInteger('likes_count')->default(0);
            $table->decimal('average_rating', 3, 2)->nullable();
            $table->unsignedInteger('reviews_count')->default(0);
            
            // SEO and discoverability
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->json('meta_keywords')->nullable();
            
            // Publishing and scheduling
            $table->timestamp('published_at')->nullable();
            $table->timestamp('featured_until')->nullable();
            $table->boolean('is_featured')->default(false);
            
            // Content management
            $table->text('changelog')->nullable();
            $table->string('version', 20)->default('1.0');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance (no fulltext)
            $table->index(['user_id', 'status', 'visibility']);
            $table->index(['type', 'status', 'published_at']);
            $table->index(['category', 'difficulty_level']);
            $table->index(['is_featured', 'featured_until']);
            $table->index(['created_at', 'views_count']);
            $table->index(['published_at', 'average_rating']);
            $table->index('slug');
            $table->index('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enhanced_collections');
    }
};