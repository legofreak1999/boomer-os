@foreach ($nodes as $node)
    @php $catKey = $listId . '-' . $node['category']->id; @endphp
    <div class="mb-2 last:mb-0">
        <button type="button" class="flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400 mb-1" wire:click="toggleCategoryCollapse('{{ $catKey }}')">
            <svg class="size-3 transition-transform {{ in_array($catKey, $collapsedCategories) ? '' : 'rotate-90' }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
            {{ $node['category']->name }}
        </button>

        @if (! in_array($catKey, $collapsedCategories))
            <div class="ml-4">
                {{-- Child categories --}}
                @if (count($node['children']))
                    @include('pages.chores._list-category-tree', ['nodes' => $node['children'], 'listId' => $listId])
                @endif

                {{-- Chore items at this level --}}
                @if ($node['items']->isNotEmpty())
                    <div class="space-y-1">
                        @foreach ($node['items'] as $item)
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    class="rounded border-zinc-300 dark:border-zinc-600"
                                    {{ $item->is_checked ? 'checked' : '' }}
                                    wire:click="toggleChoreItem({{ $item->id }})"
                                />
                                <span class="{{ $item->is_checked ? 'line-through text-zinc-400 dark:text-zinc-500' : '' }}">
                                    {{ $item->chore->name }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </div>
@endforeach
