<?php

namespace Database\Factories;

use App\Models\Chore;
use App\Models\ChoreList;
use App\Models\ChoreListItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChoreListItem>
 */
class ChoreListItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chore_list_id' => ChoreList::factory(),
            'chore_id' => Chore::factory(),
            'is_checked' => false,
            'priority' => null,
        ];
    }
}
