<?php

namespace Database\Factories;

use App\Models\HikeTag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HikeTag>
 */
class HikeTagFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
        ];
    }
}
