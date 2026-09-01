<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * "Yearly" is being removed as a repeat option entirely. Any list still
     * using it becomes a plain non-repeating list rather than being left
     * with a repeat_type the app no longer understands (which would just
     * silently stop resetting forever, since shouldResetOn()'s match falls
     * through to false for unknown types).
     */
    public function up(): void
    {
        DB::table('chore_lists')
            ->where('repeat_type', 'yearly')
            ->update([
                'repeat_type' => null,
                'repeat_value' => null,
                'repeat_start_date' => null,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not reversible: the original yearly repeat_value/start_date are
        // already gone by the time this would run.
    }
};
