<?php

namespace Database\Factories;

use App\Models\Form;
use App\Models\FormColumn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormColumn>
 */
class FormColumnFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'form_id' => Form::factory(),
            'label' => fake()->words(2, true),
            'type' => FormColumn::TYPE_TEXT,
            'options' => null,
            'position' => 0,
        ];
    }

    public function select(array $options = ['Yes', 'No', 'Maybe']): static
    {
        return $this->state(fn () => [
            'type' => FormColumn::TYPE_SELECT,
            'options' => $options,
        ]);
    }
}
