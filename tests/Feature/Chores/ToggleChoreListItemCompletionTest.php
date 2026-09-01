<?php

namespace Tests\Feature\Chores;

use App\Actions\Chores\ToggleChoreListItemCompletion;
use App\Models\Chore;
use App\Models\ChoreCompletion;
use App\Models\ChoreDayBonus;
use App\Models\ChoreDifficultyRating;
use App\Models\ChoreList;
use App\Models\ChoreListItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToggleChoreListItemCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_checking_an_item_creates_a_completion_with_time_and_difficulty_points(): void
    {
        $user = User::factory()->create();
        $chore = Chore::factory()->create(['time_points' => 3]);
        ChoreDifficultyRating::factory()->create([
            'chore_id' => $chore->id,
            'user_id' => $user->id,
            'difficulty_points' => 4,
        ]);
        $list = ChoreList::factory()->create(['repeat_type' => 'daily', 'repeat_value' => 1, 'repeat_start_date' => '2026-05-01']);
        $item = ChoreListItem::factory()->create(['chore_list_id' => $list->id, 'chore_id' => $chore->id, 'is_checked' => false]);

        (new ToggleChoreListItemCompletion)($item, $user->id);

        $this->assertTrue($item->fresh()->is_checked);
        $this->assertDatabaseHas('chore_completions', [
            'chore_list_item_id' => $item->id,
            'user_id' => $user->id,
            'time_centipoints' => 300,
            'difficulty_centipoints' => 400,
            'counts_toward_reward' => true,
        ]);
    }

    public function test_missing_difficulty_rating_defaults_to_one(): void
    {
        $user = User::factory()->create();
        $chore = Chore::factory()->create(['time_points' => 3]);
        $list = ChoreList::factory()->create(['repeat_type' => 'daily', 'repeat_value' => 1, 'repeat_start_date' => '2026-05-01']);
        $item = ChoreListItem::factory()->create(['chore_list_id' => $list->id, 'chore_id' => $chore->id, 'is_checked' => false]);

        (new ToggleChoreListItemCompletion)($item, $user->id);

        $this->assertDatabaseHas('chore_completions', [
            'chore_list_item_id' => $item->id,
            'difficulty_centipoints' => 100,
        ]);
    }

    public function test_unchecking_deletes_the_most_recent_completion(): void
    {
        $user = User::factory()->create();
        $chore = Chore::factory()->create();
        $list = ChoreList::factory()->create(['repeat_type' => 'daily', 'repeat_value' => 1, 'repeat_start_date' => '2026-05-01']);
        $item = ChoreListItem::factory()->create(['chore_list_id' => $list->id, 'chore_id' => $chore->id, 'is_checked' => false]);

        (new ToggleChoreListItemCompletion)($item, $user->id);
        (new ToggleChoreListItemCompletion)($item->fresh(), $user->id);

        $this->assertFalse($item->fresh()->is_checked);
        $this->assertSame(0, ChoreCompletion::where('chore_list_item_id', $item->id)->count());
    }

    public function test_awards_escalated_points_clamped_to_cap(): void
    {
        $user = User::factory()->create();
        $chore = Chore::factory()->create(['time_points' => 2, 'escalation_increment' => 3, 'escalation_cap' => 6]);
        $list = ChoreList::factory()->create(['repeat_type' => 'daily', 'repeat_value' => 1, 'repeat_start_date' => '2026-05-01']);
        $item = ChoreListItem::factory()->create([
            'chore_list_id' => $list->id,
            'chore_id' => $chore->id,
            'is_checked' => false,
            'escalation_level' => 5, // 2 + 5*3 = 17, capped to 6
        ]);

        (new ToggleChoreListItemCompletion)($item, $user->id);

        $this->assertDatabaseHas('chore_completions', [
            'chore_list_item_id' => $item->id,
            'time_centipoints' => 600,
            'base_time_centipoints' => 200,
            'escalation_level' => 5,
        ]);
    }

    public function test_escalation_snapshot_reconciles_to_the_awarded_bonus(): void
    {
        $user = User::factory()->create();
        $chore = Chore::factory()->create(['time_points' => 1, 'escalation_increment' => 2, 'escalation_cap' => null]);
        $list = ChoreList::factory()->create(['repeat_type' => 'daily', 'repeat_value' => 1, 'repeat_start_date' => '2026-05-01']);
        $item = ChoreListItem::factory()->create([
            'chore_list_id' => $list->id,
            'chore_id' => $chore->id,
            'is_checked' => false,
            'escalation_level' => 3, // 1 + 3*2 = 7, no cap
        ]);

        (new ToggleChoreListItemCompletion)($item, $user->id);

        $completion = ChoreCompletion::where('chore_list_item_id', $item->id)->firstOrFail();
        $this->assertSame(700, $completion->time_centipoints);
        $this->assertSame(100, $completion->base_time_centipoints);
        $this->assertSame(3, $completion->escalation_level);
        $this->assertSame(600, $completion->time_centipoints - $completion->base_time_centipoints); // the displayed bonus
    }

    public function test_no_escalation_bonus_when_disabled(): void
    {
        $user = User::factory()->create();
        $chore = Chore::factory()->create(['time_points' => 4, 'escalation_increment' => 0]);
        $list = ChoreList::factory()->create(['repeat_type' => 'daily', 'repeat_value' => 1, 'repeat_start_date' => '2026-05-01']);
        $item = ChoreListItem::factory()->create([
            'chore_list_id' => $list->id,
            'chore_id' => $chore->id,
            'is_checked' => false,
            'escalation_level' => 0,
        ]);

        (new ToggleChoreListItemCompletion)($item, $user->id);

        $this->assertDatabaseHas('chore_completions', [
            'chore_list_item_id' => $item->id,
            'time_centipoints' => 400,
            'base_time_centipoints' => 400,
        ]);
    }

    public function test_bounty_is_snapshotted_and_cleared_from_item(): void
    {
        $user = User::factory()->create();
        $chore = Chore::factory()->create();
        $list = ChoreList::factory()->create(['repeat_type' => 'daily', 'repeat_value' => 1, 'repeat_start_date' => '2026-05-01']);
        $item = ChoreListItem::factory()->create([
            'chore_list_id' => $list->id,
            'chore_id' => $chore->id,
            'is_checked' => false,
            'bounty_cents' => 1500,
        ]);

        (new ToggleChoreListItemCompletion)($item, $user->id);

        $this->assertDatabaseHas('chore_completions', [
            'chore_list_item_id' => $item->id,
            'bounty_cents' => 1500,
        ]);
        $this->assertNull($item->fresh()->bounty_cents);
    }

    public function test_unchecking_restores_the_bounty_to_the_item(): void
    {
        $user = User::factory()->create();
        $item = ChoreListItem::factory()->create(['is_checked' => false, 'bounty_cents' => 1500]);

        (new ToggleChoreListItemCompletion)($item, $user->id);
        $this->assertNull($item->fresh()->bounty_cents);

        (new ToggleChoreListItemCompletion)($item->fresh(), $user->id);

        $this->assertFalse($item->fresh()->is_checked);
        $this->assertSame(1500, $item->fresh()->bounty_cents);
    }

    public function test_unchecking_restores_the_full_bounty_when_it_was_split(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $item = ChoreListItem::factory()->create(['is_checked' => false, 'bounty_cents' => 1000]);
        $item->users()->attach([$userA->id, $userB->id]);

        (new ToggleChoreListItemCompletion)($item, $userA->id);
        (new ToggleChoreListItemCompletion)($item->fresh(), $userA->id);

        $this->assertSame(1000, $item->fresh()->bounty_cents);
    }

    public function test_reward_note_is_snapshotted_onto_the_completion(): void
    {
        $user = User::factory()->create();
        $item = ChoreListItem::factory()->create(['is_checked' => false, 'reward_note' => 'Loser does dishes for a week']);

        (new ToggleChoreListItemCompletion)($item, $user->id);

        $this->assertDatabaseHas('chore_completions', [
            'chore_list_item_id' => $item->id,
            'reward_note' => 'Loser does dishes for a week',
        ]);
        // Unlike the bounty, a text reward isn't cleared off the item at
        // check time — it's a persistent note, not a one-time payout.
        $this->assertSame('Loser does dishes for a week', $item->fresh()->reward_note);
    }

    public function test_one_off_list_completion_does_not_count_toward_reward(): void
    {
        $user = User::factory()->create();
        $chore = Chore::factory()->create();
        $list = ChoreList::factory()->create(['repeat_type' => null]);
        $item = ChoreListItem::factory()->create(['chore_list_id' => $list->id, 'chore_id' => $chore->id, 'is_checked' => false]);

        (new ToggleChoreListItemCompletion)($item, $user->id);

        $this->assertDatabaseHas('chore_completions', [
            'chore_list_item_id' => $item->id,
            'counts_toward_reward' => false,
        ]);
    }

    public function test_yearly_list_completion_does_not_count_toward_reward(): void
    {
        $user = User::factory()->create();
        $chore = Chore::factory()->create();
        $list = ChoreList::factory()->create(['repeat_type' => 'yearly', 'repeat_value' => 3, 'repeat_start_date' => '2026-03-15']);
        $item = ChoreListItem::factory()->create(['chore_list_id' => $list->id, 'chore_id' => $chore->id, 'is_checked' => false]);

        (new ToggleChoreListItemCompletion)($item, $user->id);

        $this->assertDatabaseHas('chore_completions', [
            'chore_list_item_id' => $item->id,
            'counts_toward_reward' => false,
        ]);
    }

    public function test_falls_back_to_acting_user_when_nobody_is_assigned(): void
    {
        $actingUser = User::factory()->create();
        $item = ChoreListItem::factory()->create(['is_checked' => false]);

        (new ToggleChoreListItemCompletion)($item, $actingUser->id);

        $this->assertSame(1, ChoreCompletion::where('chore_list_item_id', $item->id)->count());
        $this->assertDatabaseHas('chore_completions', [
            'chore_list_item_id' => $item->id,
            'user_id' => $actingUser->id,
        ]);
    }

    public function test_checking_an_unassigned_item_assigns_the_acting_user(): void
    {
        $actingUser = User::factory()->create();
        $item = ChoreListItem::factory()->create(['is_checked' => false]);

        (new ToggleChoreListItemCompletion)($item, $actingUser->id);

        $this->assertSame([$actingUser->id], $item->fresh()->users->pluck('id')->all());
    }

    public function test_assigned_users_are_credited_instead_of_the_acting_user(): void
    {
        $actingUser = User::factory()->create();
        $assignee = User::factory()->create();
        $item = ChoreListItem::factory()->create(['is_checked' => false]);
        $item->users()->attach($assignee->id);

        (new ToggleChoreListItemCompletion)($item, $actingUser->id);

        $this->assertSame(1, ChoreCompletion::where('chore_list_item_id', $item->id)->count());
        $this->assertDatabaseHas('chore_completions', [
            'chore_list_item_id' => $item->id,
            'user_id' => $assignee->id,
        ]);
        $this->assertDatabaseMissing('chore_completions', [
            'chore_list_item_id' => $item->id,
            'user_id' => $actingUser->id,
        ]);
    }

    public function test_effort_and_bounty_split_evenly_across_assigned_users(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $chore = Chore::factory()->create(['time_points' => 5]);
        ChoreDifficultyRating::factory()->create(['chore_id' => $chore->id, 'user_id' => $userA->id, 'difficulty_points' => 3]);
        ChoreDifficultyRating::factory()->create(['chore_id' => $chore->id, 'user_id' => $userB->id, 'difficulty_points' => 7]);
        $item = ChoreListItem::factory()->create(['chore_id' => $chore->id, 'is_checked' => false, 'bounty_cents' => 1000]);
        $item->users()->attach([$userA->id, $userB->id]);

        (new ToggleChoreListItemCompletion)($item, $userA->id);

        $this->assertSame(2, ChoreCompletion::where('chore_list_item_id', $item->id)->count());
        $this->assertDatabaseHas('chore_completions', [
            'chore_list_item_id' => $item->id,
            'user_id' => $userA->id,
            'time_centipoints' => 250, // 500 / 2, exact
            'difficulty_centipoints' => 150, // 300 / 2, exact
            'bounty_cents' => 500,
        ]);
        $this->assertDatabaseHas('chore_completions', [
            'chore_list_item_id' => $item->id,
            'user_id' => $userB->id,
            'time_centipoints' => 250,
            'difficulty_centipoints' => 350, // 700 / 2, exact
            'bounty_cents' => 500,
        ]);
    }

    public function test_unchecking_removes_the_entire_multi_user_batch(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $item = ChoreListItem::factory()->create(['is_checked' => false]);
        $item->users()->attach([$userA->id, $userB->id]);

        (new ToggleChoreListItemCompletion)($item, $userA->id);
        $this->assertSame(2, ChoreCompletion::where('chore_list_item_id', $item->id)->count());

        (new ToggleChoreListItemCompletion)($item->fresh(), $userA->id);

        $this->assertFalse($item->fresh()->is_checked);
        $this->assertSame(0, ChoreCompletion::where('chore_list_item_id', $item->id)->count());
    }

    public function test_stores_plain_base_difficulty_regardless_of_any_active_day_bonus_flag(): void
    {
        // The day-bonus multiplier is applied live at summary time, not at
        // completion time, so this write path should be completely oblivious
        // to any flag — this is a regression guard for that architecture.
        $user = User::factory()->create();
        $chore = Chore::factory()->create(['time_points' => 5]);
        ChoreDifficultyRating::factory()->create(['chore_id' => $chore->id, 'user_id' => $user->id, 'difficulty_points' => 5]);
        ChoreDayBonus::factory()->create(['user_id' => $user->id, 'date' => now()->toDateString(), 'level' => ChoreDayBonus::LEVEL_SUPER_BAD]);
        $list = ChoreList::factory()->create(['repeat_type' => 'daily', 'repeat_value' => 1, 'repeat_start_date' => '2026-05-01']);
        $item = ChoreListItem::factory()->create(['chore_list_id' => $list->id, 'chore_id' => $chore->id, 'is_checked' => false]);

        (new ToggleChoreListItemCompletion)($item, $user->id);

        $this->assertDatabaseHas('chore_completions', [
            'chore_list_item_id' => $item->id,
            'time_centipoints' => 500,
            'difficulty_centipoints' => 500, // unmultiplied, despite the active super-bad flag
        ]);
    }
}
