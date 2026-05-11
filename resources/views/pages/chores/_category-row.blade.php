{{-- Category row --}}
<flux:table.row :key="'cat-' . $category->id" class="cursor-pointer" wire:click="toggleCategoryCollapse({{ $category->id }})">
    <flux:table.cell variant="strong">
        <div class="flex items-center gap-2" style="padding-left: {{ $depth * 1.5 }}rem">
            <svg class="size-4 shrink-0 text-zinc-400 transition-transform {{ in_array($category->id, $expandedCategories) ? 'rotate-90' : '' }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
            {{ $category->name }}
            <flux:badge size="sm" color="zinc">{{ $category->chores->count() }}</flux:badge>
        </div>
    </flux:table.cell>
    <flux:table.cell>
        <div class="flex justify-end gap-1" wire:click.stop>
            <flux:dropdown position="bottom end">
                <flux:button size="sm" icon="plus" variant="ghost" />
                <flux:menu>
                    <flux:menu.item icon="tag" wire:click="openCategoryModal({{ $category->id }})">{{ __('Subcategory') }}</flux:menu.item>
                    <flux:menu.item icon="clipboard-document-list" wire:click="openChoreModal({{ $category->id }})">{{ __('Chore') }}</flux:menu.item>
                </flux:menu>
            </flux:dropdown>
            <flux:button size="sm" icon="pencil" variant="ghost" wire:click="editCategory({{ $category->id }})" />
            <flux:button size="sm" icon="trash" variant="ghost" wire:click="deleteCategory({{ $category->id }})" wire:confirm="{{ __('Are you sure you want to delete this category?') }}" />
        </div>
    </flux:table.cell>
</flux:table.row>

{{-- Expanded content: subcategories and chores --}}
@if (in_array($category->id, $expandedCategories))
    {{-- Child categories --}}
    @foreach ($category->children->sortBy('name') as $child)
        @include('pages.chores._category-row', ['category' => $child, 'depth' => $depth + 1])
    @endforeach

    {{-- Chore rows --}}
    @foreach ($category->chores as $chore)
        <flux:table.row :key="'chore-' . $chore->id">
            <flux:table.cell>
                <div style="padding-left: {{ ($depth + 1) * 1.5 }}rem">{{ $chore->name }}</div>
            </flux:table.cell>
            <flux:table.cell>
                <div class="flex justify-end gap-1">
                    <flux:button size="sm" icon="pencil" variant="ghost" wire:click="editChore({{ $chore->id }})" />
                    <flux:button size="sm" icon="trash" variant="ghost" wire:click="deleteChore({{ $chore->id }})" wire:confirm="{{ __('Are you sure you want to delete this chore?') }}" />
                </div>
            </flux:table.cell>
        </flux:table.row>
    @endforeach
@endif
