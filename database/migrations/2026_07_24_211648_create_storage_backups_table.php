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
        Schema::create('storage_backups', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('storage_key');
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamp('synced_to_primary_at')->nullable();
            $table->timestamp('synced_to_secondary_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('storage_backups');
    }
};
