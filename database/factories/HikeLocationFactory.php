<?php

namespace Database\Factories;

use App\Models\HikeLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HikeLocation>
 */
class HikeLocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'parking_lat' => fake()->latitude(51.5, 53.0),
            'parking_lng' => fake()->longitude(4.0, 6.5),
        ];
    }
}
