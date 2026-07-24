<?php

namespace App\Actions\Monitors;

use App\Actions\Notifications\SendNotification;
use App\Models\Monitor;
use Illuminate\Support\Facades\Http;

class CheckMonitor
{
    private const BROWSER_UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

    public function __construct(
        private EvaluateMonitor $evaluate,
        private SendNotification $sendNotification,
        private CheckRssFeed $checkRssFeed,
    ) {}

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
        if ($monitor->check_type === Monitor::CHECK_RSS_FEED) {
            return ($this->checkRssFeed)($monitor);
        }

        try {
            $response = Http::timeout(15)
                ->withUserAgent(self::BROWSER_UA)
                ->withHeaders([
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Accept-Encoding' => 'gzip, deflate',
                    'Cache-Control' => 'max-age=0',
                    'Sec-Ch-Ua' => '"Chromium";v="124", "Google Chrome";v="124", "Not-A.Brand";v="99"',
                    'Sec-Ch-Ua-Mobile' => '?0',
                    'Sec-Ch-Ua-Platform' => '"macOS"',
                    'Sec-Fetch-Dest' => 'document',
                    'Sec-Fetch-Mode' => 'navigate',
                    'Sec-Fetch-Site' => 'none',
                    'Sec-Fetch-User' => '?1',
                    'Upgrade-Insecure-Requests' => '1',
                ])
                ->get($monitor->url);
        } catch (\Throwable $e) {
            $this->recordFailure($monitor, $e->getMessage());

            return $this->result(
                ok: false,
                status: null,
                body: '',
                matched: null,
                notified: false,
                error: $e->getMessage(),
                monitor: $monitor,
            );
        }

        try {
            $currentlyMatched = ($this->evaluate)($monitor, $response);
        } catch (\Throwable $e) {
            $this->recordFailure($monitor, $e->getMessage());

            return $this->result(
                ok: false,
                status: $response->status(),
                body: $response->body(),
                matched: null,
                notified: false,
                error: $e->getMessage(),
                monitor: $monitor,
            );
        }

        $previouslyMatched = $monitor->last_matched;

        $shouldNotify = match ($monitor->notify_on) {
            Monitor::NOTIFY_ON_APPEARANCE => $previouslyMatched === false && $currentlyMatched === true,
            Monitor::NOTIFY_ON_DISAPPEARANCE => $previouslyMatched === true && $currentlyMatched === false,
            default => $previouslyMatched !== null && $previouslyMatched !== $currentlyMatched,
        };

        $monitor->update([
            'last_matched' => $currentlyMatched,
            'last_polled_at' => now(),
            'last_error' => null,
            'consecutive_failures' => 0,
        ]);

        $notified = false;
        if ($shouldNotify) {
            $notified = $this->fireNotifications($monitor, $currentlyMatched);
        }

        return $this->result(
            ok: true,
            status: $response->status(),
            body: $response->body(),
            matched: $currentlyMatched,
            notified: $notified,
            error: null,
            monitor: $monitor,
        );
    }

    private function recordFailure(Monitor $monitor, string $error): void
    {
        $monitor->update([
            'last_polled_at' => now(),
            'last_error' => mb_substr($error, 0, 1000),
            'consecutive_failures' => $monitor->consecutive_failures + 1,
        ]);
    }

    private function fireNotifications(Monitor $monitor, bool $currentlyMatched): bool
    {
        $title = $currentlyMatched
            ? "Monitor '{$monitor->label}': condition detected"
            : "Monitor '{$monitor->label}': condition cleared";

        $body = $currentlyMatched
            ? "The monitored condition is now true for {$monitor->label}."
            : "The monitored condition is no longer true for {$monitor->label}.";

        $payload = ['title' => $title, 'body' => $body, 'url' => $monitor->url];

        $sent = false;
        foreach ($monitor->notificationChannels()->where('enabled', true)->get() as $channel) {
            if (($this->sendNotification)($channel, $payload)) {
                $sent = true;
            }
        }

        return $sent;
    }

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
    private function result(bool $ok, ?int $status, string $body, ?bool $matched, bool $notified, ?string $error, Monitor $monitor): array
    {
        $needle = null;
        if ($monitor->check_type === Monitor::CHECK_TEXT_CONTAINS) {
            $needle = (string) ($monitor->check_config['needle'] ?? '');
        }

        return [
            'ok' => $ok,
            'status' => $status,
            'body_length' => mb_strlen($body),
            'matched' => $matched,
            'notified' => $notified,
            'error' => $error,
            'body_excerpt' => $this->excerpt($body, $needle),
            'needle_positions' => $needle ? substr_count(mb_strtolower($body), mb_strtolower($needle)) : 0,
        ];
    }

    private function excerpt(string $body, ?string $needle): string
    {
        if ($body === '') {
            return '';
        }

        if ($needle) {
            $pos = stripos($body, $needle);
            if ($pos !== false) {
                $start = max(0, $pos - 200);

                return mb_substr($body, $start, 600);
            }
        }

        return mb_substr($body, 0, 800);
    }
}
