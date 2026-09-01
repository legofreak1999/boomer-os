<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('chore_completions', function (Blueprint $table) {
            // Nullable: existing rows predate this snapshot and have no
            // reliable base value to backfill, so they're treated as "no
            // escalation on record" wherever this is read.
            $table->unsignedTinyInteger('base_time_points')->nullable()->after('time_points');
            $table->unsignedInteger('escalation_level')->default(0)->after('base_time_points');
        });

        // Best-effort backfill for existing rows: assume no escalation was
        // in effect, so base = the already-stored total.
        DB::table('chore_completions')->update(['base_time_points' => DB::raw('time_points')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chore_completions', function (Blueprint $table) {
            $table->dropColumn(['base_time_points', 'escalation_level']);
        });
    }
};
