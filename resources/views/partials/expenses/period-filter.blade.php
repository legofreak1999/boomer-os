{{-- Period filter bar - requires $viewMode, $startDate, $endDate on the component --}}
{{-- Pass $showYearMode = true to enable year option --}}
@php
    $showYearMode = $showYearMode ?? false;
    $currentStart = \Carbon\Carbon::parse($startDate);

    if ($viewMode === 'week') {
        $buttons = [];
        $currentWeekStart = $currentStart->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
        for ($i = -3; $i <= 3; $i++) {
            $w = $currentWeekStart->copy()->addWeeks($i);
            $buttons[] = [
                'action' => "selectWeek('{$w->format('Y-m-d')}')",
                'label' => 'W' . $w->isoWeek() . ' ' . $w->format('y'),
                'isCurrent' => $i === 0,
            ];
        }
        $prevAction = "selectWeek('" . $currentWeekStart->copy()->subWeek()->format('Y-m-d') . "')";
        $nextAction = "selectWeek('" . $currentWeekStart->copy()->addWeek()->format('Y-m-d') . "')";
    } elseif ($viewMode === 'year') {
        $buttons = [];
        $currentYear = $currentStart->year;
        for ($i = -3; $i <= 3; $i++) {
            $y = $currentYear + $i;
            $buttons[] = [
                'action' => "selectYear({$y})",
                'label' => (string) $y,
                'isCurrent' => $i === 0,
            ];
        }
        $prevAction = "selectYear(" . ($currentYear - 1) . ")";
        $nextAction = "selectYear(" . ($currentYear + 1) . ")";
    } else {
        $buttons = [];
        for ($i = -3; $i <= 3; $i++) {
            $m = $currentStart->copy()->startOfMonth()->addMonths($i);
            $buttons[] = [
                'action' => "selectMonth({$m->year}, {$m->month})",
                'label' => $m->isoFormat('MMM') . ' ' . $m->format('y'),
                'isCurrent' => $i === 0,
            ];
        }
        $prevMonth = $currentStart->copy()->startOfMonth()->subMonth();
        $nextMonth = $currentStart->copy()->startOfMonth()->addMonth();
        $prevAction = "selectMonth({$prevMonth->year}, {$prevMonth->month})";
        $nextAction = "selectMonth({$nextMonth->year}, {$nextMonth->month})";
    }
@endphp
<div style="display: flex; justify-content: space-between; align-items: flex-end; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
    <div style="display: flex; align-items: center; gap: 0.25rem;">
        {{-- Mode dropdown --}}
        <flux:dropdown>
            <flux:button size="sm" variant="ghost" icon-trailing="chevron-down">
                {{ $viewMode === 'week' ? __('Week') : ($viewMode === 'year' ? __('Year') : __('Month')) }}
            </flux:button>
            <flux:menu>
                <flux:menu.item wire:click="setViewMode('week')" icon="{{ $viewMode === 'week' ? 'check' : '' }}">{{ __('Week') }}</flux:menu.item>
                <flux:menu.item wire:click="setViewMode('month')" icon="{{ $viewMode === 'month' ? 'check' : '' }}">{{ __('Month') }}</flux:menu.item>
                @if ($showYearMode)
                    <flux:menu.item wire:click="setViewMode('year')" icon="{{ $viewMode === 'year' ? 'check' : '' }}">{{ __('Year') }}</flux:menu.item>
                @endif
            </flux:menu>
        </flux:dropdown>

        <span style="width: 1px; height: 1.25rem; margin: 0 0.25rem;" class="bg-zinc-300 dark:bg-zinc-600"></span>

        <flux:button size="sm" icon="chevron-left" variant="ghost" wire:click="{{ $prevAction }}" />
        @foreach ($buttons as $btn)
            <button
                type="button"
                wire:click="{{ $btn['action'] }}"
                style="padding: 0.375rem 0.625rem; border-radius: 0.375rem; font-size: 0.8125rem; font-weight: {{ $btn['isCurrent'] ? '700' : '500' }}; white-space: nowrap; transition: background-color 0.15s; {{ $btn['isCurrent'] ? 'background: #e4e4e7; color: #18181b;' : '' }}"
                class="{{ $btn['isCurrent'] ? '' : 'text-zinc-400 dark:text-zinc-500' }}"
                onmouseenter="if(!{{ $btn['isCurrent'] ? 'true' : 'false' }})this.style.backgroundColor='rgba(113,113,122,0.15)'"
                onmouseleave="if(!{{ $btn['isCurrent'] ? 'true' : 'false' }})this.style.backgroundColor=''"
            >{{ $btn['label'] }}</button>
        @endforeach
        <flux:button size="sm" icon="chevron-right" variant="ghost" wire:click="{{ $nextAction }}" />
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: flex-end;"
        x-data="{
            openPicker: null,
            pickerYear: 0,
            pickerMonth: 0,
            pickerView: 'days',
            yearRangeStart: 0,

            toDisplay(iso) {
                if (!iso) return '';
                const [y, m, d] = iso.split('-');
                return d + '-' + m + '-' + y;
            },
            toIso(display) {
                const parts = display.split('-');
                if (parts.length !== 3) return '';
                return parts[2] + '-' + parts[1] + '-' + parts[0];
            },
            startDisplay: '',
            endDisplay: '',
            init() {
                this.startDisplay = this.toDisplay($wire.startDate);
                this.endDisplay = this.toDisplay($wire.endDate);
                $watch('startDisplay', (v) => {
                    const iso = this.toIso(v);
                    if (iso.match(/^\d{4}-\d{2}-\d{2}$/)) $wire.set('startDate', iso);
                });
                $watch('endDisplay', (v) => {
                    const iso = this.toIso(v);
                    if (iso.match(/^\d{4}-\d{2}-\d{2}$/)) $wire.set('endDate', iso);
                });
            },

            togglePicker(which) {
                if (this.openPicker === which) { this.openPicker = null; return; }
                this.openPicker = which;
                this.pickerView = 'days';
                const iso = which === 'start' ? $wire.startDate : $wire.endDate;
                if (iso) {
                    const [y, m] = iso.split('-');
                    this.pickerYear = parseInt(y);
                    this.pickerMonth = parseInt(m);
                } else {
                    const now = new Date();
                    this.pickerYear = now.getFullYear();
                    this.pickerMonth = now.getMonth() + 1;
                }
                this.yearRangeStart = this.pickerYear - 4;
            },

            headerClick() {
                if (this.pickerView === 'days') { this.pickerView = 'months'; }
                else if (this.pickerView === 'months') { this.yearRangeStart = this.pickerYear - 4; this.pickerView = 'years'; }
            },

            headerText() {
                if (this.pickerView === 'days') return this.monthNames[this.pickerMonth-1] + ' ' + this.pickerYear;
                if (this.pickerView === 'months') return String(this.pickerYear);
                return this.yearRangeStart + ' - ' + (this.yearRangeStart + 8);
            },

            prev() {
                if (this.pickerView === 'days') { this.pickerMonth--; if (this.pickerMonth < 1) { this.pickerMonth = 12; this.pickerYear--; } }
                else if (this.pickerView === 'months') { this.pickerYear--; }
                else { this.yearRangeStart -= 9; }
            },
            next() {
                if (this.pickerView === 'days') { this.pickerMonth++; if (this.pickerMonth > 12) { this.pickerMonth = 1; this.pickerYear++; } }
                else if (this.pickerView === 'months') { this.pickerYear++; }
                else { this.yearRangeStart += 9; }
            },

            pickMonth(m) { this.pickerMonth = m; this.pickerView = 'days'; },
            pickYear(y) { this.pickerYear = y; this.yearRangeStart = y - 4; this.pickerView = 'months'; },

            getDays() {
                const first = new Date(this.pickerYear, this.pickerMonth - 1, 1);
                const startDay = (first.getDay() + 6) % 7;
                const daysInMonth = new Date(this.pickerYear, this.pickerMonth, 0).getDate();
                const daysInPrev = new Date(this.pickerYear, this.pickerMonth - 1, 0).getDate();
                let days = [];
                for (let i = startDay - 1; i >= 0; i--) days.push({ day: daysInPrev - i, current: false, iso: '' });
                for (let i = 1; i <= daysInMonth; i++) {
                    const m = String(this.pickerMonth).padStart(2, '0');
                    const d = String(i).padStart(2, '0');
                    days.push({ day: i, current: true, iso: this.pickerYear + '-' + m + '-' + d });
                }
                const remaining = 7 - (days.length % 7);
                if (remaining < 7) for (let i = 1; i <= remaining; i++) days.push({ day: i, current: false, iso: '' });
                return days;
            },

            getYears() {
                let years = [];
                for (let i = 0; i < 9; i++) years.push(this.yearRangeStart + i);
                return years;
            },

            pickDate(iso) {
                if (!iso) return;
                if (this.openPicker === 'start') {
                    $wire.set('startDate', iso);
                    this.startDisplay = this.toDisplay(iso);
                } else {
                    $wire.set('endDate', iso);
                    this.endDisplay = this.toDisplay(iso);
                }
                this.openPicker = null;
            },

            isSelected(iso) {
                if (!iso) return false;
                if (this.openPicker === 'start') return iso === $wire.startDate;
                return iso === $wire.endDate;
            },

            monthNames: ['jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'],
        }"
        x-effect="startDisplay = toDisplay($wire.startDate); endDisplay = toDisplay($wire.endDate);"
        @click.outside="openPicker = null"
    >
        @foreach (['start' => __('From'), 'end' => __('To')] as $pickerId => $pickerLabel)
            <div style="position: relative;">
                <label style="display: block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;" class="text-zinc-500 dark:text-zinc-400">{{ $pickerLabel }}</label>
                <input type="text" x-model.debounce.300ms="{{ $pickerId }}Display" placeholder="dd-mm-jjjj" @click="togglePicker('{{ $pickerId }}')" style="width: 7rem; padding: 0.375rem 0.5rem; border-radius: 0.375rem; font-size: 0.8125rem; border: 1px solid; font-variant-numeric: tabular-nums; cursor: pointer;" class="border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100" readonly />
                <div x-show="openPicker === '{{ $pickerId }}'" x-cloak style="position: absolute; top: 100%; right: 0; margin-top: 0.5rem; z-index: 50; width: 16rem; padding: 0.75rem; border-radius: 0.5rem; border: 1px solid; box-shadow: 0 10px 25px rgba(0,0,0,0.3);" class="border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-800">
                    <template x-if="openPicker === '{{ $pickerId }}'">
                        <div>
                            {{-- Header: nav arrows + clickable title --}}
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                <button type="button" @click="prev()" style="padding: 0.25rem 0.5rem; font-size: 1rem;" class="text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100">&lsaquo;</button>
                                <button type="button" @click="headerClick()" style="font-size: 0.8125rem; font-weight: 600; padding: 0.125rem 0.5rem; border-radius: 0.25rem;" class="text-zinc-900 dark:text-zinc-100" onmouseenter="this.style.backgroundColor='rgba(113,113,122,0.15)'" onmouseleave="this.style.backgroundColor=''" x-text="headerText()"></button>
                                <button type="button" @click="next()" style="padding: 0.25rem 0.5rem; font-size: 1rem;" class="text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100">&rsaquo;</button>
                            </div>

                            {{-- Days view --}}
                            <template x-if="pickerView === 'days'">
                                <div>
                                    <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 1px; text-align: center; font-size: 0.6875rem; margin-bottom: 0.25rem;" class="text-zinc-400 dark:text-zinc-500">
                                        <span>ma</span><span>di</span><span>wo</span><span>do</span><span>vr</span><span>za</span><span>zo</span>
                                    </div>
                                    <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 1px;">
                                        <template x-for="(d, i) in getDays()" :key="i">
                                            <button type="button" @click="pickDate(d.iso)" :disabled="!d.current" style="padding: 0.25rem; border-radius: 0.25rem; font-size: 0.75rem; text-align: center;" :style="isSelected(d.iso) ? 'background: #f4f4f5; color: #18181b; font-weight: 700;' : ''" :class="d.current ? 'text-zinc-900 dark:text-zinc-100 hover:bg-zinc-100 dark:hover:bg-zinc-700' : 'text-zinc-300 dark:text-zinc-600'" x-text="d.day"></button>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            {{-- Months view --}}
                            <template x-if="pickerView === 'months'">
                                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.25rem;">
                                    <template x-for="(name, idx) in monthNames" :key="idx">
                                        <button type="button" @click="pickMonth(idx + 1)" style="padding: 0.5rem; border-radius: 0.25rem; font-size: 0.8125rem; text-align: center;" :style="(idx + 1) === pickerMonth ? 'background: #f4f4f5; color: #18181b; font-weight: 700;' : ''" class="text-zinc-900 dark:text-zinc-100" onmouseenter="this.style.backgroundColor='rgba(113,113,122,0.15)'" onmouseleave="this.style.backgroundColor=''" x-text="name"></button>
                                    </template>
                                </div>
                            </template>

                            {{-- Years view --}}
                            <template x-if="pickerView === 'years'">
                                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.25rem;">
                                    <template x-for="y in getYears()" :key="y">
                                        <button type="button" @click="pickYear(y)" style="padding: 0.5rem; border-radius: 0.25rem; font-size: 0.8125rem; text-align: center;" :style="y === pickerYear ? 'background: #f4f4f5; color: #18181b; font-weight: 700;' : ''" class="text-zinc-900 dark:text-zinc-100" onmouseenter="this.style.backgroundColor='rgba(113,113,122,0.15)'" onmouseleave="this.style.backgroundColor=''" x-text="y"></button>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        @endforeach
    </div>
</div>
