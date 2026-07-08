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

    public function testNow(CheckMonitor $check): void
    {
        if (! $this->monitor) {
            Flux::toast(variant: 'danger', text: __('Save the monitor first before testing.'));

            return;
        }

        $check($this->monitor->fresh());
        $this->monitor->refresh();

        if ($this->monitor->last_error) {
            Flux::toast(variant: 'danger', text: __('Check failed: :error', ['error' => $this->monitor->last_error]));

            return;
        }

        Flux::toast(text: $this->monitor->last_matched
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
                <flux:button variant="subtle" icon="play" wire:click="testNow">{{ __('Test now') }}</flux:button>
            @endif
        </div>
    </form>
</section>
