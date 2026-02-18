<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rack_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rack_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('ip_address', 45);
            $table->string('user_agent')->nullable();
            $table->string('download_token', 64)->unique();
            $table->timestamp('downloaded_at');
            $table->timestamps();
            
            $table->index(['rack_id', 'downloaded_at']);
            $table->index(['user_id', 'downloaded_at']);
            $table->index('download_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rack_downloads');
    }
};