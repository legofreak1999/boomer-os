<?php

namespace Database\Factories;

use App\Models\StorageFolder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StorageFolder>
 */
class StorageFolderFactory extends Factory
{
    private static array $names = ['Documents', 'Photos', 'Videos', 'Music', 'Backups', 'Work', 'Personal', 'Archive', 'Downloads', 'Shared'];

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(self::$names),
            'parent_id' => null,
        ];
    }
}
