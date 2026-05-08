<?php

namespace Tests\Feature\Chores;

use App\Models\ChoreList;
use App\Models\ChoreListItem;
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

        // May 31 is last day of May
        Carbon::setTestNow(Carbon::parse('2026-05-31', 'Europe/Amsterdam'));
        $this->artisan('chores:reset')->assertSuccessful();

        $this->assertFalse($list->refresh()->is_hidden);
        $this->assertFalse($item->refresh()->is_checked);
    }

    public function test_yearly_resets_on_correct_date(): void
    {
        $list = ChoreList::factory()->create([
            'repeat_type' => 'yearly',
            'repeat_value' => 3, // March
            'repeat_start_date' => '2026-03-15', // Day 15
            'is_hidden' => true,
        ]);
        $item = ChoreListItem::factory()->create(['chore_list_id' => $list->id, 'is_checked' => true]);

        Carbon::setTestNow(Carbon::parse('2027-03-15', 'Europe/Amsterdam'));
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
}
