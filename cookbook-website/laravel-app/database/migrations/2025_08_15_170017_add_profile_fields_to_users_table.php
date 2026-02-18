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
        Schema::table('users', function (Blueprint $table) {
            $table->text('bio')->nullable()->after('name');
            $table->string('location')->nullable()->after('bio');
            $table->string('website')->nullable()->after('location');
            $table->string('soundcloud_url')->nullable()->after('website');
            $table->string('bandcamp_url')->nullable()->after('soundcloud_url');
            $table->string('spotify_url')->nullable()->after('bandcamp_url');
            $table->string('youtube_url')->nullable()->after('spotify_url');
            $table->string('instagram_url')->nullable()->after('youtube_url');
            $table->string('twitter_url')->nullable()->after('instagram_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'bio', 'location', 'website', 'soundcloud_url', 'bandcamp_url', 
                'spotify_url', 'youtube_url', 'instagram_url', 'twitter_url'
            ]);
        });
    }
};
