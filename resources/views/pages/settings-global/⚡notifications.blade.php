<?php

use App\Models\NotificationChannel;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Notification settings')] class extends Component {
    public bool $showForm = false;
    public ?int $editingId = null;

    public string $type = NotificationChannel::TYPE_DISCORD;
    public string $label = '';
    public array $config = [];
    public bool $enabled = true;

    /**
     * @return array<string, array<string, string>>
     */
    public static function configFields(): array
    {
        return [
            NotificationChannel::TYPE_DISCORD => [
                'webhook_url' => 'Webhook URL',
            ],
            NotificationChannel::TYPE_TELEGRAM => [
                'bot_token' => 'Bot Token',
                'chat_id' => 'Chat ID',
            ],
            NotificationChannel::TYPE_EMAIL => [
                'address' => 'Email Address',
            ],
            NotificationChannel::TYPE_SIGNAL => [
                'phone_number' => 'Phone Number',
            ],
            NotificationChannel::TYPE_NTFY => [
                'server_url' => 'Server URL',
                'topic' => 'Topic',
            ],
        ];
    }

    public static function typeLabels(): array
    {
        return [
            NotificationChannel::TYPE_DISCORD => 'Discord',
            NotificationChannel::TYPE_TELEGRAM => 'Telegram',
            NotificationChannel::TYPE_EMAIL => 'Email',
            NotificationChannel::TYPE_SIGNAL => 'Signal',
            NotificationChannel::TYPE_NTFY => 'Ntfy.sh',
        ];
    }

    #[Computed]
    public function channels()
    {
        return NotificationChannel::orderBy('label')->get();
    }

    public function openForm(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $channel = NotificationChannel::findOrFail($id);

        $this->editingId = $channel->id;
        $this->type = $channel->type;
        $this->label = $channel->label;
        $this->config = $channel->config;
        $this->enabled = $channel->enabled;
        $this->showForm = true;
    }

    public function save(): void
    {
        $configFields = array_keys(self::configFields()[$this->type] ?? []);

        $rules = [
            'type' => ['required', 'in:'.implode(',', NotificationChannel::TYPES)],
            'label' => ['required', 'string', 'max:255'],
            'enabled' => ['boolean'],
        ];

        foreach ($configFields as $field) {
            $rules["config.{$field}"] = ['required', 'string', 'max:1000'];
        }

        $validated = $this->validate($rules);

        $config = [];
        foreach ($configFields as $field) {
            $config[$field] = $validated['config'][$field];
        }

        NotificationChannel::updateOrCreate(
            ['id' => $this->editingId],
            [
                'type' => $validated['type'],
                'label' => $validated['label'],
                'config' => $config,
                'enabled' => $validated['enabled'],
            ],
        );

        $this->resetForm();
        unset($this->channels);

        Flux::toast(variant: 'success', text: $this->editingId ? __('Channel updated.') : __('Channel added.'));

        $this->editingId = null;
    }

    public function toggleEnabled(int $id): void
    {
        $channel = NotificationChannel::findOrFail($id);
        $channel->update(['enabled' => ! $channel->enabled]);
        unset($this->channels);
    }

    public function delete(int $id): void
    {
        NotificationChannel::findOrFail($id)->delete();
        unset($this->channels);

        Flux::toast(text: __('Channel deleted.'));
    }

    public function resetForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->type = NotificationChannel::TYPE_DISCORD;
        $this->label = '';
        $this->config = [];
        $this->enabled = true;
        $this->resetValidation();
    }

    public function updatedType(): void
    {
        $this->config = [];
        $this->resetValidation();
    }
}; ?>

<section class="w-full">
    <div class="relative mb-6 w-full">
        <flux:heading size="xl" level="1">{{ __('Settings') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">{{ __('Manage your application settings') }}</flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    <x-pages::settings-global.layout :heading="__('Notifications')" :subheading="__('Configure your notification channels')">
        <div class="my-6 w-full space-y-6">
            @forelse ($this->channels as $channel)
                <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <flux:heading size="sm">{{ $channel->label }}</flux:heading>
                            <flux:badge size="sm" color="zinc">{{ self::typeLabels()[$channel->type] ?? $channel->type }}</flux:badge>
                        </div>
                        <flux:text size="sm" class="mt-1">
                            @foreach (self::configFields()[$channel->type] ?? [] as $key => $fieldLabel)
                                {{ $fieldLabel }}: {{ Str::limit($channel->config[$key] ?? '', 40) }}@if (! $loop->last), @endif
                            @endforeach
                        </flux:text>
                    </div>
                    <div class="flex items-center gap-3">
                        <flux:switch wire:click="toggleEnabled({{ $channel->id }})" :checked="$channel->enabled" />
                        <flux:button size="sm" icon="pencil" variant="ghost" wire:click="edit({{ $channel->id }})" />
                        <flux:button size="sm" icon="trash" variant="ghost" wire:click="delete({{ $channel->id }})" wire:confirm="{{ __('Are you sure you want to delete this channel?') }}" />
                    </div>
                </div>
            @empty
                <flux:text>{{ __('No notification channels configured yet.') }}</flux:text>
            @endforelse

            @if ($showForm)
                <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:heading size="sm" class="mb-4">{{ $editingId ? __('Edit Channel') : __('Add Channel') }}</flux:heading>

                    <form wire:submit="save" class="space-y-4">
                        <flux:select wire:model.live="type" :label="__('Type')">
                            @foreach (self::typeLabels() as $value => $typeLabel)
                                <flux:select.option :value="$value">{{ $typeLabel }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:input wire:model="label" :label="__('Label')" type="text" :placeholder="__('e.g. My Discord Server')" required />

                        @foreach (self::configFields()[$type] ?? [] as $key => $fieldLabel)
                            <flux:input wire:model="config.{{ $key }}" :label="__($fieldLabel)" type="text" required />
                        @endforeach

                        <flux:field variant="inline">
                            <flux:label>{{ __('Enabled') }}</flux:label>
                            <flux:switch wire:model="enabled" />
                        </flux:field>

                        <div class="flex items-center gap-2">
                            <flux:button variant="primary" type="submit">
                                {{ $editingId ? __('Update') : __('Add') }}
                            </flux:button>
                            <flux:button variant="ghost" wire:click="resetForm">
                                {{ __('Cancel') }}
                            </flux:button>
                        </div>
                    </form>
                </div>
            @else
                <flux:button variant="primary" icon="plus" wire:click="openForm">
                    {{ __('Add Channel') }}
                </flux:button>
            @endif
        </div>
    </x-pages::settings-global.layout>
</section>
