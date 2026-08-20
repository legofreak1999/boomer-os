<?php

namespace Database\Factories;

use App\Models\StorageFile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StorageFile>
 */
class StorageFileFactory extends Factory
{
    private static array $extensions = [
        ['ext' => 'pdf', 'mime' => 'application/pdf'],
        ['ext' => 'jpg', 'mime' => 'image/jpeg'],
        ['ext' => 'png', 'mime' => 'image/png'],
        ['ext' => 'docx', 'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        ['ext' => 'xlsx', 'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        ['ext' => 'mp4', 'mime' => 'video/mp4'],
        ['ext' => 'zip', 'mime' => 'application/zip'],
    ];

    public function definition(): array
    {
        $type = fake()->randomElement(self::$extensions);
        $name = Str::slug(fake()->words(fake()->numberBetween(1, 3), true)).'.'.$type['ext'];
        $storageKey = 'files/'.Str::uuid().'-'.$name;
        $syncedAt = fake()->optional(0.8)->dateTimeBetween('-30 days', 'now');

        return [
            'filename' => $name,
            'storage_key' => $storageKey,
            'mime_type' => $type['mime'],
            'size_bytes' => fake()->numberBetween(10_000, 50_000_000),
            'synced_to_primary_at' => now(),
            'synced_to_secondary_at' => $syncedAt,
        ];
    }

    public function pendingSync(): static
    {
        return $this->state(['synced_to_secondary_at' => null]);
    }

    public function fullySync(): static
    {
        return $this->state(['synced_to_secondary_at' => now()]);
    }
}
