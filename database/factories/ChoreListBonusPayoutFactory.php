<?php

namespace Database\Factories;

use App\Models\ChoreList;
use App\Models\ChoreListBonusPayout;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChoreListBonusPayout>
 */
class ChoreListBonusPayoutFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chore_list_id' => ChoreList::factory(),
            'user_id' => User::factory(),
            'weight_centipoints' => 100,
            'share_cents' => 1000,
        ];
    }
}
