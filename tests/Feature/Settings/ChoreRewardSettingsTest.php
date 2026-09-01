<?php

namespace Tests\Feature\Settings;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ChoreRewardSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_is_displayed(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('app-settings.chore-rewards'))->assertOk();
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $this->get(route('app-settings.chore-rewards'))->assertRedirect(route('login'));
    }

    public function test_page_shows_default_values_when_unset(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::settings-global.chore-rewards')
            ->assertSet('floorPerPerson', '50.00')
            ->assertSet('bonusPool', '200.00')
            ->assertSet('bountyMax', '500.00')
            ->assertSet('badDayMultiplier', 2)
            ->assertSet('superBadDayMultiplier', 3);
    }

    public function test_can_save_new_values(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::settings-global.chore-rewards')
            ->set('floorPerPerson', '70')
            ->set('bonusPool', '150')
            ->set('bountyMax', '300')
            ->set('badDayMultiplier', 4)
            ->set('superBadDayMultiplier', 6)
            ->call('save');

        $settings = AppSetting::get('chore_reward_settings');
        $this->assertSame(7000, $settings['floor_per_person_cents']);
        $this->assertSame(15000, $settings['bonus_pool_cents']);
        $this->assertSame(30000, $settings['bounty_max_cents']);
        $this->assertSame(4, $settings['bad_day_multiplier']);
        $this->assertSame(6, $settings['super_bad_day_multiplier']);
    }

    public function test_multiplier_must_be_at_least_one(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::settings-global.chore-rewards')
            ->set('badDayMultiplier', 0)
            ->call('save')
            ->assertHasErrors(['badDayMultiplier']);
    }

    public function test_saved_values_are_reflected_on_reload(): void
    {
        $this->actingAs(User::factory()->create());

        AppSetting::set('chore_reward_settings', [
            'floor_per_person_cents' => 6000,
            'bonus_pool_cents' => 18000,
            'bounty_max_cents' => 40000,
        ]);

        Livewire::test('pages::settings-global.chore-rewards')
            ->assertSet('floorPerPerson', '60.00')
            ->assertSet('bonusPool', '180.00')
            ->assertSet('bountyMax', '400.00');
    }
}
