<?php

namespace Tests\Feature\Chores;

use App\Actions\Chores\SyncChoreCompletionCredit;
use App\Actions\Chores\ToggleChoreListItemCompletion;
use App\Models\Chore;
use App\Models\ChoreCompletion;
use App\Models\ChoreDifficultyRating;
use App\Models\ChoreListItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncChoreCompletionCreditTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigning_a_user_after_completion_credits_them_retroactively(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $chore = Chore::factory()->create(['time_points' => 4]);
        $item = ChoreListItem::factory()->create(['chore_id' => $chore->id, 'is_checked' => false]);
        $item->users()->attach($userA->id);

        (new ToggleChoreListItemCompletion)($item, $userA->id);
        $this->assertSame(1, ChoreCompletion::where('chore_list_item_id', $item->id)->count());

        $item->users()->toggle($userB->id);
        (new SyncChoreCompletionCredit)($item, $userA->id);

        $this->assertSame(2, ChoreCompletion::where('chore_list_item_id', $item->id)->count());
        $this->assertDatabaseHas('chore_completions', [
            'chore_list_item_id' => $item->id,
            'user_id' => $userA->id,
            'time_centipoints' => 200,
        ]);
        $this->assertDatabaseHas('chore_completions', [
            'chore_list_item_id' => $item->id,
            'user_id' => $userB->id,
            'time_centipoints' => 200,
        ]);
    }

    public function test_unassigning_a_user_after_completion_removes_their_credit(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $chore = Chore::factory()->create(['time_points' => 4]);
        $item = ChoreListItem::factory()->create(['chore_id' => $chore->id, 'is_checked' => false]);
        $item->users()->attach([$userA->id, $userB->id]);

        (new ToggleChoreListItemCompletion)($item, $userA->id);
        $this->assertSame(2, ChoreCompletion::where('chore_list_item_id', $item->id)->count());

        $item->users()->detach($userB->id);
        (new SyncChoreCompletionCredit)($item, $userA->id);

        $this->assertSame(1, ChoreCompletion::where('chore_list_item_id', $item->id)->count());
        $this->assertDatabaseHas('chore_completions', [
            'chore_list_item_id' => $item->id,
            'user_id' => $userA->id,
            'time_centipoints' => 400, // back to full credit, no longer split
        ]);
        $this->assertDatabaseMissing('chore_completions', [
            'chore_list_item_id' => $item->id,
            'user_id' => $userB->id,
        ]);
    }

    public function test_falls_back_to_acting_user_when_last_assignee_is_removed(): void
    {
        $actingUser = User::factory()->create();
        $assignee = User::factory()->create();
        $item = ChoreListItem::factory()->create(['is_checked' => false]);
        $item->users()->attach($assignee->id);

        (new ToggleChoreListItemCompletion)($item, $actingUser->id);

        $item->users()->detach($assignee->id);
        (new SyncChoreCompletionCredit)($item, $actingUser->id);

        $this->assertSame(1, ChoreCompletion::where('chore_list_item_id', $item->id)->count());
        $this->assertDatabaseHas('chore_completions', [
            'chore_list_item_id' => $item->id,
            'user_id' => $actingUser->id,
        ]);
    }

    public function test_does_nothing_while_item_is_still_unchecked(): void
    {
        $user = User::factory()->create();
        $item = ChoreListItem::factory()->create(['is_checked' => false]);
        $item->users()->attach($user->id);

        (new SyncChoreCompletionCredit)($item, $user->id);

        $this->assertSame(0, ChoreCompletion::where('chore_list_item_id', $item->id)->count());
    }

    public function test_resync_preserves_the_original_completed_at_timestamp(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $item = ChoreListItem::factory()->create(['is_checked' => false]);
        $item->users()->attach($userA->id);

        (new ToggleChoreListItemCompletion)($item, $userA->id);
        $originalTimestamp = ChoreCompletion::where('chore_list_item_id', $item->id)->first()->completed_at;

        $item->users()->toggle($userB->id);
        (new SyncChoreCompletionCredit)($item, $userA->id);

        $this->assertTrue(
            ChoreCompletion::where('chore_list_item_id', $item->id)->get()
                ->every(fn (ChoreCompletion $completion) => $completion->completed_at->eq($originalTimestamp))
        );
    }

    public function test_resync_preserves_the_bounty_total_when_reassigning(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $item = ChoreListItem::factory()->create(['is_checked' => false, 'bounty_cents' => 1000]);
        $item->users()->attach($userA->id);

        (new ToggleChoreListItemCompletion)($item, $userA->id);

        $item->users()->toggle($userB->id);
        (new SyncChoreCompletionCredit)($item, $userA->id);

        $this->assertDatabaseHas('chore_completions', [
            'chore_list_item_id' => $item->id,
            'user_id' => $userA->id,
            'bounty_cents' => 500,
        ]);
        $this->assertDatabaseHas('chore_completions', [
            'chore_list_item_id' => $item->id,
            'user_id' => $userB->id,
            'bounty_cents' => 500,
        ]);
    }

    public function test_resync_uses_the_newly_assigned_users_own_difficulty_rating(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $chore = Chore::factory()->create();
        ChoreDifficultyRating::factory()->create(['chore_id' => $chore->id, 'user_id' => $userB->id, 'difficulty_points' => 8]);
        $item = ChoreListItem::factory()->create(['chore_id' => $chore->id, 'is_checked' => false]);
        $item->users()->attach($userA->id);

        (new ToggleChoreListItemCompletion)($item, $userA->id);

        $item->users()->toggle($userB->id);
        (new SyncChoreCompletionCredit)($item, $userA->id);

        $this->assertDatabaseHas('chore_completions', [
            'chore_list_item_id' => $item->id,
            'user_id' => $userB->id,
            'difficulty_centipoints' => 400, // 8 / 2, userB's own rating
        ]);
    }
}
