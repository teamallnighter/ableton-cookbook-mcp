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
        // Racks table indexes
        $this->addIndexIfNotExists('racks', ['title'], 'idx_racks_title');
        $this->addIndexIfNotExists('racks', ['category'], 'idx_racks_category');
        $this->addIndexIfNotExists('racks', ['rack_type'], 'idx_racks_type');
        $this->addIndexIfNotExists('racks', ['ableton_edition'], 'idx_racks_edition');
        $this->addIndexIfNotExists('racks', ['status', 'is_public'], 'idx_racks_published');
        $this->addIndexIfNotExists('racks', ['user_id'], 'idx_racks_user');
        $this->addIndexIfNotExists('racks', ['average_rating'], 'idx_racks_rating');
        $this->addIndexIfNotExists('racks', ['downloads_count'], 'idx_racks_downloads');
        $this->addIndexIfNotExists('racks', ['views_count'], 'idx_racks_views');
        $this->addIndexIfNotExists('racks', ['created_at'], 'idx_racks_created');
        $this->addIndexIfNotExists('racks', ['published_at'], 'idx_racks_published_at');
        $this->addIndexIfNotExists('racks', ['status', 'is_public', 'created_at'], 'idx_racks_list_performance');
        $this->addIndexIfNotExists('racks', ['user_id', 'status', 'created_at'], 'idx_racks_user_performance');
        
        // Other tables
        $this->addIndexIfNotExists('rack_favorites', ['user_id', 'rack_id'], 'idx_favorites_user_rack');
        $this->addIndexIfNotExists('rack_favorites', ['created_at'], 'idx_favorites_created');
        $this->addIndexIfNotExists('rack_ratings', ['user_id', 'rack_id'], 'idx_ratings_user_rack');
        $this->addIndexIfNotExists('rack_ratings', ['rack_id', 'rating'], 'idx_ratings_rack_value');
        $this->addIndexIfNotExists('tags', ['name'], 'idx_tags_name');
        $this->addIndexIfNotExists('tags', ['slug'], 'idx_tags_slug');
        $this->addIndexIfNotExists('rack_tags', ['rack_id', 'tag_id'], 'idx_rack_tags_composite');
        $this->addIndexIfNotExists('notifications', ['notifiable_type', 'notifiable_id'], 'idx_notifications_morphs');
        $this->addIndexIfNotExists('notifications', ['read_at'], 'idx_notifications_read');
        $this->addIndexIfNotExists('notifications', ['created_at'], 'idx_notifications_created');
    }

    private function addIndexIfNotExists($table, $columns, $indexName)
    {
        try {
            Schema::table($table, function (Blueprint $table) use ($columns, $indexName) {
                $table->index($columns, $indexName);
            });
        } catch (\Exception $e) {
            // Index already exists or table doesn't exist, skip silently
        }
    }

    public function down(): void
    {
        // Drop indexes silently if they exist
        $indexes = [
            'racks' => ['idx_racks_title', 'idx_racks_category', 'idx_racks_type', 'idx_racks_edition', 'idx_racks_published', 'idx_racks_user', 'idx_racks_rating', 'idx_racks_downloads', 'idx_racks_views', 'idx_racks_created', 'idx_racks_published_at', 'idx_racks_list_performance', 'idx_racks_user_performance'],
            'rack_favorites' => ['idx_favorites_user_rack', 'idx_favorites_created'],
            'rack_ratings' => ['idx_ratings_user_rack', 'idx_ratings_rack_value'],
            'tags' => ['idx_tags_name', 'idx_tags_slug'],
            'rack_tags' => ['idx_rack_tags_composite'],
            'notifications' => ['idx_notifications_morphs', 'idx_notifications_read', 'idx_notifications_created']
        ];
        
        foreach ($indexes as $table => $tableIndexes) {
            foreach ($tableIndexes as $index) {
                try {
                    Schema::table($table, function (Blueprint $table) use ($index) {
                        $table->dropIndex($index);
                    });
                } catch (\Exception $e) {
                    // Index doesn't exist, skip
                }
            }
        }
    }
};
