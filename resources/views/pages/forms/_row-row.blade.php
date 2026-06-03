<div
    wire:key="row-{{ $row->id }}"
    wire:sort:item="{{ $row->id }}"
    class="group/row flex items-center gap-3 rounded-lg border border-zinc-200 dark:border-zinc-700 px-4 py-2 hover:bg-zinc-100 dark:hover:bg-zinc-700/50 transition-colors"
>
    <div wire:sort:handle class="cursor-grab active:cursor-grabbing text-zinc-300 hover:text-zinc-500 dark:text-zinc-600 dark:hover:text-zinc-400">
        <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
    </div>

    <div class="flex-1 min-w-0 flex items-center flex-wrap gap-x-3 gap-y-1">
        @if ($row->defaults->isEmpty())
            <flux:text size="sm" class="italic">{{ __('(no defaults)') }}</flux:text>
        @else
            @foreach ($row->defaults as $default)
                @php $column = $this->columns->firstWhere('id', $default->form_column_id); @endphp
                @if ($column)
                    <span class="text-xs text-zinc-700 dark:text-zinc-300 inline-flex items-center gap-1">
                        <span class="text-zinc-500">{{ $column->label }}:</span>
                        <span class="font-medium">{{ $default->value }}</span>
                        @if ($default->locked)
                            <flux:icon name="lock-closed" variant="micro" class="size-3 text-zinc-500" />
                        @endif
                    </span>
                @endif
            @endforeach
        @endif
    </div>

    <div class="flex items-center gap-0.5 shrink-0 invisible group-hover/row:visible">
        <flux:button size="xs" icon="pencil" variant="ghost" wire:click="editRow({{ $row->id }})" />
        <flux:button size="xs" icon="trash" variant="ghost" wire:click="deleteRow({{ $row->id }})" wire:confirm="{{ __('Delete this row and all answers?') }}" />
    </div>
</div>
