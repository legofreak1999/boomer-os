<?php

use App\Models\Chore;
use App\Models\ChoreCategory;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Chores')] class extends Component {
    public string $choreName = '';
    public ?int $choreCategoryId = null;
    public ?int $editingChoreId = null;

    #[Computed]
    public function categories()
    {
        return ChoreCategory::orderBy('name')->get();
    }

    #[Computed]
    public function chores()
    {
        return Chore::with('category')->orderBy('name')->get();
    }

    public function openChoreModal(): void
    {
        $this->reset('choreName', 'choreCategoryId', 'editingChoreId');
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
        unset($this->chores);
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
        unset($this->chores);
        Flux::toast('Chore deleted.');
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Chores') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Manage your chores.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openChoreModal">
            {{ __('Add Chore') }}
        </flux:button>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Category') }}</flux:table.column>
            <flux:table.column class="w-32"></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->chores as $chore)
                <flux:table.row :key="$chore->id">
                    <flux:table.cell variant="strong">{{ $chore->name }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" color="zinc">{{ $chore->category->name }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end gap-2">
                            <flux:button size="sm" icon="pencil" variant="ghost" wire:click="editChore({{ $chore->id }})" />
                            <flux:button size="sm" icon="trash" variant="ghost" wire:click="deleteChore({{ $chore->id }})" wire:confirm="{{ __('Are you sure you want to delete this chore?') }}" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="3" class="text-center">
                        <flux:text>{{ __('No chores yet. Create categories first, then add chores.') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

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
