<?php

use App\Models\Form;
use App\Models\FormColumn;
use App\Models\FormColumnCategory;
use App\Models\FormRow;
use App\Models\FormRowCategory;
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
    public int|string|null $columnCategoryId = null;

    // -- Column category form --
    public ?int $editingColumnCategoryId = null;
    public string $columnCategoryName = '';

    // -- Row category form --
    public ?int $editingRowCategoryId = null;
    public string $rowCategoryName = '';

    // -- Row form --
    public ?int $editingRowId = null;
    public int|string|null $rowCategoryId = null;
    /** @var array<int, string> */
    public array $rowDefaults = [];
    /** @var array<int, bool> */
    public array $rowLocks = [];
    /** @var array<int, string> */
    public array $rowDescriptions = [];

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
    public function columnCategories()
    {
        return $this->form->columnCategories()->get();
    }

    #[Computed]
    public function rowCategories()
    {
        return $this->form->rowCategories()
            ->with(['rows' => fn ($q) => $q->orderBy('position')->with('defaults')])
            ->get();
    }

    #[Computed]
    public function uncategorizedRows()
    {
        return $this->form->rows()->whereNull('form_row_category_id')->with('defaults')->get();
    }

    // ---------------- Columns ----------------

    public function openColumnModal(?int $columnCategoryId = null): void
    {
        $this->resetColumnForm();
        $this->columnCategoryId = $columnCategoryId;
        Flux::modal('column-form')->show();
    }

    public function editColumn(int $id): void
    {
        $column = FormColumn::where('form_id', $this->form->id)->findOrFail($id);
        $this->editingColumnId = $id;
        $this->columnLabel = $column->label;
        $this->columnType = $column->type;
        $this->columnOptionsRaw = is_array($column->options)
            ? collect($column->options)
                ->map(fn ($row) => is_array($row) ? implode("\n", $row) : $row)
                ->implode("\n\n")
            : '';
        $this->columnCategoryId = $column->form_column_category_id;
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

        $categoryId = $this->columnCategoryId === '' || $this->columnCategoryId === null
            ? null
            : (int) $this->columnCategoryId;

        $data = [
            'label' => $this->columnLabel,
            'type' => $this->columnType,
            'options' => $options,
            'form_column_category_id' => $categoryId,
        ];

        if ($this->editingColumnId) {
            FormColumn::where('form_id', $this->form->id)
                ->findOrFail($this->editingColumnId)
                ->update($data);
            $this->normalizeColumnPositions();
            Flux::toast('Column updated.');
        } else {
            $data['form_id'] = $this->form->id;
            $data['position'] = $this->endOfCategoryPosition($categoryId);
            FormColumn::create($data);
            $this->normalizeColumnPositions();
            Flux::toast('Column added.');
        }

        $this->resetColumnForm();
        unset($this->columns, $this->columnCategories, $this->rowCategories, $this->uncategorizedRows);
        Flux::modal('column-form')->close();
    }

    public function deleteColumn(int $id): void
    {
        FormColumn::where('form_id', $this->form->id)->findOrFail($id)->delete();
        unset($this->columns, $this->rowCategories, $this->uncategorizedRows);
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
        $this->columnCategoryId = null;
        $this->resetValidation();
    }

    // ---------------- Column categories ----------------

    public function openColumnCategoryModal(): void
    {
        $this->resetColumnCategoryForm();
        Flux::modal('column-category-form')->show();
    }

    public function editColumnCategory(int $id): void
    {
        $category = FormColumnCategory::where('form_id', $this->form->id)->findOrFail($id);
        $this->editingColumnCategoryId = $id;
        $this->columnCategoryName = $category->name;
        Flux::modal('column-category-form')->show();
    }

    public function saveColumnCategory(): void
    {
        $this->validate([
            'columnCategoryName' => ['required', 'string', 'max:255'],
        ]);

        if ($this->editingColumnCategoryId) {
            FormColumnCategory::where('form_id', $this->form->id)
                ->findOrFail($this->editingColumnCategoryId)
                ->update(['name' => $this->columnCategoryName]);
            Flux::toast('Column category updated.');
        } else {
            FormColumnCategory::create([
                'form_id' => $this->form->id,
                'name' => $this->columnCategoryName,
                'position' => ($this->form->columnCategories()->max('position') ?? 0) + 1,
            ]);
            Flux::toast('Column category created.');
        }

        $this->resetColumnCategoryForm();
        unset($this->columnCategories, $this->columns);
        Flux::modal('column-category-form')->close();
    }

    public function deleteColumnCategory(int $id): void
    {
        FormColumnCategory::where('form_id', $this->form->id)->findOrFail($id)->delete();
        unset($this->columnCategories, $this->columns);
        Flux::toast('Column category deleted. Its columns moved to uncategorized.');
    }

    public function resetColumnCategoryForm(): void
    {
        $this->editingColumnCategoryId = null;
        $this->columnCategoryName = '';
        $this->resetValidation();
    }

    // ---------------- Row categories ----------------

    public function openRowCategoryModal(): void
    {
        $this->resetRowCategoryForm();
        Flux::modal('row-category-form')->show();
    }

    public function editRowCategory(int $id): void
    {
        $category = FormRowCategory::where('form_id', $this->form->id)->findOrFail($id);
        $this->editingRowCategoryId = $id;
        $this->rowCategoryName = $category->name;
        Flux::modal('row-category-form')->show();
    }

    public function saveRowCategory(): void
    {
        $this->validate([
            'rowCategoryName' => ['required', 'string', 'max:255'],
        ]);

        if ($this->editingRowCategoryId) {
            FormRowCategory::where('form_id', $this->form->id)
                ->findOrFail($this->editingRowCategoryId)
                ->update(['name' => $this->rowCategoryName]);
            Flux::toast('Row category updated.');
        } else {
            FormRowCategory::create([
                'form_id' => $this->form->id,
                'name' => $this->rowCategoryName,
                'position' => ($this->form->rowCategories()->max('position') ?? 0) + 1,
            ]);
            Flux::toast('Row category created.');
        }

        $this->resetRowCategoryForm();
        unset($this->rowCategories, $this->uncategorizedRows);
        Flux::modal('row-category-form')->close();
    }

    public function deleteRowCategory(int $id): void
    {
        FormRowCategory::where('form_id', $this->form->id)->findOrFail($id)->delete();
        unset($this->rowCategories, $this->uncategorizedRows);
        Flux::toast('Row category deleted. Its rows moved to uncategorized.');
    }

    public function resetRowCategoryForm(): void
    {
        $this->editingRowCategoryId = null;
        $this->rowCategoryName = '';
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
            $this->rowDescriptions[$column->id] = '';
        }
        Flux::modal('row-form')->show();
    }

    public function editRow(int $id): void
    {
        $row = FormRow::where('form_id', $this->form->id)->with('defaults')->findOrFail($id);
        $this->resetRowForm();
        $this->editingRowId = $id;
        $this->rowCategoryId = $row->form_row_category_id;

        foreach ($this->columns as $column) {
            $default = $row->defaults->firstWhere('form_column_id', $column->id);
            $this->rowDefaults[$column->id] = $default?->value ?? '';
            $this->rowLocks[$column->id] = (bool) ($default?->locked);
            $this->rowDescriptions[$column->id] = $default?->description ?? '';
        }
        Flux::modal('row-form')->show();
    }

    public function saveRow(): void
    {
        $categoryId = $this->rowCategoryId === '' || $this->rowCategoryId === null
            ? null
            : (int) $this->rowCategoryId;

        if ($this->editingRowId) {
            $row = FormRow::where('form_id', $this->form->id)->findOrFail($this->editingRowId);
            $row->update(['form_row_category_id' => $categoryId]);
            Flux::toast('Row updated.');
        } else {
            $row = FormRow::create([
                'form_id' => $this->form->id,
                'form_row_category_id' => $categoryId,
                'position' => ($this->form->rows()->where('form_row_category_id', $categoryId)->max('position') ?? 0) + 1,
            ]);
            Flux::toast('Row added.');
        }

        foreach ($this->columns as $column) {
            $value = trim((string) ($this->rowDefaults[$column->id] ?? ''));
            $locked = (bool) ($this->rowLocks[$column->id] ?? false);
            $description = trim((string) ($this->rowDescriptions[$column->id] ?? ''));

            if ($value === '' && $description === '') {
                FormRowDefault::where('form_row_id', $row->id)
                    ->where('form_column_id', $column->id)
                    ->delete();

                continue;
            }

            FormRowDefault::updateOrCreate(
                ['form_row_id' => $row->id, 'form_column_id' => $column->id],
                [
                    'value' => $value === '' ? null : $value,
                    'locked' => $value === '' ? false : $locked,
                    'description' => $description === '' ? null : $description,
                ],
            );
        }

        $this->resetRowForm();
        unset($this->rowCategories, $this->uncategorizedRows);
        Flux::modal('row-form')->close();
    }

    public function deleteRow(int $id): void
    {
        FormRow::where('form_id', $this->form->id)->findOrFail($id)->delete();
        unset($this->rowCategories, $this->uncategorizedRows);
        Flux::toast('Row deleted.');
    }

    public function sortRows(int $id, int $position): void
    {
        $row = FormRow::where('form_id', $this->form->id)->findOrFail($id);
        $categoryId = $row->form_row_category_id;

        $group = $this->form->rows()
            ->where('form_row_category_id', $categoryId)
            ->orderBy('position')
            ->get();

        $filtered = $group->reject(fn ($r) => $r->id === $row->id)->values();
        $filtered->splice($position, 0, [$row]);

        foreach ($filtered as $index => $r) {
            if ($r->position !== $index) {
                $r->update(['position' => $index]);
            }
        }

        unset($this->rowCategories, $this->uncategorizedRows);
    }

    public function resetRowForm(): void
    {
        $this->editingRowId = null;
        $this->rowCategoryId = null;
        $this->rowDefaults = [];
        $this->rowLocks = [];
        $this->rowDescriptions = [];
        $this->resetValidation();
    }

    // ---------------- Helpers ----------------

    private function parseOptions(string $raw): array
    {
        // Options are grouped into rows. Single newline = next option on the
        // same horizontal row. Blank line = next vertical row. Each inner array
        // is one row of options.
        return collect(preg_split('/\r?\n\s*\r?\n+/', trim($raw)))
            ->map(fn ($block) => collect(preg_split('/\r?\n/', $block))
                ->map(fn ($item) => trim($item))
                ->filter()
                ->values()
                ->all()
            )
            ->filter(fn ($row) => count($row) > 0)
            ->values()
            ->all();
    }

    private function endOfCategoryPosition(?int $categoryId): int
    {
        $all = $this->form->columns()->get();
        $lastInCategory = $all->filter(fn ($c) => $c->form_column_category_id === $categoryId)->last();

        return $lastInCategory ? $lastInCategory->position + 1 : ($all->max('position') + 1 ?? 0);
    }

    private function normalizeColumnPositions(): void
    {
        // Re-sort columns so that all columns within a category are adjacent.
        // Categories ordered by their own position; columns within a category keep relative order.
        $all = $this->form->columns()->get();
        $catOrder = $this->form->columnCategories()->pluck('position', 'id');
        $sorted = $all->sortBy(function ($c) use ($catOrder) {
            // null category goes last (large key)
            $catKey = $c->form_column_category_id === null
                ? PHP_INT_MAX
                : ($catOrder[$c->form_column_category_id] ?? PHP_INT_MAX - 1);

            return [$catKey, $c->position];
        })->values();

        foreach ($sorted as $index => $c) {
            if ($c->position !== $index) {
                $c->update(['position' => $index]);
            }
        }
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
            <flux:button size="sm" icon="plus" wire:click="openColumnCategoryModal">{{ __('Add column category') }}</flux:button>
            <flux:button size="sm" icon="plus" wire:click="openColumnModal">{{ __('Add column') }}</flux:button>
        </div>

        @if ($this->columnCategories->isNotEmpty())
            <div class="flex flex-wrap items-center gap-1 mb-3">
                @foreach ($this->columnCategories as $cat)
                    <span class="group/cc inline-flex items-center gap-1 rounded-full border border-zinc-200 dark:border-zinc-700 px-2 py-0.5 text-xs">
                        <span class="text-zinc-700 dark:text-zinc-300">{{ $cat->name }}</span>
                        <button type="button" wire:click="editColumnCategory({{ $cat->id }})" class="text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200">
                            <flux:icon name="pencil" variant="micro" class="size-3" />
                        </button>
                        <button type="button" wire:click="deleteColumnCategory({{ $cat->id }})" wire:confirm="{{ __('Delete column category? Its columns move to uncategorized.') }}" class="text-zinc-400 hover:text-rose-600">
                            <flux:icon name="trash" variant="micro" class="size-3" />
                        </button>
                    </span>
                @endforeach
            </div>
        @endif

        @if ($this->columns->isEmpty())
            <flux:text class="px-4 py-3 italic">{{ __('No columns yet. Add at least one column to start collecting answers.') }}</flux:text>
        @else
            <div class="space-y-1" wire:sort="sortColumns">
                @foreach ($this->columns as $column)
                    @php
                        $catName = $column->form_column_category_id
                            ? optional($this->columnCategories->firstWhere('id', $column->form_column_category_id))->name
                            : null;
                    @endphp
                    <div
                        wire:key="column-{{ $column->id }}"
                        wire:sort:item="{{ $column->id }}"
                        class="group/col flex items-center gap-3 rounded-lg border border-zinc-200 dark:border-zinc-700 px-4 py-2 hover:bg-zinc-100 dark:hover:bg-zinc-700/50 transition-colors"
                    >
                        <div wire:sort:handle class="cursor-grab active:cursor-grabbing text-zinc-300 hover:text-zinc-500 dark:text-zinc-600 dark:hover:text-zinc-400">
                            <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
                        </div>

                        @php
                            $typeIcon = match ($column->type) {
                                'select' => 'chevron-up-down',
                                'textarea' => 'bars-3-bottom-left',
                                default => 'pencil-square',
                            };
                        @endphp
                        <flux:icon :name="$typeIcon" variant="micro" class="size-4 shrink-0 text-zinc-500" />

                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium truncate text-zinc-900 dark:text-zinc-100">{{ $column->label }}</div>
                            @if ($column->type === 'select' && is_array($column->options))
                                <div class="text-xs text-zinc-500 truncate">{{ collect($column->options)->flatten()->implode(' · ') }}</div>
                            @endif
                        </div>

                        @if ($catName)
                            <flux:badge size="sm" color="indigo">{{ $catName }}</flux:badge>
                        @endif
                        <flux:badge size="sm" color="zinc">
                            {{ match ($column->type) { 'select' => __('Select'), 'textarea' => __('Long text'), default => __('Text') } }}
                        </flux:badge>

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
            <flux:button size="sm" icon="plus" wire:click="openRowCategoryModal">{{ __('Add row category') }}</flux:button>
            <flux:button size="sm" variant="primary" icon="plus" wire:click="openRowModal" :disabled="$this->columns->isEmpty()">{{ __('Add row') }}</flux:button>
        </div>

        @if ($this->columns->isEmpty())
            <flux:text class="px-4 py-3 italic">{{ __('Add a column first before adding rows.') }}</flux:text>
        @else
            <div class="space-y-6">
                @foreach ($this->rowCategories as $category)
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <flux:heading size="sm">{{ $category->name }}</flux:heading>
                            <flux:badge size="sm" color="zinc">{{ $category->rows->count() }}</flux:badge>
                            <flux:spacer />
                            <flux:button size="xs" icon="plus" variant="ghost" wire:click="openRowModal({{ $category->id }})">{{ __('Add row') }}</flux:button>
                            <flux:button size="xs" icon="pencil" variant="ghost" wire:click="editRowCategory({{ $category->id }})" />
                            <flux:button size="xs" icon="trash" variant="ghost" wire:click="deleteRowCategory({{ $category->id }})" wire:confirm="{{ __('Delete category? Rows move to uncategorized.') }}" />
                        </div>

                        <div class="space-y-1" wire:sort="sortRows">
                            @foreach ($category->rows as $row)
                                @include('pages.forms._row-row', ['row' => $row])
                            @endforeach
                            @if ($category->rows->isEmpty())
                                <flux:text size="sm" class="px-4 py-3 italic">{{ __('No rows in this category yet.') }}</flux:text>
                            @endif
                        </div>
                    </div>
                @endforeach

                @if ($this->uncategorizedRows->isNotEmpty() || $this->rowCategories->isNotEmpty())
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <flux:heading size="sm">{{ __('Uncategorized') }}</flux:heading>
                            <flux:badge size="sm" color="zinc">{{ $this->uncategorizedRows->count() }}</flux:badge>
                            <flux:spacer />
                            <flux:button size="xs" icon="plus" variant="ghost" wire:click="openRowModal">{{ __('Add row') }}</flux:button>
                        </div>

                        <div class="space-y-1" wire:sort="sortRows">
                            @foreach ($this->uncategorizedRows as $row)
                                @include('pages.forms._row-row', ['row' => $row])
                            @endforeach
                            @if ($this->uncategorizedRows->isEmpty())
                                <flux:text size="sm" class="px-4 py-3 italic">{{ __('No uncategorized rows.') }}</flux:text>
                            @endif
                        </div>
                    </div>
                @endif

                @if ($this->rowCategories->isEmpty() && $this->uncategorizedRows->isEmpty())
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
                <flux:select.option value="textarea">{{ __('Long text') }}</flux:select.option>
                <flux:select.option value="select">{{ __('Select') }}</flux:select.option>
            </flux:select>

            @if ($columnType === 'select')
                <flux:textarea wire:model="columnOptionsRaw" :label="__('Options')" :description="__('One option per line. Use a blank line to start a new horizontal row of options.')" rows="6" placeholder="Yes&#10;No&#10;Maybe&#10;&#10;Not sure" />
            @endif

            <flux:select wire:model="columnCategoryId" :label="__('Category')">
                <flux:select.option :value="null">{{ __('Uncategorized') }}</flux:select.option>
                @foreach ($this->columnCategories as $cat)
                    <flux:select.option :value="$cat->id">{{ $cat->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">{{ $editingColumnId ? __('Update') : __('Create') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Column category modal --}}
    <flux:modal name="column-category-form" class="md:w-96">
        <form wire:submit="saveColumnCategory" class="space-y-6">
            <flux:heading size="lg">{{ $editingColumnCategoryId ? __('Edit column category') : __('New column category') }}</flux:heading>

            <flux:input wire:model="columnCategoryName" :label="__('Name')" placeholder="{{ __('e.g. Decision details') }}" autofocus />

            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">{{ $editingColumnCategoryId ? __('Update') : __('Create') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Row category modal --}}
    <flux:modal name="row-category-form" class="md:w-96">
        <form wire:submit="saveRowCategory" class="space-y-6">
            <flux:heading size="lg">{{ $editingRowCategoryId ? __('Edit row category') : __('New row category') }}</flux:heading>

            <flux:input wire:model="rowCategoryName" :label="__('Name')" placeholder="{{ __('e.g. Outdoors') }}" autofocus />

            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">{{ $editingRowCategoryId ? __('Update') : __('Create') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Row modal --}}
    <flux:modal name="row-form" class="md:w-[32rem]">
        <form wire:submit="saveRow" class="space-y-6">
            <flux:heading size="lg">{{ $editingRowId ? __('Edit row') : __('New row') }}</flux:heading>

            <flux:select wire:model="rowCategoryId" :label="__('Category')">
                <flux:select.option :value="null">{{ __('Uncategorized') }}</flux:select.option>
                @foreach ($this->rowCategories as $category)
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
                    <div class="space-y-1" wire:key="default-{{ $column->id }}">
                        <div class="flex items-end gap-2">
                            <div class="flex-1">
                                @if ($column->type === 'select')
                                    <flux:select wire:model.live="rowDefaults.{{ $column->id }}" :label="$column->label">
                                        <flux:select.option :value="''">{{ __('(no default)') }}</flux:select.option>
                                        @foreach (collect($column->options ?? [])->flatten() as $opt)
                                            <flux:select.option :value="$opt">{{ $opt }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                @elseif ($column->type === 'textarea')
                                    <flux:textarea wire:model.live="rowDefaults.{{ $column->id }}" :label="$column->label" rows="2" placeholder="{{ __('(no default)') }}" />
                                @else
                                    <flux:input wire:model.live="rowDefaults.{{ $column->id }}" :label="$column->label" placeholder="{{ __('(no default)') }}" />
                                @endif
                            </div>
                            <div class="pb-1">
                                <flux:checkbox wire:model="rowLocks.{{ $column->id }}" :disabled="$disableLock" :label="__('Lock')" />
                            </div>
                        </div>
                        <flux:input
                            wire:model="rowDescriptions.{{ $column->id }}"
                            size="sm"
                            placeholder="{{ __('Optional description shown below the cell') }}"
                        />
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
