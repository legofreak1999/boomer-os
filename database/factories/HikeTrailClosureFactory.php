<?php

namespace Database\Factories;

use App\Models\HikeTrail;
use App\Models\HikeTrailClosure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HikeTrailClosure>
 */
class HikeTrailClosureFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('now', '+6 months');

        return [
            'hike_trail_id' => HikeTrail::factory(),
            'start_date' => $start,
            'end_date' => fake()->dateTimeBetween($start, '+8 months'),
            'reason' => fake()->sentence(3),
        ];
    }
}
