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
    public ?int $categoryParentId = null;
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
        return ChoreCategory::with(['chores' => fn ($q) => $q->orderBy('name'), 'children.chores' => fn ($q) => $q->orderBy('name'), 'children.children'])
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function allCategories()
    {
        return ChoreCategory::with('parent')->orderBy('name')->get();
    }

    // --- Category actions ---

    public function openCategoryModal(?int $parentId = null): void
    {
        $this->reset('categoryName', 'editingCategoryId', 'categoryParentId');
        $this->categoryParentId = $parentId;
        $this->resetValidation();
        Flux::modal('category-form')->show();
    }

    public function editCategory(int $id): void
    {
        $category = ChoreCategory::findOrFail($id);
        $this->editingCategoryId = $id;
        $this->categoryName = $category->name;
        $this->categoryParentId = $category->parent_id;
        Flux::modal('category-form')->show();
    }

    public function saveCategory(): void
    {
        $this->validate([
            'categoryName' => ['required', 'string', 'max:255'],
            'categoryParentId' => ['nullable', 'exists:chore_categories,id'],
        ]);

        $data = [
            'name' => $this->categoryName,
            'parent_id' => $this->categoryParentId,
        ];

        if ($this->editingCategoryId) {
            ChoreCategory::findOrFail($this->editingCategoryId)->update($data);
            Flux::toast('Category updated.');
        } else {
            ChoreCategory::create($data);
            Flux::toast('Category created.');
        }

        $this->reset('categoryName', 'editingCategoryId', 'categoryParentId');
        unset($this->categories, $this->allCategories);
        Flux::modal('category-form')->close();
    }

    public function deleteCategory(int $id): void
    {
        $category = ChoreCategory::findOrFail($id);

        if ($category->chores()->exists() || $category->children()->exists()) {
            Flux::toast('Cannot delete a category that has chores or subcategories.', variant: 'danger');

            return;
        }

        $category->delete();
        unset($this->categories, $this->allCategories);
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
        unset($this->categories, $this->allCategories);
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
        unset($this->categories, $this->allCategories);
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
                @include('pages.chores._category-row', ['category' => $category, 'depth' => 0])
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

            <flux:select wire:model="categoryParentId" :label="__('Parent Category')" placeholder="{{ __('None (top level)') }}">
                @foreach ($this->allCategories->reject(fn ($c) => $c->id === $editingCategoryId) as $cat)
                    <flux:select.option :value="$cat->id">{{ $cat->fullPath() }}</flux:select.option>
                @endforeach
            </flux:select>

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
                @foreach ($this->allCategories as $cat)
                    <flux:select.option :value="$cat->id">{{ $cat->fullPath() }}</flux:select.option>
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
