<?php

namespace Tests\Feature\Chores;

use App\Models\Chore;
use App\Models\ChoreCompletion;
use App\Models\ChoreDayBonus;
use App\Models\ChoreListItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ChoreRewardsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_rewards_page_is_displayed(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('chores.rewards'))->assertOk();
    }

    public function test_rewards_page_redirects_unauthenticated(): void
    {
        $this->get(route('chores.rewards'))->assertRedirect(route('login'));
    }

    public function test_unclaimed_jobs_lists_a_checked_item_with_no_assignee(): void
    {
        $this->actingAs(User::factory()->create());

        $chore = Chore::factory()->create(['name' => 'Vacuum stairs']);
        ChoreListItem::factory()->create(['chore_id' => $chore->id, 'is_checked' => true]);

        $this->get(route('chores.rewards'))
            ->assertSee('Unclaimed jobs')
            ->assertSee('Vacuum stairs');
    }

    public function test_unclaimed_jobs_section_is_hidden_when_nothing_to_claim(): void
    {
        $this->actingAs(User::factory()->create());

        ChoreListItem::factory()->create(['is_checked' => false]);

        $this->get(route('chores.rewards'))->assertDontSee('Unclaimed jobs');
    }

    public function test_claiming_an_unclaimed_job_assigns_and_credits_the_user(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $item = ChoreListItem::factory()->create(['is_checked' => true]);

        Livewire::test('pages::chores.rewards')
            ->call('claimJob', $item->id, $user->id);

        $this->assertTrue($item->users()->where('user_id', $user->id)->exists());
        $this->assertDatabaseHas('chore_completions', [
            'chore_list_item_id' => $item->id,
            'user_id' => $user->id,
        ]);
        $this->get(route('chores.rewards'))->assertDontSee('Unclaimed jobs');
    }

    public function test_receipt_breakdown_is_split_into_points_rewards_and_excluded_sections(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Counts toward points, no reward.
        ChoreCompletion::factory()->create([
            'chore_list_item_id' => ChoreListItem::factory()->create()->id,
            'user_id' => $user->id,
            'counts_toward_reward' => true,
            'completed_at' => now(),
        ]);
        // Counts toward points AND has a bounty — the deliberate double record.
        ChoreCompletion::factory()->create([
            'chore_list_item_id' => ChoreListItem::factory()->create()->id,
            'user_id' => $user->id,
            'bounty_cents' => 500,
            'counts_toward_reward' => true,
            'completed_at' => now(),
        ]);
        // A one-off chore's reward, doesn't count toward points.
        ChoreCompletion::factory()->create([
            'chore_list_item_id' => ChoreListItem::factory()->create()->id,
            'user_id' => $user->id,
            'reward_note' => 'Winner picks dinner',
            'counts_toward_reward' => false,
            'completed_at' => now(),
        ]);
        // Doesn't count toward points, no reward either.
        ChoreCompletion::factory()->create([
            'chore_list_item_id' => ChoreListItem::factory()->create()->id,
            'user_id' => $user->id,
            'counts_toward_reward' => false,
            'completed_at' => now(),
        ]);

        $response = Livewire::test('pages::chores.rewards')
            ->call('toggleReceipt', $user->id);

        $response->assertSee(__('Points'))
            ->assertSee(__('Rewards'))
            ->assertSee(__("Points that don't count"))
            ->assertSee('Winner picks dinner')
            ->assertSee('&euro;5,00', escape: false);
    }

    public function test_receipt_shows_split_math_for_a_shared_completion(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $item = ChoreListItem::factory()->create();
        $timestamp = now();

        // Same shape as sharing "Clean the oven": time splits evenly, but
        // each person's own difficulty rating is split by their own share —
        // userA's own rating was 2, userB's was 8, both divided by 2 people.
        ChoreCompletion::factory()->create([
            'chore_list_item_id' => $item->id,
            'user_id' => $userA->id,
            'time_centipoints' => 200,
            'base_time_centipoints' => 200,
            'difficulty_centipoints' => 100,
            'counts_toward_reward' => true,
            'completed_at' => $timestamp,
        ]);
        ChoreCompletion::factory()->create([
            'chore_list_item_id' => $item->id,
            'user_id' => $userB->id,
            'time_centipoints' => 200,
            'base_time_centipoints' => 200,
            'difficulty_centipoints' => 400,
            'counts_toward_reward' => true,
            'completed_at' => $timestamp,
        ]);

        $responseA = Livewire::test('pages::chores.rewards')->call('toggleReceipt', $userA->id);
        $responseB = Livewire::test('pages::chores.rewards')->call('toggleReceipt', $userB->id);

        // 4 (raw time total) / 2 = 2 each; 2 (userA's own rating) / 2 = 1;
        // 8 (userB's own rating) / 2 = 4. &divide; is the raw entity Blade
        // outputs, not the decoded ÷ character.
        $responseA->assertSee('4 &divide; 2 = 2', escape: false)->assertSee('2 &divide; 2 = 1', escape: false);
        $responseB->assertSee('4 &divide; 2 = 2', escape: false)->assertSee('8 &divide; 2 = 4', escape: false);
    }

    public function test_month_navigation_changes_displayed_summary(): void
    {
        $this->actingAs(User::factory()->create());
        $user = User::factory()->create();
        $item = ChoreListItem::factory()->create();
        ChoreCompletion::factory()->create([
            'chore_list_item_id' => $item->id,
            'user_id' => $user->id,
            'time_centipoints' => 500,
            'counts_toward_reward' => true,
            'completed_at' => now()->subMonth(),
        ]);

        Livewire::test('pages::chores.rewards')
            ->assertSet('month', now()->format('Y-m'))
            ->call('previousMonth')
            ->assertSet('month', now()->subMonth()->format('Y-m'));
    }

    public function test_can_set_a_day_bonus_for_self(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test('pages::chores.rewards')
            ->call('newBonus')
            ->set('bonusUserId', $user->id)
            ->set('bonusDate', now()->toDateString())
            ->set('bonusLevel', ChoreDayBonus::LEVEL_BAD)
            ->call('saveBonus')
            ->assertHasNoErrors();

        $this->assertSame(ChoreDayBonus::LEVEL_BAD, ChoreDayBonus::levelFor($user->id, now()));
    }

    public function test_can_set_a_day_bonus_for_someone_else(): void
    {
        $this->actingAs(User::factory()->create());
        $otherUser = User::factory()->create();

        Livewire::test('pages::chores.rewards')
            ->call('newBonus')
            ->set('bonusUserId', $otherUser->id)
            ->set('bonusDate', now()->toDateString())
            ->set('bonusLevel', ChoreDayBonus::LEVEL_SUPER_BAD)
            ->call('saveBonus')
            ->assertHasNoErrors();

        $this->assertSame(ChoreDayBonus::LEVEL_SUPER_BAD, ChoreDayBonus::levelFor($otherUser->id, now()));
    }

    public function test_can_set_a_day_bonus_for_a_past_date(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test('pages::chores.rewards')
            ->set('month', '2026-05')
            ->call('newBonus')
            ->set('bonusUserId', $user->id)
            ->set('bonusDate', '2026-05-05')
            ->set('bonusLevel', ChoreDayBonus::LEVEL_BAD)
            ->call('saveBonus')
            ->assertHasNoErrors();

        $this->assertSame(ChoreDayBonus::LEVEL_BAD, ChoreDayBonus::levelFor($user->id, Carbon::parse('2026-05-05')));
    }

    public function test_future_date_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test('pages::chores.rewards')
            ->call('newBonus')
            ->set('bonusUserId', $user->id)
            ->set('bonusDate', now()->addDay()->toDateString())
            ->set('bonusLevel', ChoreDayBonus::LEVEL_BAD)
            ->call('saveBonus')
            ->assertHasErrors(['bonusDate']);
    }

    public function test_can_clear_a_flag_from_the_list(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        ChoreDayBonus::factory()->create(['user_id' => $user->id, 'date' => now()->toDateString(), 'level' => ChoreDayBonus::LEVEL_BAD]);

        Livewire::test('pages::chores.rewards')
            ->call('clearBonus', $user->id, now()->toDateString());

        $this->assertNull(ChoreDayBonus::levelFor($user->id, now()));
    }

    public function test_edit_bonus_populates_the_form_with_the_existing_level(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        ChoreDayBonus::factory()->create(['user_id' => $user->id, 'date' => now()->toDateString(), 'level' => ChoreDayBonus::LEVEL_SUPER_BAD]);

        Livewire::test('pages::chores.rewards')
            ->call('editBonus', $user->id, now()->toDateString())
            ->assertSet('bonusLevel', ChoreDayBonus::LEVEL_SUPER_BAD)
            ->assertSet('showBonusForm', true);
    }
}
