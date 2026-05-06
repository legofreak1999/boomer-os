<?php

use App\Models\Store;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Stores')] class extends Component {
    #[Rule(['required', 'string', 'max:255'])]
    public string $name = '';

    public ?int $editingId = null;

    #[Computed]
    public function stores()
    {
        return Store::orderBy('name')->get();
    }

    public function createStore(): void
    {
        $this->validate();

        Store::create(['name' => $this->name]);

        $this->reset('name');
        unset($this->stores);
        Flux::modal('store-form')->close();
        Flux::toast('Store created.');
    }

    public function editStore(int $id): void
    {
        $store = Store::findOrFail($id);
        $this->editingId = $id;
        $this->name = $store->name;
        Flux::modal('store-form')->show();
    }

    public function updateStore(): void
    {
        $this->validate();

        $store = Store::findOrFail($this->editingId);
        $store->update(['name' => $this->name]);

        $this->reset('name', 'editingId');
        unset($this->stores);
        Flux::modal('store-form')->close();
        Flux::toast('Store updated.');
    }

    public function saveStore(): void
    {
        if ($this->editingId) {
            $this->updateStore();
        } else {
            $this->createStore();
        }
    }

    public function confirmDelete(int $id): void
    {
        $store = Store::findOrFail($id);

        if ($store->receipts()->exists()) {
            Flux::toast('Cannot delete a store that has receipts.', variant: 'danger');

            return;
        }

        $store->delete();
        unset($this->stores);
        Flux::toast('Store deleted.');
    }

    public function openCreateModal(): void
    {
        $this->reset('name', 'editingId');
        $this->resetValidation();
        Flux::modal('store-form')->show();
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Stores') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Manage stores where you shop.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
            {{ __('Add Store') }}
        </flux:button>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column class="w-32"></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->stores as $store)
                <flux:table.row :key="$store->id">
                    <flux:table.cell variant="strong">{{ $store->name }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end gap-2">
                            <flux:button size="sm" icon="pencil" variant="ghost" wire:click="editStore({{ $store->id }})" />
                            <flux:button size="sm" icon="trash" variant="ghost" wire:click="confirmDelete({{ $store->id }})" wire:confirm="{{ __('Are you sure you want to delete this store?') }}" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="2" class="text-center">
                        <flux:text>{{ __('No stores yet.') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="store-form" class="md:w-96">
        <form wire:submit="saveStore" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingId ? __('Edit Store') : __('Add Store') }}</flux:heading>
            </div>

            <flux:input wire:model="name" label="{{ __('Name') }}" placeholder="{{ __('e.g. Albert Heijn') }}" autofocus />

            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">
                    {{ $editingId ? __('Update') : __('Create') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
