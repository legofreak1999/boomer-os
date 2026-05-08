<?php

use App\Models\ChoreCategory;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Chore Categories')] class extends Component {
    public string $categoryName = '';
    public ?int $editingCategoryId = null;

    #[Computed]
    public function categories()
    {
        return ChoreCategory::orderBy('name')->get();
    }

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
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Chore Categories') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Manage chore categories.') }}</flux:text>
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
                <flux:table.row :key="$category->id">
                    <flux:table.cell variant="strong">{{ $category->name }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end gap-2">
                            <flux:button size="sm" icon="pencil" variant="ghost" wire:click="editCategory({{ $category->id }})" />
                            <flux:button size="sm" icon="trash" variant="ghost" wire:click="deleteCategory({{ $category->id }})" wire:confirm="{{ __('Are you sure you want to delete this category?') }}" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="2" class="text-center">
                        <flux:text>{{ __('No categories yet.') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

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
</div>
