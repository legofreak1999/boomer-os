<?php

namespace App\Actions\Notifications;

use App\Models\NotificationChannel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendNotification
{
    /**
     * @param  array{title: string, body: string, url?: string|null}  $payload
     */
    public function __invoke(NotificationChannel $channel, array $payload): bool
    {
        if (! $channel->enabled) {
            return false;
        }

        try {
            return match ($channel->type) {
                NotificationChannel::TYPE_DISCORD => $this->discord($channel, $payload),
                NotificationChannel::TYPE_TELEGRAM => $this->telegram($channel, $payload),
                NotificationChannel::TYPE_EMAIL => $this->email($channel, $payload),
                NotificationChannel::TYPE_NTFY => $this->ntfy($channel, $payload),
                NotificationChannel::TYPE_SIGNAL => $this->signal($channel, $payload),
                default => false,
            };
        } catch (\Throwable $e) {
            Log::warning('Notification dispatch failed', [
                'channel_id' => $channel->id,
                'channel_type' => $channel->type,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @param  array{title: string, body: string, url?: string|null}  $payload
     */
    private function discord(NotificationChannel $channel, array $payload): bool
    {
        $content = "**{$payload['title']}**\n{$payload['body']}";
        if (! empty($payload['url'])) {
            $content .= "\n".$payload['url'];
        }

        return Http::timeout(10)
            ->post($channel->config['webhook_url'], ['content' => $content])
            ->successful();
    }

    /**
     * @param  array{title: string, body: string, url?: string|null}  $payload
     */
    private function telegram(NotificationChannel $channel, array $payload): bool
    {
        $text = "*{$payload['title']}*\n{$payload['body']}";
        if (! empty($payload['url'])) {
            $text .= "\n".$payload['url'];
        }

        $token = $channel->config['bot_token'];

        return Http::timeout(10)
            ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $channel->config['chat_id'],
                'text' => $text,
                'parse_mode' => 'Markdown',
            ])
            ->successful();
    }

    /**
     * @param  array{title: string, body: string, url?: string|null}  $payload
     */
    private function email(NotificationChannel $channel, array $payload): bool
    {
        $body = $payload['body'];
        if (! empty($payload['url'])) {
            $body .= "\n\n".$payload['url'];
        }

        Mail::raw($body, function ($message) use ($channel, $payload) {
            $message->to($channel->config['address'])->subject($payload['title']);
        });

        return true;
    }

    /**
     * @param  array{title: string, body: string, url?: string|null}  $payload
     */
    private function ntfy(NotificationChannel $channel, array $payload): bool
    {
        $server = rtrim($channel->config['server_url'], '/');
        $topic = $channel->config['topic'];

        $headers = ['Title' => $payload['title']];
        if (! empty($payload['url'])) {
            $headers['Click'] = $payload['url'];
        }

        return Http::timeout(10)
            ->withHeaders($headers)
            ->withBody($payload['body'], 'text/plain')
            ->post("{$server}/{$topic}")
            ->successful();
    }

    /**
     * @param  array{title: string, body: string, url?: string|null}  $payload
     */
    private function signal(NotificationChannel $channel, array $payload): bool
    {
        Log::info('Signal transport not implemented yet', [
            'channel_id' => $channel->id,
            'phone_number' => $channel->config['phone_number'] ?? null,
        ]);

        return false;
    }
}
