<?php

namespace Tests\Feature\Settings;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AppSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_is_displayed(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('app-settings.index'))->assertOk();
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $this->get(route('app-settings.index'))->assertRedirect(route('login'));
    }

    public function test_can_toggle_feature_off(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::settings-global.index')
            ->call('toggleFeature', 'expenses');

        $features = AppSetting::get('sidebar_features');
        $this->assertFalse($features['expenses']);
    }

    public function test_can_toggle_feature_back_on(): void
    {
        $this->actingAs(User::factory()->create());

        AppSetting::set('sidebar_features', ['expenses' => false]);

        Livewire::test('pages::settings-global.index')
            ->call('toggleFeature', 'expenses');

        $features = AppSetting::get('sidebar_features');
        $this->assertTrue($features['expenses']);
    }

    public function test_sidebar_hides_feature_when_disabled(): void
    {
        $this->actingAs(User::factory()->create());

        AppSetting::set('sidebar_features', ['expenses' => false]);

        $response = $this->get(route('dashboard'));
        $response->assertDontSee('Expenses</');
    }

    public function test_sidebar_shows_feature_when_enabled(): void
    {
        $this->actingAs(User::factory()->create());

        AppSetting::set('sidebar_features', ['expenses' => true]);

        $response = $this->get(route('dashboard'));
        $response->assertSee('Expenses');
    }

    public function test_disabled_feature_routes_still_accessible(): void
    {
        $this->actingAs(User::factory()->create());

        AppSetting::set('sidebar_features', ['expenses' => false]);

        $this->get(route('expenses.index'))->assertOk();
    }

    public function test_settings_item_always_visible_in_sidebar(): void
    {
        $this->actingAs(User::factory()->create());

        AppSetting::set('sidebar_features', ['expenses' => false]);

        $response = $this->get(route('dashboard'));
        $response->assertSee('Settings');
    }
}
