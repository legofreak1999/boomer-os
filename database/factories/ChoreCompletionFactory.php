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
        return [
            'chore_list_item_id' => ChoreListItem::factory(),
            'user_id' => User::factory(),
            'time_centipoints' => fake()->numberBetween(1, 5) * 100,
            // A closure, not a value copied from a local variable: Laravel
            // resolves it against the final merged attributes, so it still
            // matches whatever a caller overrides 'time_centipoints' to
            // (e.g. ChoreCompletion::factory()->create(['time_centipoints' => 200]))
            // instead of silently diverging from an unrelated random draw
            // and looking like an unintended escalation bonus.
            'base_time_centipoints' => fn (array $attributes) => $attributes['time_centipoints'],
            'escalation_level' => 0,
            'difficulty_centipoints' => fake()->numberBetween(1, 5) * 100,
            'bounty_cents' => null,
            'counts_toward_reward' => true,
            'completed_at' => now(),
        ];
    }
}
