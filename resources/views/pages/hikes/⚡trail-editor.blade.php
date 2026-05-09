<?php

use App\Models\HikeLocation;
use App\Models\HikeTag;
use App\Models\HikeTrail;
use App\Models\HikeTrailClosure;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Trail Editor')] class extends Component {
    public HikeLocation $hikeLocation;
    public ?HikeTrail $hikeTrail = null;

    public string $trailName = '';
    public string $trailDescription = '';
    public ?string $difficulty = null;
    public array $waypoints = [];
    public ?array $routeGeojson = null;
    public int $distanceM = 0;
    public int $durationS = 0;
    public array $selectedTagIds = [];

    // Closure form
    public array $closures = [];
    public string $closureStartDate = '';
    public string $closureEndDate = '';
    public string $closureReason = '';

    public function mount(HikeLocation $hikeLocation, ?HikeTrail $hikeTrail = null): void
    {
        $this->hikeLocation = $hikeLocation;

        if ($hikeTrail) {
            $this->hikeTrail = $hikeTrail;
            $this->trailName = $hikeTrail->name;
            $this->trailDescription = $hikeTrail->description ?? '';
            $this->difficulty = $hikeTrail->difficulty;
            $this->waypoints = $hikeTrail->waypoints ?? [];
            $this->routeGeojson = $hikeTrail->route_geojson;
            $this->distanceM = $hikeTrail->distance_m;
            $this->durationS = $hikeTrail->duration_s;
            $this->selectedTagIds = $hikeTrail->tags->pluck('id')->map(fn ($id) => (string) $id)->all();
            $this->closures = $hikeTrail->closures->map(fn ($c) => [
                'id' => $c->id,
                'start_date' => $c->start_date->format('Y-m-d'),
                'end_date' => $c->end_date->format('Y-m-d'),
                'reason' => $c->reason ?? '',
            ])->all();
        }
    }

    #[Computed]
    public function availableTags()
    {
        return HikeTag::orderBy('name')->get();
    }

    public function updateRouteData(array $waypoints, ?array $geojson, int $distanceM, int $durationS): void
    {
        $this->waypoints = $waypoints;
        $this->routeGeojson = $geojson;
        $this->distanceM = $distanceM;
        $this->durationS = $durationS;
    }

    public function addClosure(): void
    {
        $this->validate([
            'closureStartDate' => ['required', 'date'],
            'closureEndDate' => ['required', 'date', 'after_or_equal:closureStartDate'],
        ]);

        $this->closures[] = [
            'id' => null,
            'start_date' => $this->closureStartDate,
            'end_date' => $this->closureEndDate,
            'reason' => $this->closureReason,
        ];

        $this->reset('closureStartDate', 'closureEndDate', 'closureReason');
    }

    public function removeClosure(int $index): void
    {
        unset($this->closures[$index]);
        $this->closures = array_values($this->closures);
    }

    public function save(): void
    {
        $this->validate([
            'trailName' => ['required', 'string', 'max:255'],
            'trailDescription' => ['nullable', 'string', 'max:5000'],
            'difficulty' => ['nullable', 'in:'.implode(',', HikeTrail::DIFFICULTIES)],
            'waypoints' => ['required', 'array', 'min:2'],
        ]);

        $data = [
            'hike_location_id' => $this->hikeLocation->id,
            'name' => $this->trailName,
            'description' => $this->trailDescription ?: null,
            'difficulty' => $this->difficulty ?: null,
            'waypoints' => $this->waypoints,
            'route_geojson' => $this->routeGeojson,
            'distance_m' => $this->distanceM,
            'duration_s' => $this->durationS,
        ];

        if ($this->hikeTrail) {
            $this->hikeTrail->update($data);
            $trail = $this->hikeTrail;
        } else {
            $trail = HikeTrail::create($data);
        }

        $trail->tags()->sync(array_map('intval', $this->selectedTagIds));

        // Sync closures
        $existingIds = collect($this->closures)->pluck('id')->filter()->all();
        $trail->closures()->whereNotIn('id', $existingIds)->delete();

        foreach ($this->closures as $closure) {
            if ($closure['id']) {
                HikeTrailClosure::where('id', $closure['id'])->update([
                    'start_date' => $closure['start_date'],
                    'end_date' => $closure['end_date'],
                    'reason' => $closure['reason'] ?: null,
                ]);
            } else {
                $trail->closures()->create([
                    'start_date' => $closure['start_date'],
                    'end_date' => $closure['end_date'],
                    'reason' => $closure['reason'] ?: null,
                ]);
            }
        }

        Flux::toast($this->hikeTrail ? 'Trail updated.' : 'Trail created.');
        $this->redirect(route('hikes.edit', $this->hikeLocation), navigate: true);
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ $hikeTrail ? __('Edit Trail') : __('New Trail') }}</flux:heading>
            <flux:text class="mt-1">{{ $hikeLocation->name }}</flux:text>
        </div>
        <flux:button variant="ghost" :href="route('hikes.edit', $hikeLocation)" wire:navigate icon="arrow-left">{{ __('Back') }}</flux:button>
    </div>

    <form wire:submit="save">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Map --}}
        <div>
            <div class="flex items-center justify-between mb-2">
                <flux:label>{{ __('Route') }}</flux:label>
                <button type="button" id="fullscreen-toggle" class="text-xs text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300 cursor-pointer">{{ __('Fullscreen') }}</button>
            </div>
            <div wire:ignore id="trail-map"
                data-parking-lat="{{ $hikeLocation->parking_lat }}"
                data-parking-lng="{{ $hikeLocation->parking_lng }}"
                data-waypoints="{{ json_encode($waypoints) }}"
                data-route-geojson="{{ json_encode($routeGeojson) }}"
                style="height: 500px; border-radius: 0.5rem; z-index: 0; transition: all 0.2s;"></div>
            <div class="flex items-center gap-4 mt-3">
                <flux:text size="sm"><strong>{{ number_format($distanceM / 1000, 2) }} km</strong></flux:text>
                <flux:text size="sm">
                    @php
                        $h = intdiv($durationS, 3600);
                        $m = intdiv($durationS % 3600, 60);
                    @endphp
                    <strong>{{ $h > 0 ? $h . 'h ' . $m . 'min' : $m . ' min' }}</strong>
                </flux:text>
                <flux:text size="sm" class="text-zinc-400">{{ count($waypoints) }} {{ __('waypoints') }}</flux:text>
            </div>
        </div>

        {{-- Form --}}
        <div class="space-y-6">
                <flux:input wire:model="trailName" :label="__('Name')" placeholder="{{ __('e.g. Blue Loop') }}" required />
                <flux:textarea wire:model="trailDescription" :label="__('Description')" rows="3" />

                <flux:select wire:model="difficulty" :label="__('Difficulty')" placeholder="{{ __('Select...') }}">
                    <flux:select.option value="easy">{{ __('Easy') }}</flux:select.option>
                    <flux:select.option value="moderate">{{ __('Moderate') }}</flux:select.option>
                    <flux:select.option value="hard">{{ __('Hard') }}</flux:select.option>
                </flux:select>

                <div>
                    <flux:label>{{ __('Tags') }}</flux:label>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($this->availableTags as $tag)
                            <label class="inline-flex items-center cursor-pointer rounded-full border px-3 py-1 text-sm transition-colors {{ in_array((string) $tag->id, $selectedTagIds) ? 'bg-zinc-800 text-white border-zinc-800 dark:bg-zinc-200 dark:text-zinc-900 dark:border-zinc-200' : 'border-zinc-300 dark:border-zinc-600' }}">
                                <input type="checkbox" value="{{ $tag->id }}" wire:model.live="selectedTagIds" class="sr-only" />
                                {{ $tag->name }}
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- Closures --}}
                <div>
                    <flux:label>{{ __('Seasonal Closures') }}</flux:label>
                    @foreach ($closures as $index => $closure)
                        <div class="flex items-center gap-2 mt-2 rounded border border-zinc-200 dark:border-zinc-700 p-2">
                            <flux:text size="sm" class="flex-1">
                                {{ \Carbon\Carbon::parse($closure['start_date'])->isoFormat('D MMM Y') }} &mdash;
                                {{ \Carbon\Carbon::parse($closure['end_date'])->isoFormat('D MMM Y') }}
                                @if ($closure['reason'])
                                    &middot; {{ $closure['reason'] }}
                                @endif
                            </flux:text>
                            <flux:button size="xs" icon="trash" variant="ghost" wire:click="removeClosure({{ $index }})" />
                        </div>
                    @endforeach

                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <flux:input wire:model="closureStartDate" type="date" placeholder="{{ __('Start') }}" size="sm" />
                        <flux:input wire:model="closureEndDate" type="date" placeholder="{{ __('End') }}" size="sm" />
                    </div>
                    <flux:input wire:model="closureReason" placeholder="{{ __('Reason (optional)') }}" size="sm" class="mt-2" />
                    <flux:button size="sm" variant="ghost" icon="plus" wire:click="addClosure" class="mt-2">{{ __('Add Closure') }}</flux:button>
                </div>

                @error('waypoints') <div class="text-sm text-red-500">{{ __('Add at least 2 waypoints on the map.') }}</div> @enderror
        </div>
    </div>

    <div class="flex justify-end mt-6">
        <flux:button variant="primary" type="submit">{{ $hikeTrail ? __('Update Trail') : __('Create Trail') }}</flux:button>
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
        await loadScript('https://unpkg.com/leaflet-polylinedecorator@1.6.0/dist/leaflet.polylineDecorator.js');

        const mapEl = document.getElementById('trail-map');
        if (!mapEl || mapEl._leaflet_id) return;

        const parkingLat = parseFloat(mapEl.dataset.parkingLat);
        const parkingLng = parseFloat(mapEl.dataset.parkingLng);
        const existingWaypoints = JSON.parse(mapEl.dataset.waypoints || '[]');

        const map = L.map('trail-map').setView([parkingLat, parkingLng], 14);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OSM', maxZoom: 19,
        }).addTo(map);

        // Parking marker
        L.circleMarker([parkingLat, parkingLng], {
            radius: 8, fillColor: '#10b981', color: '#059669', weight: 2, fillOpacity: 0.8,
        }).addTo(map).bindPopup('Parking');

        let waypoints = [];
        let markers = [];
        let routeLayers = [];

        function makeNumberedIcon(number, straight) {
            const bg = straight ? '#f59e0b' : '#3b82f6';
            const border = straight ? '#d97706' : '#2563eb';
            return L.divIcon({
                className: '',
                iconSize: [28, 28],
                iconAnchor: [14, 14],
                html: '<div style="width:28px;height:28px;border-radius:50%;background:' + bg + ';border:2px solid ' + border + ';color:#fff;font-size:12px;font-weight:700;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 4px rgba(0,0,0,0.3);">' + number + '</div>',
            });
        }

        function refreshMarkerIcons() {
            markers.forEach((marker, idx) => {
                marker.setIcon(makeNumberedIcon(idx + 1, waypoints[idx].straight));
            });
        }

        function clearRouteLayers() {
            routeLayers.forEach(l => map.removeLayer(l));
            routeLayers = [];
        }

        function addArrowDecorator(polyline, color) {
            const d = L.polylineDecorator(polyline, {
                patterns: [{ offset: 25, repeat: 80, symbol: L.Symbol.arrowHead({ pixelSize: 10, polygon: false, pathOptions: { stroke: true, color: color, weight: 2, opacity: 0.8 } }) }],
            }).addTo(map);
            routeLayers.push(d);
        }

        function haversineDistance(a, b) {
            const R = 6371000, toRad = x => x * Math.PI / 180;
            const dLat = toRad(b.lat - a.lat), dLng = toRad(b.lng - a.lng);
            const h = Math.sin(dLat/2)**2 + Math.cos(toRad(a.lat)) * Math.cos(toRad(b.lat)) * Math.sin(dLng/2)**2;
            return R * 2 * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h));
        }

        function syncToLivewire(totalDistance, totalDuration, geojsonParts) {
            const wpData = waypoints.map(wp => ({ lat: wp.latlng.lat, lng: wp.latlng.lng, straight: wp.straight }));
            const geojson = geojsonParts.length ? { type: 'FeatureCollection', features: geojsonParts } : null;
            $wire.updateRouteData(wpData, geojson, Math.round(totalDistance), Math.round(totalDuration));
        }

        let segmentClickLayers = [];

        function addSegmentClickTargets() {
            segmentClickLayers.forEach(l => map.removeLayer(l));
            segmentClickLayers = [];

            for (let s = 0; s < waypoints.length - 1; s++) {
                const a = waypoints[s].latlng;
                const b = waypoints[s + 1].latlng;
                const clickLine = L.polyline([a, b], { weight: 20, opacity: 0, interactive: true }).addTo(map);
                clickLine._segmentAfter = s + 1;
                clickLine.on('click', e => {
                    L.DomEvent.stopPropagation(e);
                    insertWaypoint(e.latlng, clickLine._segmentAfter);
                });
                segmentClickLayers.push(clickLine);
            }
        }

        async function calculateRoute() {
            clearRouteLayers();
            if (waypoints.length < 2) { syncToLivewire(0, 0, []); return; }

            let totalDistance = 0, totalDuration = 0;
            const geojsonParts = [];
            let i = 0;

            while (i < waypoints.length - 1) {
                const nextWp = waypoints[i + 1];
                if (nextWp.straight) {
                    const a = waypoints[i].latlng, b = nextWp.latlng;
                    const line = L.polyline([a, b], { color: '#f59e0b', weight: 4, opacity: 0.8, dashArray: '8, 8' }).addTo(map);
                    routeLayers.push(line);
                    addArrowDecorator(line, '#f59e0b');
                    const dist = haversineDistance(a, b);
                    totalDistance += dist;
                    totalDuration += dist / 1.2;
                    i++;
                } else {
                    const group = [waypoints[i]];
                    while (i + 1 < waypoints.length && !waypoints[i + 1].straight) { group.push(waypoints[i + 1]); i++; }
                    if (group.length >= 2) {
                        const coords = group.map(wp => wp.latlng.lng + ',' + wp.latlng.lat).join(';');
                        try {
                            const resp = await fetch('https://routing.openstreetmap.de/routed-foot/route/v1/driving/' + coords + '?overview=full&geometries=geojson');
                            const data = await resp.json();
                            if (data.code === 'Ok' && data.routes.length) {
                                const route = data.routes[0];
                                const layer = L.geoJSON(route.geometry, { style: { color: '#3b82f6', weight: 4, opacity: 0.8 } }).addTo(map);
                                routeLayers.push(layer);
                                layer.eachLayer(l => { if (l.getLatLngs) addArrowDecorator(l, '#3b82f6'); });
                                totalDistance += route.distance;
                                totalDuration += route.duration;
                                geojsonParts.push({ type: 'Feature', geometry: route.geometry, properties: {} });
                            }
                        } catch (err) { console.error('Route error:', err); }
                    }
                    if (i + 1 < waypoints.length && waypoints[i + 1].straight) continue;
                    i++;
                }
            }

            syncToLivewire(totalDistance, totalDuration, geojsonParts);
            addSegmentClickTargets();
        }

        function insertWaypoint(latlng, atIndex, straight = false) {
            const wp = { latlng, straight };
            waypoints.splice(atIndex, 0, wp);

            const marker = L.marker(latlng, { draggable: true, icon: makeNumberedIcon(atIndex + 1, straight) }).addTo(map);

            marker.on('click', e => {
                L.DomEvent.stopPropagation(e);
                const idx = markers.indexOf(marker);
                if (idx > 0) { waypoints[idx].straight = !waypoints[idx].straight; refreshMarkerIcons(); calculateRoute(); }
            });
            marker.on('drag', () => { const idx = markers.indexOf(marker); if (idx !== -1) waypoints[idx].latlng = marker.getLatLng(); });
            marker.on('dragend', () => calculateRoute());
            marker.on('contextmenu', e => {
                L.DomEvent.preventDefault(e);
                const idx = markers.indexOf(marker);
                if (idx !== -1) { waypoints.splice(idx, 1); markers.splice(idx, 1); map.removeLayer(marker); refreshMarkerIcons(); calculateRoute(); }
            });

            markers.splice(atIndex, 0, marker);
            refreshMarkerIcons();
            calculateRoute();
        }

        function addWaypoint(latlng, straight = false) {
            insertWaypoint(latlng, waypoints.length, straight);
        }




        map.on('click', e => addWaypoint(e.latlng));

        // Load existing waypoints without triggering route calculation for each
        let initialLoad = true;
        if (existingWaypoints.length) {
            existingWaypoints.forEach((wp) => {
                const latlng = L.latLng(wp.lat, wp.lng);
                const wpObj = { latlng, straight: wp.straight || false };
                waypoints.push(wpObj);

                const marker = L.marker(latlng, { draggable: true, icon: makeNumberedIcon(waypoints.length, wpObj.straight) }).addTo(map);

                marker.on('click', e => {
                    L.DomEvent.stopPropagation(e);
                    const idx = markers.indexOf(marker);
                    if (idx > 0) { waypoints[idx].straight = !waypoints[idx].straight; refreshMarkerIcons(); calculateRoute(); }
                });
                marker.on('drag', () => { const idx = markers.indexOf(marker); if (idx !== -1) waypoints[idx].latlng = marker.getLatLng(); });
                marker.on('dragend', () => calculateRoute());
                marker.on('contextmenu', e => {
                    L.DomEvent.preventDefault(e);
                    const idx = markers.indexOf(marker);
                    if (idx !== -1) { waypoints.splice(idx, 1); markers.splice(idx, 1); map.removeLayer(marker); refreshMarkerIcons(); calculateRoute(); }
                });

                markers.push(marker);
            });

            // Show cached route geometry if available, otherwise calculate
            const cachedGeojson = mapEl.dataset.routeGeojson;
            if (cachedGeojson && cachedGeojson !== 'null') {
                const geojson = JSON.parse(cachedGeojson);
                const layer = L.geoJSON(geojson, { style: { color: '#3b82f6', weight: 4, opacity: 0.8 } }).addTo(map);
                routeLayers.push(layer);
                addSegmentClickTargets();
            } else {
                calculateRoute();
            }

            if (markers.length) {
                const bounds = L.latLngBounds(markers.map(m => m.getLatLng()));
                map.fitBounds(bounds.pad(0.1));
            }
        }
        initialLoad = false;

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
