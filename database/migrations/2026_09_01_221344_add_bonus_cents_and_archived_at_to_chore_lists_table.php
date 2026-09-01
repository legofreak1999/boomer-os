<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * bonus_cents: a one-time cash reward on a non-repeating list, split
     * between contributors by their point weight on that list once it's
     * archived (see CalculateArchivedListBonusPayouts) — separate from the
     * per-item bounty_cents, which is a flat amount for a single chore.
     *
     * archived_at: set instead of deleting a completed non-repeating list
     * when it carries a bonus, so the split has something to read from
     * after completion and the payout can be shown under whichever month
     * it was archived in on the Rewards page.
     */
    public function up(): void
    {
        Schema::table('chore_lists', function (Blueprint $table) {
            $table->unsignedInteger('bonus_cents')->nullable()->after('is_hidden');
            $table->timestamp('archived_at')->nullable()->after('bonus_cents');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chore_lists', function (Blueprint $table) {
            $table->dropColumn(['bonus_cents', 'archived_at']);
        });
    }
};
