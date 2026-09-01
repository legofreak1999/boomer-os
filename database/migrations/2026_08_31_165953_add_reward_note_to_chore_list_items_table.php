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
        Schema::table('chore_list_items', function (Blueprint $table) {
            $table->string('reward_note', 500)->nullable()->after('bounty_cents');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chore_list_items', function (Blueprint $table) {
            $table->dropColumn('reward_note');
        });
    }
};
