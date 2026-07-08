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
        Schema::create('monitors', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('url', 2048);
            $table->unsignedInteger('interval_minutes')->default(15);
            $table->string('check_type');
            $table->json('check_config');
            $table->string('notify_on')->default('both');
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_polled_at')->nullable();
            $table->boolean('last_matched')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monitors');
    }
};
