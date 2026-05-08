<?php

namespace Database\Seeders;

use App\Models\Task;
use Illuminate\Database\Seeder;

class TaskSeeder extends Seeder
{
    public function run(): void
    {
        $tasks = [
            ['name' => 'Fix kitchen light', 'priority' => 'high', 'due_date' => now()->addDays(2)],
            ['name' => 'Replace toilet seat', 'priority' => 'medium', 'due_date' => now()->addWeek()],
            ['name' => 'Hang new shelves in garage', 'priority' => 'low'],
            ['name' => 'Fix leaking tap in bathroom', 'priority' => 'high', 'due_date' => now()->subDay()],
            ['name' => 'Paint hallway wall', 'priority' => 'low'],
            ['name' => 'Replace doorbell battery', 'priority' => 'medium', 'due_date' => now()->addDays(3)],
            ['name' => 'Fix squeaky floorboard', 'priority' => 'low'],
            ['name' => 'Install smoke detector in attic', 'priority' => 'high', 'due_date' => now()],
            ['name' => 'Order new mailbox', 'priority' => 'medium'],
            ['name' => 'Patch hole in garden fence', 'priority' => 'medium', 'due_date' => now()->addDays(10)],
        ];

        foreach ($tasks as $index => $data) {
            Task::firstOrCreate(
                ['name' => $data['name']],
                array_merge($data, ['position' => $index]),
            );
        }

        // A couple completed tasks
        Task::firstOrCreate(
            ['name' => 'Replace air filter'],
            ['priority' => 'medium', 'is_completed' => true, 'completed_at' => now()->subDays(3), 'position' => 100],
        );

        Task::firstOrCreate(
            ['name' => 'Fix garden gate latch'],
            ['priority' => 'high', 'is_completed' => true, 'completed_at' => now()->subWeek(), 'position' => 101],
        );
    }
}
