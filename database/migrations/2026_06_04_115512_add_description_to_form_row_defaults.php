<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_row_defaults', function (Blueprint $table) {
            $table->text('description')->nullable()->after('locked');
            $table->text('value')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('form_row_defaults', function (Blueprint $table) {
            $table->dropColumn('description');
            $table->text('value')->nullable(false)->change();
        });
    }
};
