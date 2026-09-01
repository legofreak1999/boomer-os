<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * chore_categories.parent_id previously cascade-deleted, so deleting a
     * category with an app-level guard bug (or a direct DB/tinker delete)
     * could silently wipe an entire subcategory subtree with no DB-level
     * backstop — unlike the sibling chores.chore_category_id FK, which
     * already uses restrictOnDelete for exactly this kind of protection.
     */
    public function up(): void
    {
        Schema::table('chore_categories', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
        });

        Schema::table('chore_categories', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('chore_categories')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chore_categories', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
        });

        Schema::table('chore_categories', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('chore_categories')->cascadeOnDelete();
        });
    }
};
