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
            $table->unsignedInteger('escalation_level')->default(0)->after('priority');
            $table->unsignedInteger('bounty_cents')->nullable()->after('escalation_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chore_list_items', function (Blueprint $table) {
            $table->dropColumn(['escalation_level', 'bounty_cents']);
        });
    }
};
