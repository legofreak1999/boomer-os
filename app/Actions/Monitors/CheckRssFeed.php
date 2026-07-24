<?php

namespace App\Actions\Monitors;

use App\Actions\Notifications\SendNotification;
use App\Models\Monitor;
use App\Models\NotificationChannel;
use Illuminate\Support\Facades\Http;

class CheckRssFeed
{
    public function __construct(private SendNotification $sendNotification) {}

    /**
     * @return array{
     *   ok: bool,
     *   status: int|null,
     *   body_length: int,
     *   matched: bool|null,
     *   notified: bool,
     *   error: string|null,
     *   body_excerpt: string,
     *   needle_positions: int,
     * }
     */
    public function __invoke(Monitor $monitor): array
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders(['Accept' => 'application/rss+xml, application/atom+xml, application/xml, text/xml, */*'])
                ->get($monitor->url);
        } catch (\Throwable $e) {
            $this->recordFailure($monitor, $e->getMessage());

            return $this->result(ok: false, status: null, body: '', newItems: [], notified: false, error: $e->getMessage());
        }

        $body = $response->body();

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        libxml_clear_errors();

        if ($xml === false) {
            $error = 'Could not parse feed XML.';
            $this->recordFailure($monitor, $error);

            return $this->result(ok: false, status: $response->status(), body: $body, newItems: [], notified: false, error: $error);
        }

        $items = $this->extractItems($xml);
        $lastSeenGuid = $monitor->check_config['last_seen_guid'] ?? null;

        if ($lastSeenGuid === null) {
            $this->saveLastSeenGuid($monitor, $items[0]['guid'] ?? null);

            $monitor->update([
                'last_matched' => false,
                'last_polled_at' => now(),
                'last_error' => null,
                'consecutive_failures' => 0,
            ]);

            $excerpt = implode("\n", array_slice(array_column($items, 'title'), 0, 5));

            return $this->result(ok: true, status: $response->status(), body: $body, newItems: [], notified: false, error: null, excerpt: $excerpt);
        }

        $newItems = $this->itemsNewerThan($items, $lastSeenGuid);

        if (count($newItems) > 0) {
            $this->saveLastSeenGuid($monitor, $newItems[0]['guid']);
        }

        $monitor->update([
            'last_matched' => count($newItems) > 0,
            'last_polled_at' => now(),
            'last_error' => null,
            'consecutive_failures' => 0,
        ]);

        $notified = false;
        if (count($newItems) > 0) {
            $notified = $this->fireNotifications($monitor, $newItems);
        }

        $excerpt = count($newItems) > 0
            ? implode("\n", array_slice(array_column($newItems, 'title'), 0, 5))
            : implode("\n", array_slice(array_column($items, 'title'), 0, 5));

        return $this->result(ok: true, status: $response->status(), body: $body, newItems: $newItems, notified: $notified, error: null, excerpt: $excerpt);
    }

    /**
     * @param  array<int, array{guid: string, title: string, link: string}>  $items
     * @return array<int, array{guid: string, title: string, link: string}>
     */
    private function itemsNewerThan(array $items, string $lastSeenGuid): array
    {
        $new = [];
        foreach ($items as $item) {
            if ($item['guid'] === $lastSeenGuid) {
                break;
            }
            $new[] = $item;
        }

        return $new;
    }

    /**
     * @return array<int, array{guid: string, title: string, link: string}>
     */
    private function extractItems(\SimpleXMLElement $xml): array
    {
        // Atom feed: root element is <feed>
        if ($xml->getName() === 'feed') {
            return $this->extractAtomItems($xml);
        }

        // RSS 2.0: root element is <rss> or items live under <channel>
        $channel = $xml->channel ?? $xml;

        return $this->extractRssItems($channel);
    }

    /**
     * @return array<int, array{guid: string, title: string, link: string}>
     */
    private function extractRssItems(\SimpleXMLElement $channel): array
    {
        $items = [];
        foreach ($channel->item ?? [] as $item) {
            $guid = (string) ($item->guid ?? $item->link ?? $item->title ?? '');
            $title = (string) ($item->title ?? '');
            $link = (string) ($item->link ?? '');

            if ($guid !== '') {
                $items[] = ['guid' => $guid, 'title' => $title, 'link' => $link];
            }
        }

        return $items;
    }

    /**
     * @return array<int, array{guid: string, title: string, link: string}>
     */
    private function extractAtomItems(\SimpleXMLElement $feed): array
    {
        $items = [];
        foreach ($feed->entry ?? [] as $entry) {
            $id = (string) ($entry->id ?? '');
            $title = (string) ($entry->title ?? '');
            $link = '';
            foreach ($entry->link ?? [] as $l) {
                if ((string) ($l['rel'] ?? 'alternate') === 'alternate') {
                    $link = (string) ($l['href'] ?? '');
                    break;
                }
            }
            $guid = $id !== '' ? $id : ($link !== '' ? $link : $title);

            if ($guid !== '') {
                $items[] = ['guid' => $guid, 'title' => $title, 'link' => $link];
            }
        }

        return $items;
    }

    /**
     * @param  array<int, array{guid: string, title: string, link: string}>  $newItems
     */
    private function fireNotifications(Monitor $monitor, array $newItems): bool
    {
        $channels = $monitor->notificationChannels()->where('enabled', true)->get();
        $sent = false;

        foreach ($newItems as $item) {
            $payload = [
                'title' => "New post: {$item['title']}",
                'body' => "New item in '{$monitor->label}': {$item['title']}",
                'url' => $item['link'] ?: $monitor->url,
            ];

            foreach ($channels as $channel) {
                /** @var NotificationChannel $channel */
                if (($this->sendNotification)($channel, $payload)) {
                    $sent = true;
                }
            }
        }

        return $sent;
    }

    /**
     * @param  array<int, array{guid: string, title: string, link: string}>  $items
     */
    private function saveLastSeenGuid(Monitor $monitor, ?string $guid): void
    {
        if ($guid === null) {
            return;
        }

        $config = $monitor->check_config ?? [];
        $config['last_seen_guid'] = $guid;
        $monitor->update(['check_config' => $config]);
        $monitor->refresh();
    }

    private function recordFailure(Monitor $monitor, string $error): void
    {
        $monitor->update([
            'last_polled_at' => now(),
            'last_error' => mb_substr($error, 0, 1000),
            'consecutive_failures' => $monitor->consecutive_failures + 1,
        ]);
    }

    /**
     * @param  array<int, array{guid: string, title: string, link: string}>  $newItems
     * @return array{
     *   ok: bool,
     *   status: int|null,
     *   body_length: int,
     *   matched: bool|null,
     *   notified: bool,
     *   error: string|null,
     *   body_excerpt: string,
     *   needle_positions: int,
     * }
     */
    private function result(bool $ok, ?int $status, string $body, array $newItems, bool $notified, ?string $error, string $excerpt = ''): array
    {
        return [
            'ok' => $ok,
            'status' => $status,
            'body_length' => mb_strlen($body),
            'matched' => $ok ? count($newItems) > 0 : null,
            'notified' => $notified,
            'error' => $error,
            'body_excerpt' => $excerpt,
            'needle_positions' => count($newItems),
        ];
    }
}
