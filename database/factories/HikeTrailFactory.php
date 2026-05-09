<?php

namespace Database\Factories;

use App\Models\HikeLocation;
use App\Models\HikeTrail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HikeTrail>
 */
class HikeTrailFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hike_location_id' => HikeLocation::factory(),
            'name' => fake()->words(2, true),
            'distance_m' => fake()->numberBetween(2000, 15000),
            'duration_s' => fake()->numberBetween(1800, 14400),
            'waypoints' => [
                ['lat' => 52.0, 'lng' => 5.0, 'straight' => false],
                ['lat' => 52.01, 'lng' => 5.01, 'straight' => false],
            ],
        ];
    }
}
