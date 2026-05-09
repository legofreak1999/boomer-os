<?php

use App\Models\AppSetting;
use App\Models\HikeLocation;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Home Location')] class extends Component {
    public ?float $homeLat = null;
    public ?float $homeLng = null;

    public function mount(): void
    {
        $home = AppSetting::get('home_location');
        if ($home) {
            $this->homeLat = $home['lat'] ?? null;
            $this->homeLng = $home['lng'] ?? null;
        }
    }

    public function save(): void
    {
        $this->validate([
            'homeLat' => ['required', 'numeric', 'between:-90,90'],
            'homeLng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        AppSetting::set('home_location', [
            'lat' => $this->homeLat,
            'lng' => $this->homeLng,
        ]);

        Flux::toast(variant: 'success', text: 'Home location saved.');
    }

    public function recalculateDriveTimes(): void
    {
        if (! $this->homeLat || ! $this->homeLng) {
            Flux::toast('Set a home location first.', variant: 'danger');

            return;
        }

        $locations = HikeLocation::all();
        $count = 0;

        foreach ($locations as $location) {
            $location->calculateDriveTime($this->homeLat, $this->homeLng);
            $count++;
            usleep(200000); // 200ms delay between requests
        }

        Flux::toast("{$count} drive times calculated.");
    }
}; ?>

<section class="w-full">
    <div class="relative mb-6 w-full">
        <flux:heading size="xl" level="1">{{ __('Settings') }}</flux:heading>
        <flux:subheading size="lg" class="mb-6">{{ __('Manage your application settings') }}</flux:subheading>
        <flux:separator variant="subtle" />
    </div>

    <x-pages::settings-global.layout :heading="__('Home Location')" :subheading="__('Set your home for drive time calculations')">
        <div class="my-6 w-full space-y-6">
            @if ($homeLat && $homeLng)
                <flux:text>{{ number_format($homeLat, 5) }}, {{ number_format($homeLng, 5) }}</flux:text>
            @else
                <flux:text class="text-zinc-400">{{ __('Click the map to set your home location.') }}</flux:text>
            @endif

            <div wire:ignore id="home-map" style="height: 300px; border-radius: 0.5rem; z-index: 0;"></div>

            @error('homeLat') <div class="text-sm text-red-500">{{ __('Please set a location on the map.') }}</div> @enderror

            <div class="flex items-center gap-3">
                <flux:button variant="primary" wire:click="save">{{ __('Save') }}</flux:button>
                <flux:button variant="ghost" wire:click="recalculateDriveTimes" wire:confirm="{{ __('Recalculate drive times for all locations? This may take a moment.') }}">
                    {{ __('Recalculate Drive Times') }}
                </flux:button>
            </div>
        </div>
    </x-pages::settings-global.layout>
</section>

<script>
    function loadCSS(href) {
        if (document.querySelector('link[href="' + href + '"]')) return Promise.resolve();
        return new Promise(resolve => { const l = document.createElement('link'); l.rel = 'stylesheet'; l.href = href; l.onload = resolve; document.head.appendChild(l); });
    }
    function loadScript(src) {
        if (document.querySelector('script[src="' + src + '"]')) return Promise.resolve();
        return new Promise(resolve => { const s = document.createElement('script'); s.src = src; s.onload = resolve; document.head.appendChild(s); });
    }

    (async function () {
        await loadCSS('https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
        await loadScript('https://unpkg.com/leaflet@1.9.4/dist/leaflet.js');

        const mapEl = document.getElementById('home-map');
        if (!mapEl || mapEl._leaflet_id) return;

        const lat = $wire.homeLat || 52.37;
        const lng = $wire.homeLng || 4.90;
        const zoom = $wire.homeLat ? 14 : 8;

        const map = L.map('home-map').setView([lat, lng], zoom);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OSM', maxZoom: 19,
        }).addTo(map);

        let marker = null;
        if ($wire.homeLat && $wire.homeLng) {
            marker = L.marker([lat, lng], { draggable: true }).addTo(map);
            marker.on('dragend', () => {
                const p = marker.getLatLng();
                $wire.set('homeLat', p.lat);
                $wire.set('homeLng', p.lng);
            });
        }

        map.on('click', function (e) {
            if (marker) marker.setLatLng(e.latlng);
            else {
                marker = L.marker(e.latlng, { draggable: true }).addTo(map);
                marker.on('dragend', () => {
                    const p = marker.getLatLng();
                    $wire.set('homeLat', p.lat);
                    $wire.set('homeLng', p.lng);
                });
            }
            $wire.set('homeLat', e.latlng.lat);
            $wire.set('homeLng', e.latlng.lng);
        });
    })();
</script>
