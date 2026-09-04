<?php

namespace Tests\Feature\Chores;

use App\Actions\Chores\PreviewChoreListItemPoints;
use App\Models\Chore;
use App\Models\ChoreDayBonus;
use App\Models\ChoreDifficultyRating;
use App\Models\ChoreListItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreviewChoreListItemPointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_unassigned_item_defaults_credit_to_the_viewer(): void
    {
        $user = User::factory()->create();
        $chore = Chore::factory()->create(['time_points' => 5]);
        ChoreDifficultyRating::factory()->create(['chore_id' => $chore->id, 'user_id' => $user->id, 'difficulty_points' => 3]);
        $item = ChoreListItem::factory()->create(['chore_id' => $chore->id]);

        $preview = (new PreviewChoreListItemPoints)($item, $user->id);

        $this->assertTrue($preview['is_credited']);
        $this->assertSame(1, $preview['assignee_count']);
        $this->assertSame(8.0, $preview['total']);
    }

    public function test_multi_assignee_item_splits_evenly(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $chore = Chore::factory()->create(['time_points' => 6]);
        ChoreDifficultyRating::factory()->create(['chore_id' => $chore->id, 'user_id' => $userA->id, 'difficulty_points' => 4]);
        $item = ChoreListItem::factory()->create(['chore_id' => $chore->id]);
        $item->users()->attach([$userA->id, $userB->id]);

        $preview = (new PreviewChoreListItemPoints)($item->fresh(), $userA->id);

        $this->assertTrue($preview['is_credited']);
        $this->assertSame(2, $preview['assignee_count']);
        $this->assertSame(3.0, $preview['time_share']); // 6 / 2
        $this->assertSame(2.0, $preview['difficulty_share']); // 4 / 2
        $this->assertSame(5.0, $preview['total']);
    }

    public function test_item_assigned_only_to_someone_else_is_not_credited(): void
    {
        $viewer = User::factory()->create();
        $otherUser = User::factory()->create();
        $chore = Chore::factory()->create(['time_points' => 5]);
        $item = ChoreListItem::factory()->create(['chore_id' => $chore->id]);
        $item->users()->attach($otherUser->id);

        $preview = (new PreviewChoreListItemPoints)($item->fresh(), $viewer->id);

        $this->assertFalse($preview['is_credited']);
        $this->assertSame(0.0, $preview['total']);
    }

    public function test_escalation_bonus_is_folded_into_the_time_share(): void
    {
        $user = User::factory()->create();
        $chore = Chore::factory()->create([
            'time_points' => 5,
            'escalation_increment' => 2,
            'escalation_cap' => 20,
        ]);
        $item = ChoreListItem::factory()->create(['chore_id' => $chore->id, 'escalation_level' => 3]);

        $preview = (new PreviewChoreListItemPoints)($item, $user->id);

        $this->assertSame(6, $preview['escalation_bonus']); // 3 misses * 2
        $this->assertSame(11.0, $preview['time_share']); // 5 base + 6 bonus
    }

    public function test_escalation_bonus_is_clamped_to_the_cap(): void
    {
        $user = User::factory()->create();
        $chore = Chore::factory()->create([
            'time_points' => 5,
            'escalation_increment' => 10,
            'escalation_cap' => 12,
        ]);
        $item = ChoreListItem::factory()->create(['chore_id' => $chore->id, 'escalation_level' => 5]);

        $preview = (new PreviewChoreListItemPoints)($item, $user->id);

        $this->assertSame(7, $preview['escalation_bonus']); // capped at 12 total, so 12 - 5
        $this->assertSame(12.0, $preview['time_share']);
    }

    public function test_bad_day_multiplier_applies_only_to_the_difficulty_share(): void
    {
        $user = User::factory()->create();
        $chore = Chore::factory()->create(['time_points' => 10]);
        ChoreDifficultyRating::factory()->create(['chore_id' => $chore->id, 'user_id' => $user->id, 'difficulty_points' => 4]);
        $item = ChoreListItem::factory()->create(['chore_id' => $chore->id]);
        ChoreDayBonus::factory()->create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'level' => ChoreDayBonus::LEVEL_BAD,
        ]);

        $preview = (new PreviewChoreListItemPoints)($item, $user->id);

        $this->assertSame(2, $preview['multiplier']);
        $this->assertSame(10.0, $preview['time_share']);
        $this->assertSame(8.0, $preview['difficulty_share']); // 4 * 2
        $this->assertSame(18.0, $preview['total']);
    }

    public function test_super_bad_day_multiplier_applies_only_to_the_difficulty_share(): void
    {
        $user = User::factory()->create();
        $chore = Chore::factory()->create(['time_points' => 10]);
        ChoreDifficultyRating::factory()->create(['chore_id' => $chore->id, 'user_id' => $user->id, 'difficulty_points' => 4]);
        $item = ChoreListItem::factory()->create(['chore_id' => $chore->id]);
        ChoreDayBonus::factory()->create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'level' => ChoreDayBonus::LEVEL_SUPER_BAD,
        ]);

        $preview = (new PreviewChoreListItemPoints)($item, $user->id);

        $this->assertSame(3, $preview['multiplier']);
        $this->assertSame(10.0, $preview['time_share']);
        $this->assertSame(12.0, $preview['difficulty_share']); // 4 * 3
        $this->assertSame(22.0, $preview['total']);
    }
}
