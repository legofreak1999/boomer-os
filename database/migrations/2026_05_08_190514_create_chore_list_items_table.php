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
        Schema::create('chore_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chore_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chore_id')->constrained()->restrictOnDelete();
            $table->boolean('is_checked')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chore_list_items');
    }
};
