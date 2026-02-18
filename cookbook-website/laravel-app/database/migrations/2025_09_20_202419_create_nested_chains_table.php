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
        Schema::create('nested_chains', function (Blueprint $table) {
            $table->id();

            // Foreign key to racks table
            $table->foreignId('rack_id')->constrained('racks')->onDelete('cascade');

            // Chain identification
            $table->string('chain_identifier');
            $table->text('xml_path');

            // Hierarchical structure
            $table->foreignId('parent_chain_id')->nullable()->constrained('nested_chains')->onDelete('cascade');
            $table->integer('depth_level')->default(0);

            // Chain analysis
            $table->integer('device_count')->default(0);
            $table->boolean('is_empty')->default(false);
            $table->enum('chain_type', ['instrument', 'audio_effect', 'drum_pad', 'midi_effect', 'unknown'])->default('unknown');

            // Chain metadata
            $table->json('devices')->nullable();
            $table->json('parameters')->nullable();
            $table->json('chain_metadata')->nullable();

            // Performance tracking
            $table->timestamp('analyzed_at');

            $table->timestamps();

            // Indexes for performance and hierarchical queries
            $table->index('rack_id');
            $table->index('parent_chain_id');
            $table->index('depth_level');
            $table->index('chain_type');
            $table->index(['rack_id', 'chain_identifier']);
            $table->index(['rack_id', 'depth_level']);

            // Unique constraint to prevent duplicate chains per rack
            $table->unique(['rack_id', 'chain_identifier']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nested_chains');
    }
};
