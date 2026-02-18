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
            if (!Schema::hasColumn('racks', 'how_to_article')) {
                $table->longText('how_to_article')->nullable()->after('description');
            }
            if (!Schema::hasColumn('racks', 'how_to_updated_at')) {
                $table->timestamp('how_to_updated_at')->nullable()->after('how_to_article');
            }
            
            // Note: We can't create an index on longText column 'how_to_article'
            // This would cause an error. Commenting out for safety.
            // $table->index(['how_to_article'], 'racks_how_to_article_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('racks', function (Blueprint $table) {
            $table->dropIndex('racks_how_to_article_index');
            $table->dropColumn(['how_to_article', 'how_to_updated_at']);
        });
    }
};