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
        Schema::create('hike_trails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hike_location_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('distance_m')->default(0);
            $table->integer('duration_s')->default(0);
            $table->json('waypoints');
            $table->json('route_geojson')->nullable();
            $table->string('difficulty')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hike_trails');
    }
};
