<?php

use App\Actions\Chores\CalculateMonthlyRewardSummary;
use App\Models\AppSetting;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Chore Reward Settings')] class extends Component {
    public string $floorPerPerson = '';
    public string $bonusPool = '';
    public string $bountyMax = '';
    public int $badDayMultiplier = 2;
    public int $superBadDayMultiplier = 3;

    public function mount(): void
    {
        $settings = array_merge(CalculateMonthlyRewardSummary::DEFAULT_SETTINGS, AppSetting::get('chore_reward_settings', []));

        $this->floorPerPerson = number_format($settings['floor_per_person_cents'] / 100, 2, '.', '');
        $this->bonusPool = number_format($settings['bonus_pool_cents'] / 100, 2, '.', '');
        $this->bountyMax = number_format($settings['bounty_max_cents'] / 100, 2, '.', '');
        $this->badDayMultiplier = $settings['bad_day_multiplier'];
        $this->superBadDayMultiplier = $settings['super_bad_day_multiplier'];
    }

    public function save(): void
    {
        $this->validate([
            'floorPerPerson' => ['required', 'numeric', 'min:0'],
            'bonusPool' => ['required', 'numeric', 'min:0'],
            'bountyMax' => ['required', 'numeric', 'min:0'],
            'badDayMultiplier' => ['required', 'integer', 'min:1', 'max:20'],
            'superBadDayMultiplier' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        AppSetting::set('chore_reward_settings', [
            'floor_per_person_cents' => (int) round(((float) $this->floorPerPerson) * 100),
            'bonus_pool_cents' => (int) round(((float) $this->bonusPool) * 100),
            'bounty_max_cents' => (int) round(((float) $this->bountyMax) * 100),
            'bad_day_multiplier' => $this->badDayMultiplier,
            'super_bad_day_multiplier' => $this->superBadDayMultiplier,
        ]);

        Flux::toast(variant: 'success', text: __('Chore reward settings updated.'));
    }
}; ?>

<section class="w-full">
    <div class="relative mb-6 w-full">
        <flux:heading size="xl" level="1">{{ __('Settings') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">{{ __('Manage your application settings') }}</flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    <x-pages::settings-global.layout :heading="__('Chore Rewards')" :subheading="__('Tune the monthly chore reward budget')">
        <form wire:submit="save" class="my-6 w-full space-y-6">
            <flux:input wire:model="floorPerPerson" type="number" step="0.01" min="0" label="{{ __('Floor per person (€)') }}" :description="__('Guaranteed each month, no matter what.')" />

            <flux:input wire:model="bonusPool" type="number" step="0.01" min="0" label="{{ __('Bonus pool total (€)') }}" :description="__('Shared pool, unlocked based on how much gets done and split by contribution.')" />

            <flux:input wire:model="bountyMax" type="number" step="0.01" min="0" label="{{ __('Maximum bounty (€)') }}" :description="__('Sanity ceiling on any single ad hoc bounty.')" />

            <div>
                <flux:label>{{ __('Bad day multipliers') }}</flux:label>
                <flux:text size="sm" class="text-zinc-500">{{ __('How much your difficulty points are multiplied on a flagged bad/super bad day.') }}</flux:text>
                <div class="mt-2 flex items-end gap-4">
                    <div>
                        <flux:text size="sm" class="mb-1">{{ __('Bad day') }}</flux:text>
                        <flux:input wire:model="badDayMultiplier" type="number" min="1" max="20" class="w-20" />
                    </div>
                    <div>
                        <flux:text size="sm" class="mb-1">{{ __('Super bad day') }}</flux:text>
                        <flux:input wire:model="superBadDayMultiplier" type="number" min="1" max="20" class="w-20" />
                    </div>
                </div>
            </div>

            <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
        </form>
    </x-pages::settings-global.layout>
</section>
