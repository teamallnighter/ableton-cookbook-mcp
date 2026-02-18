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
        Schema::create('enhanced_rack_analysis', function (Blueprint $table) {
            $table->id();

            // Foreign key to racks table
            $table->foreignId('rack_id')->constrained('racks')->onDelete('cascade');

            // Constitutional compliance tracking
            $table->boolean('constitutional_compliant')->default(false);
            $table->json('compliance_issues')->nullable();

            // Nested chain analysis results
            $table->boolean('has_nested_chains')->default(false);
            $table->integer('total_chains_detected')->default(0);
            $table->integer('max_nesting_depth')->default(0);

            // Device analysis results
            $table->integer('total_devices')->default(0);
            $table->json('device_type_breakdown')->nullable();

            // Performance tracking
            $table->integer('analysis_duration_ms')->default(0);
            $table->timestamp('processed_at');

            // Analysis metadata
            $table->string('analyzer_version')->nullable();
            $table->json('analysis_metadata')->nullable();

            $table->timestamps();

            // Indexes for performance
            $table->index('rack_id');
            $table->index('constitutional_compliant');
            $table->index('has_nested_chains');
            $table->index('processed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enhanced_rack_analysis');
    }
};
