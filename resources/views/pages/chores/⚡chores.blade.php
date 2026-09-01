<?php

use App\Models\Chore;
use App\Models\ChoreCategory;
use App\Models\ChoreDifficultyRating;
use App\Models\User;
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
    public int $choreTimePoints = 1;
    public int $choreEscalationIncrement = 0;
    public ?int $choreEscalationCap = null;
    public array $choreDifficultyPoints = [];

    // Collapse state
    public array $expandedCategories = [];

    #[Computed]
    public function categories()
    {
        return ChoreCategory::with([
            'chores' => fn ($q) => $q->orderBy('name'),
            'chores.difficultyRatings.user',
            'children.chores' => fn ($q) => $q->orderBy('name'),
            'children.chores.difficultyRatings.user',
            'children.children',
        ])
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function allCategories()
    {
        return ChoreCategory::with('parent')->orderBy('name')->get();
    }

    #[Computed]
    public function users()
    {
        return User::orderBy('name')->get();
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
        $this->reset('choreName', 'choreCategoryId', 'editingChoreId', 'choreTimePoints', 'choreEscalationIncrement', 'choreEscalationCap');
        $this->choreCategoryId = $categoryId;
        $this->choreDifficultyPoints = $this->users->mapWithKeys(fn ($user) => [$user->id => 1])->all();
        $this->resetValidation();
        Flux::modal('chore-form')->show();
    }

    public function editChore(int $id): void
    {
        $chore = Chore::with('difficultyRatings')->findOrFail($id);
        $this->editingChoreId = $id;
        $this->choreName = $chore->name;
        $this->choreCategoryId = $chore->chore_category_id;
        $this->choreTimePoints = $chore->time_points;
        $this->choreEscalationIncrement = $chore->escalation_increment;
        $this->choreEscalationCap = $chore->escalation_cap;
        $this->choreDifficultyPoints = $this->users->mapWithKeys(
            fn ($user) => [$user->id => $chore->difficultyPointsFor($user->id)]
        )->all();
        Flux::modal('chore-form')->show();
    }

    public function saveChore(): void
    {
        $rules = [
            'choreName' => ['required', 'string', 'max:255'],
            'choreCategoryId' => ['required', 'exists:chore_categories,id'],
            'choreTimePoints' => ['required', 'integer', 'min:1', 'max:10'],
            'choreEscalationIncrement' => ['required', 'integer', 'min:0', 'max:10'],
            'choreEscalationCap' => [
                $this->choreEscalationIncrement > 0 ? 'required' : 'nullable',
                'integer',
                'min:1',
                'max:255',
            ],
            'choreDifficultyPoints.*' => ['required', 'integer', 'min:1', 'max:10'],
        ];

        $this->validate($rules);

        if ($this->choreEscalationCap !== null && $this->choreEscalationCap < $this->choreTimePoints) {
            $this->addError('choreEscalationCap', __('Escalation cap must be at least the time/size points.'));

            return;
        }

        $data = [
            'name' => $this->choreName,
            'chore_category_id' => $this->choreCategoryId,
            'time_points' => $this->choreTimePoints,
            'escalation_increment' => $this->choreEscalationIncrement,
            'escalation_cap' => $this->choreEscalationIncrement > 0 ? $this->choreEscalationCap : null,
        ];

        if ($this->editingChoreId) {
            $chore = Chore::findOrFail($this->editingChoreId);
            $chore->update($data);
            Flux::toast('Chore updated.');
        } else {
            $chore = Chore::create($data);
            Flux::toast('Chore created.');
        }

        foreach ($this->choreDifficultyPoints as $userId => $points) {
            ChoreDifficultyRating::updateOrCreate(
                ['chore_id' => $chore->id, 'user_id' => $userId],
                ['difficulty_points' => $points],
            );
        }

        $this->reset('choreName', 'choreCategoryId', 'editingChoreId', 'choreTimePoints', 'choreEscalationIncrement', 'choreEscalationCap', 'choreDifficultyPoints');
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

            <div>
                <flux:label>{{ __('Time / size') }}</flux:label>
                <div class="mt-1 flex items-center gap-3">
                    <flux:input wire:model="choreTimePoints" type="number" min="1" max="10" class="w-20" />
                    <flux:text size="sm" class="text-zinc-500">{{ __('How long/big this is (1–10), same for both of you.') }}</flux:text>
                </div>
            </div>

            <div>
                <flux:label>{{ __('Difficulty for each of you') }}</flux:label>
                <flux:text size="sm" class="text-zinc-500">{{ __('How hard this feels for each of you, independent of how long it takes.') }}</flux:text>
                <div class="mt-2 flex flex-wrap gap-4">
                    @foreach ($this->users as $user)
                        <div>
                            <flux:text size="sm" class="mb-1">{{ $user->name }}</flux:text>
                            <flux:input type="number" min="1" max="10" wire:model="choreDifficultyPoints.{{ $user->id }}" class="w-20" />
                        </div>
                    @endforeach
                </div>
            </div>

            <div>
                <flux:label>{{ __('Escalation') }}</flux:label>
                <flux:text size="sm" class="text-zinc-500">{{ __('Points added each time this chore\'s cycle is missed. 0 = disabled.') }}</flux:text>
                <div class="mt-2 flex items-end gap-4">
                    <div>
                        <flux:text size="sm" class="mb-1">{{ __('Per miss') }}</flux:text>
                        <flux:input wire:model.live="choreEscalationIncrement" type="number" min="0" max="10" class="w-20" />
                    </div>
                    @if ($choreEscalationIncrement > 0)
                        <div>
                            <flux:text size="sm" class="mb-1">{{ __('Cap') }}</flux:text>
                            <flux:input wire:model="choreEscalationCap" type="number" min="1" max="255" class="w-20" />
                        </div>
                    @endif
                </div>
            </div>

            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">
                    {{ $editingChoreId ? __('Update') : __('Create') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
