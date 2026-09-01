<?php

namespace Database\Factories;

use App\Models\ChoreCompletion;
use App\Models\ChoreListItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChoreCompletion>
 */
class ChoreCompletionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $timeCentipoints = fake()->numberBetween(1, 5) * 100;

        return [
            'chore_list_item_id' => ChoreListItem::factory(),
            'user_id' => User::factory(),
            'time_centipoints' => $timeCentipoints,
            'base_time_centipoints' => $timeCentipoints, // no escalation by default
            'escalation_level' => 0,
            'difficulty_centipoints' => fake()->numberBetween(1, 5) * 100,
            'bounty_cents' => null,
            'counts_toward_reward' => true,
            'completed_at' => now(),
        ];
    }
}
