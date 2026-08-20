<?php

namespace Database\Factories;

use App\Models\StorageBackup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StorageBackup>
 */
class StorageBackupFactory extends Factory
{
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-30 days', 'now');
        $filename = 'backup-'.$date->format('Y-m-d-His').'.sql.gz';

        return [
            'filename' => $filename,
            'storage_key' => 'backups/'.$filename,
            'size_bytes' => fake()->numberBetween(500_000, 5_000_000),
            'synced_to_primary_at' => $date,
            'synced_to_secondary_at' => fake()->optional(0.9)->dateTimeBetween($date, 'now'),
            'notes' => null,
            'created_at' => $date,
            'updated_at' => $date,
        ];
    }

    public function failed(): static
    {
        return $this->state([
            'synced_to_primary_at' => null,
            'synced_to_secondary_at' => null,
            'notes' => 'Primary upload failed: Connection refused',
        ]);
    }
}
