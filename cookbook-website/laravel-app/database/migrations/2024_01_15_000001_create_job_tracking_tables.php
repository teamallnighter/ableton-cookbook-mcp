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
        // Enhanced job tracking table
        Schema::create('job_executions', function (Blueprint $table) {
            $table->id();
            $table->string('job_id', 36)->unique()->index(); // UUID for job identification
            $table->string('job_class');
            $table->string('queue')->default('default');
            $table->unsignedBigInteger('model_id')->nullable();
            $table->string('model_type')->nullable();
            $table->index(['model_type', 'model_id']);
            
            // Job lifecycle tracking
            $table->enum('status', [
                'queued', 'processing', 'completed', 'failed', 
                'retry_scheduled', 'permanently_failed', 'cancelled'
            ])->default('queued');
            $table->timestamp('queued_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            
            // Retry management
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(3);
            $table->timestamp('next_retry_at')->nullable();
            $table->unsignedInteger('retry_delay')->default(0); // seconds
            
            // Progress tracking
            $table->unsignedTinyInteger('progress_percentage')->default(0);
            $table->string('current_stage')->nullable();
            $table->json('stage_data')->nullable(); // Additional stage-specific data
            
            // Performance metrics
            $table->unsignedInteger('memory_peak')->nullable(); // bytes
            $table->unsignedInteger('execution_time')->nullable(); // milliseconds
            $table->unsignedInteger('queue_wait_time')->nullable(); // milliseconds
            
            // Failure tracking
            $table->string('failure_category')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('failure_context')->nullable();
            $table->text('stack_trace')->nullable();
            
            // Payload and results
            $table->json('payload')->nullable();
            $table->json('result_data')->nullable();
            
            // Metadata
            $table->json('tags')->nullable(); // For categorization and filtering
            $table->json('metadata')->nullable(); // Extensible metadata
            
            $table->timestamps();
            
            // Indexes for common queries
            $table->index(['status', 'created_at']);
            $table->index(['job_class', 'status']);
            $table->index(['queue', 'status']);
            $table->index(['attempts', 'next_retry_at']);
            $table->index('failure_category');
        });
        
        // Job progress tracking for real-time updates
        Schema::create('job_progress', function (Blueprint $table) {
            $table->id();
            $table->string('job_id', 36)->index();
            $table->foreign('job_id')->references('job_id')->on('job_executions')->onDelete('cascade');
            
            $table->string('stage');
            $table->unsignedTinyInteger('percentage');
            $table->string('message')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('timestamp');
            
            $table->index(['job_id', 'timestamp']);
            $table->index(['job_id', 'stage']);
        });
        
        // Error logging for detailed failure analysis
        Schema::create('job_error_logs', function (Blueprint $table) {
            $table->id();
            $table->string('job_id', 36)->index();
            $table->foreign('job_id')->references('job_id')->on('job_executions')->onDelete('cascade');
            
            $table->enum('severity', ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency']);
            $table->string('error_code')->nullable();
            $table->string('error_type');
            $table->text('message');
            $table->text('details')->nullable();
            $table->json('context')->nullable();
            $table->text('stack_trace')->nullable();
            
            $table->string('file')->nullable();
            $table->unsignedInteger('line')->nullable();
            
            $table->timestamp('occurred_at');
            $table->timestamps();
            
            $table->index(['severity', 'occurred_at']);
            $table->index(['error_type', 'occurred_at']);
            $table->index(['job_id', 'severity']);
        });
        
        // User notifications for job status updates
        Schema::create('job_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('job_id', 36)->index();
            $table->foreign('job_id')->references('job_id')->on('job_executions')->onDelete('cascade');
            
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            $table->enum('type', ['progress', 'completion', 'failure', 'retry', 'escalation']);
            $table->string('channel'); // email, browser, webhook, etc.
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable();
            
            $table->enum('status', ['pending', 'sent', 'failed', 'dismissed']);
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index(['job_id', 'type']);
            $table->index(['channel', 'status']);
            $table->index(['scheduled_for', 'status']);
        });
        
        // System health and performance metrics
        Schema::create('job_system_metrics', function (Blueprint $table) {
            $table->id();
            $table->timestamp('recorded_at');
            
            // Queue metrics
            $table->string('queue');
            $table->unsignedInteger('jobs_pending');
            $table->unsignedInteger('jobs_processing');
            $table->unsignedInteger('jobs_failed');
            $table->unsignedInteger('jobs_completed_last_hour');
            
            // Performance metrics
            $table->unsignedInteger('avg_execution_time')->nullable(); // milliseconds
            $table->unsignedInteger('avg_wait_time')->nullable(); // milliseconds
            $table->unsignedBigInteger('peak_memory_usage')->nullable(); // bytes
            
            // System resources
            $table->decimal('cpu_usage', 5, 2)->nullable(); // percentage
            $table->unsignedBigInteger('memory_usage')->nullable(); // bytes
            $table->unsignedBigInteger('disk_usage')->nullable(); // bytes
            $table->decimal('disk_usage_percentage', 5, 2)->nullable();
            
            // Error rates
            $table->decimal('error_rate', 5, 4)->nullable(); // percentage
            $table->decimal('retry_rate', 5, 4)->nullable(); // percentage
            
            $table->timestamps();
            
            $table->index(['queue', 'recorded_at']);
            $table->index('recorded_at');
        });
        
        // Add enhanced columns to racks table
        Schema::table('racks', function (Blueprint $table) {
            // Enhanced status tracking
            $table->enum('processing_status', [
                'uploaded', 'queued', 'analyzing', 'analysis_complete',
                'processing_metadata', 'ready_for_annotation', 'pending',
                'approved', 'failed', 'retry_scheduled', 'permanently_failed',
                'suspended', 'archived'
            ])->default('uploaded')->after('status');
            
            // Job tracking
            $table->string('current_job_id', 36)->nullable()->after('processing_status');
            $table->foreign('current_job_id')->references('job_id')->on('job_executions')->onDelete('set null');
            
            // Progress tracking
            $table->unsignedTinyInteger('processing_progress')->default(0)->after('current_job_id');
            $table->string('processing_stage')->nullable()->after('processing_progress');
            $table->text('processing_message')->nullable()->after('processing_stage');
            
            // Enhanced error tracking
            $table->string('failure_category')->nullable()->after('processing_error');
            $table->json('failure_context')->nullable()->after('failure_category');
            $table->unsignedTinyInteger('retry_count')->default(0)->after('failure_context');
            $table->timestamp('last_retry_at')->nullable()->after('retry_count');
            $table->timestamp('next_retry_at')->nullable()->after('last_retry_at');
            
            // Performance metrics
            $table->unsignedInteger('last_processing_time')->nullable()->after('next_retry_at'); // milliseconds
            $table->unsignedBigInteger('last_memory_usage')->nullable()->after('last_processing_time'); // bytes
            
            // User experience
            $table->boolean('notify_on_completion')->default(true)->after('last_memory_usage');
            $table->boolean('notify_on_failure')->default(true)->after('notify_on_completion');
            
            // Add indexes for common queries
            $table->index(['processing_status', 'created_at']);
            $table->index(['user_id', 'processing_status']);
            $table->index(['failure_category', 'retry_count']);
            $table->index('next_retry_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove added columns from racks table
        Schema::table('racks', function (Blueprint $table) {
            $table->dropForeign(['current_job_id']);
            $table->dropColumn([
                'processing_status', 'current_job_id', 'processing_progress',
                'processing_stage', 'processing_message', 'failure_category',
                'failure_context', 'retry_count', 'last_retry_at', 'next_retry_at',
                'last_processing_time', 'last_memory_usage', 'notify_on_completion',
                'notify_on_failure'
            ]);
        });
        
        // Drop tables in correct order (foreign key dependencies)
        Schema::dropIfExists('job_system_metrics');
        Schema::dropIfExists('job_notifications');
        Schema::dropIfExists('job_error_logs');
        Schema::dropIfExists('job_progress');
        Schema::dropIfExists('job_executions');
    }
};