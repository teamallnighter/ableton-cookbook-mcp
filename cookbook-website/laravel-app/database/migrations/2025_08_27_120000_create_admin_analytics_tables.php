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
        // Daily analytics aggregation table
        Schema::create('daily_analytics', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('metric_type'); // 'users', 'racks', 'downloads', 'comments', etc.
            $table->bigInteger('count')->default(0);
            $table->json('metadata')->nullable(); // Additional context data
            $table->timestamps();
            
            $table->unique(['date', 'metric_type']);
            $table->index(['date', 'metric_type']);
            $table->index('date');
        });
        
        // Email analytics table
        Schema::create('email_analytics', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('email_type'); // 'newsletter', 'transactional', 'marketing'
            $table->string('campaign_id')->nullable();
            $table->integer('sent')->default(0);
            $table->integer('delivered')->default(0);
            $table->integer('opened')->default(0);
            $table->integer('clicked')->default(0);
            $table->integer('bounced')->default(0);
            $table->integer('unsubscribed')->default(0);
            $table->decimal('open_rate', 5, 2)->nullable();
            $table->decimal('click_rate', 5, 2)->nullable();
            $table->decimal('bounce_rate', 5, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['date', 'email_type']);
            $table->index('date');
        });
        
        // Performance metrics table
        Schema::create('performance_metrics', function (Blueprint $table) {
            $table->id();
            $table->timestamp('recorded_at');
            $table->string('metric_name'); // 'response_time', 'memory_usage', 'cpu_usage'
            $table->decimal('value', 10, 2);
            $table->string('unit')->nullable(); // 'ms', 'MB', '%'
            $table->string('endpoint')->nullable(); // For response time metrics
            $table->json('context')->nullable(); // Additional context
            $table->timestamps();
            
            $table->index(['recorded_at', 'metric_name']);
            $table->index('recorded_at');
        });
        
        // System health logs
        Schema::create('system_health_logs', function (Blueprint $table) {
            $table->id();
            $table->timestamp('checked_at');
            $table->string('component'); // 'database', 'redis', 'queue', 'storage'
            $table->enum('status', ['healthy', 'warning', 'critical']);
            $table->text('message')->nullable();
            $table->json('metrics')->nullable(); // Component-specific metrics
            $table->timestamps();
            
            $table->index(['checked_at', 'component']);
            $table->index('status');
        });
        
        // User activity tracking
        Schema::create('user_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('action'); // 'login', 'upload', 'download', 'comment'
            $table->string('resource_type')->nullable(); // 'rack', 'blog_post', 'comment'
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('performed_at');
            $table->timestamps();
            
            $table->index(['user_id', 'performed_at']);
            $table->index(['action', 'performed_at']);
            $table->index('performed_at');
        });
        
        // Rack processing analytics
        Schema::create('rack_processing_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rack_id')->constrained()->onDelete('cascade');
            $table->enum('stage', ['uploaded', 'queued', 'processing', 'completed', 'failed']);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('processing_time_ms')->nullable(); // Processing duration in milliseconds
            $table->integer('file_size_bytes')->nullable();
            $table->integer('device_count')->nullable();
            $table->integer('chain_count')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable(); // Processing details
            $table->timestamps();
            
            $table->index(['rack_id', 'stage']);
            $table->index('stage');
            $table->index(['completed_at', 'processing_time_ms']);
        });
        
        // Device usage analytics
        Schema::create('device_usage_analytics', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('device_name');
            $table->string('device_type')->nullable(); // 'instrument', 'effect', 'utility'
            $table->boolean('is_native')->default(true);
            $table->boolean('is_max_for_live')->default(false);
            $table->integer('usage_count')->default(0);
            $table->integer('unique_racks')->default(0); // Number of different racks using this device
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->unique(['date', 'device_name']);
            $table->index(['date', 'usage_count']);
            $table->index('device_name');
        });
        
        // Content engagement analytics
        Schema::create('content_engagement_logs', function (Blueprint $table) {
            $table->id();
            $table->string('content_type'); // 'rack', 'blog_post', 'how_to'
            $table->unsignedBigInteger('content_id');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('engagement_type'); // 'view', 'download', 'like', 'share', 'comment'
            $table->integer('duration_seconds')->nullable(); // Time spent viewing
            $table->string('referrer')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('engaged_at');
            $table->timestamps();
            
            $table->index(['content_type', 'content_id', 'engaged_at']);
            $table->index(['engagement_type', 'engaged_at']);
            $table->index('engaged_at');
        });
        
        // Feature usage analytics
        Schema::create('feature_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->string('feature_name'); // 'how_to_editor', 'rack_browser', 'search'
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamp('used_at');
            $table->integer('duration_ms')->nullable();
            $table->json('context')->nullable(); // Feature-specific data
            $table->timestamps();
            
            $table->index(['feature_name', 'used_at']);
            $table->index('used_at');
        });
        
        // Error tracking
        Schema::create('error_logs', function (Blueprint $table) {
            $table->id();
            $table->string('error_type'); // 'exception', 'validation', 'http'
            $table->string('error_code')->nullable();
            $table->text('error_message');
            $table->text('stack_trace')->nullable();
            $table->string('file_path')->nullable();
            $table->integer('line_number')->nullable();
            $table->string('url')->nullable();
            $table->string('method')->nullable(); // HTTP method
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('request_data')->nullable();
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->boolean('resolved')->default(false);
            $table->timestamp('occurred_at');
            $table->timestamps();
            
            $table->index(['error_type', 'occurred_at']);
            $table->index(['severity', 'resolved']);
            $table->index('occurred_at');
        });
        
        // Cache performance metrics
        Schema::create('cache_metrics', function (Blueprint $table) {
            $table->id();
            $table->timestamp('recorded_at');
            $table->string('cache_key_pattern'); // Pattern like 'rack.analytics.*'
            $table->integer('hits')->default(0);
            $table->integer('misses')->default(0);
            $table->decimal('hit_rate', 5, 2)->nullable();
            $table->integer('avg_retrieval_time_ms')->nullable();
            $table->bigInteger('memory_usage_bytes')->nullable();
            $table->timestamps();
            
            $table->index(['recorded_at', 'cache_key_pattern']);
            $table->index('recorded_at');
        });
        
        // API usage analytics
        Schema::create('api_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->string('endpoint');
            $table->string('method'); // GET, POST, PUT, DELETE
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('api_key_id')->nullable(); // For API key tracking
            $table->integer('response_code');
            $table->integer('response_time_ms');
            $table->bigInteger('request_size_bytes')->nullable();
            $table->bigInteger('response_size_bytes')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('requested_at');
            $table->timestamps();
            
            $table->index(['endpoint', 'requested_at']);
            $table->index(['user_id', 'requested_at']);
            $table->index('requested_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_usage_logs');
        Schema::dropIfExists('cache_metrics');
        Schema::dropIfExists('error_logs');
        Schema::dropIfExists('feature_usage_logs');
        Schema::dropIfExists('content_engagement_logs');
        Schema::dropIfExists('device_usage_analytics');
        Schema::dropIfExists('rack_processing_logs');
        Schema::dropIfExists('user_activity_logs');
        Schema::dropIfExists('system_health_logs');
        Schema::dropIfExists('performance_metrics');
        Schema::dropIfExists('email_analytics');
        Schema::dropIfExists('daily_analytics');
    }
};