<?php

use App\Models\HikeLocation;
use App\Models\HikeTag;
use App\Models\HikeTrail;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Hikes')] class extends Component {
    public string $search = '';
    public array $selectedTagIds = [];
    public ?string $difficulty = null;
    public ?int $maxDriveMinutes = null;
    public ?float $maxDistanceKm = null;
    public ?int $maxWalkMinutes = null;
    public bool $hideClosed = false;
    public ?int $selectedTrailId = null;

    #[Computed]
    public function availableTags()
    {
        return HikeTag::orderBy('name')->get();
    }

    #[Computed]
    public function locations()
    {
        return HikeLocation::with(['trails.tags', 'trails.closures', 'tags'])
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function filteredLocations(): array
    {
        $result = [];

        foreach ($this->locations as $location) {
            $trails = $location->trails->filter(function ($trail) use ($location) {
                // Search filter
                if ($this->search) {
                    $q = strtolower($this->search);
                    $matchesLocation = str_contains(strtolower($location->name), $q);
                    $matchesTrail = str_contains(strtolower($trail->name), $q);
                    if (! $matchesLocation && ! $matchesTrail) {
                        return false;
                    }
                }

                // Tag filter
                if (! empty($this->selectedTagIds)) {
                    $tagIds = array_map('intval', $this->selectedTagIds);
                    $locationTagIds = $location->tags->pluck('id')->all();
                    $trailTagIds = $trail->tags->pluck('id')->all();
                    $combined = array_unique(array_merge($locationTagIds, $trailTagIds));
                    if (empty(array_intersect($tagIds, $combined))) {
                        return false;
                    }
                }

                // Difficulty filter
                if ($this->difficulty && $trail->difficulty !== $this->difficulty) {
                    return false;
                }

                // Distance filter
                if ($this->maxDistanceKm && ($trail->distance_m / 1000) > $this->maxDistanceKm) {
                    return false;
                }

                // Walk time filter
                if ($this->maxWalkMinutes && ($trail->duration_s / 60) > $this->maxWalkMinutes) {
                    return false;
                }

                // Closed filter
                if ($this->hideClosed && $trail->isCurrentlyClosed()) {
                    return false;
                }

                return true;
            });

            // Drive time filter (location level)
            if ($this->maxDriveMinutes && $location->drive_duration_s) {
                if (($location->drive_duration_s / 60) > $this->maxDriveMinutes) {
                    continue;
                }
            }

            $hasTrailFilters = $this->difficulty || $this->maxDistanceKm || $this->maxWalkMinutes || $this->hideClosed;

            if ($trails->isNotEmpty() || (! $hasTrailFilters && $location->trails->isEmpty())) {
                $result[] = ['location' => $location, 'trails' => $trails->values()];
            }
        }

        return $result;
    }

    public function selectTrail(?int $trailId): void
    {
        $this->selectedTrailId = $this->selectedTrailId === $trailId ? null : $trailId;
    }

    #[Computed]
    public function mapData(): array
    {
        return collect($this->filteredLocations)->map(fn ($entry) => [
            'id' => $entry['location']->id,
            'name' => $entry['location']->name,
            'lat' => (float) $entry['location']->parking_lat,
            'lng' => (float) $entry['location']->parking_lng,
            'trail_count' => $entry['trails']->count(),
            'trails' => $entry['trails']->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'route_geojson' => $t->route_geojson,
            ])->all(),
        ])->values()->all();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'selectedTagIds', 'difficulty', 'maxDriveMinutes', 'maxDistanceKm', 'maxWalkMinutes', 'hideClosed');
    }
}; ?>

<div style="display: flex; flex-direction: column; height: calc(100vh - 4rem);">
    <div class="flex items-center justify-between mb-4" style="flex-shrink: 0;">
        <div>
            <flux:heading size="xl">{{ __('Hikes') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Explore hiking locations and trails.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" :href="route('hikes.create')" wire:navigate>
            {{ __('New Location') }}
        </flux:button>
    </div>

    <div class="flex gap-4" style="flex: 1; min-height: 0;">
        {{-- Map --}}
        <div style="flex: 3; min-width: 0;" class="max-lg:hidden">
            <div id="hike-map-data" data-locations="{{ json_encode($this->mapData) }}" style="display:none;"></div>
            <div wire:ignore id="hike-overview-map" style="width: 100%; height: 100%; border-radius: 0.5rem; z-index: 0;"></div>
        </div>

        {{-- List panel --}}
        <div style="flex: 2; min-width: 0;" class="flex flex-col">
            {{-- Filters --}}
            <div class="space-y-2 mb-4" style="flex-shrink: 0;">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Search locations & trails...') }}" size="sm" />

                <div class="flex flex-wrap gap-2">
                    @foreach ($this->availableTags as $tag)
                        <label class="inline-flex items-center cursor-pointer rounded-full border px-2 py-0.5 text-xs transition-colors {{ in_array((string) $tag->id, $selectedTagIds) ? 'bg-zinc-800 text-white border-zinc-800 dark:bg-zinc-200 dark:text-zinc-900 dark:border-zinc-200' : 'border-zinc-300 dark:border-zinc-600' }}">
                            <input type="checkbox" value="{{ $tag->id }}" wire:model.live="selectedTagIds" class="sr-only" />
                            {{ $tag->name }}
                        </label>
                    @endforeach
                </div>

                <div class="flex gap-2">
                    <flux:select wire:model.live="difficulty" placeholder="{{ __('Difficulty') }}" size="sm">
                        <flux:select.option value="">{{ __('All') }}</flux:select.option>
                        <flux:select.option value="easy">{{ __('Easy') }}</flux:select.option>
                        <flux:select.option value="moderate">{{ __('Moderate') }}</flux:select.option>
                        <flux:select.option value="hard">{{ __('Hard') }}</flux:select.option>
                    </flux:select>
                    <flux:input wire:model.live.debounce.500ms="maxDriveMinutes" type="number" placeholder="{{ __('Max drive (min)') }}" size="sm" min="1" />
                    <flux:input wire:model.live.debounce.500ms="maxDistanceKm" type="number" placeholder="{{ __('Max km') }}" size="sm" min="0" step="0.5" />
                    <flux:input wire:model.live.debounce.500ms="maxWalkMinutes" type="number" placeholder="{{ __('Max walk (min)') }}" size="sm" min="1" />
                </div>

                <div class="flex items-center justify-between">
                    <flux:field variant="inline">
                        <flux:label class="text-xs">{{ __('Hide closed') }}</flux:label>
                        <flux:switch wire:model.live="hideClosed" />
                    </flux:field>
                    <flux:button size="xs" variant="ghost" wire:click="clearFilters">{{ __('Clear filters') }}</flux:button>
                </div>
            </div>

            {{-- Location list --}}
            <div style="flex: 1; overflow-y: auto;" class="space-y-2">
                @forelse ($this->filteredLocations as $entry)
                    @php $location = $entry['location']; $trails = $entry['trails']; @endphp
                    <div x-data="{ open: false }" class="rounded-lg border border-zinc-200 dark:border-zinc-700">
                        <button type="button" @click="open = !open" class="w-full flex items-center justify-between p-3 text-left">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <flux:heading size="sm" class="truncate">{{ $location->name }}</flux:heading>
                                    @if ($location->drive_duration_s)
                                        <flux:badge size="sm" color="zinc">{{ $location->driveTimeFormatted() }}</flux:badge>
                                    @endif
                                </div>
                                <div class="flex gap-1 mt-1">
                                    @foreach ($location->tags as $tag)
                                        <span class="text-xs px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-500">{{ $tag->name }}</span>
                                    @endforeach
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0" @click.stop>
                                <flux:badge size="sm" color="zinc">{{ $trails->count() }} {{ __('trails') }}</flux:badge>
                                <flux:button size="xs" icon="pencil" variant="ghost" :href="route('hikes.edit', $location)" wire:navigate />
                                <svg class="size-4 text-zinc-400 transition-transform" :class="{ 'rotate-90': open }" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                            </div>
                        </button>

                        <div x-show="open" x-cloak class="border-t border-zinc-200 dark:border-zinc-700 p-3 space-y-1">
                            @foreach ($trails as $trail)
                                <div
                                    class="flex items-center justify-between rounded p-2 cursor-pointer transition-colors {{ $selectedTrailId === $trail->id ? 'bg-blue-50 dark:bg-blue-900/20' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}"
                                    wire:click="selectTrail({{ $trail->id }})"
                                >
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-medium truncate">{{ $trail->name }}</span>
                                            @if ($trail->difficulty)
                                                <flux:badge size="sm" color="{{ $trail->difficulty === 'hard' ? 'red' : ($trail->difficulty === 'moderate' ? 'orange' : 'green') }}">{{ ucfirst($trail->difficulty) }}</flux:badge>
                                            @endif
                                            @if ($trail->isCurrentlyClosed())
                                                <flux:badge size="sm" color="red">{{ __('Closed') }}</flux:badge>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <flux:text size="xs">{{ $trail->distanceKm() }} km</flux:text>
                                            <flux:text size="xs">{{ $trail->durationFormatted() }}</flux:text>
                                            @foreach ($trail->tags as $tag)
                                                <span class="text-xs px-1 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-500">{{ $tag->name }}</span>
                                            @endforeach
                                        </div>
                                    </div>
                                    <flux:button size="xs" icon="pencil" variant="ghost" :href="route('hikes.trails.edit', [$location, $trail])" wire:navigate wire:click.stop />
                                </div>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8">
                        <flux:text>{{ __('No locations match your filters.') }}</flux:text>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
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

        const mapEl = document.getElementById('hike-overview-map');
        if (!mapEl || mapEl._leaflet_id) return;

        const map = L.map('hike-overview-map').setView([52.2, 5.3], 8);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OSM', maxZoom: 19,
        }).addTo(map);

        let locationMarkers = {};
        let trailLayer = null;

        function renderLocations(locations) {
            Object.values(locationMarkers).forEach(m => map.removeLayer(m));
            locationMarkers = {};

            const bounds = [];
            locations.forEach(loc => {
                const marker = L.marker([loc.lat, loc.lng])
                    .addTo(map)
                    .bindPopup('<strong>' + loc.name + '</strong><br>' + loc.trail_count + ' trail(s)');
                locationMarkers[loc.id] = marker;
                bounds.push([loc.lat, loc.lng]);
            });

            if (bounds.length > 1) {
                map.fitBounds(bounds, { padding: [30, 30] });
            } else if (bounds.length === 1) {
                map.setView(bounds[0], 12);
            }
        }

        function showTrailRoute(geojson) {
            if (trailLayer) { map.removeLayer(trailLayer); trailLayer = null; }
            if (!geojson) return;

            trailLayer = L.geoJSON(geojson, {
                style: { color: '#3b82f6', weight: 4, opacity: 0.8 },
            }).addTo(map);

            map.fitBounds(trailLayer.getBounds().pad(0.1));
        }

        const dataEl = document.getElementById('hike-map-data');
        let currentData = JSON.parse(dataEl.dataset.locations || '[]');
        renderLocations(currentData);

        // Watch for trail selection
        $wire.$watch('selectedTrailId', trailId => {
            if (!trailId) { showTrailRoute(null); return; }
            for (const loc of currentData) {
                for (const trail of (loc.trails || [])) {
                    if (trail.id === trailId && trail.route_geojson) {
                        showTrailRoute(trail.route_geojson);
                        return;
                    }
                }
            }
            showTrailRoute(null);
        });

        // Re-render when Livewire updates the data div (filter changes)
        const observer = new MutationObserver(() => {
            const newData = dataEl.dataset.locations;
            if (newData) {
                currentData = JSON.parse(newData);
                renderLocations(currentData);
            }
        });
        observer.observe(dataEl, { attributes: true, attributeFilter: ['data-locations'] });
    })();
</script>
