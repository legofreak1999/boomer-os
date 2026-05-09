<?php

use App\Models\HikeTag;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Hike Tags')] class extends Component {
    public string $tagName = '';
    public ?int $editingTagId = null;
    public bool $showForm = false;

    #[Computed]
    public function tags()
    {
        return HikeTag::orderBy('name')->get();
    }

    public function openForm(): void
    {
        $this->reset('tagName', 'editingTagId');
        $this->resetValidation();
        $this->showForm = true;
    }

    public function editTag(int $id): void
    {
        $tag = HikeTag::findOrFail($id);
        $this->editingTagId = $id;
        $this->tagName = $tag->name;
        $this->showForm = true;
    }

    public function saveTag(): void
    {
        $this->validate(['tagName' => ['required', 'string', 'max:255']]);

        if ($this->editingTagId) {
            HikeTag::findOrFail($this->editingTagId)->update(['name' => $this->tagName]);
            Flux::toast('Tag updated.');
        } else {
            HikeTag::create(['name' => $this->tagName]);
            Flux::toast('Tag created.');
        }

        $this->reset('tagName', 'editingTagId', 'showForm');
        unset($this->tags);
    }

    public function cancel(): void
    {
        $this->reset('tagName', 'editingTagId', 'showForm');
        $this->resetValidation();
    }

    public function deleteTag(int $id): void
    {
        $tag = HikeTag::findOrFail($id);

        if ($tag->locations()->exists() || $tag->trails()->exists()) {
            Flux::toast('Cannot delete a tag that is in use.', variant: 'danger');

            return;
        }

        $tag->delete();
        unset($this->tags);
        Flux::toast('Tag deleted.');
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Hike Tags') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Manage tags for locations and trails.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openForm">
            {{ __('Add Tag') }}
        </flux:button>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column class="w-32"></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->tags as $tag)
                <flux:table.row :key="$tag->id">
                    <flux:table.cell variant="strong">{{ $tag->name }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end gap-2">
                            <flux:button size="sm" icon="pencil" variant="ghost" wire:click="editTag({{ $tag->id }})" />
                            <flux:button size="sm" icon="trash" variant="ghost" wire:click="deleteTag({{ $tag->id }})" wire:confirm="{{ __('Are you sure?') }}" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="2" class="text-center">
                        <flux:text>{{ __('No tags yet.') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal :wire:model="'showForm'" class="md:w-96">
        <div class="space-y-6">
            <flux:heading size="lg">{{ $editingTagId ? __('Edit Tag') : __('Add Tag') }}</flux:heading>
            <flux:input wire:model="tagName" placeholder="{{ __('e.g. dog-friendly') }}" autofocus wire:keydown.enter="saveTag" />
            <div class="flex justify-end gap-2">
                <flux:button size="sm" variant="ghost" wire:click="cancel">{{ __('Cancel') }}</flux:button>
                <flux:button size="sm" variant="primary" wire:click="saveTag">{{ $editingTagId ? __('Update') : __('Create') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
