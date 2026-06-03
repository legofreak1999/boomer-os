<?php

namespace Database\Factories;

use App\Models\Form;
use App\Models\FormRow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormRow>
 */
class FormRowFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'form_id' => Form::factory(),
            'form_category_id' => null,
            'position' => 0,
        ];
    }
}
