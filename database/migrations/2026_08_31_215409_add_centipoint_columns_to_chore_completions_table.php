<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Splitting a completion's effort between multiple credited people used
     * integer (floored) division on whole points, which could silently drop
     * points entirely — a 1-point chore split between two people floored to
     * 0 for both. Storing hundredths of a point ("centipoints", same idea as
     * storing money as cents) instead of whole points means an even 2-way
     * split is always exact, and lets the rest of the app keep doing plain
     * integer math instead of introducing floats/decimals.
     *
     * New columns are added and the old ones dropped (rather than resizing
     * or renaming in place) so this needs no doctrine/dbal, which isn't
     * installed in this app.
     */
    public function up(): void
    {
        Schema::table('chore_completions', function (Blueprint $table) {
            $table->unsignedInteger('time_centipoints')->nullable()->after('time_points');
            $table->unsignedInteger('base_time_centipoints')->nullable()->after('base_time_points');
            $table->unsignedInteger('difficulty_centipoints')->nullable()->after('difficulty_points');
        });

        DB::table('chore_completions')->update([
            'time_centipoints' => DB::raw('time_points * 100'),
            'base_time_centipoints' => DB::raw('base_time_points * 100'),
            'difficulty_centipoints' => DB::raw('difficulty_points * 100'),
        ]);

        Schema::table('chore_completions', function (Blueprint $table) {
            $table->dropColumn(['time_points', 'base_time_points', 'difficulty_points']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chore_completions', function (Blueprint $table) {
            $table->unsignedTinyInteger('time_points')->nullable()->after('user_id');
            $table->unsignedTinyInteger('base_time_points')->nullable()->after('time_points');
            $table->unsignedTinyInteger('difficulty_points')->nullable()->after('base_time_points');
        });

        DB::table('chore_completions')->update([
            'time_points' => DB::raw('time_centipoints / 100'),
            'base_time_points' => DB::raw('base_time_centipoints / 100'),
            'difficulty_points' => DB::raw('difficulty_centipoints / 100'),
        ]);

        Schema::table('chore_completions', function (Blueprint $table) {
            $table->dropColumn(['time_centipoints', 'base_time_centipoints', 'difficulty_centipoints']);
        });
    }
};
