<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(ExpenseSeeder::class);
        $this->call(ChoreSeeder::class);
        $this->call(TaskSeeder::class);
    }
}
