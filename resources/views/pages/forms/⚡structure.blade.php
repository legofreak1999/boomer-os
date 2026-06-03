<?php

use App\Models\Form;
use App\Models\FormCategory;
use App\Models\FormColumn;
use App\Models\FormRow;
use App\Models\FormRowDefault;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Form structure')] class extends Component {
    public Form $form;

    // -- Column form --
    public ?int $editingColumnId = null;
    public string $columnLabel = '';
    public string $columnType = FormColumn::TYPE_TEXT;
    public string $columnOptionsRaw = '';

    // -- Category form --
    public ?int $editingCategoryId = null;
    public string $categoryName = '';

    // -- Row form --
    public ?int $editingRowId = null;
    public ?int $rowCategoryId = null;
    /** @var array<int, string> */
    public array $rowDefaults = [];
    /** @var array<int, bool> */
    public array $rowLocks = [];

    public function mount(Form $form): void
    {
        $this->form = $form;
    }

    #[Computed]
    public function columns()
    {
        return $this->form->columns()->get();
    }

    #[Computed]
    public function categories()
    {
        return $this->form->categories()
            ->with(['rows' => fn ($q) => $q->orderBy('position')->with('defaults')])
            ->get();
    }

    #[Computed]
    public function uncategorizedRows()
    {
        return $this->form->rows()->whereNull('form_category_id')->with('defaults')->get();
    }

    // ---------------- Columns ----------------

    public function openColumnModal(): void
    {
        $this->resetColumnForm();
        Flux::modal('column-form')->show();
    }

    public function editColumn(int $id): void
    {
        $column = FormColumn::where('form_id', $this->form->id)->findOrFail($id);
        $this->editingColumnId = $id;
        $this->columnLabel = $column->label;
        $this->columnType = $column->type;
        $this->columnOptionsRaw = is_array($column->options) ? implode("\n", $column->options) : '';
        Flux::modal('column-form')->show();
    }

    public function saveColumn(): void
    {
        $this->validate([
            'columnLabel' => ['required', 'string', 'max:255'],
            'columnType' => ['required', 'in:'.implode(',', FormColumn::TYPES)],
            'columnOptionsRaw' => ['nullable', 'string', 'max:5000'],
        ]);

        $options = null;
        if ($this->columnType === FormColumn::TYPE_SELECT) {
            $options = $this->parseOptions($this->columnOptionsRaw);
            if (count($options) === 0) {
                $this->addError('columnOptionsRaw', __('Add at least one option.'));

                return;
            }
        }

        $data = [
            'label' => $this->columnLabel,
            'type' => $this->columnType,
            'options' => $options,
        ];

        if ($this->editingColumnId) {
            FormColumn::where('form_id', $this->form->id)
                ->findOrFail($this->editingColumnId)
                ->update($data);
            Flux::toast('Column updated.');
        } else {
            $data['form_id'] = $this->form->id;
            $data['position'] = ($this->form->columns()->max('position') ?? 0) + 1;
            FormColumn::create($data);
            Flux::toast('Column added.');
        }

        $this->resetColumnForm();
        unset($this->columns, $this->categories, $this->uncategorizedRows);
        Flux::modal('column-form')->close();
    }

    public function deleteColumn(int $id): void
    {
        FormColumn::where('form_id', $this->form->id)->findOrFail($id)->delete();
        unset($this->columns, $this->categories, $this->uncategorizedRows);
        Flux::toast('Column deleted.');
    }

    public function sortColumns(int $id, int $position): void
    {
        $column = FormColumn::where('form_id', $this->form->id)->findOrFail($id);
        $group = $this->form->columns()->get();

        $filtered = $group->reject(fn ($c) => $c->id === $column->id)->values();
        $filtered->splice($position, 0, [$column]);

        foreach ($filtered as $index => $c) {
            if ($c->position !== $index) {
                $c->update(['position' => $index]);
            }
        }

        unset($this->columns);
    }

    public function resetColumnForm(): void
    {
        $this->editingColumnId = null;
        $this->columnLabel = '';
        $this->columnType = FormColumn::TYPE_TEXT;
        $this->columnOptionsRaw = '';
        $this->resetValidation();
    }

    // ---------------- Categories ----------------

    public function openCategoryModal(): void
    {
        $this->resetCategoryForm();
        Flux::modal('category-form')->show();
    }

    public function editCategory(int $id): void
    {
        $category = FormCategory::where('form_id', $this->form->id)->findOrFail($id);
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
            FormCategory::where('form_id', $this->form->id)
                ->findOrFail($this->editingCategoryId)
                ->update(['name' => $this->categoryName]);
            Flux::toast('Category updated.');
        } else {
            FormCategory::create([
                'form_id' => $this->form->id,
                'name' => $this->categoryName,
                'position' => ($this->form->categories()->max('position') ?? 0) + 1,
            ]);
            Flux::toast('Category created.');
        }

        $this->resetCategoryForm();
        unset($this->categories, $this->uncategorizedRows);
        Flux::modal('category-form')->close();
    }

    public function deleteCategory(int $id): void
    {
        FormCategory::where('form_id', $this->form->id)->findOrFail($id)->delete();
        unset($this->categories, $this->uncategorizedRows);
        Flux::toast('Category deleted. Its rows moved to uncategorized.');
    }

    public function resetCategoryForm(): void
    {
        $this->editingCategoryId = null;
        $this->categoryName = '';
        $this->resetValidation();
    }

    // ---------------- Rows ----------------

    public function openRowModal(?int $categoryId = null): void
    {
        $this->resetRowForm();
        $this->rowCategoryId = $categoryId;
        foreach ($this->columns as $column) {
            $this->rowDefaults[$column->id] = '';
            $this->rowLocks[$column->id] = false;
        }
        Flux::modal('row-form')->show();
    }

    public function editRow(int $id): void
    {
        $row = FormRow::where('form_id', $this->form->id)->with('defaults')->findOrFail($id);
        $this->resetRowForm();
        $this->editingRowId = $id;
        $this->rowCategoryId = $row->form_category_id;

        foreach ($this->columns as $column) {
            $default = $row->defaults->firstWhere('form_column_id', $column->id);
            $this->rowDefaults[$column->id] = $default?->value ?? '';
            $this->rowLocks[$column->id] = (bool) ($default?->locked);
        }
        Flux::modal('row-form')->show();
    }

    public function saveRow(): void
    {
        if ($this->editingRowId) {
            $row = FormRow::where('form_id', $this->form->id)->findOrFail($this->editingRowId);
            $row->update(['form_category_id' => $this->rowCategoryId]);
            Flux::toast('Row updated.');
        } else {
            $row = FormRow::create([
                'form_id' => $this->form->id,
                'form_category_id' => $this->rowCategoryId,
                'position' => ($this->form->rows()->where('form_category_id', $this->rowCategoryId)->max('position') ?? 0) + 1,
            ]);
            Flux::toast('Row added.');
        }

        foreach ($this->columns as $column) {
            $value = trim((string) ($this->rowDefaults[$column->id] ?? ''));
            $locked = (bool) ($this->rowLocks[$column->id] ?? false);

            if ($value === '') {
                FormRowDefault::where('form_row_id', $row->id)
                    ->where('form_column_id', $column->id)
                    ->delete();

                continue;
            }

            FormRowDefault::updateOrCreate(
                ['form_row_id' => $row->id, 'form_column_id' => $column->id],
                ['value' => $value, 'locked' => $locked],
            );
        }

        $this->resetRowForm();
        unset($this->categories, $this->uncategorizedRows);
        Flux::modal('row-form')->close();
    }

    public function deleteRow(int $id): void
    {
        FormRow::where('form_id', $this->form->id)->findOrFail($id)->delete();
        unset($this->categories, $this->uncategorizedRows);
        Flux::toast('Row deleted.');
    }

    public function sortRows(int $id, int $position, ?int $categoryId = null): void
    {
        $row = FormRow::where('form_id', $this->form->id)->findOrFail($id);

        $group = $this->form->rows()
            ->where('form_category_id', $categoryId)
            ->orderBy('position')
            ->get();

        $filtered = $group->reject(fn ($r) => $r->id === $row->id)->values();

        if ($row->form_category_id !== $categoryId) {
            $row->update(['form_category_id' => $categoryId]);
        }

        $filtered->splice($position, 0, [$row]);

        foreach ($filtered as $index => $r) {
            if ($r->position !== $index) {
                $r->update(['position' => $index]);
            }
        }

        unset($this->categories, $this->uncategorizedRows);
    }

    public function resetRowForm(): void
    {
        $this->editingRowId = null;
        $this->rowCategoryId = null;
        $this->rowDefaults = [];
        $this->rowLocks = [];
        $this->resetValidation();
    }

    private function parseOptions(string $raw): array
    {
        return collect(preg_split('/\r?\n/', $raw))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <div class="flex items-center gap-2 text-sm text-zinc-500">
                <a href="{{ route('forms.index') }}" wire:navigate class="hover:underline">{{ __('Forms') }}</a>
                <span>/</span>
                <a href="{{ route('forms.fill', $form) }}" wire:navigate class="hover:underline">{{ $form->name }}</a>
                <span>/</span>
                <span>{{ __('Structure') }}</span>
            </div>
            <flux:heading size="xl" class="mt-1">{{ $form->name }}</flux:heading>
            <flux:text class="mt-1">{{ __('Define columns and rows. Each row can carry default cell values that may be locked.') }}</flux:text>
        </div>
    </div>

    {{-- Columns section --}}
    <div class="mb-10">
        <div class="flex items-center gap-2 mb-2">
            <flux:heading size="lg">{{ __('Columns') }}</flux:heading>
            <flux:badge size="sm" color="zinc">{{ $this->columns->count() }}</flux:badge>
            <flux:spacer />
            <flux:button size="sm" icon="plus" wire:click="openColumnModal">{{ __('Add column') }}</flux:button>
        </div>

        @if ($this->columns->isEmpty())
            <flux:text class="px-4 py-3 italic">{{ __('No columns yet. Add at least one column to start collecting answers.') }}</flux:text>
        @else
            <div class="space-y-1" wire:sort="sortColumns">
                @foreach ($this->columns as $column)
                    <div
                        wire:key="column-{{ $column->id }}"
                        wire:sort:item="{{ $column->id }}"
                        class="group/col flex items-center gap-3 rounded-lg border border-zinc-200 dark:border-zinc-700 px-4 py-2 hover:bg-zinc-100 dark:hover:bg-zinc-700/50 transition-colors"
                    >
                        <div wire:sort:handle class="cursor-grab active:cursor-grabbing text-zinc-300 hover:text-zinc-500 dark:text-zinc-600 dark:hover:text-zinc-400">
                            <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
                        </div>

                        <flux:icon :name="$column->type === 'select' ? 'chevron-up-down' : 'pencil-square'" variant="micro" class="size-4 shrink-0 text-zinc-500" />

                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium truncate text-zinc-900 dark:text-zinc-100">{{ $column->label }}</div>
                            @if ($column->type === 'select' && is_array($column->options))
                                <div class="text-xs text-zinc-500 truncate">{{ implode(' · ', $column->options) }}</div>
                            @endif
                        </div>

                        <flux:badge size="sm" color="zinc">{{ $column->type === 'select' ? __('Select') : __('Text') }}</flux:badge>

                        <div class="flex items-center gap-0.5 shrink-0 invisible group-hover/col:visible">
                            <flux:button size="xs" icon="pencil" variant="ghost" wire:click="editColumn({{ $column->id }})" />
                            <flux:button size="xs" icon="trash" variant="ghost" wire:click="deleteColumn({{ $column->id }})" wire:confirm="{{ __('Delete this column? All cell values and defaults for it will be removed.') }}" />
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Rows section --}}
    <div>
        <div class="flex items-center gap-2 mb-2">
            <flux:heading size="lg">{{ __('Rows') }}</flux:heading>
            <flux:spacer />
            <flux:button size="sm" icon="plus" wire:click="openCategoryModal">{{ __('Add category') }}</flux:button>
            <flux:button size="sm" variant="primary" icon="plus" wire:click="openRowModal" :disabled="$this->columns->isEmpty()">{{ __('Add row') }}</flux:button>
        </div>

        @if ($this->columns->isEmpty())
            <flux:text class="px-4 py-3 italic">{{ __('Add a column first before adding rows.') }}</flux:text>
        @else
            <div class="space-y-6">
                @foreach ($this->categories as $category)
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <flux:heading size="sm">{{ $category->name }}</flux:heading>
                            <flux:badge size="sm" color="zinc">{{ $category->rows->count() }}</flux:badge>
                            <flux:spacer />
                            <flux:button size="xs" icon="plus" variant="ghost" wire:click="openRowModal({{ $category->id }})">{{ __('Add row') }}</flux:button>
                            <flux:button size="xs" icon="pencil" variant="ghost" wire:click="editCategory({{ $category->id }})" />
                            <flux:button size="xs" icon="trash" variant="ghost" wire:click="deleteCategory({{ $category->id }})" wire:confirm="{{ __('Delete category? Rows move to uncategorized.') }}" />
                        </div>

                        <div class="space-y-1" wire:sort="sortRows($item, $position, {{ $category->id }})">
                            @foreach ($category->rows as $row)
                                @include('pages.forms._row-row', ['row' => $row])
                            @endforeach
                            @if ($category->rows->isEmpty())
                                <flux:text size="sm" class="px-4 py-3 italic">{{ __('No rows in this category yet.') }}</flux:text>
                            @endif
                        </div>
                    </div>
                @endforeach

                @if ($this->uncategorizedRows->isNotEmpty() || $this->categories->isNotEmpty())
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <flux:heading size="sm">{{ __('Uncategorized') }}</flux:heading>
                            <flux:badge size="sm" color="zinc">{{ $this->uncategorizedRows->count() }}</flux:badge>
                            <flux:spacer />
                            <flux:button size="xs" icon="plus" variant="ghost" wire:click="openRowModal">{{ __('Add row') }}</flux:button>
                        </div>

                        <div class="space-y-1" wire:sort="sortRows($item, $position, null)">
                            @foreach ($this->uncategorizedRows as $row)
                                @include('pages.forms._row-row', ['row' => $row])
                            @endforeach
                            @if ($this->uncategorizedRows->isEmpty())
                                <flux:text size="sm" class="px-4 py-3 italic">{{ __('No uncategorized rows.') }}</flux:text>
                            @endif
                        </div>
                    </div>
                @endif

                @if ($this->categories->isEmpty() && $this->uncategorizedRows->isEmpty())
                    <div class="text-center py-12">
                        <flux:text>{{ __('No rows yet.') }}</flux:text>
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- Column modal --}}
    <flux:modal name="column-form" class="md:w-[28rem]">
        <form wire:submit="saveColumn" class="space-y-6">
            <flux:heading size="lg">{{ $editingColumnId ? __('Edit column') : __('New column') }}</flux:heading>

            <flux:input wire:model="columnLabel" :label="__('Label')" placeholder="{{ __('e.g. Question') }}" autofocus />

            <flux:select wire:model.live="columnType" :label="__('Type')">
                <flux:select.option value="text">{{ __('Text input') }}</flux:select.option>
                <flux:select.option value="select">{{ __('Select') }}</flux:select.option>
            </flux:select>

            @if ($columnType === 'select')
                <flux:textarea wire:model="columnOptionsRaw" :label="__('Options')" :description="__('One option per line.')" rows="4" placeholder="Yes&#10;No&#10;Maybe" />
            @endif

            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">{{ $editingColumnId ? __('Update') : __('Create') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Category modal --}}
    <flux:modal name="category-form" class="md:w-96">
        <form wire:submit="saveCategory" class="space-y-6">
            <flux:heading size="lg">{{ $editingCategoryId ? __('Edit category') : __('New category') }}</flux:heading>

            <flux:input wire:model="categoryName" :label="__('Name')" placeholder="{{ __('e.g. Outdoors') }}" autofocus />

            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">{{ $editingCategoryId ? __('Update') : __('Create') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Row modal --}}
    <flux:modal name="row-form" class="md:w-[32rem]">
        <form wire:submit="saveRow" class="space-y-6">
            <flux:heading size="lg">{{ $editingRowId ? __('Edit row') : __('New row') }}</flux:heading>

            <flux:select wire:model="rowCategoryId" :label="__('Category')">
                <flux:select.option :value="null">{{ __('Uncategorized') }}</flux:select.option>
                @foreach ($this->categories as $category)
                    <flux:select.option :value="$category->id">{{ $category->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="space-y-3">
                <flux:label>{{ __('Default cell values') }}</flux:label>
                <flux:text size="sm">{{ __('Optional. Lock a default to prevent users from editing that cell.') }}</flux:text>

                @foreach ($this->columns as $column)
                    @php
                        $value = $rowDefaults[$column->id] ?? '';
                        $disableLock = trim((string) $value) === '';
                    @endphp
                    <div class="flex items-end gap-2" wire:key="default-{{ $column->id }}">
                        <div class="flex-1">
                            @if ($column->type === 'select')
                                <flux:select wire:model.live="rowDefaults.{{ $column->id }}" :label="$column->label">
                                    <flux:select.option :value="''">{{ __('(no default)') }}</flux:select.option>
                                    @foreach ($column->options ?? [] as $opt)
                                        <flux:select.option :value="$opt">{{ $opt }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            @else
                                <flux:input wire:model.live="rowDefaults.{{ $column->id }}" :label="$column->label" placeholder="{{ __('(no default)') }}" />
                            @endif
                        </div>
                        <div class="pb-1">
                            <flux:checkbox wire:model="rowLocks.{{ $column->id }}" :disabled="$disableLock" :label="__('Lock')" />
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">{{ $editingRowId ? __('Update') : __('Create') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
