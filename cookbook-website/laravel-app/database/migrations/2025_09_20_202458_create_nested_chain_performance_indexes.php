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
        // Additional composite indexes for complex nested chain queries
        Schema::table('nested_chains', function (Blueprint $table) {
            // Hierarchical queries optimization
            $table->index(['parent_chain_id', 'depth_level'], 'idx_nested_chains_hierarchy');
            $table->index(['rack_id', 'parent_chain_id', 'depth_level'], 'idx_nested_chains_full_hierarchy');

            // Chain type analysis
            $table->index(['rack_id', 'chain_type', 'device_count'], 'idx_nested_chains_type_analysis');

            // Performance queries for constitutional compliance
            $table->index(['rack_id', 'is_empty', 'device_count'], 'idx_nested_chains_compliance');

            // Temporal analysis queries
            $table->index(['analyzed_at', 'rack_id'], 'idx_nested_chains_temporal');
        });

        Schema::table('enhanced_rack_analysis', function (Blueprint $table) {
            // Constitutional compliance reporting
            $table->index(['constitutional_compliant', 'processed_at'], 'idx_era_compliance_temporal');
            $table->index(['has_nested_chains', 'total_chains_detected'], 'idx_era_chain_analysis');

            // Performance analysis
            $table->index(['analysis_duration_ms', 'total_devices'], 'idx_era_performance');

            // Analyzer version tracking
            $table->index(['analyzer_version', 'processed_at'], 'idx_era_version_tracking');
        });

        Schema::table('racks', function (Blueprint $table) {
            // Enhanced analysis workflow queries
            $table->index(['enhanced_analysis_complete', 'enhanced_analysis_completed_at'], 'idx_racks_enhanced_workflow');
            $table->index(['user_id', 'enhanced_analysis_complete', 'has_nested_chains'], 'idx_racks_user_enhanced');

            // Search and filtering optimization
            $table->index(['has_nested_chains', 'total_nested_chains', 'max_chain_depth'], 'idx_racks_chain_metrics');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nested_chains', function (Blueprint $table) {
            $table->dropIndex('idx_nested_chains_hierarchy');
            $table->dropIndex('idx_nested_chains_full_hierarchy');
            $table->dropIndex('idx_nested_chains_type_analysis');
            $table->dropIndex('idx_nested_chains_compliance');
            $table->dropIndex('idx_nested_chains_temporal');
        });

        Schema::table('enhanced_rack_analysis', function (Blueprint $table) {
            $table->dropIndex('idx_era_compliance_temporal');
            $table->dropIndex('idx_era_chain_analysis');
            $table->dropIndex('idx_era_performance');
            $table->dropIndex('idx_era_version_tracking');
        });

        Schema::table('racks', function (Blueprint $table) {
            $table->dropIndex('idx_racks_enhanced_workflow');
            $table->dropIndex('idx_racks_user_enhanced');
            $table->dropIndex('idx_racks_chain_metrics');
        });
    }
};
