<?php

namespace Database\Factories;

use App\Models\ChoreDayBonus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChoreDayBonus>
 */
class ChoreDayBonusFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => now()->toDateString(),
            'level' => ChoreDayBonus::LEVEL_BAD,
        ];
    }
}
