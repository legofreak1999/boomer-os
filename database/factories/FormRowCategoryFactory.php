<?php

namespace Database\Factories;

use App\Models\Form;
use App\Models\FormRowCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormRowCategory>
 */
class FormRowCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'form_id' => Form::factory(),
            'name' => fake()->words(2, true),
            'position' => 0,
        ];
    }
}
