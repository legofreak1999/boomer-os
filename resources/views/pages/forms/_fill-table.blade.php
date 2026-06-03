<div class="rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-zinc-50 dark:bg-zinc-800/40">
            <tr>
                @foreach ($form->columns as $column)
                    <th class="px-4 py-2 text-left font-medium text-zinc-700 dark:text-zinc-300">
                        <div class="flex items-center gap-1">
                            <span>{{ $column->label }}</span>
                            @if ($column->type === 'select')
                                <flux:icon name="chevron-up-down" variant="micro" class="size-3 text-zinc-400" />
                            @endif
                        </div>
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
            @foreach ($rows as $row)
                <tr wire:key="fill-row-{{ $row->id }}">
                    @foreach ($form->columns as $column)
                        @php
                            $isLocked = $locked[$row->id][$column->id] ?? false;
                            $cellValue = $cells[$row->id][$column->id] ?? null;
                        @endphp
                        <td class="px-4 py-2 align-middle">
                            @if ($isLocked)
                                <div class="inline-flex items-center gap-1 text-zinc-900 dark:text-zinc-100">
                                    <flux:icon name="lock-closed" variant="micro" class="size-3 text-zinc-500" />
                                    <span>{{ $cellValue }}</span>
                                </div>
                            @elseif ($readOnly)
                                <div class="text-zinc-900 dark:text-zinc-100 min-h-[1.5rem]">
                                    {{ $cellValue !== null && $cellValue !== '' ? $cellValue : '—' }}
                                </div>
                            @elseif ($column->type === 'select')
                                <flux:select
                                    wire:model.live="cells.{{ $row->id }}.{{ $column->id }}"
                                    size="sm"
                                    placeholder="{{ __('Choose...') }}"
                                >
                                    <flux:select.option :value="null">{{ __('(none)') }}</flux:select.option>
                                    @foreach ($column->options ?? [] as $option)
                                        <flux:select.option :value="$option">{{ $option }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            @else
                                <flux:input
                                    wire:model.blur="cells.{{ $row->id }}.{{ $column->id }}"
                                    size="sm"
                                    placeholder="{{ __('Type your answer') }}"
                                />
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
