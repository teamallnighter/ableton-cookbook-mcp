<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create general issue types table
        Schema::create('issue_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('allows_file_upload')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Create general issues table (extends beyond just rack reports)
        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_type_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('rack_id')->nullable()->constrained()->onDelete('cascade'); // For rack-specific issues
            $table->string('title');
            $table->text('description');
            $table->string('submitter_name')->nullable(); // For anonymous submissions
            $table->string('submitter_email')->nullable(); // For anonymous submissions
            $table->enum('status', ['pending', 'in_review', 'approved', 'rejected', 'resolved'])->default('pending');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->text('admin_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            
            $table->index(['issue_type_id', 'status']);
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'priority']);
        });

        // Create file uploads table for issues
        Schema::create('issue_file_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained()->onDelete('cascade');
            $table->string('original_filename');
            $table->string('stored_filename');
            $table->string('file_path');
            $table->integer('file_size');
            $table->string('file_type', 50);
            $table->string('ableton_version', 20)->nullable();
            $table->string('rack_name')->nullable();
            $table->text('rack_description')->nullable();
            $table->json('tags')->nullable();
            $table->boolean('is_processed')->default(false);
            $table->timestamps();
        });

        // Create issue comments table
        Schema::create('issue_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->text('comment_text');
            $table->boolean('is_admin_comment')->default(false);
            $table->boolean('is_public')->default(true);
            $table->timestamps();
        });

        // Insert default issue types
        DB::table('issue_types')->insert([
            [
                'name' => 'rack_upload',
                'description' => 'Submit a new rack to be added to the cookbook',
                'allows_file_upload' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'rack_problem',
                'description' => 'Report an issue with an existing rack (integrates with existing rack_reports)',
                'allows_file_upload' => false,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'feature_request',
                'description' => 'Request new features or improvements',
                'allows_file_upload' => false,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'bug_report',
                'description' => 'Report a bug or technical issue with the website',
                'allows_file_upload' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'content_suggestion',
                'description' => 'Suggest changes or improvements to existing content',
                'allows_file_upload' => false,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'general_feedback',
                'description' => 'General feedback or questions about the site',
                'allows_file_upload' => false,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);

        // Optionally migrate existing rack_reports to the new system
        // This is commented out - uncomment if you want to migrate existing data
        /*
        if (Schema::hasTable('rack_reports')) {
            $rackProblemTypeId = DB::table('issue_types')->where('name', 'rack_problem')->value('id');
            
            DB::statement("
                INSERT INTO issues (issue_type_id, user_id, rack_id, title, description, status, admin_notes, resolved_at, created_at, updated_at)
                SELECT 
                    {$rackProblemTypeId},
                    user_id,
                    rack_id,
                    CONCAT('Rack Issue: ', issue_type),
                    description,
                    CASE 
                        WHEN status = 'dismissed' THEN 'rejected'
                        WHEN status = 'reviewed' THEN 'resolved'
                        ELSE status
                    END,
                    admin_notes,
                    resolved_at,
                    created_at,
                    updated_at
                FROM rack_reports
            ");
        }
        */
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('issue_comments');
        Schema::dropIfExists('issue_file_uploads');
        Schema::dropIfExists('issues');
        Schema::dropIfExists('issue_types');
    }
};
