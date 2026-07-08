<?php

namespace Tests\Feature\Monitors;

use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MonitorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_is_displayed(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('monitors.index'))->assertOk();
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $this->get(route('monitors.index'))->assertRedirect(route('login'));
    }

    public function test_can_create_a_text_contains_monitor(): void
    {
        $this->actingAs(User::factory()->create());
        $channel = NotificationChannel::factory()->create();

        Livewire::test('pages::monitors.edit')
            ->set('label', 'Product X')
            ->set('url', 'https://example.com/product')
            ->set('interval_minutes', 30)
            ->set('check_type', Monitor::CHECK_TEXT_CONTAINS)
            ->set('check_config.needle', 'Out of stock')
            ->set('notify_on', Monitor::NOTIFY_ON_BOTH)
            ->set('notification_channel_ids', [$channel->id])
            ->call('save')
            ->assertHasNoErrors();

        $monitor = Monitor::where('label', 'Product X')->first();
        $this->assertNotNull($monitor);
        $this->assertEquals('https://example.com/product', $monitor->url);
        $this->assertEquals(['needle' => 'Out of stock', 'case_sensitive' => false], $monitor->check_config);
        $this->assertTrue($monitor->notificationChannels()->where('notification_channels.id', $channel->id)->exists());
    }

    public function test_validation_requires_label_and_url(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::monitors.edit')
            ->set('label', '')
            ->set('url', 'not-a-url')
            ->set('check_config.needle', 'x')
            ->call('save')
            ->assertHasErrors(['label', 'url']);
    }

    public function test_can_edit_a_monitor(): void
    {
        $this->actingAs(User::factory()->create());
        $monitor = Monitor::factory()->create(['label' => 'Old']);

        Livewire::test('pages::monitors.edit', ['monitor' => $monitor])
            ->set('label', 'New')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals('New', $monitor->fresh()->label);
    }

    public function test_can_delete_a_monitor_from_index(): void
    {
        $this->actingAs(User::factory()->create());
        $monitor = Monitor::factory()->create();

        Livewire::test('pages::monitors.index')->call('delete', $monitor->id);

        $this->assertDatabaseMissing('monitors', ['id' => $monitor->id]);
    }

    public function test_can_toggle_enabled_from_index(): void
    {
        $this->actingAs(User::factory()->create());
        $monitor = Monitor::factory()->create(['enabled' => true]);

        Livewire::test('pages::monitors.index')->call('toggleEnabled', $monitor->id);

        $this->assertFalse($monitor->fresh()->enabled);
    }
}
