<?php

namespace Tests\Feature\Chores;

use App\Actions\Chores\CalculateMonthlyRewardSummary;
use App\Actions\Chores\ToggleChoreListItemCompletion;
use App\Models\AppSetting;
use App\Models\Chore;
use App\Models\ChoreCompletion;
use App\Models\ChoreDayBonus;
use App\Models\ChoreList;
use App\Models\ChoreListItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalculateMonthlyRewardSummaryTest extends TestCase
{
    use RefreshDatabase;

    private function makeDailyList(int $timePoints = 1): ChoreList
    {
        $chore = Chore::factory()->create(['time_points' => $timePoints]);
        $list = ChoreList::factory()->create([
            'repeat_type' => 'daily',
            'repeat_value' => 1,
            'repeat_start_date' => '2026-01-01',
            'is_hidden' => false,
        ]);
        ChoreListItem::factory()->create(['chore_list_id' => $list->id, 'chore_id' => $chore->id]);

        return $list;
    }

    public function test_target_uses_only_base_time_points_from_recurring_lists(): void
    {
        $this->makeDailyList(timePoints: 2);

        // May has 31 days, daily list occurs every day => 31 * 2 = 62
        $result = (new CalculateMonthlyRewardSummary)(Carbon::parse('2026-05-01'));

        $this->assertEquals(62, $result['target_points']);
    }

    public function test_target_grows_to_absorb_escalation_debt_actually_earned(): void
    {
        $this->makeDailyList(timePoints: 1); // baseline target = 31 (May)
        $user = User::factory()->create();
        $item = ChoreListItem::factory()->create();

        // Missed twice: 1 + 2*2 = 5, i.e. a +4 escalation bonus over the
        // baseline 1-point occurrence — that's real extra effort recognized
        // this month and the target should grow to reflect it, not let it
        // silently push time_completed past a target that never moved.
        ChoreCompletion::factory()->create([
            'chore_list_item_id' => $item->id,
            'user_id' => $user->id,
            'time_centipoints' => 500,
            'base_time_centipoints' => 100,
            'escalation_level' => 2,
            'counts_toward_reward' => true,
            'completed_at' => Carbon::parse('2026-05-10'),
        ]);

        $result = (new CalculateMonthlyRewardSummary)(Carbon::parse('2026-05-01'));

        $this->assertEquals(35, $result['target_points']); // 31 + 4
    }

    public function test_escalation_debt_from_non_counting_completions_does_not_inflate_target(): void
    {
        $this->makeDailyList(timePoints: 1); // baseline target = 31
        $user = User::factory()->create();
        $item = ChoreListItem::factory()->create();

        ChoreCompletion::factory()->create([
            'chore_list_item_id' => $item->id,
            'user_id' => $user->id,
            'time_centipoints' => 500,
            'base_time_centipoints' => 100,
            'escalation_level' => 2,
            'counts_toward_reward' => false, // e.g. a one-off list's item
            'completed_at' => Carbon::parse('2026-05-10'),
        ]);

        $result = (new CalculateMonthlyRewardSummary)(Carbon::parse('2026-05-01'));

        $this->assertEquals(31, $result['target_points']);
    }

    public function test_counts_toward_reward_stays_snapshotted_even_if_the_lists_repeat_type_later_changes(): void
    {
        // counts_toward_reward is recorded on the completion at credit time
        // from whatever the list's repeat_type was *then* (see
        // CreditChoreCompletion) — it deliberately does not get
        // retroactively recomputed from the list's *current* repeat_type,
        // since it should reflect what was true when the work happened.
        $user = User::factory()->create();
        $list = ChoreList::factory()->create(['repeat_type' => 'daily', 'repeat_value' => 1, 'repeat_start_date' => '2026-05-01']);
        $item = ChoreListItem::factory()->create(['chore_list_id' => $list->id, 'is_checked' => false]);

        (new ToggleChoreListItemCompletion)($item, $user->id);
        $this->assertDatabaseHas('chore_completions', [
            'chore_list_item_id' => $item->id,
            'counts_toward_reward' => true,
        ]);

        // The list is later turned into a one-off (no repeat) list.
        $list->update(['repeat_type' => null, 'repeat_value' => null, 'repeat_start_date' => null]);

        $this->assertDatabaseHas('chore_completions', [
            'chore_list_item_id' => $item->id,
            'counts_toward_reward' => true,
        ]);
    }

    public function test_non_repeating_lists_do_not_contribute_to_target(): void
    {
        $chore = Chore::factory()->create(['time_points' => 5]);
        $list = ChoreList::factory()->create(['repeat_type' => null]);
        ChoreListItem::factory()->create(['chore_list_id' => $list->id, 'chore_id' => $chore->id]);

        $result = (new CalculateMonthlyRewardSummary)(Carbon::parse('2026-05-01'));

        $this->assertEquals(0, $result['target_points']);
    }

    public function test_yearly_lists_are_excluded_from_target(): void
    {
        $chore = Chore::factory()->create(['time_points' => 50]);
        $list = ChoreList::factory()->create(['repeat_type' => 'yearly', 'repeat_value' => 5, 'repeat_start_date' => '2026-05-15']);
        ChoreListItem::factory()->create(['chore_list_id' => $list->id, 'chore_id' => $chore->id]);

        $result = (new CalculateMonthlyRewardSummary)(Carbon::parse('2026-05-01'));

        $this->assertEquals(0, $result['target_points']);
    }

    public function test_hidden_list_still_counts_toward_target(): void
    {
        $list = $this->makeDailyList(timePoints: 1);
        $list->update(['is_hidden' => true]);

        $result = (new CalculateMonthlyRewardSummary)(Carbon::parse('2026-05-01'));

        $this->assertEquals(31, $result['target_points']);
    }

    public function test_completions_outside_the_month_are_excluded(): void
    {
        $user = User::factory()->create();
        $item = ChoreListItem::factory()->create();
        ChoreCompletion::factory()->create([
            'chore_list_item_id' => $item->id,
            'user_id' => $user->id,
            'time_centipoints' => 1000,
            'completed_at' => Carbon::parse('2026-04-30 23:59:59'),
        ]);

        $result = (new CalculateMonthlyRewardSummary)(Carbon::parse('2026-05-01'));

        $this->assertEquals(0, $result['time_completed']);
    }

    public function test_difficulty_never_inflates_stage_one_time_completed(): void
    {
        $this->makeDailyList(timePoints: 1); // target = 31
        $user = User::factory()->create();
        $item = ChoreListItem::factory()->create();

        // Complete the whole month worth of time_points (31), but with very high difficulty each time.
        for ($day = 1; $day <= 31; $day++) {
            ChoreCompletion::factory()->create([
                'chore_list_item_id' => $item->id,
                'user_id' => $user->id,
                'time_centipoints' => 100,
                'difficulty_centipoints' => 1000,
                'counts_toward_reward' => true,
                'completed_at' => Carbon::parse("2026-05-{$day} 12:00:00"),
            ]);
        }

        $result = (new CalculateMonthlyRewardSummary)(Carbon::parse('2026-05-01'));

        $this->assertEquals(31, $result['time_completed']);
        $this->assertLessThanOrEqual($result['target_points'], $result['time_completed']);
        $this->assertSame(20000, $result['pool_payout_cents']); // fully unlocked, not inflated past 100%
    }

    public function test_pool_payout_scales_with_completion_ratio_and_is_capped(): void
    {
        $this->makeDailyList(timePoints: 1); // target = 31
        $user = User::factory()->create();
        $item = ChoreListItem::factory()->create();

        for ($day = 1; $day <= 16; $day++) { // ~51.6% of target
            ChoreCompletion::factory()->create([
                'chore_list_item_id' => $item->id,
                'user_id' => $user->id,
                'time_centipoints' => 100,
                'difficulty_centipoints' => 100,
                'counts_toward_reward' => true,
                'completed_at' => Carbon::parse("2026-05-{$day} 12:00:00"),
            ]);
        }

        $result = (new CalculateMonthlyRewardSummary)(Carbon::parse('2026-05-01'));

        $this->assertSame((int) round(20000 * 16 / 31), $result['pool_payout_cents']);
    }

    public function test_one_persons_share_is_unaffected_by_the_others_inactivity(): void
    {
        $this->makeDailyList(timePoints: 1);
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $item = ChoreListItem::factory()->create();

        // User B does a lot; user A does nothing.
        for ($day = 1; $day <= 20; $day++) {
            ChoreCompletion::factory()->create([
                'chore_list_item_id' => $item->id,
                'user_id' => $userB->id,
                'time_centipoints' => 100,
                'difficulty_centipoints' => 100,
                'counts_toward_reward' => true,
                'completed_at' => Carbon::parse("2026-05-{$day} 12:00:00"),
            ]);
        }

        $result = (new CalculateMonthlyRewardSummary)(Carbon::parse('2026-05-01'));

        $rowA = collect($result['breakdown'])->firstWhere('user_id', $userA->id);
        $rowB = collect($result['breakdown'])->firstWhere('user_id', $userB->id);

        $this->assertSame(0, $rowA['share_cents']);
        $this->assertGreaterThan(0, $rowB['share_cents']);
        $this->assertSame($result['pool_payout_cents'], $rowB['share_cents']); // B did 100% of the (nonzero) completed weight
    }

    public function test_zero_completion_month_yields_zero_share_but_floor_still_applies(): void
    {
        $this->makeDailyList();
        $user = User::factory()->create();

        $result = (new CalculateMonthlyRewardSummary)(Carbon::parse('2026-05-01'));

        $row = collect($result['breakdown'])->firstWhere('user_id', $user->id);
        $this->assertSame(0, $row['share_cents']);
        $this->assertSame(5000, $row['floor_cents']);
        $this->assertSame(5000, $row['grand_total_cents']);
    }

    public function test_bounty_totals_are_summed_separately_and_not_blended_into_pool(): void
    {
        $user = User::factory()->create();
        $item = ChoreListItem::factory()->create();
        ChoreCompletion::factory()->create([
            'chore_list_item_id' => $item->id,
            'user_id' => $user->id,
            'time_centipoints' => 100,
            'difficulty_centipoints' => 100,
            'bounty_cents' => 2500,
            'counts_toward_reward' => false, // e.g. a one-off chore's bounty
            'completed_at' => Carbon::parse('2026-05-10'),
        ]);

        $result = (new CalculateMonthlyRewardSummary)(Carbon::parse('2026-05-01'));

        $row = collect($result['breakdown'])->firstWhere('user_id', $user->id);
        $this->assertSame(2500, $row['bounty_cents']);
        $this->assertSame(0, $row['share_cents']);
        $this->assertSame(5000 + 2500, $row['grand_total_cents']);
    }

    public function test_uses_custom_app_settings_when_present(): void
    {
        AppSetting::set('chore_reward_settings', [
            'floor_per_person_cents' => 7000,
            'bonus_pool_cents' => 10000,
            'bounty_max_cents' => 50000,
        ]);
        User::factory()->create();

        $result = (new CalculateMonthlyRewardSummary)(Carbon::parse('2026-05-01'));

        $this->assertSame(7000, $result['breakdown'][0]['floor_cents']);
    }

    public function test_zero_floor_and_pool_settings_are_valid_and_do_not_error(): void
    {
        AppSetting::set('chore_reward_settings', [
            'floor_per_person_cents' => 0,
            'bonus_pool_cents' => 0,
            'bounty_max_cents' => 50000,
        ]);
        $this->makeDailyList(timePoints: 1);
        $user = User::factory()->create();
        $item = ChoreListItem::factory()->create();
        ChoreCompletion::factory()->create([
            'chore_list_item_id' => $item->id,
            'user_id' => $user->id,
            'time_centipoints' => 100,
            'difficulty_centipoints' => 100,
            'counts_toward_reward' => true,
            'completed_at' => Carbon::parse('2026-05-10'),
        ]);

        $result = (new CalculateMonthlyRewardSummary)(Carbon::parse('2026-05-01'));

        $this->assertSame(0, $result['pool_payout_cents']);
        $row = collect($result['breakdown'])->firstWhere('user_id', $user->id);
        $this->assertSame(0, $row['floor_cents']);
        $this->assertSame(0, $row['share_cents']);
        $this->assertSame(0, $row['grand_total_cents']);
    }

    public function test_day_bonus_boosted_difficulty_only_affects_stage_two_split(): void
    {
        $this->makeDailyList(timePoints: 1); // target = 31
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $item = ChoreListItem::factory()->create();

        ChoreDayBonus::factory()->create(['user_id' => $userA->id, 'date' => '2026-05-05', 'level' => ChoreDayBonus::LEVEL_SUPER_BAD]);

        // Same time_points and base difficulty for both; only userA has an active flag on that date.
        ChoreCompletion::factory()->create([
            'chore_list_item_id' => $item->id,
            'user_id' => $userA->id,
            'time_centipoints' => 100,
            'difficulty_centipoints' => 1000,
            'counts_toward_reward' => true,
            'completed_at' => Carbon::parse('2026-05-05'),
        ]);
        ChoreCompletion::factory()->create([
            'chore_list_item_id' => $item->id,
            'user_id' => $userB->id,
            'time_centipoints' => 100,
            'difficulty_centipoints' => 1000,
            'counts_toward_reward' => true,
            'completed_at' => Carbon::parse('2026-05-06'),
        ]);

        $result = (new CalculateMonthlyRewardSummary)(Carbon::parse('2026-05-01'));

        // Stage 1 uses time_points only: 1 + 1 = 2, regardless of the huge difficulty boost.
        $this->assertEquals(2, $result['time_completed']);

        // Stage 2 uses time+effective_difficulty, so userA's flagged day earns a much bigger share.
        $rowA = collect($result['breakdown'])->firstWhere('user_id', $userA->id);
        $rowB = collect($result['breakdown'])->firstWhere('user_id', $userB->id);
        $this->assertGreaterThan($rowB['share_cents'], $rowA['share_cents']);
    }

    public function test_day_bonus_multiplier_is_applied_live_from_current_settings(): void
    {
        $user = User::factory()->create();
        $item = ChoreListItem::factory()->create();
        ChoreDayBonus::factory()->create(['user_id' => $user->id, 'date' => '2026-05-05', 'level' => ChoreDayBonus::LEVEL_BAD]);
        ChoreCompletion::factory()->create([
            'chore_list_item_id' => $item->id,
            'user_id' => $user->id,
            'time_centipoints' => 500,
            'difficulty_centipoints' => 500,
            'counts_toward_reward' => true,
            'completed_at' => Carbon::parse('2026-05-05'),
        ]);

        $result = (new CalculateMonthlyRewardSummary)(Carbon::parse('2026-05-01'));

        $line = collect($result['breakdown'])->firstWhere('user_id', $user->id)['receipt'][0];
        $this->assertSame(ChoreDayBonus::LEVEL_BAD, $line['day_bonus_level']);
        $this->assertSame(2, $line['multiplier']);
        $this->assertEquals(10, $line['effective_difficulty_points']); // 5 base * 2
        $this->assertEquals(15, $line['weight']); // 5 time + 10 effective difficulty
    }

    public function test_a_zero_day_bonus_multiplier_setting_is_clamped_to_one(): void
    {
        // Only validated in the settings form, not at the point of use — a
        // stray 0 (however it got into storage) would zero out difficulty
        // points on a flagged day instead of boosting them.
        AppSetting::set('chore_reward_settings', ['bad_day_multiplier' => 0]);

        $user = User::factory()->create();
        $item = ChoreListItem::factory()->create();
        ChoreDayBonus::factory()->create(['user_id' => $user->id, 'date' => '2026-05-05', 'level' => ChoreDayBonus::LEVEL_BAD]);
        ChoreCompletion::factory()->create([
            'chore_list_item_id' => $item->id,
            'user_id' => $user->id,
            'time_centipoints' => 500,
            'difficulty_centipoints' => 500,
            'counts_toward_reward' => true,
            'completed_at' => Carbon::parse('2026-05-05'),
        ]);

        $result = (new CalculateMonthlyRewardSummary)(Carbon::parse('2026-05-01'));

        $line = collect($result['breakdown'])->firstWhere('user_id', $user->id)['receipt'][0];
        $this->assertSame(1, $line['multiplier']);
        $this->assertEquals(5, $line['effective_difficulty_points']);
    }

    public function test_editing_a_past_days_flag_retroactively_changes_the_summary(): void
    {
        $user = User::factory()->create();
        $item = ChoreListItem::factory()->create();
        ChoreCompletion::factory()->create([
            'chore_list_item_id' => $item->id,
            'user_id' => $user->id,
            'time_centipoints' => 500,
            'difficulty_centipoints' => 500,
            'counts_toward_reward' => true,
            'completed_at' => Carbon::parse('2026-05-05'),
        ]);

        $before = (new CalculateMonthlyRewardSummary)(Carbon::parse('2026-05-01'));
        $rowBefore = collect($before['breakdown'])->firstWhere('user_id', $user->id);
        $this->assertEquals(10, $rowBefore['points']); // 5 + 5, no boost yet

        // Flag that day as bad AFTER the fact — no change to the completion row itself.
        ChoreDayBonus::setLevel($user->id, '2026-05-05', ChoreDayBonus::LEVEL_BAD);

        $after = (new CalculateMonthlyRewardSummary)(Carbon::parse('2026-05-01'));
        $rowAfter = collect($after['breakdown'])->firstWhere('user_id', $user->id);
        $this->assertEquals(15, $rowAfter['points']); // 5 + (5*2), recomputed live

        $this->assertDatabaseHas('chore_completions', [
            'chore_list_item_id' => $item->id,
            'difficulty_centipoints' => 500, // stored row is untouched
        ]);
    }

    public function test_receipt_includes_non_counting_completions_marked_accordingly(): void
    {
        $user = User::factory()->create();
        $item = ChoreListItem::factory()->create();
        ChoreCompletion::factory()->create([
            'chore_list_item_id' => $item->id,
            'user_id' => $user->id,
            'time_centipoints' => 300,
            'difficulty_centipoints' => 200,
            'counts_toward_reward' => false,
            'completed_at' => Carbon::parse('2026-05-10'),
        ]);

        $result = (new CalculateMonthlyRewardSummary)(Carbon::parse('2026-05-01'));

        $row = collect($result['breakdown'])->firstWhere('user_id', $user->id);
        $this->assertCount(1, $row['receipt']);
        $this->assertFalse($row['receipt'][0]['counts_toward_reward']);
        $this->assertEquals(0, $row['points']); // excluded from the point total
    }

    public function test_receipt_shows_chore_name(): void
    {
        $user = User::factory()->create();
        $chore = Chore::factory()->create(['name' => 'Do the dishes']);
        $item = ChoreListItem::factory()->create(['chore_id' => $chore->id]);
        ChoreCompletion::factory()->create([
            'chore_list_item_id' => $item->id,
            'user_id' => $user->id,
            'time_centipoints' => 100,
            'difficulty_centipoints' => 100,
            'counts_toward_reward' => true,
            'completed_at' => Carbon::parse('2026-05-10'),
        ]);

        $result = (new CalculateMonthlyRewardSummary)(Carbon::parse('2026-05-01'));

        $row = collect($result['breakdown'])->firstWhere('user_id', $user->id);
        $this->assertSame('Do the dishes', $row['receipt'][0]['chore_name']);
    }

    public function test_receipt_shows_the_bounty_and_reward_note_for_a_line(): void
    {
        $user = User::factory()->create();
        $bountyItem = ChoreListItem::factory()->create();
        ChoreCompletion::factory()->create([
            'chore_list_item_id' => $bountyItem->id,
            'user_id' => $user->id,
            'time_centipoints' => 100,
            'difficulty_centipoints' => 100,
            'bounty_cents' => 1000,
            'counts_toward_reward' => true,
            'completed_at' => Carbon::parse('2026-05-10'),
        ]);
        $noteItem = ChoreListItem::factory()->create();
        ChoreCompletion::factory()->create([
            'chore_list_item_id' => $noteItem->id,
            'user_id' => $user->id,
            'time_centipoints' => 100,
            'difficulty_centipoints' => 100,
            'reward_note' => 'Winner picks dinner',
            'counts_toward_reward' => true,
            'completed_at' => Carbon::parse('2026-05-11'),
        ]);

        $result = (new CalculateMonthlyRewardSummary)(Carbon::parse('2026-05-01'));

        $row = collect($result['breakdown'])->firstWhere('user_id', $user->id);
        $lineWithBounty = collect($row['receipt'])->firstWhere('date', '2026-05-10');
        $lineWithNote = collect($row['receipt'])->firstWhere('date', '2026-05-11');

        $this->assertSame(1000, $lineWithBounty['bounty_cents']);
        $this->assertNull($lineWithBounty['reward_note']);
        $this->assertSame('Winner picks dinner', $lineWithNote['reward_note']);
        $this->assertNull($lineWithNote['bounty_cents']);
    }

    public function test_receipt_shows_unknown_chore_when_list_item_was_deleted(): void
    {
        $user = User::factory()->create();
        $item = ChoreListItem::factory()->create();
        ChoreCompletion::factory()->create([
            'chore_list_item_id' => $item->id,
            'user_id' => $user->id,
            'time_centipoints' => 100,
            'difficulty_centipoints' => 100,
            'counts_toward_reward' => true,
            'completed_at' => Carbon::parse('2026-05-10'),
        ]);
        $item->delete();

        $result = (new CalculateMonthlyRewardSummary)(Carbon::parse('2026-05-01'));

        $row = collect($result['breakdown'])->firstWhere('user_id', $user->id);
        $this->assertSame('Unknown chore', $row['receipt'][0]['chore_name']);
    }

    public function test_receipt_shows_who_a_completion_was_shared_with(): void
    {
        $userA = User::factory()->create(['name' => 'Amber']);
        $userB = User::factory()->create(['name' => 'Thomas']);
        $item = ChoreListItem::factory()->create();
        $sharedTimestamp = Carbon::parse('2026-05-10 12:00:00');

        ChoreCompletion::factory()->create([
            'chore_list_item_id' => $item->id,
            'user_id' => $userA->id,
            'time_centipoints' => 200,
            'difficulty_centipoints' => 100,
            'counts_toward_reward' => true,
            'completed_at' => $sharedTimestamp,
        ]);
        ChoreCompletion::factory()->create([
            'chore_list_item_id' => $item->id,
            'user_id' => $userB->id,
            'time_centipoints' => 200,
            'difficulty_centipoints' => 100,
            'counts_toward_reward' => true,
            'completed_at' => $sharedTimestamp,
        ]);

        $result = (new CalculateMonthlyRewardSummary)(Carbon::parse('2026-05-01'));

        $rowA = collect($result['breakdown'])->firstWhere('user_id', $userA->id);
        $rowB = collect($result['breakdown'])->firstWhere('user_id', $userB->id);

        $this->assertSame(['Thomas'], $rowA['receipt'][0]['shared_with']);
        $this->assertSame(['Amber'], $rowB['receipt'][0]['shared_with']);
        $this->assertSame(2, $rowA['receipt'][0]['completer_count']);
        $this->assertSame(2, $rowB['receipt'][0]['completer_count']);
    }

    public function test_receipt_shared_with_is_empty_for_a_solo_completion(): void
    {
        $user = User::factory()->create();
        $item = ChoreListItem::factory()->create();
        ChoreCompletion::factory()->create([
            'chore_list_item_id' => $item->id,
            'user_id' => $user->id,
            'time_centipoints' => 100,
            'difficulty_centipoints' => 100,
            'counts_toward_reward' => true,
            'completed_at' => Carbon::parse('2026-05-10'),
        ]);

        $result = (new CalculateMonthlyRewardSummary)(Carbon::parse('2026-05-01'));

        $row = collect($result['breakdown'])->firstWhere('user_id', $user->id);
        $this->assertSame([], $row['receipt'][0]['shared_with']);
        $this->assertSame(1, $row['receipt'][0]['completer_count']);
    }

    public function test_receipt_shared_with_does_not_cross_different_items_or_dates(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $itemA = ChoreListItem::factory()->create();
        $itemB = ChoreListItem::factory()->create();
        $timestamp = Carbon::parse('2026-05-10 12:00:00');

        // Same timestamp, but different items — not a real shared batch.
        ChoreCompletion::factory()->create([
            'chore_list_item_id' => $itemA->id,
            'user_id' => $userA->id,
            'time_centipoints' => 100,
            'difficulty_centipoints' => 100,
            'counts_toward_reward' => true,
            'completed_at' => $timestamp,
        ]);
        ChoreCompletion::factory()->create([
            'chore_list_item_id' => $itemB->id,
            'user_id' => $userB->id,
            'time_centipoints' => 100,
            'difficulty_centipoints' => 100,
            'counts_toward_reward' => true,
            'completed_at' => $timestamp,
        ]);

        $result = (new CalculateMonthlyRewardSummary)(Carbon::parse('2026-05-01'));

        $rowA = collect($result['breakdown'])->firstWhere('user_id', $userA->id);
        $rowB = collect($result['breakdown'])->firstWhere('user_id', $userB->id);

        $this->assertSame([], $rowA['receipt'][0]['shared_with']);
        $this->assertSame([], $rowB['receipt'][0]['shared_with']);
    }

    public function test_receipt_shows_escalation_bonus_breakdown(): void
    {
        $user = User::factory()->create();
        $item = ChoreListItem::factory()->create();
        ChoreCompletion::factory()->create([
            'chore_list_item_id' => $item->id,
            'user_id' => $user->id,
            'time_centipoints' => 700,
            'base_time_centipoints' => 100,
            'escalation_level' => 3,
            'difficulty_centipoints' => 100,
            'counts_toward_reward' => true,
            'completed_at' => Carbon::parse('2026-05-10'),
        ]);

        $result = (new CalculateMonthlyRewardSummary)(Carbon::parse('2026-05-01'));

        $line = collect($result['breakdown'])->firstWhere('user_id', $user->id)['receipt'][0];
        $this->assertEquals(1, $line['base_time_points']);
        $this->assertSame(3, $line['escalation_level']);
        $this->assertEquals(6, $line['escalation_bonus_points']);
        $this->assertEquals(7, $line['time_points']);
    }

    public function test_receipt_shows_no_escalation_bonus_when_none_accrued(): void
    {
        $user = User::factory()->create();
        $item = ChoreListItem::factory()->create();
        ChoreCompletion::factory()->create([
            'chore_list_item_id' => $item->id,
            'user_id' => $user->id,
            'time_centipoints' => 300,
            'base_time_centipoints' => 300,
            'escalation_level' => 0,
            'difficulty_centipoints' => 100,
            'counts_toward_reward' => true,
            'completed_at' => Carbon::parse('2026-05-10'),
        ]);

        $result = (new CalculateMonthlyRewardSummary)(Carbon::parse('2026-05-01'));

        $line = collect($result['breakdown'])->firstWhere('user_id', $user->id)['receipt'][0];
        $this->assertEquals(0, $line['escalation_bonus_points']);
    }

    public function test_escalation_bonus_reconciles_exactly_when_split_between_two_people(): void
    {
        // Chore worth (base 3 + escalation 4 = 7) total, split between two
        // people (via ToggleChoreListItemCompletion's own division), each
        // getting time=3, base=1 — the escalation bonus for each person's
        // line must still equal exactly their own (time - base), with no
        // rounding drift from dividing the base and bonus separately.
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $chore = Chore::factory()->create(['time_points' => 3, 'escalation_increment' => 4, 'escalation_cap' => null]);
        $list = ChoreList::factory()->create(['repeat_type' => 'daily', 'repeat_value' => 1, 'repeat_start_date' => '2026-05-01']);
        $item = ChoreListItem::factory()->create([
            'chore_list_id' => $list->id,
            'chore_id' => $chore->id,
            'is_checked' => false,
            'escalation_level' => 1, // 3 + 1*4 = 7
        ]);
        $item->users()->attach([$userA->id, $userB->id]);

        (new ToggleChoreListItemCompletion)($item, $userA->id);

        $result = (new CalculateMonthlyRewardSummary)(Carbon::parse(now()->format('Y-m-01')));

        foreach ([$userA, $userB] as $user) {
            $line = collect($result['breakdown'])->firstWhere('user_id', $user->id)['receipt'][0];
            $this->assertEquals($line['time_points'] - $line['base_time_points'], $line['escalation_bonus_points']);
        }
    }
}
