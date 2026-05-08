<?php

namespace Tests\Feature\Settings;

use App\Models\NotificationChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationChannelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifications_page_is_displayed(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('app-settings.notifications'))->assertOk();
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $this->get(route('app-settings.notifications'))->assertRedirect(route('login'));
    }

    public function test_can_create_discord_channel(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::settings-global.notifications')
            ->call('openForm')
            ->set('type', 'discord')
            ->set('label', 'My Discord')
            ->set('config.webhook_url', 'https://discord.com/api/webhooks/123/abc')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('notification_channels', [
            'type' => 'discord',
            'label' => 'My Discord',
        ]);
    }

    public function test_can_create_telegram_channel(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::settings-global.notifications')
            ->call('openForm')
            ->set('type', 'telegram')
            ->set('label', 'My Telegram')
            ->set('config.bot_token', '123456:ABC-DEF')
            ->set('config.chat_id', '-100123456')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('notification_channels', [
            'type' => 'telegram',
            'label' => 'My Telegram',
        ]);
    }

    public function test_can_create_email_channel(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::settings-global.notifications')
            ->call('openForm')
            ->set('type', 'email')
            ->set('label', 'Personal Email')
            ->set('config.address', 'test@example.com')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('notification_channels', [
            'type' => 'email',
            'label' => 'Personal Email',
        ]);
    }

    public function test_can_create_signal_channel(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::settings-global.notifications')
            ->call('openForm')
            ->set('type', 'signal')
            ->set('label', 'My Signal')
            ->set('config.phone_number', '+31612345678')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('notification_channels', [
            'type' => 'signal',
            'label' => 'My Signal',
        ]);
    }

    public function test_can_create_ntfy_channel(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::settings-global.notifications')
            ->call('openForm')
            ->set('type', 'ntfy')
            ->set('label', 'My Ntfy')
            ->set('config.server_url', 'https://ntfy.sh')
            ->set('config.topic', 'my-topic')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('notification_channels', [
            'type' => 'ntfy',
            'label' => 'My Ntfy',
        ]);
    }

    public function test_validation_requires_label(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::settings-global.notifications')
            ->call('openForm')
            ->set('type', 'discord')
            ->set('label', '')
            ->set('config.webhook_url', 'https://discord.com/api/webhooks/123/abc')
            ->call('save')
            ->assertHasErrors(['label']);
    }

    public function test_validation_requires_config_fields(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::settings-global.notifications')
            ->call('openForm')
            ->set('type', 'discord')
            ->set('label', 'My Discord')
            ->call('save')
            ->assertHasErrors(['config.webhook_url']);
    }

    public function test_can_edit_channel(): void
    {
        $this->actingAs(User::factory()->create());

        $channel = NotificationChannel::factory()->create([
            'type' => 'discord',
            'label' => 'Old Label',
            'config' => ['webhook_url' => 'https://discord.com/old'],
        ]);

        Livewire::test('pages::settings-global.notifications')
            ->call('edit', $channel->id)
            ->set('label', 'New Label')
            ->set('config.webhook_url', 'https://discord.com/new')
            ->call('save')
            ->assertHasNoErrors();

        $channel->refresh();
        $this->assertEquals('New Label', $channel->label);
        $this->assertEquals('https://discord.com/new', $channel->config['webhook_url']);
    }

    public function test_can_delete_channel(): void
    {
        $this->actingAs(User::factory()->create());

        $channel = NotificationChannel::factory()->create();

        Livewire::test('pages::settings-global.notifications')
            ->call('delete', $channel->id);

        $this->assertDatabaseMissing('notification_channels', ['id' => $channel->id]);
    }

    public function test_can_toggle_enabled(): void
    {
        $this->actingAs(User::factory()->create());

        $channel = NotificationChannel::factory()->create(['enabled' => true]);

        Livewire::test('pages::settings-global.notifications')
            ->call('toggleEnabled', $channel->id);

        $this->assertFalse($channel->refresh()->enabled);
    }

    public function test_can_create_multiple_channels_of_same_type(): void
    {
        $this->actingAs(User::factory()->create());

        NotificationChannel::factory()->create(['type' => 'discord', 'label' => 'Server 1']);

        Livewire::test('pages::settings-global.notifications')
            ->call('openForm')
            ->set('type', 'discord')
            ->set('label', 'Server 2')
            ->set('config.webhook_url', 'https://discord.com/api/webhooks/456/def')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals(2, NotificationChannel::where('type', 'discord')->count());
    }
}
