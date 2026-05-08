<?php

namespace Database\Seeders;

use App\Models\Chore;
use App\Models\ChoreCategory;
use App\Models\ChoreList;
use App\Models\ChoreListItem;
use Illuminate\Database\Seeder;

class ChoreSeeder extends Seeder
{
    /**
     * Seed realistic household chore data.
     */
    public function run(): void
    {
        $choresByCategory = [
            'Kitchen' => [
                'Clean countertops',
                'Do the dishes',
                'Clean the oven',
                'Empty the dishwasher',
                'Clean the fridge',
                'Mop kitchen floor',
                'Take out trash',
            ],
            'Bathroom' => [
                'Clean toilet',
                'Clean shower',
                'Clean sink and mirror',
                'Wash towels',
                'Restock toiletries',
            ],
            'Living Room' => [
                'Vacuum floor',
                'Dust surfaces',
                'Clean windows',
                'Tidy up cushions',
                'Water plants',
            ],
            'Bedroom' => [
                'Change bed sheets',
                'Vacuum floor',
                'Dust nightstands',
                'Organize closet',
            ],
            'Laundry' => [
                'Wash clothes',
                'Fold and put away laundry',
                'Iron clothes',
                'Wash bed linens',
            ],
            'Outdoor' => [
                'Mow the lawn',
                'Sweep the patio',
                'Water garden',
                'Clean gutters',
                'Wash windows outside',
            ],
            'General' => [
                'Vacuum stairs',
                'Mop hallway',
                'Empty all bins',
                'Check smoke detectors',
                'Declutter a room',
            ],
        ];

        $categories = [];
        $allChores = [];

        foreach ($choresByCategory as $categoryName => $chores) {
            $category = ChoreCategory::firstOrCreate(['name' => $categoryName]);
            $categories[$categoryName] = $category;

            foreach ($chores as $choreName) {
                $allChores[] = Chore::firstOrCreate([
                    'name' => $choreName,
                    'chore_category_id' => $category->id,
                ]);
            }
        }

        // Weekly cleaning list
        $weeklyList = ChoreList::create([
            'name' => 'Weekly Cleaning',
            'position' => 1,
            'repeat_type' => 'weekly',
            'repeat_value' => 6, // Saturday
            'repeat_start_date' => now()->startOfWeek()->format('Y-m-d'),
        ]);

        $weeklyChores = [
            'Vacuum floor', 'Mop kitchen floor', 'Clean countertops',
            'Clean toilet', 'Clean shower', 'Clean sink and mirror',
            'Dust surfaces', 'Take out trash', 'Empty all bins',
        ];

        foreach ($allChores as $chore) {
            if (in_array($chore->name, $weeklyChores)) {
                ChoreListItem::create([
                    'chore_list_id' => $weeklyList->id,
                    'chore_id' => $chore->id,
                    'is_checked' => fake()->boolean(30),
                ]);
            }
        }

        // Monthly deep clean
        $monthlyList = ChoreList::create([
            'name' => 'Monthly Deep Clean',
            'position' => 2,
            'repeat_type' => 'monthly_day',
            'repeat_value' => 1,
            'repeat_start_date' => now()->startOfMonth()->format('Y-m-d'),
        ]);

        $monthlyChores = [
            'Clean the oven', 'Clean the fridge', 'Clean windows',
            'Wash towels', 'Wash bed linens', 'Organize closet',
            'Vacuum stairs', 'Declutter a room', 'Restock toiletries',
        ];

        foreach ($allChores as $chore) {
            if (in_array($chore->name, $monthlyChores)) {
                ChoreListItem::create([
                    'chore_list_id' => $monthlyList->id,
                    'chore_id' => $chore->id,
                ]);
            }
        }

        // Seasonal outdoor list
        $seasonalList = ChoreList::create([
            'name' => 'Spring Garden Prep',
            'position' => 3,
        ]);

        $outdoorChores = ['Mow the lawn', 'Sweep the patio', 'Water garden', 'Clean gutters', 'Wash windows outside'];

        foreach ($allChores as $chore) {
            if (in_array($chore->name, $outdoorChores)) {
                ChoreListItem::create([
                    'chore_list_id' => $seasonalList->id,
                    'chore_id' => $chore->id,
                ]);
            }
        }

        // Daily quick tidy
        $dailyList = ChoreList::create([
            'name' => 'Daily Quick Tidy',
            'position' => 0,
            'repeat_type' => 'daily',
            'repeat_value' => 1,
            'repeat_start_date' => now()->format('Y-m-d'),
        ]);

        $dailyChores = ['Do the dishes', 'Empty the dishwasher', 'Take out trash', 'Tidy up cushions'];

        foreach ($allChores as $chore) {
            if (in_array($chore->name, $dailyChores)) {
                ChoreListItem::create([
                    'chore_list_id' => $dailyList->id,
                    'chore_id' => $chore->id,
                    'is_checked' => fake()->boolean(50),
                ]);
            }
        }
    }
}
