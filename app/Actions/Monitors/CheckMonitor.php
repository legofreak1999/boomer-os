<?php

namespace App\Actions\Monitors;

use App\Actions\Notifications\SendNotification;
use App\Models\Monitor;
use Illuminate\Support\Facades\Http;

class CheckMonitor
{
    public function __construct(
        private EvaluateMonitor $evaluate,
        private SendNotification $sendNotification,
    ) {}

    public function __invoke(Monitor $monitor): void
    {
        try {
            $response = Http::timeout(15)
                ->withUserAgent('BoomerOS-Monitor/1.0')
                ->get($monitor->url);
        } catch (\Throwable $e) {
            $this->recordFailure($monitor, $e->getMessage());

            return;
        }

        try {
            $currentlyMatched = ($this->evaluate)($monitor, $response);
        } catch (\Throwable $e) {
            $this->recordFailure($monitor, $e->getMessage());

            return;
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

        if ($shouldNotify) {
            $this->fireNotifications($monitor, $currentlyMatched);
        }
    }

    private function recordFailure(Monitor $monitor, string $error): void
    {
        $monitor->update([
            'last_polled_at' => now(),
            'last_error' => mb_substr($error, 0, 1000),
            'consecutive_failures' => $monitor->consecutive_failures + 1,
        ]);
    }

    private function fireNotifications(Monitor $monitor, bool $currentlyMatched): void
    {
        $title = $currentlyMatched
            ? "Monitor '{$monitor->label}': condition detected"
            : "Monitor '{$monitor->label}': condition cleared";

        $body = $currentlyMatched
            ? "The monitored condition is now true for {$monitor->label}."
            : "The monitored condition is no longer true for {$monitor->label}.";

        $payload = ['title' => $title, 'body' => $body, 'url' => $monitor->url];

        foreach ($monitor->notificationChannels()->where('enabled', true)->get() as $channel) {
            ($this->sendNotification)($channel, $payload);
        }
    }
}
