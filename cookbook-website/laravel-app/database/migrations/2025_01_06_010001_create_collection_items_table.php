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
        Schema::create('collection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained('enhanced_collections')->cascadeOnDelete();
            
            // Polymorphic relationship to any item type
            $table->morphs('itemable'); // Creates itemable_type and itemable_id
            
            // Item metadata within collection
            $table->string('custom_title')->nullable(); // Override item's default title
            $table->text('custom_description')->nullable(); // Override item's description
            $table->string('custom_thumbnail_path')->nullable(); // Custom thumbnail
            
            // Positioning and organization
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('section')->nullable(); // Group items into sections
            $table->json('position_metadata')->nullable(); // For timeline, grid positions
            
            // Item-specific settings
            $table->boolean('is_featured_in_collection')->default(false);
            $table->boolean('is_required')->default(false); // For learning paths
            $table->json('completion_criteria')->nullable(); // What constitutes completion
            
            // Relationships and dependencies
            $table->json('prerequisites')->nullable(); // Array of item IDs that must be completed first
            $table->json('unlocks')->nullable(); // Array of item IDs this item unlocks
            
            // Content annotations
            $table->text('notes')->nullable(); // Curator's notes about this item
            $table->json('annotations')->nullable(); // Rich annotations, timestamps, etc.
            $table->json('tags')->nullable(); // Item-specific tags within collection
            
            // Learning path specific
            $table->integer('estimated_duration_minutes')->nullable();
            $table->enum('difficulty_level', ['beginner', 'intermediate', 'advanced'])->nullable();
            
            // Statistics
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('completions_count')->default(0);
            $table->decimal('average_completion_time', 8, 2)->nullable(); // In minutes
            
            // Administrative
            $table->foreignId('added_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('added_at')->useCurrent();
            $table->text('addition_reason')->nullable(); // Why this item was added
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['collection_id', 'sort_order']);
            // Morphs already creates itemable_type/itemable_id index automatically
            $table->index(['collection_id', 'section', 'sort_order']);
            $table->index(['collection_id', 'is_featured_in_collection']);
            $table->index(['collection_id', 'is_required']);
            $table->index(['added_by', 'added_at']);
            
            // Ensure unique items per collection
            $table->unique(['collection_id', 'itemable_type', 'itemable_id'], 'unique_collection_items');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collection_items');
    }
};