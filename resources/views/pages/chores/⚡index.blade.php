<?php

use App\Models\Chore;
use App\Models\ChoreCategory;
use App\Models\ChoreList;
use App\Models\ChoreListItem;
use Illuminate\Support\Collection;
use Flux\Flux;
use Livewire\Attributes\Computed;
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

    // Collapse state
    public array $collapsedLists = [];
    public array $collapsedCategories = [];

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
    public function choreLists()
    {
        $query = ChoreList::with(['items.chore.category.parent'])->orderBy('position')->orderBy('name');

        if (! $this->showHidden) {
            $query->where('is_hidden', false);
        }

        return $query->get();
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

    @if ($this->choreLists->isEmpty())
        <div class="text-center py-12">
            <flux:text>{{ __('No chore lists yet. Create one to get started.') }}</flux:text>
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
                        <div data-list-content class="border-t border-zinc-200 dark:border-zinc-700 p-4">
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
