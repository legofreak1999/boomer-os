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
        // Options are now nested: an array of rows, each row an array of options.
        // Accept a flat array (treat as one row) or a nested array passthrough.
        $nested = isset($options[0]) && is_array($options[0])
            ? $options
            : [$options];

        return $this->state(fn () => [
            'type' => FormColumn::TYPE_SELECT,
            'options' => $nested,
        ]);
    }
}
