<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('form_categories', 'form_row_categories');

        Schema::table('form_rows', function (Blueprint $table) {
            $table->dropForeign(['form_category_id']);
            $table->renameColumn('form_category_id', 'form_row_category_id');
        });

        Schema::table('form_rows', function (Blueprint $table) {
            $table->foreign('form_row_category_id')
                ->references('id')
                ->on('form_row_categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('form_rows', function (Blueprint $table) {
            $table->dropForeign(['form_row_category_id']);
            $table->renameColumn('form_row_category_id', 'form_category_id');
        });

        Schema::rename('form_row_categories', 'form_categories');

        Schema::table('form_rows', function (Blueprint $table) {
            $table->foreign('form_category_id')
                ->references('id')
                ->on('form_categories')
                ->nullOnDelete();
        });
    }
};
