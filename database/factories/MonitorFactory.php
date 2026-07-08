<?php

namespace Database\Factories;

use App\Models\Monitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Monitor>
 */
class MonitorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'label' => fake()->words(2, true),
            'url' => fake()->url(),
            'interval_minutes' => 15,
            'check_type' => Monitor::CHECK_TEXT_CONTAINS,
            'check_config' => ['needle' => 'Out of stock', 'case_sensitive' => false],
            'notify_on' => Monitor::NOTIFY_ON_BOTH,
            'enabled' => true,
            'last_polled_at' => null,
            'last_matched' => null,
            'last_error' => null,
            'consecutive_failures' => 0,
        ];
    }
}
