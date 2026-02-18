<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rack_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rack_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->tinyInteger('rating'); // 1-5 stars
            $table->text('review')->nullable();
            $table->boolean('is_verified_purchase')->default(false); // User downloaded the rack
            $table->integer('helpful_count')->default(0);
            $table->timestamps();
            
            $table->unique(['rack_id', 'user_id']);
            $table->index(['rack_id', 'rating']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rack_ratings');
    }
};