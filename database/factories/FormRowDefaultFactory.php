<?php

namespace Database\Factories;

use App\Models\FormColumn;
use App\Models\FormRow;
use App\Models\FormRowDefault;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormRowDefault>
 */
class FormRowDefaultFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'form_row_id' => FormRow::factory(),
            'form_column_id' => FormColumn::factory(),
            'value' => fake()->sentence(3),
            'locked' => false,
        ];
    }

    public function locked(): static
    {
        return $this->state(fn () => ['locked' => true]);
    }
}
