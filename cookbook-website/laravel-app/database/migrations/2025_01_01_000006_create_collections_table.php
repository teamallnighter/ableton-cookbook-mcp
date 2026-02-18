<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('slug')->unique();
            $table->boolean('is_public')->default(false);
            $table->integer('racks_count')->default(0);
            $table->integer('followers_count')->default(0);
            $table->timestamps();
            
            $table->index(['user_id', 'is_public']);
            $table->index('slug');
        });
        
        Schema::create('collection_racks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->constrained()->onDelete('cascade');
            $table->foreignId('rack_id')->constrained()->onDelete('cascade');
            $table->integer('position')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->unique(['collection_id', 'rack_id']);
            $table->index(['collection_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_racks');
        Schema::dropIfExists('collections');
    }
};