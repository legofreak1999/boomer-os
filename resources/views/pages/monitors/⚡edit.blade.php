<?php

use App\Actions\Monitors\CheckMonitor;
use App\Models\Monitor;
use App\Models\NotificationChannel;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Monitor')] class extends Component {
    public ?Monitor $monitor = null;

    public string $label = '';
    public string $url = '';
    public int $interval_minutes = 15;
    public string $check_type = Monitor::CHECK_TEXT_CONTAINS;

    /** @var array<string, mixed> */
    public array $check_config = [];

    public string $notify_on = Monitor::NOTIFY_ON_BOTH;
    public bool $enabled = true;

    /** @var array<int> */
    public array $notification_channel_ids = [];

    /** @var array<string, mixed>|null */
    public ?array $lastCheckResult = null;

    public function mount(?Monitor $monitor = null): void
    {
        if ($monitor && $monitor->exists) {
            $this->monitor = $monitor;
            $this->label = $monitor->label;
            $this->url = $monitor->url;
            $this->interval_minutes = $monitor->interval_minutes;
            $this->check_type = $monitor->check_type;
            $this->check_config = $monitor->check_config ?? [];
            $this->notify_on = $monitor->notify_on;
            $this->enabled = $monitor->enabled;
            $this->notification_channel_ids = $monitor->notificationChannels()->pluck('notification_channels.id')->all();
        }
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function checkConfigFields(): array
    {
        return [
            Monitor::CHECK_TEXT_CONTAINS => [
                'needle' => 'Text to search for',
            ],
            Monitor::CHECK_CSS_SELECTOR => [
                'selector' => 'CSS selector (e.g. .stock-status)',
                'expected_text' => 'Expected text (leave empty to just check for presence)',
            ],
            Monitor::CHECK_REGEX => [
                'pattern' => 'Regex pattern (e.g. /out of stock/i)',
            ],
            Monitor::CHECK_HTTP_STATUS => [
                'expected_status' => 'Expected HTTP status code (e.g. 200)',
            ],
        ];
    }

    public static function checkTypeLabels(): array
    {
        return [
            Monitor::CHECK_TEXT_CONTAINS => 'Text contains',
            Monitor::CHECK_CSS_SELECTOR => 'CSS selector',
            Monitor::CHECK_REGEX => 'Regex',
            Monitor::CHECK_HTTP_STATUS => 'HTTP status',
        ];
    }

    public static function notifyOnLabels(): array
    {
        return [
            Monitor::NOTIFY_ON_APPEARANCE => 'When condition becomes true',
            Monitor::NOTIFY_ON_DISAPPEARANCE => 'When condition becomes false',
            Monitor::NOTIFY_ON_BOTH => 'On any state change',
        ];
    }

    #[Computed]
    public function availableChannels()
    {
        return NotificationChannel::where('enabled', true)->orderBy('label')->get();
    }

    public function updatedCheckType(): void
    {
        $this->check_config = [];
        $this->resetValidation();
    }

    public function save(): void
    {
        $rules = [
            'label' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:2048'],
            'interval_minutes' => ['required', 'integer', 'min:1'],
            'check_type' => ['required', 'in:'.implode(',', Monitor::CHECK_TYPES)],
            'notify_on' => ['required', 'in:'.implode(',', Monitor::NOTIFY_ON)],
            'enabled' => ['boolean'],
            'notification_channel_ids' => ['array'],
            'notification_channel_ids.*' => ['integer', 'exists:notification_channels,id'],
        ];

        foreach ($this->rulesForCheckType($this->check_type) as $field => $fieldRules) {
            $rules["check_config.{$field}"] = $fieldRules;
        }

        $validated = $this->validate($rules);

        $config = [];
        foreach (array_keys($this->rulesForCheckType($this->check_type)) as $field) {
            $config[$field] = $validated['check_config'][$field] ?? null;
        }

        if ($this->check_type === Monitor::CHECK_TEXT_CONTAINS) {
            $config['case_sensitive'] = (bool) ($this->check_config['case_sensitive'] ?? false);
        }

        $data = [
            'label' => $validated['label'],
            'url' => $validated['url'],
            'interval_minutes' => $validated['interval_minutes'],
            'check_type' => $validated['check_type'],
            'check_config' => $config,
            'notify_on' => $validated['notify_on'],
            'enabled' => $validated['enabled'],
        ];

        if ($this->monitor) {
            $this->monitor->update($data);
        } else {
            $this->monitor = Monitor::create($data);
        }

        $this->monitor->notificationChannels()->sync($validated['notification_channel_ids'] ?? []);

        Flux::toast(variant: 'success', text: __('Monitor saved.'));

        $this->redirect(route('monitors.index'), navigate: true);
    }

    public function forceCheck(CheckMonitor $check): void
    {
        if (! $this->monitor) {
            Flux::toast(variant: 'danger', text: __('Save the monitor first before running a check.'));

            return;
        }

        $this->lastCheckResult = $check($this->monitor->fresh());
        $this->monitor->refresh();

        if ($this->lastCheckResult['error']) {
            Flux::toast(variant: 'danger', text: __('Check failed: :error', ['error' => $this->lastCheckResult['error']]));

            return;
        }

        Flux::toast(text: $this->lastCheckResult['matched']
            ? __('Check ran. Condition currently matches.')
            : __('Check ran. Condition does not match.'));
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function rulesForCheckType(string $type): array
    {
        return match ($type) {
            Monitor::CHECK_TEXT_CONTAINS => [
                'needle' => ['required', 'string', 'max:1000'],
            ],
            Monitor::CHECK_CSS_SELECTOR => [
                'selector' => ['required', 'string', 'max:500'],
                'expected_text' => ['nullable', 'string', 'max:1000'],
            ],
            Monitor::CHECK_REGEX => [
                'pattern' => ['required', 'string', 'max:1000'],
            ],
            Monitor::CHECK_HTTP_STATUS => [
                'expected_status' => ['required', 'integer', 'min:100', 'max:599'],
            ],
            default => [],
        };
    }
}; ?>

<section class="w-full">
    <div class="relative mb-6 w-full">
        <flux:heading size="xl" level="1">{{ $monitor?->exists ? __('Edit monitor') : __('New monitor') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">{{ __('Configure the URL, poll interval and match rule') }}</flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    <form wire:submit="save" class="my-6 max-w-2xl space-y-6">
        <flux:input wire:model="label" :label="__('Label')" type="text" required />

        <flux:input wire:model="url" :label="__('URL')" type="url" placeholder="https://example.com/product" required />

        <flux:input wire:model="interval_minutes" :label="__('Poll interval (minutes)')" type="number" min="1" required />

        <flux:select wire:model.live="check_type" :label="__('Match type')">
            @foreach (self::checkTypeLabels() as $value => $typeLabel)
                <flux:select.option :value="$value">{{ $typeLabel }}</flux:select.option>
            @endforeach
        </flux:select>

        @foreach (self::checkConfigFields()[$check_type] ?? [] as $field => $fieldLabel)
            <flux:input wire:model="check_config.{{ $field }}" :label="__($fieldLabel)" type="text" />
        @endforeach

        @if ($check_type === \App\Models\Monitor::CHECK_TEXT_CONTAINS)
            <flux:field variant="inline">
                <flux:label>{{ __('Case sensitive') }}</flux:label>
                <flux:switch wire:model="check_config.case_sensitive" />
            </flux:field>
        @endif

        <flux:select wire:model="notify_on" :label="__('Notify')">
            @foreach (self::notifyOnLabels() as $value => $optLabel)
                <flux:select.option :value="$value">{{ $optLabel }}</flux:select.option>
            @endforeach
        </flux:select>

        <div class="space-y-2">
            <flux:label>{{ __('Notification channels') }}</flux:label>
            @forelse ($this->availableChannels as $channel)
                <flux:checkbox wire:model="notification_channel_ids" :value="$channel->id" :label="$channel->label" />
            @empty
                <flux:text size="sm">
                    {{ __('No enabled channels yet.') }}
                    <a class="underline" href="{{ route('app-settings.notifications') }}" wire:navigate>{{ __('Add one') }}</a>.
                </flux:text>
            @endforelse
        </div>

        <flux:field variant="inline">
            <flux:label>{{ __('Enabled') }}</flux:label>
            <flux:switch wire:model="enabled" />
        </flux:field>

        <div class="flex items-center gap-2">
            <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            <flux:button variant="ghost" :href="route('monitors.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
            @if ($monitor?->exists)
                <flux:spacer />
                <flux:button variant="subtle" icon="bolt" wire:click="forceCheck">{{ __('Force check') }}</flux:button>
            @endif
        </div>

        @if ($lastCheckResult)
            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:heading size="sm" class="mb-3">{{ __('Last check result') }}</flux:heading>

                <div class="grid grid-cols-2 gap-y-2 text-sm">
                    <div class="text-zinc-500 dark:text-zinc-400">{{ __('HTTP status') }}</div>
                    <div>
                        @if ($lastCheckResult['status'])
                            <flux:badge size="sm" :color="$lastCheckResult['status'] < 400 ? 'lime' : 'red'">
                                {{ $lastCheckResult['status'] }}
                            </flux:badge>
                        @else
                            <flux:badge size="sm" color="red">{{ __('Request failed') }}</flux:badge>
                        @endif
                    </div>

                    <div class="text-zinc-500 dark:text-zinc-400">{{ __('Response size') }}</div>
                    <div>{{ number_format($lastCheckResult['body_length']) }} {{ __('chars') }}</div>

                    <div class="text-zinc-500 dark:text-zinc-400">{{ __('Match result') }}</div>
                    <div>
                        @if ($lastCheckResult['matched'] === true)
                            <flux:badge size="sm" color="lime">{{ __('Matched') }}</flux:badge>
                        @elseif ($lastCheckResult['matched'] === false)
                            <flux:badge size="sm" color="zinc">{{ __('Not matched') }}</flux:badge>
                        @else
                            <flux:badge size="sm" color="red">{{ __('Evaluator error') }}</flux:badge>
                        @endif
                    </div>

                    @if ($check_type === \App\Models\Monitor::CHECK_TEXT_CONTAINS)
                        <div class="text-zinc-500 dark:text-zinc-400">{{ __('Needle occurrences') }}</div>
                        <div>{{ $lastCheckResult['needle_positions'] }}</div>
                    @endif

                    <div class="text-zinc-500 dark:text-zinc-400">{{ __('Notification sent') }}</div>
                    <div>
                        @if ($lastCheckResult['notified'])
                            <flux:badge size="sm" color="lime">{{ __('Yes') }}</flux:badge>
                        @else
                            <flux:badge size="sm" color="zinc">{{ __('No (no state change)') }}</flux:badge>
                        @endif
                    </div>
                </div>

                @if ($lastCheckResult['error'])
                    <div class="mt-3">
                        <flux:label>{{ __('Error') }}</flux:label>
                        <flux:text size="sm" class="text-red-600 dark:text-red-400">{{ $lastCheckResult['error'] }}</flux:text>
                    </div>
                @endif

                @if ($lastCheckResult['status'] === 429 || str_contains(strtolower($lastCheckResult['body_excerpt'] ?? ''), 'rate_limited') || str_contains(strtolower($lastCheckResult['body_excerpt'] ?? ''), 'access denied') || $lastCheckResult['status'] === 403)
                    <div class="mt-3 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm dark:border-amber-800 dark:bg-amber-950">
                        <flux:text size="sm">
                            <strong>{{ __('Looks like bot protection.') }}</strong>
                            {{ __("The site refused this request (rate-limit or block page). Shopify/Cloudflare-fronted stores commonly block anything that isn't a real browser. Options:") }}
                        </flux:text>
                        <ul class="mt-2 ml-4 list-disc space-y-1 text-sm">
                            <li>{{ __('Increase the poll interval — 30+ minutes drastically reduces the chance of being flagged.') }}</li>
                            <li>{{ __('For Shopify product pages, try monitoring the JSON endpoint: append ".js" or ".json" to the product URL (e.g. /products/black-reverse-seam-top.json) and match on ') }}<code>"available":false</code>{{ __(' with the Text-contains rule.') }}</li>
                            <li>{{ __('If the block persists, the site simply cannot be monitored from this server without a scraping proxy.') }}</li>
                        </ul>
                    </div>
                @endif

                @if ($lastCheckResult['body_excerpt'] !== '')
                    <div class="mt-3">
                        <flux:label>{{ __('Response excerpt') }}</flux:label>
                        <flux:text size="xs" class="mt-1">
                            {{ __('If the needle appears in JavaScript-rendered content (not raw HTML) it will not be detected. Check the excerpt below to see what we actually received.') }}
                        </flux:text>
                        <pre class="mt-2 max-h-60 overflow-auto rounded bg-zinc-100 p-3 text-xs whitespace-pre-wrap dark:bg-zinc-900">{{ $lastCheckResult['body_excerpt'] }}</pre>
                    </div>
                @endif
            </div>
        @endif
    </form>
</section>
