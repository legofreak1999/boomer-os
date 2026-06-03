<?php

namespace Database\Factories;

use App\Models\FormCell;
use App\Models\FormColumn;
use App\Models\FormResponse;
use App\Models\FormRow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormCell>
 */
class FormCellFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'form_response_id' => FormResponse::factory(),
            'form_row_id' => FormRow::factory(),
            'form_column_id' => FormColumn::factory(),
            'value' => fake()->word(),
        ];
    }
}
