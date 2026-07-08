<?php

namespace Tests\Feature\Monitors;

use App\Actions\Monitors\CheckMonitor;
use App\Actions\Notifications\SendNotification;
use App\Models\Monitor;
use App\Models\NotificationChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckMonitorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function spySender(): SendNotification
    {
        $spy = new class extends SendNotification
        {
            /** @var array<int, array{channel: NotificationChannel, payload: array}> */
            public array $calls = [];

            public function __invoke(NotificationChannel $channel, array $payload): bool
            {
                $this->calls[] = ['channel' => $channel, 'payload' => $payload];

                return true;
            }
        };

        $this->app->instance(SendNotification::class, $spy);

        return $spy;
    }

    public function test_first_poll_establishes_baseline_and_does_not_notify(): void
    {
        Http::fake(['example.com/*' => Http::response('Out of stock', 200)]);
        $spy = $this->spySender();

        $monitor = Monitor::factory()->create([
            'url' => 'https://example.com/product',
            'check_type' => Monitor::CHECK_TEXT_CONTAINS,
            'check_config' => ['needle' => 'Out of stock'],
            'notify_on' => Monitor::NOTIFY_ON_BOTH,
            'last_matched' => null,
        ]);
        $monitor->notificationChannels()->attach(NotificationChannel::factory()->create()->id);

        app(CheckMonitor::class)($monitor);

        $this->assertTrue($monitor->refresh()->last_matched);
        $this->assertCount(0, $spy->calls);
    }

    public function test_state_transition_true_to_false_fires_notification(): void
    {
        Http::fake(['example.com/*' => Http::response('Back in stock now!', 200)]);
        $spy = $this->spySender();

        $monitor = Monitor::factory()->create([
            'url' => 'https://example.com/product',
            'check_type' => Monitor::CHECK_TEXT_CONTAINS,
            'check_config' => ['needle' => 'Out of stock'],
            'notify_on' => Monitor::NOTIFY_ON_BOTH,
            'last_matched' => true,
        ]);
        $monitor->notificationChannels()->attach(NotificationChannel::factory()->create()->id);

        app(CheckMonitor::class)($monitor);

        $this->assertFalse($monitor->refresh()->last_matched);
        $this->assertCount(1, $spy->calls);
        $this->assertStringContainsString('cleared', $spy->calls[0]['payload']['title']);
    }

    public function test_no_transition_does_not_notify(): void
    {
        Http::fake(['example.com/*' => Http::response('Out of stock', 200)]);
        $spy = $this->spySender();

        $monitor = Monitor::factory()->create([
            'url' => 'https://example.com/product',
            'check_type' => Monitor::CHECK_TEXT_CONTAINS,
            'check_config' => ['needle' => 'Out of stock'],
            'notify_on' => Monitor::NOTIFY_ON_BOTH,
            'last_matched' => true,
        ]);
        $monitor->notificationChannels()->attach(NotificationChannel::factory()->create()->id);

        app(CheckMonitor::class)($monitor);

        $this->assertCount(0, $spy->calls);
    }

    public function test_notify_on_disappearance_only_fires_true_to_false(): void
    {
        Http::fake(['example.com/*' => Http::response('Available', 200)]);
        $spy = $this->spySender();

        $monitor = Monitor::factory()->create([
            'url' => 'https://example.com/product',
            'check_type' => Monitor::CHECK_TEXT_CONTAINS,
            'check_config' => ['needle' => 'Out of stock'],
            'notify_on' => Monitor::NOTIFY_ON_DISAPPEARANCE,
            'last_matched' => false,
        ]);
        $monitor->notificationChannels()->attach(NotificationChannel::factory()->create()->id);

        app(CheckMonitor::class)($monitor);
        $this->assertCount(0, $spy->calls, 'false->false should not notify');

        $monitor->update(['last_matched' => true]);
        app(CheckMonitor::class)($monitor->fresh());
        $this->assertCount(1, $spy->calls, 'true->false should notify');
    }

    public function test_http_failure_increments_consecutive_failures(): void
    {
        Http::fake(['example.com/*' => Http::response('boom', 500)]);
        $spy = $this->spySender();

        $monitor = Monitor::factory()->create([
            'url' => 'https://example.com/product',
            'check_type' => Monitor::CHECK_HTTP_STATUS,
            'check_config' => ['expected_status' => 200],
            'notify_on' => Monitor::NOTIFY_ON_BOTH,
            'last_matched' => true,
        ]);
        $monitor->notificationChannels()->attach(NotificationChannel::factory()->create()->id);

        app(CheckMonitor::class)($monitor);

        $monitor->refresh();
        $this->assertFalse($monitor->last_matched);
        $this->assertNull($monitor->last_error);
        $this->assertEquals(0, $monitor->consecutive_failures);
        $this->assertCount(1, $spy->calls);
    }

    public function test_request_exception_records_last_error(): void
    {
        Http::fake(function () {
            throw new \RuntimeException('Connection refused');
        });
        $this->spySender();

        $monitor = Monitor::factory()->create([
            'url' => 'https://example.com/product',
            'check_type' => Monitor::CHECK_TEXT_CONTAINS,
            'check_config' => ['needle' => 'foo'],
        ]);

        app(CheckMonitor::class)($monitor);

        $monitor->refresh();
        $this->assertEquals(1, $monitor->consecutive_failures);
        $this->assertStringContainsString('Connection refused', $monitor->last_error ?? '');
    }
}
