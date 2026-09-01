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
        Schema::table('chore_completions', function (Blueprint $table) {
            $table->dropColumn('day_bonus_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chore_completions', function (Blueprint $table) {
            $table->string('day_bonus_level')->nullable()->after('difficulty_points');
        });
    }
};
