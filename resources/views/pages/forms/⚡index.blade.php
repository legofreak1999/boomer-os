<?php

use App\Models\Form;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Forms')] class extends Component {
    public ?int $editingFormId = null;
    public string $formName = '';
    public string $formDescription = '';

    #[Computed]
    public function forms()
    {
        return Form::orderBy('position')->orderBy('id')->withCount('responses', 'columns', 'rows')->get();
    }

    public function openCreateModal(): void
    {
        $this->resetFormForm();
        Flux::modal('form-form')->show();
    }

    public function editForm(int $id): void
    {
        $form = Form::findOrFail($id);
        $this->editingFormId = $id;
        $this->formName = $form->name;
        $this->formDescription = $form->description ?? '';
        Flux::modal('form-form')->show();
    }

    public function saveForm(): void
    {
        $this->validate([
            'formName' => ['required', 'string', 'max:255'],
            'formDescription' => ['nullable', 'string', 'max:5000'],
        ]);

        $data = [
            'name' => $this->formName,
            'description' => $this->formDescription ?: null,
        ];

        if ($this->editingFormId) {
            Form::findOrFail($this->editingFormId)->update($data);
            Flux::toast('Form updated.');
        } else {
            $data['position'] = (Form::max('position') ?? 0) + 1;
            Form::create($data);
            Flux::toast('Form created.');
        }

        $this->resetFormForm();
        unset($this->forms);
        Flux::modal('form-form')->close();
    }

    public function deleteForm(int $id): void
    {
        Form::findOrFail($id)->delete();
        unset($this->forms);
        Flux::toast('Form deleted.');
    }

    public function resetFormForm(): void
    {
        $this->editingFormId = null;
        $this->formName = '';
        $this->formDescription = '';
        $this->resetValidation();
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Forms') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Shared questionnaires everyone fills in once.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
            {{ __('New Form') }}
        </flux:button>
    </div>

    @if ($this->forms->isEmpty())
        <div class="text-center py-12">
            <flux:text>{{ __('No forms yet. Create one to get started.') }}</flux:text>
        </div>
    @else
        <div class="space-y-2">
            @foreach ($this->forms as $form)
                <div class="group/form flex flex-col sm:flex-row sm:items-center gap-3 rounded-lg border border-zinc-200 dark:border-zinc-700 px-4 py-3 hover:bg-zinc-100 dark:hover:bg-zinc-700/50 transition-colors">
                    <a href="{{ route('forms.fill', $form) }}" wire:navigate class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:icon name="document-text" variant="micro" class="size-4 shrink-0 text-zinc-500" />
                            <span class="font-medium text-sm truncate min-w-0 text-zinc-900 dark:text-zinc-100">{{ $form->name }}</span>
                            <flux:badge size="sm" color="zinc">{{ $form->columns_count }} {{ __('columns') }}</flux:badge>
                            <flux:badge size="sm" color="zinc">{{ $form->rows_count }} {{ __('rows') }}</flux:badge>
                            <flux:badge size="sm" color="zinc">{{ $form->responses_count }} {{ __('responses') }}</flux:badge>
                        </div>
                        @if ($form->description)
                            <flux:text size="sm" class="mt-0.5 truncate">{{ $form->description }}</flux:text>
                        @endif
                    </a>

                    <div class="flex items-center gap-0.5 shrink-0 self-end sm:self-auto">
                        <flux:button size="xs" icon="adjustments-horizontal" variant="ghost" :href="route('forms.structure', $form)" wire:navigate>
                            {{ __('Structure') }}
                        </flux:button>
                        <flux:dropdown position="bottom" align="end">
                            <flux:button size="xs" icon="ellipsis-vertical" variant="ghost" />
                            <flux:menu>
                                <flux:menu.item icon="pencil" wire:click="editForm({{ $form->id }})">{{ __('Rename') }}</flux:menu.item>
                                <flux:menu.item icon="trash" variant="danger" wire:click="deleteForm({{ $form->id }})" wire:confirm="{{ __('Delete this form and all responses?') }}">{{ __('Delete') }}</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <flux:modal name="form-form" class="md:w-96">
        <form wire:submit="saveForm" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingFormId ? __('Edit Form') : __('New Form') }}</flux:heading>
            </div>

            <flux:input wire:model="formName" :label="__('Name')" placeholder="{{ __('e.g. Annual review 2026') }}" autofocus />

            <flux:textarea wire:model="formDescription" :label="__('Description')" placeholder="{{ __('Optional details...') }}" rows="3" />

            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">
                    {{ $editingFormId ? __('Update') : __('Create') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
