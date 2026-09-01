@foreach ($nodes as $node)
    <div class="mb-1 last:mb-0" wire:key="select-category-{{ $node['category']->id }}">
        <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400 {{ $depth > 0 ? 'mt-1' : '' }}">{{ $node['category']->name }}</div>
        <div class="ml-3">
            {{-- Child categories --}}
            @if (count($node['children']))
                @include('pages.chores._chore-select-tree', ['nodes' => $node['children'], 'depth' => $depth + 1])
            @endif

            {{-- Chores at this level --}}
            @foreach ($node['items'] as $chore)
                <label class="flex items-center gap-2 cursor-pointer py-0.5" wire:key="select-chore-{{ $chore->id }}">
                    <input type="checkbox" class="rounded border-zinc-300 dark:border-zinc-600" value="{{ $chore->id }}" wire:model="selectedChoreIds" />
                    <span>{{ $chore->name }}</span>
                </label>
            @endforeach
        </div>
    </div>
@endforeach
