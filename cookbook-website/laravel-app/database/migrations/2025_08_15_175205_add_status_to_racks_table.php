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
            if (!Schema::hasColumn('racks', 'status')) {
                $table->string('status')->default('pending')->after('is_public');
            }
            if (!Schema::hasColumn('racks', 'processing_error')) {
                $table->text('processing_error')->nullable()->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('racks', function (Blueprint $table) {
            $table->dropColumn(['status', 'processing_error']);
        });
    }
};
