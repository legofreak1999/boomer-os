<?php

namespace Database\Factories;

use App\Models\Chore;
use App\Models\ChoreDifficultyRating;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChoreDifficultyRating>
 */
class ChoreDifficultyRatingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chore_id' => Chore::factory(),
            'user_id' => User::factory(),
            'difficulty_points' => fake()->numberBetween(1, 5),
        ];
    }
}
