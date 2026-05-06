<?php

namespace Database\Factories;

use App\Models\Receipt;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Receipt>
 */
class ReceiptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => fake()->dateTimeBetween('-1 year', 'now'),
            'store_id' => Store::factory(),
            'user_id' => User::factory(),
        ];
    }
}
