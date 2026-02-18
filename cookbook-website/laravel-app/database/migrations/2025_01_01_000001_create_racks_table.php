<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('racks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Basic Info
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('slug')->unique();
            
            // File Info
            $table->string('file_path');
            $table->string('file_hash', 64)->index(); // SHA-256 for deduplication
            $table->integer('file_size');
            $table->string('original_filename');
            
            // Rack Type and Metadata from AbletonRackAnalyzer
            $table->enum('rack_type', ['AudioEffectGroupDevice', 'InstrumentGroupDevice', 'MidiEffectGroupDevice']);
            $table->integer('device_count')->default(0);
            $table->integer('chain_count')->default(0);
            $table->string('ableton_version', 20)->nullable();
            
            // JSON fields for complex data from analyzer
            $table->json('macro_controls')->nullable(); // Macro control configuration
            $table->json('devices')->nullable(); // Device information
            $table->json('chains')->nullable(); // Chain configuration
            $table->json('version_details')->nullable(); // Detailed version info
            $table->json('parsing_errors')->nullable(); // Any parsing errors
            $table->json('parsing_warnings')->nullable(); // Any parsing warnings
            
            // Media
            $table->string('preview_audio_path')->nullable();
            $table->string('preview_image_path')->nullable();
            
            // Status
            $table->enum('status', ['pending', 'processing', 'approved', 'rejected', 'failed'])->default('pending');
            $table->text('processing_error')->nullable();
            $table->timestamp('published_at')->nullable();
            
            // Social Stats
            $table->decimal('average_rating', 3, 2)->default(0);
            $table->integer('ratings_count')->default(0);
            $table->integer('downloads_count')->default(0);
            $table->integer('views_count')->default(0);
            $table->integer('comments_count')->default(0);
            $table->integer('likes_count')->default(0);
            
            // Visibility
            $table->boolean('is_public')->default(true);
            $table->boolean('is_featured')->default(false);
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['rack_type', 'status', 'is_public']);
            $table->index(['user_id', 'status', 'created_at']);
            $table->index(['average_rating', 'is_public']);
            $table->index(['downloads_count', 'is_public']);
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('racks');
    }
};