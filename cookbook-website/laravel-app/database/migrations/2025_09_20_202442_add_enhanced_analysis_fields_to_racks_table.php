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
        Schema::table('racks', function (Blueprint $table) {
            // Enhanced analysis tracking
            $table->boolean('enhanced_analysis_complete')->default(false)->after('processing_status');
            $table->timestamp('enhanced_analysis_started_at')->nullable()->after('enhanced_analysis_complete');
            $table->timestamp('enhanced_analysis_completed_at')->nullable()->after('enhanced_analysis_started_at');

            // Quick reference fields for performance
            $table->boolean('has_nested_chains')->default(false)->after('enhanced_analysis_completed_at');
            $table->integer('total_nested_chains')->default(0)->after('has_nested_chains');
            $table->integer('max_chain_depth')->default(0)->after('total_nested_chains');

            // Indexes for enhanced analysis queries
            $table->index('enhanced_analysis_complete');
            $table->index('has_nested_chains');
            $table->index(['enhanced_analysis_complete', 'has_nested_chains']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('racks', function (Blueprint $table) {
            $table->dropIndex(['enhanced_analysis_complete', 'has_nested_chains']);
            $table->dropIndex(['has_nested_chains']);
            $table->dropIndex(['enhanced_analysis_complete']);

            $table->dropColumn([
                'enhanced_analysis_complete',
                'enhanced_analysis_started_at',
                'enhanced_analysis_completed_at',
                'has_nested_chains',
                'total_nested_chains',
                'max_chain_depth'
            ]);
        });
    }
};
