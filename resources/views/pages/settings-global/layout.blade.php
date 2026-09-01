<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-[220px]">
        <flux:navlist aria-label="{{ __('Settings') }}">
            <flux:navlist.item :href="route('app-settings.index')" wire:navigate>{{ __('Features') }}</flux:navlist.item>
            <flux:navlist.item :href="route('app-settings.home-location')" wire:navigate>{{ __('Home Location') }}</flux:navlist.item>
            @if (App\Models\AppSetting::get('sidebar_features', [])['notifications'] ?? true)
                <flux:navlist.item :href="route('app-settings.notifications')" wire:navigate>{{ __('Notifications') }}</flux:navlist.item>
            @endif
            <flux:navlist.item :href="route('app-settings.chore-rewards')" wire:navigate>{{ __('Chore Rewards') }}</flux:navlist.item>
        </flux:navlist>
    </div>

    <flux:separator class="md:hidden" />

    <div class="flex-1 self-stretch max-md:pt-6">
        <flux:heading>{{ $heading ?? '' }}</flux:heading>
        <flux:subheading>{{ $subheading ?? '' }}</flux:subheading>

        <div class="mt-5 w-full max-w-lg">
            {{ $slot }}
        </div>
    </div>
</div>
