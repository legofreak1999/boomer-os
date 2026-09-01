<?php

namespace Tests\Feature\Chores;

use App\Actions\Chores\CalculateListBonusShares;
use App\Models\AppSetting;
use App\Models\ChoreCompletion;
use App\Models\ChoreDayBonus;
use App\Models\ChoreList;
use App\Models\ChoreListItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalculateListBonusSharesTest extends TestCase
{
    use RefreshDatabase;

    public function test_splits_the_bonus_proportionally_by_weight(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $list = ChoreList::factory()->create(['repeat_type' => null, 'bonus_cents' => 3000]);
        $itemA = ChoreListItem::factory()->create(['chore_list_id' => $list->id]);
        $itemB = ChoreListItem::factory()->create(['chore_list_id' => $list->id]);

        // userA weight = 300 centipoints, userB weight = 100 centipoints -> 3:1 split.
        ChoreCompletion::factory()->create([
            'chore_list_item_id' => $itemA->id,
            'user_id' => $userA->id,
            'time_centipoints' => 200,
            'difficulty_centipoints' => 100,
            'completed_at' => Carbon::parse('2026-05-15'),
        ]);
        ChoreCompletion::factory()->create([
            'chore_list_item_id' => $itemB->id,
            'user_id' => $userB->id,
            'time_centipoints' => 50,
            'difficulty_centipoints' => 50,
            'completed_at' => Carbon::parse('2026-05-18'),
        ]);

        $shares = (new CalculateListBonusShares)($list->fresh(['items.completions']));

        $shareA = collect($shares)->firstWhere('user_id', $userA->id);
        $shareB = collect($shares)->firstWhere('user_id', $userB->id);
        $this->assertSame(2250, $shareA['share_cents']); // 3000 * 300/400
        $this->assertSame(750, $shareB['share_cents']); // 3000 * 100/400
    }

    public function test_a_single_contributor_gets_the_full_bonus(): void
    {
        $user = User::factory()->create();
        $list = ChoreList::factory()->create(['repeat_type' => null, 'bonus_cents' => 1000]);
        $item = ChoreListItem::factory()->create(['chore_list_id' => $list->id]);
        ChoreCompletion::factory()->create([
            'chore_list_item_id' => $item->id,
            'user_id' => $user->id,
            'completed_at' => '2026-05-10',
        ]);

        $shares = (new CalculateListBonusShares)($list->fresh(['items.completions']));

        $this->assertSame(1000, $shares[0]['share_cents']);
    }

    public function test_a_list_with_no_completions_yields_no_shares_with_no_division_by_zero(): void
    {
        $list = ChoreList::factory()->create(['repeat_type' => null, 'bonus_cents' => 1000]);
        ChoreListItem::factory()->create(['chore_list_id' => $list->id]);

        $shares = (new CalculateListBonusShares)($list->fresh(['items.completions']));

        $this->assertSame([], $shares);
    }

    public function test_includes_completions_from_long_before_the_list_was_completed(): void
    {
        // The list stayed open across months before being completed — every
        // completion behind it counts toward the split, regardless of age.
        $user = User::factory()->create();
        $list = ChoreList::factory()->create(['repeat_type' => null, 'bonus_cents' => 1000]);
        $item = ChoreListItem::factory()->create(['chore_list_id' => $list->id]);
        ChoreCompletion::factory()->create([
            'chore_list_item_id' => $item->id,
            'user_id' => $user->id,
            'completed_at' => '2026-04-15',
        ]);

        $shares = (new CalculateListBonusShares)($list->fresh(['items.completions']));

        $this->assertCount(1, $shares);
        $this->assertSame(1000, $shares[0]['share_cents']);
    }

    public function test_applies_the_day_bonus_multiplier_regardless_of_when_the_completion_happened(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $list = ChoreList::factory()->create(['repeat_type' => null, 'bonus_cents' => 1000]);
        $itemA = ChoreListItem::factory()->create(['chore_list_id' => $list->id]);
        $itemB = ChoreListItem::factory()->create(['chore_list_id' => $list->id]);

        ChoreDayBonus::factory()->create(['user_id' => $userA->id, 'date' => '2026-04-15', 'level' => ChoreDayBonus::LEVEL_BAD]);
        ChoreCompletion::factory()->create([
            'chore_list_item_id' => $itemA->id,
            'user_id' => $userA->id,
            'time_centipoints' => 0,
            'difficulty_centipoints' => 100,
            'completed_at' => '2026-04-15',
        ]);
        ChoreCompletion::factory()->create([
            'chore_list_item_id' => $itemB->id,
            'user_id' => $userB->id,
            'time_centipoints' => 0,
            'difficulty_centipoints' => 100,
            'completed_at' => '2026-04-16',
        ]);

        $shares = (new CalculateListBonusShares)($list->fresh(['items.completions']));

        // userA's difficulty is doubled by the bad-day flag (200 vs 100),
        // so a 2:1 weight ratio should show in the split.
        $shareA = collect($shares)->firstWhere('user_id', $userA->id);
        $shareB = collect($shares)->firstWhere('user_id', $userB->id);
        $this->assertSame(667, $shareA['share_cents']); // 1000 * 200/300, rounded
        $this->assertSame(333, $shareB['share_cents']); // 1000 * 100/300, rounded
    }

    public function test_a_zero_multiplier_setting_is_clamped_to_one(): void
    {
        AppSetting::set('chore_reward_settings', ['bad_day_multiplier' => 0]);

        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $list = ChoreList::factory()->create(['repeat_type' => null, 'bonus_cents' => 1000]);
        $itemA = ChoreListItem::factory()->create(['chore_list_id' => $list->id]);
        $itemB = ChoreListItem::factory()->create(['chore_list_id' => $list->id]);

        ChoreDayBonus::factory()->create(['user_id' => $userA->id, 'date' => '2026-05-10', 'level' => ChoreDayBonus::LEVEL_BAD]);
        ChoreCompletion::factory()->create([
            'chore_list_item_id' => $itemA->id,
            'user_id' => $userA->id,
            'time_centipoints' => 0,
            'difficulty_centipoints' => 100,
            'completed_at' => '2026-05-10',
        ]);
        ChoreCompletion::factory()->create([
            'chore_list_item_id' => $itemB->id,
            'user_id' => $userB->id,
            'time_centipoints' => 0,
            'difficulty_centipoints' => 100,
            'completed_at' => '2026-05-11',
        ]);

        $shares = (new CalculateListBonusShares)($list->fresh(['items.completions']));

        // If the 0 multiplier weren't clamped, userA's weight would be
        // wiped out (100 * 0) and userB would take the whole bonus.
        $shareA = collect($shares)->firstWhere('user_id', $userA->id);
        $shareB = collect($shares)->firstWhere('user_id', $userB->id);
        $this->assertSame(500, $shareA['share_cents']);
        $this->assertSame(500, $shareB['share_cents']);
    }
}
