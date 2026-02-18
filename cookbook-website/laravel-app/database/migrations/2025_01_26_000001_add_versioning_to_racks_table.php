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
        // Add columns only if they don't exist
        Schema::table('racks', function (Blueprint $table) {
            // Optimistic locking version control
            if (!Schema::hasColumn('racks', 'version')) {
                $table->integer('version')->default(1)->after('updated_at');
            }
            
            // Auto-save tracking
            if (!Schema::hasColumn('racks', 'last_auto_save')) {
                $table->timestamp('last_auto_save')->nullable()->after('version');
            }
            if (!Schema::hasColumn('racks', 'last_auto_save_session')) {
                $table->string('last_auto_save_session', 100)->nullable()->after('last_auto_save');
            }
            
            // How-to article field (if not already present)
            if (!Schema::hasColumn('racks', 'how_to_article')) {
                $table->longText('how_to_article')->nullable()->after('description');
            }
            
            // Category field (if not already present)  
            if (!Schema::hasColumn('racks', 'category')) {
                $table->string('category', 100)->nullable()->after('description');
            }
            
            // Additional edition field for better compatibility tracking
            if (!Schema::hasColumn('racks', 'ableton_edition')) {
                $table->string('ableton_edition', 20)->nullable()->after('ableton_version');
            }
        });
        
        // Add indexes only if they don't exist
        $existingIndexes = collect(DB::select("SHOW INDEX FROM racks"))->pluck('Key_name')->unique()->toArray();
        
        Schema::table('racks', function (Blueprint $table) use ($existingIndexes) {
            if (!in_array('racks_version_index', $existingIndexes)) {
                $table->index('version');
            }
            if (!in_array('racks_last_auto_save_index', $existingIndexes)) {
                $table->index('last_auto_save');
            }
            // Skip category index as it likely already exists from migration 2025_08_14_193154_add_category_to_racks_table
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('racks', function (Blueprint $table) {
            $table->dropIndex(['version']);
            $table->dropIndex(['last_auto_save']);
            $table->dropIndex(['category']);
            
            $table->dropColumn([
                'version',
                'last_auto_save', 
                'last_auto_save_session'
            ]);
            
            // Only drop these if they were added by this migration
            // Check if the columns exist and don't have data before dropping
            if (Schema::hasColumn('racks', 'how_to_article')) {
                // Only drop if it looks like we added it (empty or mostly empty)
                $hasContent = \DB::table('racks')->whereNotNull('how_to_article')->where('how_to_article', '!=', '')->exists();
                if (!$hasContent) {
                    $table->dropColumn('how_to_article');
                }
            }
            
            if (Schema::hasColumn('racks', 'category')) {
                $hasContent = \DB::table('racks')->whereNotNull('category')->where('category', '!=', '')->exists();
                if (!$hasContent) {
                    $table->dropColumn('category');
                }
            }
            
            if (Schema::hasColumn('racks', 'ableton_edition')) {
                $hasContent = \DB::table('racks')->whereNotNull('ableton_edition')->where('ableton_edition', '!=', '')->exists();
                if (!$hasContent) {
                    $table->dropColumn('ableton_edition');
                }
            }
        });
    }
};