<?php

namespace Tests\Feature\Chores;

use App\Models\ChoreDayBonus;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ChoreDayBonusTest extends TestCase
{
    use RefreshDatabase;

    public function test_level_for_returns_null_with_no_flag(): void
    {
        $user = User::factory()->create();

        $this->assertNull(ChoreDayBonus::levelFor($user->id, now()));
    }

    public function test_level_for_returns_the_flagged_level(): void
    {
        $user = User::factory()->create();
        ChoreDayBonus::factory()->create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'level' => ChoreDayBonus::LEVEL_SUPER_BAD,
        ]);

        $this->assertSame(ChoreDayBonus::LEVEL_SUPER_BAD, ChoreDayBonus::levelFor($user->id, now()));
    }

    public function test_level_for_is_isolated_per_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        ChoreDayBonus::factory()->create([
            'user_id' => $userA->id,
            'date' => now()->toDateString(),
            'level' => ChoreDayBonus::LEVEL_BAD,
        ]);

        $this->assertNull(ChoreDayBonus::levelFor($userB->id, now()));
    }

    public function test_level_for_is_isolated_per_day(): void
    {
        $user = User::factory()->create();
        ChoreDayBonus::factory()->create([
            'user_id' => $user->id,
            'date' => now()->subDay()->toDateString(),
            'level' => ChoreDayBonus::LEVEL_BAD,
        ]);

        $this->assertNull(ChoreDayBonus::levelFor($user->id, now()));
    }

    public function test_can_set_own_day_bonus(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test('pages::chores.index')
            ->call('setDayBonus', ChoreDayBonus::LEVEL_BAD);

        $this->assertSame(ChoreDayBonus::LEVEL_BAD, ChoreDayBonus::levelFor($user->id, now()));
    }

    public function test_can_update_own_day_bonus(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test('pages::chores.index')
            ->call('setDayBonus', ChoreDayBonus::LEVEL_BAD)
            ->call('setDayBonus', ChoreDayBonus::LEVEL_SUPER_BAD);

        $this->assertSame(1, ChoreDayBonus::where('user_id', $user->id)->count());
        $this->assertSame(ChoreDayBonus::LEVEL_SUPER_BAD, ChoreDayBonus::levelFor($user->id, now()));
    }

    public function test_setting_neutral_clears_the_flag(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        ChoreDayBonus::factory()->create(['user_id' => $user->id, 'date' => now()->toDateString(), 'level' => ChoreDayBonus::LEVEL_BAD]);

        Livewire::test('pages::chores.index')
            ->call('setDayBonus', null);

        $this->assertDatabaseMissing('chore_day_bonuses', ['user_id' => $user->id]);
    }

    public function test_my_day_bonus_level_reflects_only_the_logged_in_users_flag(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->actingAs($user);
        ChoreDayBonus::factory()->create(['user_id' => $otherUser->id, 'date' => now()->toDateString(), 'level' => ChoreDayBonus::LEVEL_SUPER_BAD]);

        $component = Livewire::test('pages::chores.index');

        $this->assertNull($component->instance()->myDayBonusLevel());
    }

    public function test_set_level_creates_a_flag_for_a_past_date(): void
    {
        $user = User::factory()->create();

        ChoreDayBonus::setLevel($user->id, '2026-05-05', ChoreDayBonus::LEVEL_BAD);

        $this->assertSame(ChoreDayBonus::LEVEL_BAD, ChoreDayBonus::levelFor($user->id, Carbon::parse('2026-05-05')));
    }

    public function test_set_level_can_set_someone_elses_flag(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        ChoreDayBonus::setLevel($userB->id, now()->toDateString(), ChoreDayBonus::LEVEL_SUPER_BAD);

        $this->assertNull(ChoreDayBonus::levelFor($userA->id, now()));
        $this->assertSame(ChoreDayBonus::LEVEL_SUPER_BAD, ChoreDayBonus::levelFor($userB->id, now()));
    }

    public function test_set_level_updates_an_existing_flag_without_creating_a_duplicate(): void
    {
        $user = User::factory()->create();
        ChoreDayBonus::setLevel($user->id, '2026-05-05', ChoreDayBonus::LEVEL_BAD);

        ChoreDayBonus::setLevel($user->id, '2026-05-05', ChoreDayBonus::LEVEL_SUPER_BAD);

        $this->assertSame(1, ChoreDayBonus::where('user_id', $user->id)->count());
        $this->assertSame(ChoreDayBonus::LEVEL_SUPER_BAD, ChoreDayBonus::levelFor($user->id, Carbon::parse('2026-05-05')));
    }

    public function test_set_level_null_clears_a_past_flag(): void
    {
        $user = User::factory()->create();
        ChoreDayBonus::setLevel($user->id, '2026-05-05', ChoreDayBonus::LEVEL_BAD);

        ChoreDayBonus::setLevel($user->id, '2026-05-05', null);

        $this->assertDatabaseMissing('chore_day_bonuses', ['user_id' => $user->id]);
    }
}
