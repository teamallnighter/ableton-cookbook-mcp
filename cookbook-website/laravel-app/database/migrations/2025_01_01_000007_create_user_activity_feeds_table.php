<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_activity_feeds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('activity_type'); // 'rack_uploaded', 'rack_liked', 'user_followed', etc.
            $table->morphs('subject'); // Polymorphic relation to rack, user, collection, etc.
            $table->json('metadata')->nullable(); // Additional activity data
            $table->timestamps();
            
            $table->index(['user_id', 'created_at']);
            $table->index(['activity_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_activity_feeds');
    }
};