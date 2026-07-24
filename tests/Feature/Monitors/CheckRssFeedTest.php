<?php

namespace Tests\Feature\Monitors;

use App\Actions\Monitors\CheckMonitor;
use App\Actions\Notifications\SendNotification;
use App\Models\Monitor;
use App\Models\NotificationChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckRssFeedTest extends TestCase
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

    private function rss(string ...$guids): string
    {
        $items = '';
        foreach ($guids as $guid) {
            $items .= "<item><title>Item {$guid}</title><link>https://example.com/{$guid}</link><guid>{$guid}</guid></item>";
        }

        return "<?xml version=\"1.0\"?><rss version=\"2.0\"><channel><title>Test Feed</title>{$items}</channel></rss>";
    }

    private function atom(string ...$ids): string
    {
        $entries = '';
        foreach ($ids as $id) {
            $entries .= "<entry><id>{$id}</id><title>Entry {$id}</title><link rel=\"alternate\" href=\"https://example.com/{$id}\"/></entry>";
        }

        return "<?xml version=\"1.0\"?><feed xmlns=\"http://www.w3.org/2005/Atom\"><title>Test Feed</title>{$entries}</feed>";
    }

    private function monitor(?string $lastSeenGuid = null): Monitor
    {
        $monitor = Monitor::factory()->create([
            'url' => 'https://example.com/feed.rss',
            'check_type' => Monitor::CHECK_RSS_FEED,
            'check_config' => $lastSeenGuid ? ['last_seen_guid' => $lastSeenGuid] : [],
            'notify_on' => Monitor::NOTIFY_ON_APPEARANCE,
            'last_matched' => null,
        ]);

        $monitor->notificationChannels()->attach(NotificationChannel::factory()->create()->id);

        return $monitor;
    }

    public function test_first_run_stores_latest_guid_and_sends_no_notification(): void
    {
        Http::fake(['example.com/*' => Http::response($this->rss('item-1', 'item-2'), 200)]);
        $spy = $this->spySender();

        $monitor = $this->monitor();

        $result = app(CheckMonitor::class)($monitor);

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['matched']);
        $this->assertFalse($result['notified']);
        $this->assertCount(0, $spy->calls);
        $this->assertEquals('item-1', $monitor->refresh()->check_config['last_seen_guid']);
    }

    public function test_new_item_triggers_notification(): void
    {
        Http::fake(['example.com/*' => Http::response($this->rss('item-2', 'item-1'), 200)]);
        $spy = $this->spySender();

        $monitor = $this->monitor('item-1');

        $result = app(CheckMonitor::class)($monitor);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['matched']);
        $this->assertTrue($result['notified']);
        $this->assertCount(1, $spy->calls);
        $this->assertStringContainsString('item-2', $spy->calls[0]['payload']['title']);
        $this->assertEquals('item-2', $monitor->refresh()->check_config['last_seen_guid']);
    }

    public function test_no_new_items_sends_no_notification(): void
    {
        Http::fake(['example.com/*' => Http::response($this->rss('item-1', 'item-2'), 200)]);
        $spy = $this->spySender();

        $monitor = $this->monitor('item-1');

        $result = app(CheckMonitor::class)($monitor);

        $this->assertFalse($result['matched']);
        $this->assertFalse($result['notified']);
        $this->assertCount(0, $spy->calls);
    }

    public function test_multiple_new_items_send_one_notification_each(): void
    {
        Http::fake(['example.com/*' => Http::response($this->rss('item-3', 'item-2', 'item-1'), 200)]);
        $spy = $this->spySender();

        $monitor = $this->monitor('item-1');

        $result = app(CheckMonitor::class)($monitor);

        $this->assertEquals(2, $result['needle_positions']);
        $this->assertCount(2, $spy->calls);
        $this->assertEquals('item-3', $monitor->refresh()->check_config['last_seen_guid']);
    }

    public function test_invalid_xml_records_failure_and_returns_error(): void
    {
        Http::fake(['example.com/*' => Http::response('not xml at all', 200)]);
        $spy = $this->spySender();

        $monitor = $this->monitor();

        $result = app(CheckMonitor::class)($monitor);

        $this->assertFalse($result['ok']);
        $this->assertNotNull($result['error']);
        $this->assertEquals(1, $monitor->refresh()->consecutive_failures);
        $this->assertCount(0, $spy->calls);
    }

    public function test_atom_feed_is_parsed_correctly(): void
    {
        Http::fake(['example.com/*' => Http::response($this->atom('entry-2', 'entry-1'), 200)]);
        $spy = $this->spySender();

        $monitor = $this->monitor('entry-1');

        $result = app(CheckMonitor::class)($monitor);

        $this->assertTrue($result['matched']);
        $this->assertCount(1, $spy->calls);
        $this->assertStringContainsString('entry-2', $spy->calls[0]['payload']['title']);
        $this->assertEquals('entry-2', $monitor->refresh()->check_config['last_seen_guid']);
    }

    public function test_request_failure_records_error(): void
    {
        Http::fake(function () {
            throw new \RuntimeException('Connection refused');
        });
        $this->spySender();

        $monitor = $this->monitor();

        $result = app(CheckMonitor::class)($monitor);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Connection refused', $result['error'] ?? '');
        $this->assertEquals(1, $monitor->refresh()->consecutive_failures);
    }
}
