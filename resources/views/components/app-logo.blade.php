@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="Boomer OS" {{ $attributes }}>
        <x-slot name="logo" class="flex items-center justify-center" style="overflow: visible;">
            <x-app-logo-icon style="width: 3.5rem; height: 3.5rem; object-fit: contain; overflow: visible;" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="Boomer OS" {{ $attributes }}>
        <x-slot name="logo" class="flex items-center justify-center" style="overflow: visible;">
            <x-app-logo-icon style="width: 3.5rem; height: 3.5rem; object-fit: contain; overflow: visible;" />
        </x-slot>
    </flux:brand>
@endif
