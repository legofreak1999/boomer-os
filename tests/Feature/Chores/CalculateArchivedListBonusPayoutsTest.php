<?php

namespace Tests\Feature\Chores;

use App\Actions\Chores\CalculateArchivedListBonusPayouts;
use App\Models\ChoreList;
use App\Models\ChoreListBonusPayout;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalculateArchivedListBonusPayoutsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reads_back_the_persisted_payout_for_a_list_archived_in_the_month(): void
    {
        $user = User::factory()->create(['name' => 'Amber']);
        $list = ChoreList::factory()->create([
            'repeat_type' => null,
            'name' => 'Spring Cleaning',
            'bonus_cents' => 3000,
            'archived_at' => '2026-05-20',
        ]);
        ChoreListBonusPayout::factory()->create([
            'chore_list_id' => $list->id,
            'user_id' => $user->id,
            'weight_centipoints' => 400,
            'share_cents' => 3000,
        ]);

        $result = (new CalculateArchivedListBonusPayouts)(Carbon::parse('2026-05-01'));

        $this->assertCount(1, $result);
        $payout = $result[0];
        $this->assertSame($list->id, $payout['list_id']);
        $this->assertSame('Spring Cleaning', $payout['list_name']);
        $this->assertSame(3000, $payout['bonus_cents']);
        $this->assertSame(4, $payout['total_weight']);
        $this->assertSame(['user_id' => $user->id, 'name' => 'Amber', 'weight' => 4, 'share_cents' => 3000], $payout['shares'][0]);
    }

    public function test_a_list_with_no_payout_rows_yields_empty_shares_with_no_division_by_zero(): void
    {
        $list = ChoreList::factory()->create(['repeat_type' => null, 'bonus_cents' => 1000, 'archived_at' => '2026-05-20']);

        $result = (new CalculateArchivedListBonusPayouts)(Carbon::parse('2026-05-01'));

        $this->assertCount(1, $result);
        $this->assertSame(0, $result[0]['total_weight']);
        $this->assertSame([], $result[0]['shares']);
    }

    public function test_ignores_lists_without_a_bonus(): void
    {
        ChoreList::factory()->create(['repeat_type' => null, 'bonus_cents' => null, 'archived_at' => '2026-05-20']);

        $result = (new CalculateArchivedListBonusPayouts)(Carbon::parse('2026-05-01'));

        $this->assertSame([], $result);
    }

    public function test_ignores_lists_that_are_not_archived_yet(): void
    {
        ChoreList::factory()->create(['repeat_type' => null, 'bonus_cents' => 1000, 'archived_at' => null]);

        $result = (new CalculateArchivedListBonusPayouts)(Carbon::parse('2026-05-01'));

        $this->assertSame([], $result);
    }

    public function test_only_shows_a_payout_under_the_month_it_was_archived_in(): void
    {
        $list = ChoreList::factory()->create(['repeat_type' => null, 'bonus_cents' => 1000, 'archived_at' => '2026-06-01']);
        ChoreListBonusPayout::factory()->create(['chore_list_id' => $list->id]);

        $result = (new CalculateArchivedListBonusPayouts)(Carbon::parse('2026-05-01'));

        $this->assertSame([], $result);
    }
}
