<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReceiptItem>
 */
class ReceiptItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'receipt_id' => Receipt::factory(),
            'category_id' => Category::factory(),
            'amount' => fake()->numberBetween(100, 10000),
        ];
    }
}
