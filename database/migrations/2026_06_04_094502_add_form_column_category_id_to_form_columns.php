<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_columns', function (Blueprint $table) {
            $table->foreignId('form_column_category_id')
                ->nullable()
                ->after('form_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('form_columns', function (Blueprint $table) {
            $table->dropForeign(['form_column_category_id']);
            $table->dropColumn('form_column_category_id');
        });
    }
};
