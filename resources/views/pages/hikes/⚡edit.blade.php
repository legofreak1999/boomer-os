<?php

use App\Models\HikeLocation;
use App\Models\HikeTag;
use App\Models\HikeTrail;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit Location')] class extends Component {
    public HikeLocation $hikeLocation;

    public string $name = '';
    public string $description = '';
    public ?float $parkingLat = null;
    public ?float $parkingLng = null;
    public array $selectedTagIds = [];

    public function mount(HikeLocation $hikeLocation): void
    {
        $this->hikeLocation = $hikeLocation;
        $this->name = $hikeLocation->name;
        $this->description = $hikeLocation->description ?? '';
        $this->parkingLat = (float) $hikeLocation->parking_lat;
        $this->parkingLng = (float) $hikeLocation->parking_lng;
        $this->selectedTagIds = $hikeLocation->tags->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    #[Computed]
    public function availableTags()
    {
        return HikeTag::orderBy('name')->get();
    }

    #[Computed]
    public function trails()
    {
        return $this->hikeLocation->trails()->with('closures')->orderBy('name')->get();
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'parkingLat' => ['required', 'numeric', 'between:-90,90'],
            'parkingLng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $this->hikeLocation->update([
            'name' => $this->name,
            'description' => $this->description ?: null,
            'parking_lat' => $this->parkingLat,
            'parking_lng' => $this->parkingLng,
        ]);

        $this->hikeLocation->tags()->sync(array_map('intval', $this->selectedTagIds));

        Flux::toast('Location updated.');
    }

    public function deleteTrail(int $id): void
    {
        HikeTrail::where('hike_location_id', $this->hikeLocation->id)->findOrFail($id)->delete();
        unset($this->trails);
        Flux::toast('Trail deleted.');
    }

    public function deleteLocation(): void
    {
        $this->hikeLocation->delete();
        Flux::toast('Location deleted.');
        $this->redirect(route('hikes.index'), navigate: true);
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Edit Location') }}</flux:heading>
            <flux:text class="mt-1">{{ $hikeLocation->name }}</flux:text>
        </div>
        <div class="flex items-center gap-2">
            <flux:button variant="ghost" :href="route('hikes.index')" wire:navigate icon="arrow-left">{{ __('Back') }}</flux:button>
            <flux:button variant="danger" wire:click="deleteLocation" wire:confirm="{{ __('Delete this location and all its trails?') }}" icon="trash">{{ __('Delete') }}</flux:button>
        </div>
    </div>

    <form wire:submit="save">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        {{-- Left: Location form --}}
        <div class="space-y-6">
                <flux:input wire:model="name" :label="__('Name')" required />
                <flux:textarea wire:model="description" :label="__('Description')" rows="3" />

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

                <div>
                    <div class="flex items-center justify-between">
                        <flux:label>{{ __('Parking Spot') }}</flux:label>
                        <button type="button" id="fullscreen-toggle" class="text-xs text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300 cursor-pointer">{{ __('Fullscreen') }}</button>
                    </div>
                    @if ($parkingLat && $parkingLng)
                        <flux:text size="sm" class="mt-1">{{ number_format($parkingLat, 5) }}, {{ number_format($parkingLng, 5) }}</flux:text>
                    @endif
                    <div wire:ignore id="parking-map" style="height: 400px; border-radius: 0.5rem; margin-top: 0.5rem; z-index: 0; transition: all 0.2s;"></div>
                </div>
        </div>

        {{-- Right: Trails --}}
        <div>
            <div class="flex items-center justify-between mb-4">
                <flux:heading size="lg">{{ __('Trails') }}</flux:heading>
                <flux:button variant="primary" icon="plus" size="sm" :href="route('hikes.trails.create', $hikeLocation)" wire:navigate>
                    {{ __('Add Trail') }}
                </flux:button>
            </div>

            @forelse ($this->trails as $trail)
                <div class="flex items-center justify-between rounded-lg border border-zinc-200 dark:border-zinc-700 p-4 mb-2">
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:heading size="sm">{{ $trail->name }}</flux:heading>
                            @if ($trail->difficulty)
                                <flux:badge size="sm" color="{{ $trail->difficulty === 'hard' ? 'red' : ($trail->difficulty === 'moderate' ? 'orange' : 'green') }}">{{ ucfirst($trail->difficulty) }}</flux:badge>
                            @endif
                            @if ($trail->isCurrentlyClosed())
                                <flux:badge size="sm" color="red">{{ __('Closed') }}</flux:badge>
                            @endif
                        </div>
                        <flux:text size="sm">{{ $trail->distanceKm() }} km &middot; {{ $trail->durationFormatted() }}</flux:text>
                    </div>
                    <div class="flex items-center gap-1">
                        <flux:button size="sm" icon="pencil" variant="ghost" :href="route('hikes.trails.edit', [$hikeLocation, $trail])" wire:navigate />
                        <flux:button size="sm" icon="trash" variant="ghost" wire:click="deleteTrail({{ $trail->id }})" wire:confirm="{{ __('Delete this trail?') }}" />
                    </div>
                </div>
            @empty
                <flux:text>{{ __('No trails yet. Add one to get started.') }}</flux:text>
            @endforelse

            <div class="flex justify-end mt-4">
                <flux:button variant="primary" type="submit">{{ __('Save Location') }}</flux:button>
            </div>
        </div>
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

        const lat = $wire.parkingLat || 52.37;
        const lng = $wire.parkingLng || 4.90;

        const map = L.map('parking-map').setView([lat, lng], 14);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OSM', maxZoom: 19,
        }).addTo(map);

        let marker = L.marker([lat, lng], { draggable: true }).addTo(map);
        marker.on('dragend', () => {
            const p = marker.getLatLng();
            $wire.set('parkingLat', p.lat);
            $wire.set('parkingLng', p.lng);
        });

        map.on('click', function (e) {
            marker.setLatLng(e.latlng);
            $wire.set('parkingLat', e.latlng.lat);
            $wire.set('parkingLng', e.latlng.lng);
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
