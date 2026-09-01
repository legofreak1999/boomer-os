<?php

namespace Database\Factories;

use App\Models\Chore;
use App\Models\ChoreCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Chore>
 */
class ChoreFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'chore_category_id' => ChoreCategory::factory(),
            'time_points' => fake()->numberBetween(1, 5),
            'escalation_increment' => 0,
            'escalation_cap' => null,
        ];
    }
}
