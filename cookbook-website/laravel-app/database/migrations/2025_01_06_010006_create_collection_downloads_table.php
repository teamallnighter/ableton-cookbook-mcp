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
        Schema::create('collection_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('collection_id')->constrained('enhanced_collections')->cascadeOnDelete();
            
            // Download identification
            $table->string('download_token')->unique(); // Unique token for this download
            $table->string('download_uuid')->unique(); // UUID for tracking
            
            // Download metadata
            $table->enum('download_type', ['full_collection', 'selected_items', 'single_item'])->default('full_collection');
            $table->json('selected_items')->nullable(); // Array of item IDs if partial download
            $table->enum('format', ['zip', 'individual_files', 'package'])->default('zip');
            
            // Download status tracking
            $table->enum('status', ['initiated', 'preparing', 'ready', 'downloading', 'completed', 'failed', 'expired'])->default('initiated');
            $table->text('status_message')->nullable(); // Detailed status information
            $table->integer('progress_percentage')->default(0);
            
            // File information
            $table->string('file_name')->nullable(); // Generated file name
            $table->string('file_path')->nullable(); // Path to generated file
            $table->unsignedBigInteger('file_size_bytes')->nullable(); // Size in bytes
            $table->string('file_hash')->nullable(); // SHA256 hash for integrity
            $table->string('mime_type')->nullable(); // File MIME type
            
            // Timing and expiration
            $table->timestamp('initiated_at')->useCurrent();
            $table->timestamp('prepared_at')->nullable(); // When file was prepared
            $table->timestamp('download_started_at')->nullable(); // When download actually started
            $table->timestamp('completed_at')->nullable(); // When download completed
            $table->timestamp('expires_at')->nullable(); // When download link expires
            
            // Download configuration
            $table->boolean('include_metadata')->default(true); // Include JSON metadata
            $table->boolean('include_thumbnails')->default(true); // Include preview images
            $table->boolean('include_documentation')->default(true); // Include how-to articles
            $table->json('custom_options')->nullable(); // User-selected options
            
            // Quality and compression
            $table->enum('quality', ['original', 'high', 'medium', 'low'])->default('original');
            $table->enum('compression', ['none', 'light', 'medium', 'heavy'])->default('medium');
            $table->json('processing_options')->nullable(); // Detailed processing settings
            
            // Analytics and tracking
            $table->string('ip_address', 45)->nullable(); // IPv4 or IPv6
            $table->string('user_agent', 500)->nullable();
            $table->string('referrer_url', 500)->nullable();
            $table->string('download_source')->nullable(); // web, mobile_app, api, etc.
            $table->json('device_info')->nullable(); // Device and browser details
            $table->json('location_data')->nullable(); // Country, region (anonymized)
            
            // Performance metrics
            $table->decimal('preparation_time_seconds', 8, 2)->nullable(); // Time to prepare download
            $table->decimal('download_speed_mbps', 8, 2)->nullable(); // Average download speed
            $table->unsignedInteger('retry_count')->default(0); // Number of retries
            $table->json('error_log')->nullable(); // Any errors encountered
            
            // Business metrics
            $table->boolean('is_premium_download')->default(false); // Required premium access
            $table->decimal('cost_charged', 10, 2)->nullable(); // If paid download
            $table->string('payment_id')->nullable(); // Payment reference
            $table->boolean('license_agreement_accepted')->default(false);
            
            // Usage restrictions
            $table->integer('download_limit')->nullable(); // How many times this can be downloaded
            $table->integer('download_count')->default(0); // How many times actually downloaded
            $table->json('usage_terms')->nullable(); // Specific usage terms for this download
            
            // Collaboration and sharing
            $table->boolean('shared_download')->default(false); // Downloaded via shared link
            $table->foreignId('shared_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('share_context')->nullable(); // How this download was shared
            
            // Security and compliance
            $table->boolean('virus_scanned')->default(false);
            $table->timestamp('virus_scan_at')->nullable();
            $table->string('scan_result')->nullable(); // clean, infected, failed
            $table->json('security_metadata')->nullable(); // Security-related data
            
            // Backup and recovery
            $table->boolean('backed_up')->default(false);
            $table->timestamp('backup_at')->nullable();
            $table->string('backup_location')->nullable();
            
            // Integration data
            $table->string('external_download_id')->nullable(); // If using external download service
            $table->json('external_metadata')->nullable(); // External service metadata
            
            // User feedback
            $table->integer('user_rating')->nullable(); // Rating of download experience
            $table->text('user_feedback')->nullable(); // User feedback about download
            $table->boolean('report_issue')->default(false); // User reported an issue
            
            // Automatic cleanup
            $table->boolean('auto_cleanup')->default(true); // Whether to auto-delete expired files
            $table->timestamp('scheduled_cleanup_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['user_id', 'status', 'created_at']);
            $table->index(['collection_id', 'status', 'created_at']);
            $table->index(['download_token', 'status']);
            $table->index(['expires_at', 'status']);
            $table->index(['scheduled_cleanup_at', 'auto_cleanup']);
            $table->index(['virus_scanned', 'scan_result']);
            $table->index(['is_premium_download', 'payment_id']);
            $table->index(['download_uuid']);
            $table->index(['shared_download', 'shared_by_user_id']);
            $table->index(['initiated_at', 'completed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collection_downloads');
    }
};