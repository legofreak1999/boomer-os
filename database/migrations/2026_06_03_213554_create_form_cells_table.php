<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_cells', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_response_id')->constrained()->cascadeOnDelete();
            $table->foreignId('form_row_id')->constrained()->cascadeOnDelete();
            $table->foreignId('form_column_id')->constrained()->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['form_response_id', 'form_row_id', 'form_column_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_cells');
    }
};
