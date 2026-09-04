@foreach ($nodes as $node)
    @php $catKey = $listId . '-' . $node['category']->id; @endphp
    <div class="mb-2 last:mb-0" wire:key="category-{{ $catKey }}">
        <div class="group/cat flex items-center gap-1 mb-1">
            <button type="button" class="flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400" wire:click="toggleCategoryCollapse('{{ $catKey }}')">
                <svg class="size-3 transition-transform {{ in_array($catKey, $collapsedCategories) ? '' : 'rotate-90' }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                {{ $node['category']->name }}
            </button>

            <div class="ml-auto flex items-center gap-1 opacity-0 group-hover/cat:opacity-100 transition-opacity">
                {{-- Bulk assign user --}}
                <flux:dropdown position="bottom" align="end" class="flex items-center">
                    <button type="button" class="inline-flex items-center justify-center size-4">
                        <svg class="size-3.5 text-zinc-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z" /></svg>
                    </button>
                    <flux:menu>
                        <flux:menu.heading>{{ __('Assign to all') }}</flux:menu.heading>
                        @foreach ($this->users as $user)
                            <flux:menu.item wire:click="bulkAssignUser({{ $listId }}, {{ $node['category']->id }}, {{ $user->id }})" keep-open>
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center justify-center size-5 rounded-full bg-zinc-200 dark:bg-zinc-600 text-[10px] font-medium leading-none text-zinc-700 dark:text-zinc-200">
                                        {{ $user->initials() }}
                                    </span>
                                    {{ $user->name }}
                                </div>
                            </flux:menu.item>
                        @endforeach
                        <flux:menu.separator />
                        <flux:menu.heading>{{ __('Remove from all') }}</flux:menu.heading>
                        @foreach ($this->users as $user)
                            <flux:menu.item wire:click="bulkRemoveUser({{ $listId }}, {{ $node['category']->id }}, {{ $user->id }})" keep-open>
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center justify-center size-5 rounded-full bg-zinc-200 dark:bg-zinc-600 text-[10px] font-medium leading-none text-zinc-700 dark:text-zinc-200">
                                        {{ $user->initials() }}
                                    </span>
                                    {{ $user->name }}
                                </div>
                            </flux:menu.item>
                        @endforeach
                        <flux:menu.separator />
                        <flux:menu.item wire:click="bulkClearAssignees({{ $listId }}, {{ $node['category']->id }})">
                            {{ __('Clear all assignees') }}
                        </flux:menu.item>
                    </flux:menu>
                </flux:dropdown>

                {{-- Bulk priority --}}
                <flux:dropdown position="bottom" align="end" class="flex items-center">
                    <button type="button" class="inline-flex items-center justify-center size-4">
                        <svg class="size-3.5 text-zinc-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v1.5M3 21v-6m0 0 2.77-.693a9 9 0 0 1 6.208.682l.108.054a9 9 0 0 0 6.086.71l3.114-.732a48.524 48.524 0 0 1-.005-10.499l-3.11.732a9 9 0 0 1-6.085-.711l-.108-.054a9 9 0 0 0-6.208-.682L3 4.5M3 15V4.5" /></svg>
                    </button>
                    <flux:menu>
                        <flux:menu.item wire:click="bulkSetPriority({{ $listId }}, {{ $node['category']->id }}, null)">
                            {{ __('None') }}
                        </flux:menu.item>
                        <flux:menu.item wire:click="bulkSetPriority({{ $listId }}, {{ $node['category']->id }}, 'high')">
                            <div class="flex items-center gap-2">
                                <span class="size-2 rounded-full bg-red-500"></span>
                                {{ __('High') }}
                            </div>
                        </flux:menu.item>
                        <flux:menu.item wire:click="bulkSetPriority({{ $listId }}, {{ $node['category']->id }}, 'medium')">
                            <div class="flex items-center gap-2">
                                <span class="size-2 rounded-full bg-amber-500"></span>
                                {{ __('Medium') }}
                            </div>
                        </flux:menu.item>
                        <flux:menu.item wire:click="bulkSetPriority({{ $listId }}, {{ $node['category']->id }}, 'low')">
                            <div class="flex items-center gap-2">
                                <span class="size-2 rounded-full bg-green-500"></span>
                                {{ __('Low') }}
                            </div>
                        </flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
            </div>
        </div>

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
                            <div class="group/item flex items-center gap-2" wire:key="item-{{ $item->id }}">
                                <label class="flex items-center gap-2 cursor-pointer min-w-0 flex-1">
                                    <input
                                        type="checkbox"
                                        class="rounded border-zinc-300 dark:border-zinc-600 shrink-0"
                                        {{ $item->is_checked ? 'checked' : '' }}
                                        wire:click="toggleChoreItem({{ $item->id }})"
                                    />
                                    @if ($item->priority)
                                        <span class="size-2 shrink-0 rounded-full {{ match($item->priority) {
                                            'high' => 'bg-red-500',
                                            'medium' => 'bg-amber-500',
                                            'low' => 'bg-green-500',
                                            default => '',
                                        } }}"></span>
                                    @endif
                                    <span class="truncate {{ $item->is_checked ? 'line-through text-zinc-400 dark:text-zinc-500' : '' }}">
                                        {{ $item->chore->name }}
                                    </span>
                                    @php $pointsPreview = $this->pointsPreviewFor($item); @endphp
                                    <flux:tooltip content="{{ $this->pointsTooltipText($pointsPreview) }}">
                                        <flux:badge size="sm" :color="$pointsPreview['is_credited'] && $pointsPreview['escalation_bonus'] > 0 ? 'orange' : null">{{ $pointsPreview['total'] }} pts</flux:badge>
                                    </flux:tooltip>
                                    @php $displayReward = $item->displayReward(); @endphp
                                    @if ($displayReward['reward_note'])
                                        <flux:tooltip content="{{ $displayReward['reward_note'] }}">
                                            <flux:icon name="gift" variant="micro" class="size-3.5 shrink-0 text-amber-500" />
                                        </flux:tooltip>
                                    @elseif ($displayReward['bounty_cents'])
                                        <flux:badge size="sm" color="amber">&euro;{{ number_format($displayReward['bounty_cents'] / 100, 0, ',', '.') }}</flux:badge>
                                    @endif
                                </label>

                                <div class="ml-auto flex items-center gap-1 shrink-0">
                                    {{-- Who's assigned — also who gets completion credit when checked, split evenly if more than one --}}
                                    <flux:dropdown position="bottom" align="end" class="flex items-center">
                                        <button type="button" class="inline-flex items-center justify-center size-5">
                                            @if ($item->users->isNotEmpty())
                                                <div class="flex -space-x-1.5">
                                                    @foreach ($item->users as $assignee)
                                                        <span
                                                            class="inline-flex items-center justify-center size-5 rounded-full text-[10px] font-medium leading-none ring-1 ring-white dark:ring-zinc-800 {{ $item->is_checked ? 'bg-lime-200 text-lime-800 dark:bg-lime-700 dark:text-lime-100' : 'bg-zinc-200 text-zinc-700 dark:bg-zinc-600 dark:text-zinc-200' }}"
                                                            title="{{ $assignee->name }}"
                                                        >
                                                            {{ $assignee->initials() }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @else
                                                <flux:icon name="user-plus" variant="micro" class="size-4 text-zinc-300 dark:text-zinc-600" />
                                            @endif
                                        </button>
                                        <flux:menu>
                                            <flux:menu.heading>{{ __('Who did / will do this') }}</flux:menu.heading>
                                            @php $assignedIds = $item->users->pluck('id')->all(); @endphp
                                            @foreach ($this->users as $user)
                                                <flux:menu.item wire:click="toggleUserAssignment({{ $item->id }}, {{ $user->id }})" keep-open>
                                                    <div class="flex items-center gap-2">
                                                        @if (in_array($user->id, $assignedIds))
                                                            <svg class="size-4 text-lime-500 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" /></svg>
                                                        @else
                                                            <span class="size-4 shrink-0"></span>
                                                        @endif
                                                        <span class="inline-flex items-center justify-center size-5 rounded-full bg-zinc-200 dark:bg-zinc-600 text-[10px] font-medium leading-none text-zinc-700 dark:text-zinc-200">
                                                            {{ $user->initials() }}
                                                        </span>
                                                        {{ $user->name }}
                                                    </div>
                                                </flux:menu.item>
                                            @endforeach
                                            @if ($item->users->isNotEmpty())
                                                <flux:menu.separator />
                                                <flux:menu.item wire:click="clearAssignees({{ $item->id }})">
                                                    {{ __('Clear all') }}
                                                </flux:menu.item>
                                            @endif
                                        </flux:menu>
                                    </flux:dropdown>

                                    {{-- Reward: either a cash bounty or a text note, never both --}}
                                    <flux:dropdown position="bottom" align="end" class="flex items-center">
                                        <button
                                            type="button"
                                            title="{{ __('Set reward') }}"
                                            class="inline-flex items-center justify-center size-5"
                                            wire:click="$set('bountyInputs.{{ $item->id }}', @js($displayReward['bounty_cents'] ? number_format($displayReward['bounty_cents'] / 100, 2, '.', '') : null)); $set('rewardNoteInputs.{{ $item->id }}', @js($displayReward['reward_note']))"
                                        >
                                            @if ($displayReward['bounty_cents'])
                                                <span class="text-[10px] font-medium text-amber-600 dark:text-amber-400">&euro;{{ number_format($displayReward['bounty_cents'] / 100, 0, ',', '.') }}</span>
                                            @else
                                                <flux:icon name="gift" variant="micro" class="size-4 {{ $displayReward['reward_note'] ? 'text-amber-500' : 'text-zinc-300 dark:text-zinc-600' }}" />
                                            @endif
                                        </button>
                                        <flux:menu class="p-3 space-y-2 w-56" x-data="{ mode: '{{ $displayReward['reward_note'] && ! $displayReward['bounty_cents'] ? 'text' : 'money' }}' }">
                                            <div class="flex gap-1 mb-1">
                                                <div role="button" tabindex="0" @click="mode = 'money'" @keydown.enter="mode = 'money'" @keydown.space.prevent="mode = 'money'" :class="mode === 'money' ? 'bg-zinc-800 text-white dark:bg-zinc-200 dark:text-zinc-900' : 'text-zinc-500 dark:text-zinc-400'" class="flex-1 text-xs text-center rounded-md py-1 cursor-pointer transition-colors">{{ __('Money') }}</div>
                                                <div role="button" tabindex="0" @click="mode = 'text'" @keydown.enter="mode = 'text'" @keydown.space.prevent="mode = 'text'" :class="mode === 'text' ? 'bg-zinc-800 text-white dark:bg-zinc-200 dark:text-zinc-900' : 'text-zinc-500 dark:text-zinc-400'" class="flex-1 text-xs text-center rounded-md py-1 cursor-pointer transition-colors">{{ __('Text') }}</div>
                                            </div>

                                            <div x-show="mode === 'money'">
                                                <flux:input type="number" step="0.01" min="0" size="sm" wire:model="bountyInputs.{{ $item->id }}" placeholder="{{ __('Amount in €') }}" />
                                            </div>
                                            <div x-show="mode === 'text'">
                                                <flux:input type="text" size="sm" wire:model="rewardNoteInputs.{{ $item->id }}" placeholder="{{ __('e.g. winner picks dinner') }}" />
                                            </div>

                                            <div class="flex gap-1">
                                                <flux:button size="xs" variant="primary" x-on:click="$wire.setReward({{ $item->id }}, mode)" class="flex-1">{{ __('Set') }}</flux:button>
                                                @if ($displayReward['bounty_cents'] || $displayReward['reward_note'])
                                                    <flux:button size="xs" variant="ghost" wire:click="clearReward({{ $item->id }})">{{ __('Clear') }}</flux:button>
                                                @endif
                                            </div>
                                        </flux:menu>
                                    </flux:dropdown>

                                    {{-- Priority --}}
                                    <flux:dropdown position="bottom" align="end" class="flex items-center">
                                        <button type="button" class="inline-flex items-center justify-center size-5">
                                            <svg class="size-4 {{ match($item->priority) {
                                                'high' => 'text-red-500',
                                                'medium' => 'text-amber-500',
                                                'low' => 'text-green-500',
                                                default => 'text-zinc-300 dark:text-zinc-600',
                                            } }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v1.5M3 21v-6m0 0 2.77-.693a9 9 0 0 1 6.208.682l.108.054a9 9 0 0 0 6.086.71l3.114-.732a48.524 48.524 0 0 1-.005-10.499l-3.11.732a9 9 0 0 1-6.085-.711l-.108-.054a9 9 0 0 0-6.208-.682L3 4.5M3 15V4.5" /></svg>
                                        </button>
                                        <flux:menu>
                                            <flux:menu.item wire:click="setItemPriority({{ $item->id }}, null)">
                                                {{ __('None') }}
                                            </flux:menu.item>
                                            <flux:menu.item wire:click="setItemPriority({{ $item->id }}, 'high')">
                                                <div class="flex items-center gap-2">
                                                    <span class="size-2 rounded-full bg-red-500"></span>
                                                    {{ __('High') }}
                                                </div>
                                            </flux:menu.item>
                                            <flux:menu.item wire:click="setItemPriority({{ $item->id }}, 'medium')">
                                                <div class="flex items-center gap-2">
                                                    <span class="size-2 rounded-full bg-amber-500"></span>
                                                    {{ __('Medium') }}
                                                </div>
                                            </flux:menu.item>
                                            <flux:menu.item wire:click="setItemPriority({{ $item->id }}, 'low')">
                                                <div class="flex items-center gap-2">
                                                    <span class="size-2 rounded-full bg-green-500"></span>
                                                    {{ __('Low') }}
                                                </div>
                                            </flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </div>
@endforeach
