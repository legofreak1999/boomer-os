<?php

use App\Models\Chore;
use App\Models\ChoreCategory;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Manage Chores')] class extends Component {
    // Category form
    public string $categoryName = '';
    public ?int $editingCategoryId = null;

    // Chore form
    public string $choreName = '';
    public ?int $choreCategoryId = null;
    public ?int $editingChoreId = null;

    // Collapse state
    public array $expandedCategories = [];

    #[Computed]
    public function categories()
    {
        return ChoreCategory::with(['chores' => fn ($q) => $q->orderBy('name')])->orderBy('name')->get();
    }

    // --- Category actions ---

    public function openCategoryModal(): void
    {
        $this->reset('categoryName', 'editingCategoryId');
        $this->resetValidation();
        Flux::modal('category-form')->show();
    }

    public function editCategory(int $id): void
    {
        $category = ChoreCategory::findOrFail($id);
        $this->editingCategoryId = $id;
        $this->categoryName = $category->name;
        Flux::modal('category-form')->show();
    }

    public function saveCategory(): void
    {
        $this->validate([
            'categoryName' => ['required', 'string', 'max:255'],
        ]);

        if ($this->editingCategoryId) {
            ChoreCategory::findOrFail($this->editingCategoryId)->update(['name' => $this->categoryName]);
            Flux::toast('Category updated.');
        } else {
            ChoreCategory::create(['name' => $this->categoryName]);
            Flux::toast('Category created.');
        }

        $this->reset('categoryName', 'editingCategoryId');
        unset($this->categories);
        Flux::modal('category-form')->close();
    }

    public function deleteCategory(int $id): void
    {
        $category = ChoreCategory::findOrFail($id);

        if ($category->chores()->exists()) {
            Flux::toast('Cannot delete a category that has chores.', variant: 'danger');

            return;
        }

        $category->delete();
        unset($this->categories);
        Flux::toast('Category deleted.');
    }

    // --- Chore actions ---

    public function openChoreModal(?int $categoryId = null): void
    {
        $this->reset('choreName', 'choreCategoryId', 'editingChoreId');
        $this->choreCategoryId = $categoryId;
        $this->resetValidation();
        Flux::modal('chore-form')->show();
    }

    public function editChore(int $id): void
    {
        $chore = Chore::findOrFail($id);
        $this->editingChoreId = $id;
        $this->choreName = $chore->name;
        $this->choreCategoryId = $chore->chore_category_id;
        Flux::modal('chore-form')->show();
    }

    public function saveChore(): void
    {
        $this->validate([
            'choreName' => ['required', 'string', 'max:255'],
            'choreCategoryId' => ['required', 'exists:chore_categories,id'],
        ]);

        if ($this->editingChoreId) {
            Chore::findOrFail($this->editingChoreId)->update([
                'name' => $this->choreName,
                'chore_category_id' => $this->choreCategoryId,
            ]);
            Flux::toast('Chore updated.');
        } else {
            Chore::create([
                'name' => $this->choreName,
                'chore_category_id' => $this->choreCategoryId,
            ]);
            Flux::toast('Chore created.');
        }

        $this->reset('choreName', 'choreCategoryId', 'editingChoreId');
        unset($this->categories);
        Flux::modal('chore-form')->close();
    }

    public function deleteChore(int $id): void
    {
        $chore = Chore::findOrFail($id);

        if ($chore->choreListItems()->exists()) {
            Flux::toast('Cannot delete a chore that is used in a list.', variant: 'danger');

            return;
        }

        $chore->delete();
        unset($this->categories);
        Flux::toast('Chore deleted.');
    }

    public function toggleCategoryCollapse(int $id): void
    {
        if (in_array($id, $this->expandedCategories)) {
            $this->expandedCategories = array_values(array_diff($this->expandedCategories, [$id]));
        } else {
            $this->expandedCategories[] = $id;
        }
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Manage Chores') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Manage your chore categories and chores.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openCategoryModal">
            {{ __('Add Category') }}
        </flux:button>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column class="w-32"></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->categories as $category)
                {{-- Category row --}}
                <flux:table.row :key="'cat-' . $category->id" class="cursor-pointer" wire:click="toggleCategoryCollapse({{ $category->id }})">
                    <flux:table.cell variant="strong">
                        <div class="flex items-center gap-2">
                            <svg class="size-4 shrink-0 text-zinc-400 transition-transform {{ in_array($category->id, $expandedCategories) ? 'rotate-90' : '' }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                            {{ $category->name }}
                            <flux:badge size="sm" color="zinc">{{ $category->chores->count() }}</flux:badge>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end gap-1" wire:click.stop>
                            <flux:button size="sm" icon="plus" variant="ghost" wire:click="openChoreModal({{ $category->id }})" />
                            <flux:button size="sm" icon="pencil" variant="ghost" wire:click="editCategory({{ $category->id }})" />
                            <flux:button size="sm" icon="trash" variant="ghost" wire:click="deleteCategory({{ $category->id }})" wire:confirm="{{ __('Are you sure you want to delete this category?') }}" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>

                {{-- Chore rows (nested under category) --}}
                @if (in_array($category->id, $expandedCategories))
                    @foreach ($category->chores as $chore)
                        <flux:table.row :key="'chore-' . $chore->id">
                            <flux:table.cell>
                                <div class="pl-8">{{ $chore->name }}</div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex justify-end gap-1">
                                    <flux:button size="sm" icon="pencil" variant="ghost" wire:click="editChore({{ $chore->id }})" />
                                    <flux:button size="sm" icon="trash" variant="ghost" wire:click="deleteChore({{ $chore->id }})" wire:confirm="{{ __('Are you sure you want to delete this chore?') }}" />
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                @endif
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="2" class="text-center">
                        <flux:text>{{ __('No categories yet. Add a category to get started.') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{-- Category Modal --}}
    <flux:modal name="category-form" class="md:w-96">
        <form wire:submit="saveCategory" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingCategoryId ? __('Edit Category') : __('Add Category') }}</flux:heading>
            </div>

            <flux:input wire:model="categoryName" label="{{ __('Name') }}" placeholder="{{ __('e.g. Kitchen') }}" autofocus />

            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">
                    {{ $editingCategoryId ? __('Update') : __('Create') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Chore Modal --}}
    <flux:modal name="chore-form" class="md:w-96">
        <form wire:submit="saveChore" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingChoreId ? __('Edit Chore') : __('Add Chore') }}</flux:heading>
            </div>

            <flux:input wire:model="choreName" label="{{ __('Name') }}" placeholder="{{ __('e.g. Vacuum living room') }}" autofocus />

            <flux:select wire:model="choreCategoryId" :label="__('Category')" placeholder="{{ __('Select a category...') }}">
                @foreach ($this->categories as $category)
                    <flux:select.option :value="$category->id">{{ $category->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">
                    {{ $editingChoreId ? __('Update') : __('Create') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
