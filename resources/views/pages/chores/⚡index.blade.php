<?php

use App\Models\Chore;
use App\Models\ChoreCategory;
use App\Models\ChoreList;
use App\Models\ChoreListItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Session;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Chores')] class extends Component {
    public bool $showHidden = false;

    // List form
    public ?int $editingListId = null;
    public string $listName = '';
    public ?string $listRepeatType = null;
    public ?int $listRepeatValue = null;
    public ?string $listRepeatStartDate = null;
    public array $selectedChoreIds = [];

    // Filters
    public array $filterUserIds = [];
    public array $filterPriorities = [];
    public array $filterCategoryIds = [];

    // Collapse state & list heights
    #[Session]
    public array $collapsedLists = [];

    #[Session]
    public array $collapsedCategories = [];

    #[Session]
    public array $listHeights = [];

    public static function repeatTypeLabels(): array
    {
        return [
            '' => 'None',
            ChoreList::REPEAT_DAILY => 'Daily',
            ChoreList::REPEAT_WEEKLY => 'Weekly',
            ChoreList::REPEAT_MONTHLY_DAY => 'Monthly (specific day)',
            ChoreList::REPEAT_MONTHLY_LAST => 'Monthly (last day)',
            ChoreList::REPEAT_YEARLY => 'Yearly',
        ];
    }

    public static function weekdayLabels(): array
    {
        return [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
    }

    public static function monthLabels(): array
    {
        return [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'];
    }

    #[Computed]
    public function users()
    {
        return User::orderBy('name')->get();
    }

    #[Computed]
    public function allCategories()
    {
        return ChoreCategory::orderBy('name')->get();
    }

    public function hasActiveFilters(): bool
    {
        return ! empty($this->filterUserIds) || ! empty($this->filterPriorities) || ! empty($this->filterCategoryIds);
    }

    public function toggleFilter(string $type, string $value): void
    {
        $property = match ($type) {
            'user' => 'filterUserIds',
            'priority' => 'filterPriorities',
            'category' => 'filterCategoryIds',
        };

        if (in_array($value, $this->{$property})) {
            $this->{$property} = array_values(array_diff($this->{$property}, [$value]));
        } else {
            $this->{$property}[] = $value;
        }

        unset($this->choreLists);
    }

    public function clearFilters(): void
    {
        $this->filterUserIds = [];
        $this->filterPriorities = [];
        $this->filterCategoryIds = [];
        unset($this->choreLists);
    }

    #[Computed]
    public function choreLists()
    {
        $query = ChoreList::with(['items.chore.category.parent', 'items.users'])->orderBy('position')->orderBy('name');

        if (! $this->showHidden) {
            $query->where('is_hidden', false);
        }

        $lists = $query->get();

        if (! $this->hasActiveFilters()) {
            return $lists;
        }

        // Expand selected categories to include all descendants
        $expandedCategoryIds = [];
        foreach ($this->filterCategoryIds as $catId) {
            $expandedCategoryIds = array_merge($expandedCategoryIds, $this->collectCategoryIds((int) $catId));
        }

        return $lists->map(function ($list) use ($expandedCategoryIds) {
            $filteredItems = $list->items->filter(function ($item) use ($expandedCategoryIds) {
                if (! empty($this->filterUserIds)) {
                    $itemUserIds = $item->users->pluck('id')->all();
                    if (empty(array_intersect(array_map('intval', $this->filterUserIds), $itemUserIds))) {
                        return false;
                    }
                }

                if (! empty($this->filterPriorities)) {
                    if (! in_array($item->priority, $this->filterPriorities)) {
                        return false;
                    }
                }

                if (! empty($expandedCategoryIds)) {
                    if (! in_array($item->chore->chore_category_id, $expandedCategoryIds)) {
                        return false;
                    }
                }

                return true;
            });

            $list->setRelation('items', $filteredItems);

            return $list;
        })->filter(fn ($list) => $list->items->isNotEmpty());
    }

    #[Computed]
    public function availableChores()
    {
        return Chore::with('category.parent')->orderBy('name')->get();
    }

    public function openListModal(): void
    {
        $this->resetListForm();
        Flux::modal('list-form')->show();
    }

    public function editList(int $id): void
    {
        $list = ChoreList::with('items')->findOrFail($id);
        $this->editingListId = $id;
        $this->listName = $list->name;
        $this->listRepeatType = $list->repeat_type;
        $this->listRepeatValue = $list->repeat_value;
        $this->listRepeatStartDate = $list->repeat_start_date?->format('Y-m-d');
        $this->selectedChoreIds = $list->items->pluck('chore_id')->map(fn ($id) => (string) $id)->all();
        Flux::modal('list-form')->show();
    }

    public function saveList(): void
    {
        $rules = [
            'listName' => ['required', 'string', 'max:255'],
            'listRepeatType' => ['nullable', 'in:'.implode(',', ChoreList::REPEAT_TYPES)],
            'selectedChoreIds' => ['required', 'array', 'min:1'],
            'selectedChoreIds.*' => ['exists:chores,id'],
        ];

        if ($this->listRepeatType && $this->listRepeatType !== ChoreList::REPEAT_MONTHLY_LAST) {
            $rules['listRepeatValue'] = ['required', 'integer', 'min:1'];
        }

        if ($this->listRepeatType) {
            $rules['listRepeatStartDate'] = ['required', 'date'];
        }

        $this->validate($rules);

        $data = [
            'name' => $this->listName,
            'repeat_type' => $this->listRepeatType ?: null,
            'repeat_value' => $this->listRepeatType && $this->listRepeatType !== ChoreList::REPEAT_MONTHLY_LAST
                ? $this->listRepeatValue : null,
            'repeat_start_date' => $this->listRepeatType ? $this->listRepeatStartDate : null,
        ];

        if ($this->editingListId) {
            $list = ChoreList::findOrFail($this->editingListId);
            $list->update($data);
        } else {
            $data['position'] = (ChoreList::max('position') ?? 0) + 1;
            $list = ChoreList::create($data);
        }

        // Sync chore items
        $existingChoreIds = $list->items()->pluck('chore_id')->all();
        $newChoreIds = array_map('intval', $this->selectedChoreIds);

        // Remove unchecked chores
        $list->items()->whereNotIn('chore_id', $newChoreIds)->delete();

        // Add new chores
        $toAdd = array_diff($newChoreIds, $existingChoreIds);
        foreach ($toAdd as $choreId) {
            ChoreListItem::create([
                'chore_list_id' => $list->id,
                'chore_id' => $choreId,
            ]);
        }

        $this->resetListForm();
        unset($this->choreLists);
        Flux::modal('list-form')->close();
        Flux::toast($this->editingListId ? 'List updated.' : 'List created.');
        $this->editingListId = null;
    }

    public function deleteList(int $id): void
    {
        ChoreList::findOrFail($id)->delete();
        unset($this->choreLists);
        Flux::toast('List deleted.');
    }

    public function toggleHidden(int $id): void
    {
        $list = ChoreList::findOrFail($id);
        $list->update(['is_hidden' => ! $list->is_hidden]);
        unset($this->choreLists);
    }

    public function toggleChoreItem(int $itemId): void
    {
        $item = ChoreListItem::findOrFail($itemId);
        $item->update(['is_checked' => ! $item->is_checked]);
        unset($this->choreLists);
    }

    public function completeList(int $id): void
    {
        $list = ChoreList::findOrFail($id);
        $list->complete();
        unset($this->choreLists);
        Flux::toast($list->hasRepeat() ? 'List completed and hidden until next reset.' : 'List completed and removed.');
    }

    public function handleSort(int $id, int $position): void
    {
        $list = ChoreList::findOrFail($id);
        $oldPosition = $list->position;

        // Shift other lists to make room
        if ($oldPosition < $position) {
            ChoreList::where('position', '>', $oldPosition)
                ->where('position', '<=', $position)
                ->decrement('position');
        } else {
            ChoreList::where('position', '>=', $position)
                ->where('position', '<', $oldPosition)
                ->increment('position');
        }

        $list->update(['position' => $position]);
        unset($this->choreLists);
    }

    public function saveListHeight(int $id, int $height): void
    {
        $step = 48;
        $snapped = (int) (round($height / $step) * $step);
        $this->listHeights[$id] = max(96, min($snapped, 2000));
        unset($this->choreLists);
    }

    public function toggleListCollapse(int $id): void
    {
        if (in_array($id, $this->collapsedLists)) {
            $this->collapsedLists = array_values(array_diff($this->collapsedLists, [$id]));
        } else {
            $this->collapsedLists[] = $id;
        }
    }

    public function toggleCategoryCollapse(string $key): void
    {
        if (in_array($key, $this->collapsedCategories)) {
            $this->collapsedCategories = array_values(array_diff($this->collapsedCategories, [$key]));
        } else {
            $this->collapsedCategories[] = $key;
        }
    }

    public function bulkSetPriority(int $listId, int $categoryId, ?string $priority): void
    {
        $categoryIds = $this->collectCategoryIds($categoryId);

        ChoreListItem::where('chore_list_id', $listId)
            ->whereHas('chore', fn ($q) => $q->whereIn('chore_category_id', $categoryIds))
            ->update(['priority' => $priority ?: null]);

        unset($this->choreLists);
    }

    public function bulkAssignUser(int $listId, int $categoryId, int $userId): void
    {
        $categoryIds = $this->collectCategoryIds($categoryId);

        $items = ChoreListItem::where('chore_list_id', $listId)
            ->whereHas('chore', fn ($q) => $q->whereIn('chore_category_id', $categoryIds))
            ->get();

        foreach ($items as $item) {
            $item->users()->syncWithoutDetaching([$userId]);
        }

        unset($this->choreLists);
    }

    public function bulkRemoveUser(int $listId, int $categoryId, int $userId): void
    {
        $categoryIds = $this->collectCategoryIds($categoryId);

        $items = ChoreListItem::where('chore_list_id', $listId)
            ->whereHas('chore', fn ($q) => $q->whereIn('chore_category_id', $categoryIds))
            ->get();

        foreach ($items as $item) {
            $item->users()->detach($userId);
        }

        unset($this->choreLists);
    }

    public function bulkClearAssignees(int $listId, int $categoryId): void
    {
        $categoryIds = $this->collectCategoryIds($categoryId);

        $items = ChoreListItem::where('chore_list_id', $listId)
            ->whereHas('chore', fn ($q) => $q->whereIn('chore_category_id', $categoryIds))
            ->get();

        foreach ($items as $item) {
            $item->users()->detach();
        }

        unset($this->choreLists);
    }

    /**
     * Collect a category ID and all its descendant category IDs.
     *
     * @return array<int, int>
     */
    private function collectCategoryIds(int $categoryId): array
    {
        $ids = [$categoryId];
        $children = ChoreCategory::where('parent_id', $categoryId)->pluck('id')->all();

        foreach ($children as $childId) {
            $ids = array_merge($ids, $this->collectCategoryIds($childId));
        }

        return $ids;
    }

    public function toggleUserAssignment(int $itemId, int $userId): void
    {
        $item = ChoreListItem::findOrFail($itemId);
        $item->users()->toggle($userId);
        unset($this->choreLists);
    }

    public function clearAssignees(int $itemId): void
    {
        $item = ChoreListItem::findOrFail($itemId);
        $item->users()->detach();
        unset($this->choreLists);
    }

    public function setItemPriority(int $itemId, ?string $priority): void
    {
        $item = ChoreListItem::findOrFail($itemId);
        $item->update(['priority' => $priority ?: null]);
        unset($this->choreLists);
    }

    public function duplicateList(int $id): void
    {
        $list = ChoreList::with('items.users')->findOrFail($id);

        $newList = ChoreList::create([
            'name' => $list->name.' (copy)',
            'position' => (ChoreList::max('position') ?? 0) + 1,
            'is_hidden' => false,
            'repeat_type' => $list->repeat_type,
            'repeat_value' => $list->repeat_value,
            'repeat_start_date' => $list->repeat_start_date,
        ]);

        foreach ($list->items as $item) {
            $newItem = ChoreListItem::create([
                'chore_list_id' => $newList->id,
                'chore_id' => $item->chore_id,
                'is_checked' => false,
                'priority' => $item->priority,
            ]);
            $newItem->users()->attach($item->users->pluck('id'));
        }

        unset($this->choreLists);
        Flux::toast('List duplicated.');
    }

    public function resetListForm(): void
    {
        $this->editingListId = null;
        $this->listName = '';
        $this->listRepeatType = null;
        $this->listRepeatValue = null;
        $this->listRepeatStartDate = null;
        $this->selectedChoreIds = [];
        $this->resetValidation();
    }

    public function updatedListRepeatType(): void
    {
        $this->listRepeatValue = null;
        $this->resetValidation();
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Chores') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Manage your chore lists.') }}</flux:text>
        </div>
        <div class="flex items-center gap-3">
            <flux:button variant="{{ $showHidden ? 'filled' : 'ghost' }}" icon="eye" wire:click="$toggle('showHidden')" size="sm">
                {{ __('Hidden') }}
            </flux:button>
            <flux:button variant="primary" icon="plus" wire:click="openListModal">
                {{ __('New List') }}
            </flux:button>
        </div>
    </div>

    {{-- Filters --}}
    <div class="flex flex-wrap items-center gap-2 mb-4">
        {{-- Assigned to --}}
        <flux:dropdown position="bottom" align="start" class="flex items-center">
            <flux:button size="sm" icon="user" variant="{{ ! empty($filterUserIds) ? 'filled' : 'ghost' }}">
                {{ __('Assigned') }}
                @if (! empty($filterUserIds))
                    <flux:badge size="sm" color="zinc" class="ml-1">{{ count($filterUserIds) }}</flux:badge>
                @endif
            </flux:button>
            <flux:menu>
                @foreach ($this->users as $user)
                    <flux:menu.item wire:click="toggleFilter('user', '{{ $user->id }}')" keep-open>
                        <div class="flex items-center gap-2">
                            @if (in_array((string) $user->id, $filterUserIds))
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
            </flux:menu>
        </flux:dropdown>

        {{-- Priority --}}
        <flux:dropdown position="bottom" align="start" class="flex items-center">
            <flux:button size="sm" icon="flag" variant="{{ ! empty($filterPriorities) ? 'filled' : 'ghost' }}">
                {{ __('Priority') }}
                @if (! empty($filterPriorities))
                    <flux:badge size="sm" color="zinc" class="ml-1">{{ count($filterPriorities) }}</flux:badge>
                @endif
            </flux:button>
            <flux:menu>
                @foreach (['high' => ['High', 'bg-red-500'], 'medium' => ['Medium', 'bg-amber-500'], 'low' => ['Low', 'bg-green-500']] as $pValue => $pMeta)
                    <flux:menu.item wire:click="toggleFilter('priority', '{{ $pValue }}')" keep-open>
                        <div class="flex items-center gap-2">
                            @if (in_array($pValue, $filterPriorities))
                                <svg class="size-4 text-lime-500 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" /></svg>
                            @else
                                <span class="size-4 shrink-0"></span>
                            @endif
                            <span class="size-2 rounded-full {{ $pMeta[1] }}"></span>
                            {{ __($pMeta[0]) }}
                        </div>
                    </flux:menu.item>
                @endforeach
            </flux:menu>
        </flux:dropdown>

        {{-- Categories --}}
        <flux:dropdown position="bottom" align="start" class="flex items-center">
            <flux:button size="sm" icon="tag" variant="{{ ! empty($filterCategoryIds) ? 'filled' : 'ghost' }}">
                {{ __('Category') }}
                @if (! empty($filterCategoryIds))
                    <flux:badge size="sm" color="zinc" class="ml-1">{{ count($filterCategoryIds) }}</flux:badge>
                @endif
            </flux:button>
            <flux:menu class="max-h-60 overflow-y-auto">
                @foreach ($this->allCategories as $category)
                    <flux:menu.item wire:click="toggleFilter('category', '{{ $category->id }}')" keep-open>
                        <div class="flex items-center gap-2">
                            @if (in_array((string) $category->id, $filterCategoryIds))
                                <svg class="size-4 text-lime-500 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" /></svg>
                            @else
                                <span class="size-4 shrink-0"></span>
                            @endif
                            {{ $category->fullPath() }}
                        </div>
                    </flux:menu.item>
                @endforeach
            </flux:menu>
        </flux:dropdown>

        @if ($this->hasActiveFilters())
            <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="clearFilters">
                {{ __('Clear') }}
            </flux:button>
        @endif
    </div>

    @if ($this->choreLists->isEmpty())
        <div class="text-center py-12">
            <flux:text>{{ $this->hasActiveFilters() ? __('No chore lists match your filters.') : __('No chore lists yet. Create one to get started.') }}</flux:text>
        </div>
    @else
        <div
            class="grid grid-cols-1 lg:grid-cols-3 gap-4 [&.is-dragging_[data-list-content]]:hidden"
            wire:sort="handleSort"
            x-data
            @pointerdown.capture="if ($event.target.closest('[wire\\:sort\\:handle]')) $el.classList.add('is-dragging')"
            @pointerup.window="$el.classList.remove('is-dragging')"
        >
            @foreach ($this->choreLists as $list)
                <div wire:key="{{ $list->id }}" wire:sort:item="{{ $list->id }}" class="self-start rounded-lg border {{ $list->is_hidden ? 'border-zinc-300 dark:border-zinc-600 opacity-60' : 'border-zinc-200 dark:border-zinc-700' }}">
                    {{-- List Header --}}
                    <div class="group/card p-3 cursor-pointer" wire:click="toggleListCollapse({{ $list->id }})">
                        <div class="flex items-center gap-2">
                            <svg class="size-4 shrink-0 text-zinc-400 transition-transform {{ in_array($list->id, $collapsedLists) ? '' : 'rotate-90' }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                            <flux:heading size="sm" class="truncate">{{ $list->name }}</flux:heading>
                            @if ($list->hasRepeat())
                                <flux:icon name="arrow-path" variant="micro" class="size-4 shrink-0 text-lime-500" />
                            @endif
                            @if ($list->is_hidden)
                                <flux:icon name="eye-slash" variant="micro" class="size-4 shrink-0 text-zinc-400" />
                            @endif
                            <div class="ml-auto flex items-center gap-1 shrink-0" wire:click.stop>
                                <div class="hidden group-hover/card:flex items-center">
                                    <flux:button size="xs" icon="pencil" variant="ghost" wire:click="editList({{ $list->id }})" />
                                    <flux:button size="xs" icon="document-duplicate" variant="ghost" wire:click="duplicateList({{ $list->id }})" />
                                    <flux:button size="xs" icon="{{ $list->is_hidden ? 'eye' : 'eye-slash' }}" variant="ghost" wire:click="toggleHidden({{ $list->id }})" />
                                    <flux:button size="xs" icon="trash" variant="ghost" wire:click="deleteList({{ $list->id }})" wire:confirm="{{ __('Are you sure you want to delete this list?') }}" />
                                </div>
                                <div wire:sort:handle class="cursor-grab active:cursor-grabbing p-1 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300">
                                    <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- List Content --}}
                    @if (! in_array($list->id, $collapsedLists))
                        <div data-list-content class="border-t border-zinc-200 dark:border-zinc-700 flex flex-col" style="max-height: {{ $listHeights[$list->id] ?? 384 }}px">
                            <div class="p-4 overflow-y-auto flex-1">
                                @php
                                    $categories = $list->items->pluck('chore.category')->unique('id');
                                    $itemsByCategory = $list->items->groupBy(fn ($item) => $item->chore->chore_category_id);
                                    $tree = ChoreCategory::buildTree($categories, $itemsByCategory);
                                @endphp

                                @include('pages.chores._list-category-tree', ['nodes' => $tree, 'listId' => $list->id])

                                {{-- Complete button --}}
                                @if ($list->isComplete())
                                    <div class="mt-4 pt-3 border-t border-zinc-200 dark:border-zinc-700">
                                        <flux:button variant="primary" size="sm" icon="check" wire:click="completeList({{ $list->id }})" class="w-full">
                                            {{ $list->hasRepeat() ? __('Complete & Hide') : __('Complete & Remove') }}
                                        </flux:button>
                                    </div>
                                @endif
                            </div>

                            {{-- Resize handle --}}
                            <div
                                class="h-2 cursor-ns-resize flex items-center justify-center border-t border-zinc-200 dark:border-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors shrink-0"
                                x-data="{ resizing: false, startY: 0, startH: 0 }"
                                x-on:pointerdown.stop="
                                    resizing = true;
                                    startY = $event.clientY;
                                    startH = $el.parentElement.offsetHeight;
                                    $el.setPointerCapture($event.pointerId);
                                "
                                x-on:pointermove="
                                    if (! resizing) return;
                                    let step = 48;
                                    let maxH = window.innerHeight;
                                    let raw = Math.max(96, Math.min(startH + ($event.clientY - startY), maxH));
                                    let newH = Math.round(raw / step) * step;
                                    $el.parentElement.style.maxHeight = newH + 'px';
                                "
                                x-on:pointerup="
                                    if (! resizing) return;
                                    resizing = false;
                                    let h = Math.round(parseFloat($el.parentElement.style.maxHeight));
                                    $wire.saveListHeight({{ $list->id }}, h);
                                "
                            >
                                <svg class="size-4 text-zinc-300 dark:text-zinc-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5 7.5 3m0 0L12 7.5M7.5 3v13.5m13.5-5.5L16.5 16.5m0 0L12 12m4.5 4.5V7.5" /></svg>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- List Form Modal --}}
    <flux:modal name="list-form" class="md:w-[32rem]">
        <form wire:submit="saveList" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingListId ? __('Edit List') : __('New List') }}</flux:heading>
            </div>

            <flux:input wire:model="listName" :label="__('Name')" placeholder="{{ __('e.g. Weekly Cleaning') }}" autofocus />

            {{-- Repeat Settings --}}
            <flux:select wire:model.live="listRepeatType" :label="__('Repeat')">
                @foreach (self::repeatTypeLabels() as $value => $typeLabel)
                    <flux:select.option :value="$value">{{ __($typeLabel) }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($listRepeatType === 'daily')
                <flux:input wire:model="listRepeatValue" :label="__('Every X days')" type="number" min="1" />
            @elseif ($listRepeatType === 'weekly')
                <div>
                    <flux:label>{{ __('Day of week') }}</flux:label>
                    <div class="flex gap-1 mt-2">
                        @foreach (self::weekdayLabels() as $dayNum => $dayLabel)
                            <button
                                type="button"
                                class="px-3 py-1.5 text-sm rounded-md border {{ $listRepeatValue == $dayNum ? 'bg-zinc-800 text-white border-zinc-800 dark:bg-zinc-200 dark:text-zinc-900 dark:border-zinc-200' : 'border-zinc-300 dark:border-zinc-600' }}"
                                wire:click="$set('listRepeatValue', {{ $dayNum }})"
                            >
                                {{ __($dayLabel) }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @elseif ($listRepeatType === 'monthly_day')
                <flux:input wire:model="listRepeatValue" :label="__('Day of month')" type="number" min="1" max="28" />
            @elseif ($listRepeatType === 'yearly')
                <div>
                    <flux:label>{{ __('Month') }}</flux:label>
                    <div class="flex flex-wrap gap-1 mt-2">
                        @foreach (self::monthLabels() as $monthNum => $monthLabel)
                            <button
                                type="button"
                                class="px-3 py-1.5 text-sm rounded-md border {{ $listRepeatValue == $monthNum ? 'bg-zinc-800 text-white border-zinc-800 dark:bg-zinc-200 dark:text-zinc-900 dark:border-zinc-200' : 'border-zinc-300 dark:border-zinc-600' }}"
                                wire:click="$set('listRepeatValue', {{ $monthNum }})"
                            >
                                {{ __($monthLabel) }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($listRepeatType)
                <flux:input wire:model="listRepeatStartDate" :label="__('Start date')" type="date" />
            @endif

            {{-- Chore Selection --}}
            <div>
                <flux:label>{{ __('Chores') }}</flux:label>
                <div class="mt-2 max-h-60 overflow-y-auto space-y-1 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
                    @php
                        $choreCategories = $this->availableChores->pluck('category')->unique('id');
                        $choresByCategory = $this->availableChores->groupBy('chore_category_id');
                        $choreTree = ChoreCategory::buildTree($choreCategories, $choresByCategory);
                    @endphp

                    @if (count($choreTree))
                        @include('pages.chores._chore-select-tree', ['nodes' => $choreTree, 'depth' => 0])
                    @else
                        <flux:text>{{ __('No chores available. Create some in Manage first.') }}</flux:text>
                    @endif
                </div>
                @error('selectedChoreIds') <div class="text-sm text-red-500 mt-1">{{ $message }}</div> @enderror
            </div>

            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">
                    {{ $editingListId ? __('Update') : __('Create') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
