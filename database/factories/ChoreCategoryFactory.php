<?php

namespace Database\Factories;

use App\Models\ChoreCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChoreCategory>
 */
class ChoreCategoryFactory extends Factory
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
