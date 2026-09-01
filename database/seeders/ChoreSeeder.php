<?php

namespace Database\Seeders;

use App\Models\Chore;
use App\Models\ChoreCategory;
use App\Models\ChoreCompletion;
use App\Models\ChoreDifficultyRating;
use App\Models\ChoreList;
use App\Models\ChoreListItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class ChoreSeeder extends Seeder
{
    /**
     * Seed realistic household chore data, including the reward-system fields
     * (time/effort points, escalation, personal difficulty, reward notes,
     * a bounty, and some completion history) so the reward split has
     * something real to show right after seeding.
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

        // Chore-specific reward tuning: intentionally varied so the reward
        // system's different mechanics are all visible after seeding.
        $timePoints = [
            'Do the dishes' => 1,
            'Empty the dishwasher' => 1,
            'Take out trash' => 1,
            'Clean the oven' => 4,
            'Mow the lawn' => 5,
            'Clean gutters' => 5,
        ];

        $escalation = [
            // Steep buildup: skipping dishes gets messier fast.
            'Do the dishes' => ['increment' => 2, 'cap' => 9],
            // Mild buildup: still want it done, but it doesn't compound much.
            'Empty the dishwasher' => ['increment' => 1, 'cap' => 4],
        ];

        // Personal difficulty overrides for the two household users, keyed
        // by chore name. Values are [first user's difficulty, second user's].
        $difficultyOverrides = [
            'Clean the oven' => [2, 8],
            'Clean toilet' => [7, 2],
            'Do the dishes' => [3, 6],
            'Fold and put away laundry' => [6, 2],
            'Mow the lawn' => [2, 6],
            'Vacuum stairs' => [5, 5],
        ];

        // This module's demo data (difficulty ratings, day bonuses) only
        // makes sense with two people — mirrors ExpenseSeeder's fallback of
        // creating a default user when none exists yet.
        while (User::count() < 2) {
            User::factory()->create(User::count() === 0
                ? ['name' => 'Thomas', 'email' => 'thomas@fam-ruiter.eu']
                : ['name' => 'Amber', 'email' => 'amber@fam-ruiter.eu']
            );
        }

        $users = User::orderBy('id')->take(2)->get();

        $categories = [];
        $allChores = [];

        foreach ($choresByCategory as $categoryName => $chores) {
            $category = ChoreCategory::firstOrCreate(['name' => $categoryName]);
            $categories[$categoryName] = $category;

            foreach ($chores as $choreName) {
                $esc = $escalation[$choreName] ?? null;

                $chore = Chore::updateOrCreate(
                    ['name' => $choreName, 'chore_category_id' => $category->id],
                    [
                        'time_points' => $timePoints[$choreName] ?? fake()->numberBetween(1, 3),
                        'escalation_increment' => $esc['increment'] ?? 0,
                        'escalation_cap' => $esc['cap'] ?? null,
                    ],
                );

                if ($users->count() === 2 && isset($difficultyOverrides[$choreName])) {
                    [$firstDifficulty, $secondDifficulty] = $difficultyOverrides[$choreName];

                    ChoreDifficultyRating::updateOrCreate(
                        ['chore_id' => $chore->id, 'user_id' => $users[0]->id],
                        ['difficulty_points' => $firstDifficulty],
                    );
                    ChoreDifficultyRating::updateOrCreate(
                        ['chore_id' => $chore->id, 'user_id' => $users[1]->id],
                        ['difficulty_points' => $secondDifficulty],
                    );
                }

                $allChores[] = $chore;
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

        $weeklyItems = [];
        foreach ($allChores as $chore) {
            if (in_array($chore->name, $weeklyChores)) {
                $weeklyItems[] = ChoreListItem::create([
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

        $monthlyItems = [];
        foreach ($allChores as $chore) {
            if (in_array($chore->name, $monthlyChores)) {
                $monthlyItems[] = ChoreListItem::create([
                    'chore_list_id' => $monthlyList->id,
                    'chore_id' => $chore->id,
                ]);
            }
        }

        // Set an ad hoc bounty on one urgent, not-yet-done monthly item, and a
        // text reward on another (a reward is either cash or a note, never both).
        if ($ovenItem = collect($monthlyItems)->first(fn ($item) => $item->chore->name === 'Clean the oven')) {
            $ovenItem->update(['bounty_cents' => 1000]);
        }
        if ($fridgeItem = collect($monthlyItems)->first(fn ($item) => $item->chore->name === 'Clean the fridge')) {
            $fridgeItem->update(['reward_note' => 'Loser does dishes for a week']);
        }

        // Seasonal outdoor list (one-off, excluded from the reward pool by design)
        $seasonalList = ChoreList::create([
            'name' => 'Spring Garden Prep',
            'position' => 3,
        ]);

        $outdoorChores = ['Mow the lawn', 'Sweep the patio', 'Water garden', 'Clean gutters', 'Wash windows outside'];

        $seasonalItems = [];
        foreach ($allChores as $chore) {
            if (in_array($chore->name, $outdoorChores)) {
                $seasonalItems[] = ChoreListItem::create([
                    'chore_list_id' => $seasonalList->id,
                    'chore_id' => $chore->id,
                    'reward_note' => $chore->name === 'Mow the lawn' ? 'Winner picks the weekend activity' : null,
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

        // Simulate a couple of missed cycles on the chores that have
        // escalation configured, so the buildup is actually visible rather
        // than just theoretically possible.
        $escalationLevels = [
            'Do the dishes' => 2,
            'Empty the dishwasher' => 3,
        ];

        $dailyItems = [];
        foreach ($allChores as $chore) {
            if (in_array($chore->name, $dailyChores)) {
                $hasEscalation = isset($escalationLevels[$chore->name]);

                $dailyItems[] = ChoreListItem::create([
                    'chore_list_id' => $dailyList->id,
                    'chore_id' => $chore->id,
                    // An escalated item needs to still be uncompleted for the
                    // buildup to actually show — otherwise it'd just reset.
                    'is_checked' => $hasEscalation ? false : fake()->boolean(50),
                    'escalation_level' => $escalationLevels[$chore->name] ?? 0,
                ]);
            }
        }

        if ($users->count() === 2) {
            $this->seedCompletionHistory($users, [...$dailyItems, ...$weeklyItems]);

            // A real, already-completed example of escalation paying off, so
            // the Rewards receipt shows the base+bonus math immediately
            // rather than only after manually completing a built-up item.
            // Dated "today" specifically (not a few days back) so it always
            // lands in whatever month the Rewards page currently defaults
            // to, regardless of when the seeder is run.
            if ($dishesItem = collect($dailyItems)->first(fn ($item) => $item->chore->name === 'Do the dishes')) {
                ChoreCompletion::create([
                    'chore_list_item_id' => $dishesItem->id,
                    'user_id' => $users[0]->id,
                    'time_centipoints' => 300, // base 1 + (1 miss * increment 2)
                    'base_time_centipoints' => 100,
                    'escalation_level' => 1,
                    'difficulty_centipoints' => $dishesItem->chore->difficultyPointsFor($users[0]->id) * 100,
                    'counts_toward_reward' => true,
                    'completed_at' => now(),
                ]);
            }

            // An already-claimed bounty: counts toward points AND carries a
            // reward, so the Rewards receipt has a real example of a line
            // showing up in both the "Points" and "Rewards" sections.
            if ($ovenItem = collect($monthlyItems)->first(fn ($item) => $item->chore->name === 'Clean the oven')) {
                ChoreCompletion::create([
                    'chore_list_item_id' => $ovenItem->id,
                    'user_id' => $users[1]->id,
                    'time_centipoints' => $ovenItem->chore->time_points * 100,
                    'base_time_centipoints' => $ovenItem->chore->time_points * 100,
                    'escalation_level' => 0,
                    'difficulty_centipoints' => $ovenItem->chore->difficultyPointsFor($users[1]->id) * 100,
                    'bounty_cents' => 1000,
                    'counts_toward_reward' => true,
                    'completed_at' => now(),
                ]);
                // Mirrors what ToggleChoreListItemCompletion does at check
                // time: the bounty moves off the item and onto the
                // completion, the item itself is marked done, and the
                // credited user is bound as the assignee — assignment IS
                // completion credit in this app, so a checked item with no
                // assignee would look broken in the UI.
                $ovenItem->update(['is_checked' => true, 'bounty_cents' => null]);
                $ovenItem->users()->attach($users[1]->id);
            }

            // A completed one-off chore with a reward: doesn't count toward
            // points (seasonal list, no repeat), but still carries a reward
            // — a real example of the "Rewards" and "Points that don't
            // count" sections both showing the same line.
            if ($mowItem = collect($seasonalItems)->first(fn ($item) => $item->chore->name === 'Mow the lawn')) {
                ChoreCompletion::create([
                    'chore_list_item_id' => $mowItem->id,
                    'user_id' => $users[0]->id,
                    'time_centipoints' => $mowItem->chore->time_points * 100,
                    'base_time_centipoints' => $mowItem->chore->time_points * 100,
                    'escalation_level' => 0,
                    'difficulty_centipoints' => $mowItem->chore->difficultyPointsFor($users[0]->id) * 100,
                    'reward_note' => 'Winner picks the weekend activity',
                    'counts_toward_reward' => false,
                    'completed_at' => now(),
                ]);
                $mowItem->update(['is_checked' => true]);
                $mowItem->users()->attach($users[0]->id);
            }
        }
    }

    /**
     * Seed a couple of weeks of realistic completion history so the Rewards
     * page has real numbers to show immediately after seeding, rather than
     * all zeros. Deliberately spans a full trailing window regardless of the
     * current day of the month — if that spills into the previous month,
     * it's still useful demo data for the Rewards page's month navigation,
     * and the explicit "today" completions above guarantee the default
     * (current month) view isn't empty on its own.
     *
     * @param  Collection<int, User>  $users
     * @param  array<int, ChoreListItem>  $items
     */
    private function seedCompletionHistory($users, array $items): void
    {
        foreach (range(13, 0) as $daysAgo) {
            $date = now()->subDays($daysAgo);

            // Not every item gets done every day — that's the point.
            $itemsDoneToday = collect($items)->random(min(count($items), fake()->numberBetween(1, 3)));

            foreach ($itemsDoneToday as $item) {
                $chore = $item->chore;
                $creditedUser = $users->random();

                ChoreCompletion::create([
                    'chore_list_item_id' => $item->id,
                    'user_id' => $creditedUser->id,
                    'time_centipoints' => $chore->time_points * 100,
                    'base_time_centipoints' => $chore->time_points * 100, // no escalation in this simulated history
                    'escalation_level' => 0,
                    'difficulty_centipoints' => $chore->difficultyPointsFor($creditedUser->id) * 100,
                    'bounty_cents' => null,
                    'counts_toward_reward' => $item->choreList->hasRepeat() && $item->choreList->repeat_type !== ChoreList::REPEAT_YEARLY,
                    'completed_at' => $date,
                ]);
            }
        }
    }
}
