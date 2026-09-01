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
        Schema::table('chores', function (Blueprint $table) {
            $table->unsignedTinyInteger('time_points')->default(1)->after('chore_category_id');
            $table->string('reward_note', 500)->nullable()->after('time_points');
            $table->unsignedTinyInteger('escalation_increment')->default(0)->after('reward_note');
            $table->unsignedTinyInteger('escalation_cap')->nullable()->after('escalation_increment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chores', function (Blueprint $table) {
            $table->dropColumn(['time_points', 'reward_note', 'escalation_increment', 'escalation_cap']);
        });
    }
};
