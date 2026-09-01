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
            // Snapshotted so the Rewards receipt can still show what the
            // text reward was, even after the chore-list item's own note is
            // later changed or the item is deleted.
            $table->string('reward_note', 500)->nullable()->after('bounty_cents');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chore_completions', function (Blueprint $table) {
            $table->dropColumn('reward_note');
        });
    }
};
