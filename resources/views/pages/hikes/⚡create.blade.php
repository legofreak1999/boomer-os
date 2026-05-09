<?php

use App\Models\HikeLocation;
use App\Models\HikeTag;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Create Location')] class extends Component {
    public string $name = '';
    public string $description = '';
    public ?float $parkingLat = null;
    public ?float $parkingLng = null;
    public array $selectedTagIds = [];

    #[Computed]
    public function availableTags()
    {
        return HikeTag::orderBy('name')->get();
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'parkingLat' => ['required', 'numeric', 'between:-90,90'],
            'parkingLng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $location = HikeLocation::create([
            'name' => $this->name,
            'description' => $this->description ?: null,
            'parking_lat' => $this->parkingLat,
            'parking_lng' => $this->parkingLng,
        ]);

        if (! empty($this->selectedTagIds)) {
            $location->tags()->sync($this->selectedTagIds);
        }

        Flux::toast('Location created.');
        $this->redirect(route('hikes.edit', $location), navigate: true);
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('New Location') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Add a new hiking location. Click the map to set the parking spot.') }}</flux:text>
        </div>
        <flux:button variant="ghost" :href="route('hikes.index')" wire:navigate icon="arrow-left">{{ __('Back') }}</flux:button>
    </div>

    <form wire:submit="save">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            {{-- Left: Form fields --}}
            <div class="space-y-6">
                <flux:input wire:model="name" :label="__('Name')" placeholder="{{ __('e.g. Veluwe National Park') }}" required autofocus />
                <flux:textarea wire:model="description" :label="__('Description')" placeholder="{{ __('Optional notes...') }}" rows="3" />

                <div>
                    <flux:label>{{ __('Tags') }}</flux:label>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($this->availableTags as $tag)
                            <label class="inline-flex items-center gap-1.5 cursor-pointer rounded-full border px-3 py-1 text-sm transition-colors {{ in_array((string) $tag->id, $selectedTagIds) ? 'bg-zinc-800 text-white border-zinc-800 dark:bg-zinc-200 dark:text-zinc-900 dark:border-zinc-200' : 'border-zinc-300 dark:border-zinc-600' }}">
                                <input type="checkbox" value="{{ $tag->id }}" wire:model.live="selectedTagIds" class="sr-only" />
                                {{ $tag->name }}
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Right: Parking map --}}
            <div>
                <div class="flex items-center justify-between">
                    <flux:label>{{ __('Parking Spot') }}</flux:label>
                    <button type="button" id="fullscreen-toggle" class="text-xs text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300 cursor-pointer">{{ __('Fullscreen') }}</button>
                </div>
                @if ($parkingLat && $parkingLng)
                    <flux:text size="sm" class="mt-1">{{ number_format($parkingLat, 5) }}, {{ number_format($parkingLng, 5) }}</flux:text>
                @else
                    <flux:text size="sm" class="mt-1 text-zinc-400">{{ __('Click the map to set the parking location') }}</flux:text>
                @endif
                <div wire:ignore id="parking-map" style="height: 400px; border-radius: 0.5rem; margin-top: 0.5rem; z-index: 0; transition: all 0.2s;"></div>
                @error('parkingLat') <div class="text-sm text-red-500 mt-1">{{ __('Please set a parking location on the map.') }}</div> @enderror
            </div>
        </div>

        <div class="flex justify-end mt-6">
            <flux:button variant="primary" type="submit">{{ __('Create Location') }}</flux:button>
        </div>
    </form>
</div>

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

        const mapEl = document.getElementById('parking-map');
        if (!mapEl || mapEl._leaflet_id) return;

        const initLat = $wire.parkingLat || 52.37;
        const initLng = $wire.parkingLng || 4.90;
        const initZoom = $wire.parkingLat ? 14 : 8;

        const map = L.map('parking-map').setView([initLat, initLng], initZoom);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OSM', maxZoom: 19,
        }).addTo(map);

        let marker = null;
        if ($wire.parkingLat && $wire.parkingLng) {
            marker = L.marker([initLat, initLng], { draggable: true }).addTo(map);
            marker.on('dragend', () => {
                const p = marker.getLatLng();
                $wire.set('parkingLat', p.lat);
                $wire.set('parkingLng', p.lng);
            });
        }

        map.on('click', function (e) {
            if (marker) map.removeLayer(marker);
            marker = L.marker(e.latlng, { draggable: true }).addTo(map);
            $wire.set('parkingLat', e.latlng.lat);
            $wire.set('parkingLng', e.latlng.lng);
            marker.on('dragend', () => {
                const p = marker.getLatLng();
                $wire.set('parkingLat', p.lat);
                $wire.set('parkingLng', p.lng);
            });
        });

        // Fullscreen toggle using native Fullscreen API
        const toggle = document.getElementById('fullscreen-toggle');
        const exitBtn = document.createElement('button');
        exitBtn.textContent = 'Exit Fullscreen';
        exitBtn.style.cssText = 'position:absolute;top:0.75rem;right:0.75rem;z-index:10000;background:#fff;color:#000;padding:0.5rem 1rem;border-radius:0.5rem;box-shadow:0 2px 8px rgba(0,0,0,0.3);font-size:0.875rem;font-weight:500;cursor:pointer;display:none;';
        mapEl.style.position = 'relative';
        mapEl.appendChild(exitBtn);

        exitBtn.addEventListener('click', () => document.exitFullscreen());
        if (toggle) {
            toggle.addEventListener('click', () => mapEl.requestFullscreen());
        }
        document.addEventListener('fullscreenchange', () => {
            map.invalidateSize();
            setTimeout(() => map.invalidateSize(), 50);
            setTimeout(() => map.invalidateSize(), 200);
            setTimeout(() => map.invalidateSize(), 500);
            exitBtn.style.display = document.fullscreenElement ? 'block' : 'none';
        });
    })();
</script>
