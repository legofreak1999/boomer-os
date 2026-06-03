<?php

use App\Models\Form;
use App\Models\FormCell;
use App\Models\FormResponse;
use App\Models\FormRowDefault;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Form')] class extends Component {
    public Form $form;
    public User $viewedUser;
    public bool $readOnly = false;

    /** @var array<int, array<int, string|null>> */
    public array $cells = [];

    /** @var array<int, array<int, bool>> */
    public array $locked = [];

    public function mount(Form $form, ?User $user = null): void
    {
        $this->form = $form;
        $this->viewedUser = $user?->exists ? $user : auth()->user();
        $this->readOnly = $this->viewedUser->id !== auth()->id();

        $form->load([
            'columns',
            'rows.defaults',
            'rows.category',
        ]);

        $response = $form->responseFor($this->viewedUser);
        $response?->load('cells');

        foreach ($form->rows as $row) {
            foreach ($form->columns as $column) {
                $default = $row->defaults->firstWhere('form_column_id', $column->id);
                $userCell = $response?->cells->first(
                    fn ($c) => $c->form_row_id === $row->id && $c->form_column_id === $column->id
                );

                $isLocked = (bool) ($default?->locked);
                $this->locked[$row->id][$column->id] = $isLocked;

                if ($isLocked) {
                    $this->cells[$row->id][$column->id] = $default->value;
                } else {
                    $this->cells[$row->id][$column->id] = $userCell?->value ?? $default?->value;
                }
            }
        }
    }

    #[Computed]
    public function userTabs()
    {
        $current = auth()->user();
        $responders = User::whereIn(
            'id',
            $this->form->responses()->pluck('user_id')
        )->orderBy('name')->get();

        return $responders->reject(fn ($u) => $u->id === $current->id)
            ->prepend($current)
            ->values();
    }

    public function updatedCells(mixed $value, string $key): void
    {
        if ($this->readOnly) {
            return;
        }

        $parts = explode('.', $key);
        if (count($parts) !== 2) {
            return;
        }

        $rowId = (int) $parts[0];
        $columnId = (int) $parts[1];

        if (! $this->form->rows()->whereKey($rowId)->exists()) {
            return;
        }
        if (! $this->form->columns()->whereKey($columnId)->exists()) {
            return;
        }

        $default = FormRowDefault::where('form_row_id', $rowId)
            ->where('form_column_id', $columnId)
            ->first();
        if ($default && $default->locked) {
            return;
        }

        $response = FormResponse::firstOrCreate([
            'form_id' => $this->form->id,
            'user_id' => auth()->id(),
        ]);

        FormCell::updateOrCreate(
            [
                'form_response_id' => $response->id,
                'form_row_id' => $rowId,
                'form_column_id' => $columnId,
            ],
            ['value' => $value === '' ? null : $value],
        );

        unset($this->userTabs);
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-4">
        <div>
            <div class="flex items-center gap-2 text-sm text-zinc-500">
                <a href="{{ route('forms.index') }}" wire:navigate class="hover:underline">{{ __('Forms') }}</a>
                <span>/</span>
                <span>{{ $form->name }}</span>
            </div>
            <flux:heading size="xl" class="mt-1">{{ $form->name }}</flux:heading>
            @if ($form->description)
                <flux:text class="mt-1">{{ $form->description }}</flux:text>
            @endif
        </div>
        <div class="flex items-center gap-2">
            @if ($readOnly)
                <flux:badge color="amber" icon="eye">{{ __('Viewing :name', ['name' => $viewedUser->name]) }}</flux:badge>
            @endif
            <flux:button size="sm" icon="adjustments-horizontal" :href="route('forms.structure', $form)" wire:navigate>{{ __('Structure') }}</flux:button>
        </div>
    </div>

    <div class="flex items-center gap-1 border-b border-zinc-200 dark:border-zinc-700 mb-6 overflow-x-auto">
        @foreach ($this->userTabs as $tab)
            @php
                $isSelf = $tab->id === auth()->id();
                $isActive = $tab->id === $viewedUser->id;
                $href = $isSelf ? route('forms.fill', $form) : route('forms.fill', [$form, $tab]);
            @endphp
            <a
                href="{{ $href }}"
                wire:navigate
                class="px-3 py-2 text-sm border-b-2 -mb-px transition-colors {{ $isActive ? 'border-zinc-900 dark:border-zinc-100 text-zinc-900 dark:text-zinc-100 font-medium' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}"
            >
                {{ $isSelf ? __('Me') : $tab->name }}
            </a>
        @endforeach
    </div>

    @if ($form->columns->isEmpty() || $form->rows->isEmpty())
        <div class="text-center py-12 space-y-3">
            <flux:text>{{ __('This form has no columns or rows yet.') }}</flux:text>
            <flux:button :href="route('forms.structure', $form)" wire:navigate variant="primary" icon="plus">{{ __('Edit structure') }}</flux:button>
        </div>
    @else
        @php
            $rowsByCategory = $form->rows->groupBy('form_category_id');
        @endphp

        <div class="space-y-6">
            @foreach ($form->categories as $category)
                @if ($rowsByCategory->has($category->id))
                    <div>
                        <flux:heading size="sm" class="mb-2">{{ $category->name }}</flux:heading>
                        @include('pages.forms._fill-table', ['rows' => $rowsByCategory[$category->id]])
                    </div>
                @endif
            @endforeach

            @if ($rowsByCategory->has(null))
                <div>
                    @if ($form->categories->isNotEmpty())
                        <flux:heading size="sm" class="mb-2">{{ __('Uncategorized') }}</flux:heading>
                    @endif
                    @include('pages.forms._fill-table', ['rows' => $rowsByCategory[null]])
                </div>
            @endif
        </div>
    @endif
</div>
