<?php

use App\Models\AppSetting;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Settings')] class extends Component {
    /**
     * @return array<string, array<string, string>>
     */
    public static function features(): array
    {
        return [
            'expenses' => [
                'label' => 'Expenses',
                'description' => 'Track and manage expenses by category and store',
                'icon' => 'banknotes',
            ],
            'chores' => [
                'label' => 'Chores',
                'description' => 'Manage household chore lists and schedules',
                'icon' => 'clipboard-document-check',
            ],
            'tasks' => [
                'label' => 'Tasks',
                'description' => 'One-off to-do items and fixes',
                'icon' => 'clipboard-document-list',
            ],
            'forms' => [
                'label' => 'Forms',
                'description' => 'Shared questionnaires everyone fills in once',
                'icon' => 'document-text',
            ],
            'hikes' => [
                'label' => 'Hikes',
                'description' => 'Map and manage hiking locations and trails',
                'icon' => 'map',
            ],
            'notifications' => [
                'label' => 'Notifications',
                'description' => 'Notification channel configuration',
                'icon' => 'bell',
            ],
        ];
    }

    #[Computed]
    public function sidebarFeatures(): array
    {
        return AppSetting::get('sidebar_features', []);
    }

    public function isFeatureEnabled(string $feature): bool
    {
        $features = $this->sidebarFeatures;

        return $features[$feature] ?? true;
    }

    public function toggleFeature(string $feature): void
    {
        $features = AppSetting::get('sidebar_features', []);
        $features[$feature] = ! ($features[$feature] ?? true);
        AppSetting::set('sidebar_features', $features);

        $this->redirect(route('app-settings.index'), navigate: true);
    }
}; ?>

<section class="w-full">
    <div class="relative mb-6 w-full">
        <flux:heading size="xl" level="1">{{ __('Settings') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">{{ __('Manage your application settings') }}</flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    <x-pages::settings-global.layout :heading="__('Features')" :subheading="__('Toggle features visible in the sidebar')">
        <div class="my-6 w-full space-y-4">
            @foreach (self::features() as $key => $feature)
                <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="flex items-center gap-3">
                        <flux:icon :name="$feature['icon']" variant="outline" class="size-5 text-zinc-500 dark:text-zinc-400" />
                        <div>
                            <flux:heading size="sm">{{ __($feature['label']) }}</flux:heading>
                            <flux:text size="sm">{{ __($feature['description']) }}</flux:text>
                        </div>
                    </div>
                    <flux:switch wire:click="toggleFeature('{{ $key }}')" :checked="$this->isFeatureEnabled($key)" />
                </div>
            @endforeach
        </div>
    </x-pages::settings-global.layout>
</section>
