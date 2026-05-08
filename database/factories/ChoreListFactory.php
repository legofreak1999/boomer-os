<?php

namespace Database\Factories;

use App\Models\ChoreList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChoreList>
 */
class ChoreListFactory extends Factory
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
            'position' => 0,
            'is_hidden' => false,
        ];
    }
}
