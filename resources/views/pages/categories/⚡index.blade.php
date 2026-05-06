<?php

use App\Models\Category;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Categories')] class extends Component {
    #[Rule(['required', 'string', 'max:255'])]
    public string $name = '';

    public ?int $editingId = null;

    #[Computed]
    public function categories()
    {
        return Category::orderBy('name')->get();
    }

    public function createCategory(): void
    {
        $this->validate();

        Category::create(['name' => $this->name]);

        $this->reset('name');
        unset($this->categories);
        Flux::modal('category-form')->close();
        Flux::toast('Category created.');
    }

    public function editCategory(int $id): void
    {
        $category = Category::findOrFail($id);
        $this->editingId = $id;
        $this->name = $category->name;
        Flux::modal('category-form')->show();
    }

    public function updateCategory(): void
    {
        $this->validate();

        $category = Category::findOrFail($this->editingId);
        $category->update(['name' => $this->name]);

        $this->reset('name', 'editingId');
        unset($this->categories);
        Flux::modal('category-form')->close();
        Flux::toast('Category updated.');
    }

    public function saveCategory(): void
    {
        if ($this->editingId) {
            $this->updateCategory();
        } else {
            $this->createCategory();
        }
    }

    public function confirmDelete(int $id): void
    {
        $category = Category::findOrFail($id);

        if ($category->receiptItems()->exists()) {
            Flux::toast('Cannot delete a category that has expense items.', variant: 'danger');

            return;
        }

        $category->delete();
        unset($this->categories);
        Flux::toast('Category deleted.');
    }

    public function openCreateModal(): void
    {
        $this->reset('name', 'editingId');
        $this->resetValidation();
        Flux::modal('category-form')->show();
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Categories') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Manage expense categories.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
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
                            <flux:button size="sm" icon="trash" variant="ghost" wire:click="confirmDelete({{ $category->id }})" wire:confirm="{{ __('Are you sure you want to delete this category?') }}" />
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
                <flux:heading size="lg">{{ $editingId ? __('Edit Category') : __('Add Category') }}</flux:heading>
            </div>

            <flux:input wire:model="name" label="{{ __('Name') }}" placeholder="{{ __('e.g. Groceries') }}" autofocus />

            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">
                    {{ $editingId ? __('Update') : __('Create') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
