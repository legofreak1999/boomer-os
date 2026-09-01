<?php

namespace Tests\Feature\Chores;

use App\Models\Chore;
use App\Models\ChoreList;
use App\Models\ChoreListItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResetChoreListsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_repeat_resets_on_correct_day(): void
    {
        $list = ChoreList::factory()->create([
            'repeat_type' => 'daily',
            'repeat_value' => 3,
            'repeat_start_date' => '2026-05-01',
            'is_hidden' => true,
        ]);
        $item = ChoreListItem::factory()->create(['chore_list_id' => $list->id, 'is_checked' => true]);
        $item->users()->attach(User::factory()->create()->id);

        // Day 3 after start (May 4) should reset
        Carbon::setTestNow(Carbon::parse('2026-05-04', 'Europe/Amsterdam'));
        $this->artisan('chores:reset')->assertSuccessful();

        $this->assertFalse($list->refresh()->is_hidden);
        $this->assertFalse($item->refresh()->is_checked);
    }

    public function test_daily_repeat_does_not_reset_on_wrong_day(): void
    {
        $list = ChoreList::factory()->create([
            'repeat_type' => 'daily',
            'repeat_value' => 3,
            'repeat_start_date' => '2026-05-01',
            'is_hidden' => true,
        ]);

        // Day 2 after start (May 3) should NOT reset
        Carbon::setTestNow(Carbon::parse('2026-05-03', 'Europe/Amsterdam'));
        $this->artisan('chores:reset')->assertSuccessful();

        $this->assertTrue($list->refresh()->is_hidden);
    }

    public function test_weekly_repeat_resets_on_correct_weekday(): void
    {
        $list = ChoreList::factory()->create([
            'repeat_type' => 'weekly',
            'repeat_value' => 3, // Wednesday
            'repeat_start_date' => '2026-05-01',
            'is_hidden' => true,
        ]);
        $item = ChoreListItem::factory()->create(['chore_list_id' => $list->id, 'is_checked' => true]);
        $item->users()->attach(User::factory()->create()->id);

        // 2026-05-06 is a Wednesday
        Carbon::setTestNow(Carbon::parse('2026-05-06', 'Europe/Amsterdam'));
        $this->artisan('chores:reset')->assertSuccessful();

        $this->assertFalse($list->refresh()->is_hidden);
        $this->assertFalse($item->refresh()->is_checked);
    }

    public function test_monthly_day_resets_on_correct_day(): void
    {
        $list = ChoreList::factory()->create([
            'repeat_type' => 'monthly_day',
            'repeat_value' => 15,
            'repeat_start_date' => '2026-05-01',
            'is_hidden' => true,
        ]);
        $item = ChoreListItem::factory()->create(['chore_list_id' => $list->id, 'is_checked' => true]);
        $item->users()->attach(User::factory()->create()->id);

        Carbon::setTestNow(Carbon::parse('2026-06-15', 'Europe/Amsterdam'));
        $this->artisan('chores:reset')->assertSuccessful();

        $this->assertFalse($list->refresh()->is_hidden);
        $this->assertFalse($item->refresh()->is_checked);
    }

    public function test_monthly_last_resets_on_last_day_of_month(): void
    {
        $list = ChoreList::factory()->create([
            'repeat_type' => 'monthly_last',
            'repeat_value' => null,
            'repeat_start_date' => '2026-05-01',
            'is_hidden' => true,
        ]);
        $item = ChoreListItem::factory()->create(['chore_list_id' => $list->id, 'is_checked' => true]);
        $item->users()->attach(User::factory()->create()->id);

        // May 31 is last day of May
        Carbon::setTestNow(Carbon::parse('2026-05-31', 'Europe/Amsterdam'));
        $this->artisan('chores:reset')->assertSuccessful();

        $this->assertFalse($list->refresh()->is_hidden);
        $this->assertFalse($item->refresh()->is_checked);
    }

    public function test_incomplete_non_hidden_repeating_list_also_resets(): void
    {
        $list = ChoreList::factory()->create([
            'repeat_type' => 'weekly',
            'repeat_value' => 1, // Monday
            'repeat_start_date' => '2026-05-01',
            'is_hidden' => false,
        ]);
        $item = ChoreListItem::factory()->create(['chore_list_id' => $list->id, 'is_checked' => true]);
        $item->users()->attach(User::factory()->create()->id);

        // 2026-05-04 is a Monday
        Carbon::setTestNow(Carbon::parse('2026-05-04', 'Europe/Amsterdam'));
        $this->artisan('chores:reset')->assertSuccessful();

        $this->assertFalse($list->refresh()->is_hidden);
        $this->assertFalse($item->refresh()->is_checked);
    }

    public function test_non_repeating_lists_are_not_affected(): void
    {
        $list = ChoreList::factory()->create([
            'repeat_type' => null,
            'is_hidden' => true,
        ]);
        $item = ChoreListItem::factory()->create(['chore_list_id' => $list->id, 'is_checked' => true]);

        Carbon::setTestNow(Carbon::parse('2026-05-08', 'Europe/Amsterdam'));
        $this->artisan('chores:reset')->assertSuccessful();

        $this->assertTrue($list->refresh()->is_hidden);
        $this->assertTrue($item->refresh()->is_checked);
    }

    public function test_unclaimed_item_is_not_reset_so_it_stays_visible_to_claim(): void
    {
        $list = ChoreList::factory()->create([
            'repeat_type' => 'daily',
            'repeat_value' => 3,
            'repeat_start_date' => '2026-05-01',
            'is_hidden' => true,
        ]);
        // Checked, but nobody assigned — no completion recorded it either.
        $item = ChoreListItem::factory()->create(['chore_list_id' => $list->id, 'is_checked' => true]);

        Carbon::setTestNow(Carbon::parse('2026-05-04', 'Europe/Amsterdam'));
        $this->artisan('chores:reset')->assertSuccessful();

        // The list itself still unhides and becomes due again...
        $this->assertFalse($list->refresh()->is_hidden);
        // ...but the uncredited item is left alone rather than silently
        // wiped, since resetting it would erase the only sign it was ever
        // done with no way to claim it afterward.
        $this->assertTrue($item->refresh()->is_checked);
    }

    public function test_claimed_item_still_resets_normally(): void
    {
        $list = ChoreList::factory()->create([
            'repeat_type' => 'daily',
            'repeat_value' => 3,
            'repeat_start_date' => '2026-05-01',
            'is_hidden' => true,
        ]);
        $item = ChoreListItem::factory()->create(['chore_list_id' => $list->id, 'is_checked' => true]);
        $item->users()->attach(User::factory()->create()->id);

        Carbon::setTestNow(Carbon::parse('2026-05-04', 'Europe/Amsterdam'));
        $this->artisan('chores:reset')->assertSuccessful();

        $this->assertFalse($item->refresh()->is_checked);
    }

    public function test_escalation_level_increments_on_missed_cycle(): void
    {
        $chore = Chore::factory()->create(['escalation_increment' => 2]);
        $list = ChoreList::factory()->create([
            'repeat_type' => 'daily',
            'repeat_value' => 1,
            'repeat_start_date' => '2026-05-01',
            'is_hidden' => false,
        ]);
        $item = ChoreListItem::factory()->create([
            'chore_list_id' => $list->id,
            'chore_id' => $chore->id,
            'is_checked' => false,
            'escalation_level' => 0,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-05-02', 'Europe/Amsterdam'));
        $this->artisan('chores:reset')->assertSuccessful();

        $this->assertSame(1, $item->refresh()->escalation_level);
    }

    public function test_escalation_does_not_increment_when_disabled_on_chore(): void
    {
        $chore = Chore::factory()->create(['escalation_increment' => 0]);
        $list = ChoreList::factory()->create([
            'repeat_type' => 'daily',
            'repeat_value' => 1,
            'repeat_start_date' => '2026-05-01',
            'is_hidden' => false,
        ]);
        $item = ChoreListItem::factory()->create([
            'chore_list_id' => $list->id,
            'chore_id' => $chore->id,
            'is_checked' => false,
            'escalation_level' => 0,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-05-02', 'Europe/Amsterdam'));
        $this->artisan('chores:reset')->assertSuccessful();

        $this->assertSame(0, $item->refresh()->escalation_level);
    }

    public function test_escalation_level_resets_to_zero_on_successful_completion_cycle(): void
    {
        $chore = Chore::factory()->create(['escalation_increment' => 2]);
        $list = ChoreList::factory()->create([
            'repeat_type' => 'daily',
            'repeat_value' => 1,
            'repeat_start_date' => '2026-05-01',
            'is_hidden' => false,
        ]);
        $item = ChoreListItem::factory()->create([
            'chore_list_id' => $list->id,
            'chore_id' => $chore->id,
            'is_checked' => true,
            'escalation_level' => 3,
        ]);
        $item->users()->attach(User::factory()->create()->id);

        Carbon::setTestNow(Carbon::parse('2026-05-02', 'Europe/Amsterdam'));
        $this->artisan('chores:reset')->assertSuccessful();

        $this->assertSame(0, $item->refresh()->escalation_level);
        $this->assertFalse($item->is_checked);
    }

    public function test_escalation_accumulates_across_multiple_missed_cycles(): void
    {
        $chore = Chore::factory()->create(['escalation_increment' => 1]);
        $list = ChoreList::factory()->create([
            'repeat_type' => 'daily',
            'repeat_value' => 1,
            'repeat_start_date' => '2026-05-01',
            'is_hidden' => false,
        ]);
        $item = ChoreListItem::factory()->create([
            'chore_list_id' => $list->id,
            'chore_id' => $chore->id,
            'is_checked' => false,
            'escalation_level' => 0,
        ]);

        foreach (['2026-05-02', '2026-05-03', '2026-05-04', '2026-05-05'] as $date) {
            Carbon::setTestNow(Carbon::parse($date, 'Europe/Amsterdam'));
            $this->artisan('chores:reset')->assertSuccessful();
        }

        $this->assertSame(4, $item->refresh()->escalation_level);
    }
}
