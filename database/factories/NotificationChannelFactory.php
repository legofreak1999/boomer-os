<?php

namespace Database\Factories;

use App\Models\NotificationChannel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationChannel>
 */
class NotificationChannelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => NotificationChannel::TYPE_DISCORD,
            'label' => fake()->words(2, true),
            'config' => ['webhook_url' => 'https://discord.com/api/webhooks/'.fake()->numerify('##########').'/'.fake()->sha256()],
            'enabled' => true,
        ];
    }
}
