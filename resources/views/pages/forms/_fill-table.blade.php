@php
    $groups = collect($form->columns)->reduce(function ($acc, $col) {
        $catId = $col->form_column_category_id;
        if (! empty($acc) && end($acc)['catId'] === $catId) {
            $acc[count($acc) - 1]['count']++;
        } else {
            $acc[] = ['catId' => $catId, 'count' => 1, 'firstColId' => $col->id];
        }

        return $acc;
    }, []);
    $anyCategorized = collect($groups)->contains(fn ($g) => $g['catId'] !== null);

    // First column-id of each group (except the very first) marks where a new
    // category run begins — used to draw vertical separators in the table.
    $boundaryColumnIds = collect($groups)->skip(1)->pluck('firstColId')->all();
    $boundary = 'border-l-2 border-zinc-300 dark:border-zinc-600';
    $columnDivider = 'border-l border-zinc-200 dark:border-zinc-700';
    $firstColumnId = optional($form->columns->first())->id;

    $columnBorderClass = function (int $columnId) use ($boundaryColumnIds, $boundary, $columnDivider, $firstColumnId) {
        if ($columnId === $firstColumnId) {
            return '';
        }

        return in_array($columnId, $boundaryColumnIds, true) ? $boundary : $columnDivider;
    };
@endphp

<div class="rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-zinc-50 dark:bg-zinc-800/40">
            @if ($anyCategorized)
                <tr>
                    @foreach ($groups as $i => $g)
                        <th colspan="{{ $g['count'] }}" class="px-4 py-1 text-left text-xs font-medium {{ $i > 0 ? $boundary : '' }} {{ $g['catId'] !== null ? 'text-zinc-700 dark:text-zinc-200 bg-zinc-100 dark:bg-zinc-800/60 border-b border-zinc-200 dark:border-zinc-700' : 'text-transparent' }}">
                            {{ $g['catId'] !== null ? optional($form->columnCategories->firstWhere('id', $g['catId']))->name : '' }}
                        </th>
                    @endforeach
                </tr>
            @endif
            <tr>
                @foreach ($form->columns as $column)
                    <th class="px-4 py-2 text-left font-medium text-zinc-700 dark:text-zinc-300 border-b border-zinc-200 dark:border-zinc-700 {{ $columnBorderClass($column->id) }}">
                        <div class="flex items-center gap-1">
                            <span>{{ $column->label }}</span>
                            @if ($column->type === 'select')
                                <flux:icon name="chevron-up-down" variant="micro" class="size-3 text-zinc-400" />
                            @elseif ($column->type === 'textarea')
                                <flux:icon name="bars-3-bottom-left" variant="micro" class="size-3 text-zinc-400" />
                            @endif
                        </div>
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                @php $isLastRow = $loop->last; @endphp
                <tr wire:key="fill-row-{{ $row->id }}">
                    @foreach ($form->columns as $column)
                        @php
                            $isLocked = $locked[$row->id][$column->id] ?? false;
                            $cellValue = $cells[$row->id][$column->id] ?? null;
                        @endphp
                        <td class="px-4 py-2 align-middle {{ $columnBorderClass($column->id) }} {{ ! $isLastRow ? 'border-b border-zinc-200 dark:border-zinc-700' : '' }}">
                            @if ($isLocked)
                                <div class="inline-flex items-center gap-1 text-zinc-900 dark:text-zinc-100">
                                    <flux:icon name="lock-closed" variant="micro" class="size-3 text-zinc-500" />
                                    <span class="{{ in_array($column->type, ['textarea', 'select'], true) ? 'whitespace-pre' : '' }}">{{ $cellValue }}</span>
                                </div>
                            @elseif ($column->type === 'select')
                                <div class="flex flex-col items-start gap-y-1">
                                    @foreach ($column->options ?? [] as $optionRow)
                                        <div class="flex flex-wrap items-start gap-x-3">
                                            @foreach ((array) $optionRow as $option)
                                                @php $chosen = ($cellValue ?? null) === $option; @endphp
                                                @php
                                                    $optionBase = 'text-sm text-left';
                                                    $chosenStyle = 'font-medium text-zinc-900 dark:text-zinc-100 underline decoration-2 decoration-zinc-900 dark:decoration-zinc-100 underline-offset-4';
                                                    $strike = 'line-through decoration-2 decoration-zinc-900 dark:decoration-zinc-100';
                                                @endphp
                                                @if ($readOnly)
                                                    <span class="{{ $optionBase }} {{ $chosen ? $chosenStyle : "$strike text-zinc-400 dark:text-zinc-500" }}">{{ $option }}</span>
                                                @else
                                                    <button type="button"
                                                        wire:click="setCell({{ $row->id }}, {{ $column->id }}, @js($option))"
                                                        class="{{ $optionBase }} cursor-pointer transition-colors {{ $chosen ? $chosenStyle : ($cellValue === null ? 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' : "$strike text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300") }}"
                                                    >{{ $option }}</button>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endforeach
                                </div>
                            @elseif ($column->type === 'textarea')
                                @if ($readOnly)
                                    <div class="text-zinc-900 dark:text-zinc-100 min-h-[1.5rem] whitespace-pre">
                                        {{ $cellValue !== null && $cellValue !== '' ? $cellValue : '—' }}
                                    </div>
                                @else
                                    <flux:textarea
                                        wire:model.blur="cells.{{ $row->id }}.{{ $column->id }}"
                                        rows="1"
                                        size="sm"
                                        placeholder="{{ __('Type your answer') }}"
                                    />
                                @endif
                            @else
                                @if ($readOnly)
                                    <div class="text-zinc-900 dark:text-zinc-100 min-h-[1.5rem]">
                                        {{ $cellValue !== null && $cellValue !== '' ? $cellValue : '—' }}
                                    </div>
                                @else
                                    <flux:input
                                        wire:model.blur="cells.{{ $row->id }}.{{ $column->id }}"
                                        size="sm"
                                        placeholder="{{ __('Type your answer') }}"
                                    />
                                @endif
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
