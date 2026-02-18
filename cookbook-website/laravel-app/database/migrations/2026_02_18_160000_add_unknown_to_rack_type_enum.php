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
        // Alter the rack_type enum to include 'Unknown' and 'drum_rack'
        DB::statement("ALTER TABLE racks MODIFY COLUMN rack_type ENUM('Unknown', 'AudioEffectGroupDevice', 'InstrumentGroupDevice', 'MidiEffectGroupDevice', 'drum_rack') NOT NULL DEFAULT 'Unknown'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum values (this will fail if there are 'Unknown' or 'drum_rack' values in the table)
        DB::statement("ALTER TABLE racks MODIFY COLUMN rack_type ENUM('AudioEffectGroupDevice', 'InstrumentGroupDevice', 'MidiEffectGroupDevice') NOT NULL");
    }
};
