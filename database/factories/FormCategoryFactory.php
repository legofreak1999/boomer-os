<?php

namespace Database\Factories;

use App\Models\Form;
use App\Models\FormCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormCategory>
 */
class FormCategoryFactory extends Factory
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
