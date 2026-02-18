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
            // Add composite index for trending queries optimization
            $table->index(['downloads_count', 'average_rating', 'created_at'], 'idx_trending_performance');
            
            // Add index for published status filtering
            $table->index(['status', 'is_public', 'published_at'], 'idx_published_status');
            
            // Add index for user racks
            $table->index(['user_id', 'status', 'is_public'], 'idx_user_racks');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('racks', function (Blueprint $table) {
            $table->dropIndex('idx_trending_performance');
            $table->dropIndex('idx_published_status');
            $table->dropIndex('idx_user_racks');
        });
    }
};