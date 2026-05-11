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
            'parent_id' => null,
        ];
    }

    public function childOf(ChoreCategory $parent): static
    {
        return $this->state(fn () => ['parent_id' => $parent->id]);
    }
}
