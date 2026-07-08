<?php

use App\Models\Monitor;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Monitors')] class extends Component {
    #[Computed]
    public function monitors()
    {
        return Monitor::orderBy('label')->get();
    }

    public function toggleEnabled(int $id): void
    {
        $monitor = Monitor::findOrFail($id);
        $monitor->update(['enabled' => ! $monitor->enabled]);
        unset($this->monitors);
    }

    public function delete(int $id): void
    {
        Monitor::findOrFail($id)->delete();
        unset($this->monitors);

        Flux::toast(text: __('Monitor deleted.'));
    }

    public static function typeLabels(): array
    {
        return [
            Monitor::CHECK_TEXT_CONTAINS => 'Text contains',
            Monitor::CHECK_CSS_SELECTOR => 'CSS selector',
            Monitor::CHECK_REGEX => 'Regex',
            Monitor::CHECK_HTTP_STATUS => 'HTTP status',
        ];
    }
}; ?>

<section class="w-full">
    <div class="relative mb-6 w-full">
        <flux:heading size="xl" level="1">{{ __('Monitors') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">{{ __('Poll URLs and get notified when things change') }}</flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    <div class="my-6 flex justify-end">
        <flux:button variant="primary" icon="plus" :href="route('monitors.create')" wire:navigate>
            {{ __('Add Monitor') }}
        </flux:button>
    </div>

    <div class="my-6 w-full space-y-4">
        @forelse ($this->monitors as $monitor)
            <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <div class="flex-1 space-y-1">
                    <div class="flex items-center gap-2">
                        <flux:heading size="sm">{{ $monitor->label }}</flux:heading>
                        <flux:badge size="sm" color="zinc">{{ self::typeLabels()[$monitor->check_type] ?? $monitor->check_type }}</flux:badge>
                        @if ($monitor->last_matched === true)
                            <flux:badge size="sm" color="lime">{{ __('Matched') }}</flux:badge>
                        @elseif ($monitor->last_matched === false)
                            <flux:badge size="sm" color="zinc">{{ __('Not matched') }}</flux:badge>
                        @else
                            <flux:badge size="sm" color="zinc">{{ __('Never polled') }}</flux:badge>
                        @endif
                        @if ($monitor->last_error)
                            <flux:badge size="sm" color="red">{{ __('Error') }}</flux:badge>
                        @endif
                    </div>
                    <flux:text size="sm" class="truncate">{{ $monitor->url }}</flux:text>
                    <flux:text size="xs">
                        {{ __('Every :n min', ['n' => $monitor->interval_minutes]) }}
                        @if ($monitor->last_polled_at)
                            · {{ __('Last checked :when', ['when' => $monitor->last_polled_at->diffForHumans()]) }}
                        @endif
                    </flux:text>
                    @if ($monitor->last_error)
                        <flux:text size="xs" class="text-red-600 dark:text-red-400">{{ Str::limit($monitor->last_error, 120) }}</flux:text>
                    @endif
                </div>
                <div class="flex items-center gap-3">
                    <flux:switch wire:click="toggleEnabled({{ $monitor->id }})" :checked="$monitor->enabled" />
                    <flux:button size="sm" icon="pencil" variant="ghost" :href="route('monitors.edit', $monitor)" wire:navigate />
                    <flux:button size="sm" icon="trash" variant="ghost" wire:click="delete({{ $monitor->id }})" wire:confirm="{{ __('Are you sure you want to delete this monitor?') }}" />
                </div>
            </div>
        @empty
            <flux:text>{{ __('No monitors configured yet.') }}</flux:text>
        @endforelse
    </div>
</section>
