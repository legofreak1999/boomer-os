<?php

use App\Models\Task;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Tasks')] class extends Component {
    public ?int $editingTaskId = null;
    public string $taskName = '';
    public string $taskDescription = '';
    public string $taskPriority = Task::PRIORITY_MEDIUM;
    public ?string $taskDueDate = null;
    public bool $showCompleted = false;

    public static function priorityLabels(): array
    {
        return [
            Task::PRIORITY_HIGH => 'High',
            Task::PRIORITY_MEDIUM => 'Medium',
            Task::PRIORITY_LOW => 'Low',
        ];
    }

    public static function priorityColors(): array
    {
        return [
            Task::PRIORITY_HIGH => 'bg-red-500',
            Task::PRIORITY_MEDIUM => 'bg-yellow-500',
            Task::PRIORITY_LOW => 'bg-green-500',
        ];
    }

    #[Computed]
    public function tasksByPriority(): array
    {
        $tasks = Task::pending()->orderBy('position')->orderBy('due_date')->get();

        return [
            Task::PRIORITY_HIGH => $tasks->where('priority', Task::PRIORITY_HIGH)->values(),
            Task::PRIORITY_MEDIUM => $tasks->where('priority', Task::PRIORITY_MEDIUM)->values(),
            Task::PRIORITY_LOW => $tasks->where('priority', Task::PRIORITY_LOW)->values(),
        ];
    }

    public function hasPendingTasks(): bool
    {
        foreach ($this->tasksByPriority as $tasks) {
            if ($tasks->isNotEmpty()) {
                return true;
            }
        }

        return false;
    }

    #[Computed]
    public function completedTasks()
    {
        return Task::completed()
            ->orderByDesc('completed_at')
            ->get();
    }

    public function openTaskModal(): void
    {
        $this->resetTaskForm();
        Flux::modal('task-form')->show();
    }

    public function editTask(int $id): void
    {
        $task = Task::findOrFail($id);
        $this->editingTaskId = $id;
        $this->taskName = $task->name;
        $this->taskDescription = $task->description ?? '';
        $this->taskPriority = $task->priority;
        $this->taskDueDate = $task->due_date?->format('Y-m-d');
        Flux::modal('task-form')->show();
    }

    public function saveTask(): void
    {
        $this->validate([
            'taskName' => ['required', 'string', 'max:255'],
            'taskDescription' => ['nullable', 'string', 'max:5000'],
            'taskPriority' => ['required', 'in:'.implode(',', Task::PRIORITIES)],
            'taskDueDate' => ['nullable', 'date'],
        ]);

        $data = [
            'name' => $this->taskName,
            'description' => $this->taskDescription ?: null,
            'priority' => $this->taskPriority,
            'due_date' => $this->taskDueDate ?: null,
        ];

        if ($this->editingTaskId) {
            Task::findOrFail($this->editingTaskId)->update($data);
            Flux::toast('Task updated.');
        } else {
            $data['position'] = (Task::pending()->max('position') ?? 0) + 1;
            Task::create($data);
            Flux::toast('Task created.');
        }

        $this->resetTaskForm();
        unset($this->tasksByPriority, $this->completedTasks);
        Flux::modal('task-form')->close();
    }

    public function completeTask(int $id): void
    {
        Task::findOrFail($id)->complete();
        unset($this->tasksByPriority, $this->completedTasks);
    }

    public function reopenTask(int $id): void
    {
        $task = Task::findOrFail($id);
        $task->reopen();
        $task->update(['position' => (Task::pending()->max('position') ?? 0) + 1]);
        unset($this->tasksByPriority, $this->completedTasks);
    }

    public function deleteTask(int $id): void
    {
        Task::findOrFail($id)->delete();
        unset($this->tasksByPriority, $this->completedTasks);
        Flux::toast('Task deleted.');
    }

    public function handleSort(int $id, int $position): void
    {
        $task = Task::findOrFail($id);

        // Get all tasks in this priority group, ordered by current position
        $group = Task::pending()
            ->where('priority', $task->priority)
            ->orderBy('position')
            ->get();

        // Remove the dragged task and reinsert at new position
        $filtered = $group->reject(fn ($t) => $t->id === $task->id)->values();
        $filtered->splice($position, 0, [$task]);

        // Rewrite positions sequentially
        foreach ($filtered as $index => $t) {
            if ($t->position !== $index) {
                $t->update(['position' => $index]);
            }
        }

        unset($this->tasksByPriority);
    }

    public function resetTaskForm(): void
    {
        $this->editingTaskId = null;
        $this->taskName = '';
        $this->taskDescription = '';
        $this->taskPriority = Task::PRIORITY_MEDIUM;
        $this->taskDueDate = null;
        $this->resetValidation();
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Tasks') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Things that need to be done.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openTaskModal">
            {{ __('New Task') }}
        </flux:button>
    </div>

    {{-- Pending Tasks grouped by priority --}}
    @if (! $this->hasPendingTasks())
        <div class="text-center py-12">
            <flux:text>{{ __('No tasks. Enjoy the free time!') }}</flux:text>
        </div>
    @else
        @php
            $priorityLabels = self::priorityLabels();
            $priorityColors = self::priorityColors();
            $borderColors = [
                'high' => 'border-l-red-500',
                'medium' => 'border-l-yellow-500',
                'low' => 'border-l-green-500',
            ];
        @endphp

        <div class="space-y-6">
            @foreach ($this->tasksByPriority as $priority => $tasks)
                @if ($tasks->isNotEmpty())
                    <div>
                        <div class="flex items-center gap-2 mb-2">
                            <div class="size-2.5 rounded-full {{ $priorityColors[$priority] }}"></div>
                            <flux:heading size="sm">{{ __($priorityLabels[$priority]) }}</flux:heading>
                            <flux:badge size="sm" color="zinc">{{ $tasks->count() }}</flux:badge>
                        </div>

                        <div class="space-y-1" wire:sort="handleSort">
                            @foreach ($tasks as $task)
                                <div
                                    wire:key="{{ $task->id }}"
                                    wire:sort:item="{{ $task->id }}"
                                    class="group/task flex items-center gap-3 rounded-lg border border-l-4 {{ $borderColors[$priority] }} border-zinc-200 dark:border-zinc-700 px-4 py-3 hover:bg-zinc-100 dark:hover:bg-zinc-700/50 transition-colors"
                                >
                                    <div wire:sort:handle class="cursor-grab active:cursor-grabbing text-zinc-300 hover:text-zinc-500 dark:text-zinc-600 dark:hover:text-zinc-400">
                                        <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
                                    </div>

                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium text-sm truncate text-zinc-900 dark:text-zinc-100">{{ $task->name }}</span>
                                            @if ($task->description)
                                                <flux:icon name="document-text" variant="micro" class="size-3.5 shrink-0 text-zinc-400" />
                                            @endif
                                        </div>
                                    </div>

                                    @if ($task->due_date)
                                        @if ($task->isOverdue())
                                            <flux:badge size="sm" color="red">{{ $task->due_date->isoFormat('D MMM') }}</flux:badge>
                                        @elseif ($task->due_date->isToday())
                                            <flux:badge size="sm" color="orange">{{ __('Today') }}</flux:badge>
                                        @else
                                            <flux:badge size="sm" color="zinc">{{ $task->due_date->isoFormat('D MMM') }}</flux:badge>
                                        @endif
                                    @endif

                                    <div class="flex items-center gap-0.5 shrink-0 invisible group-hover/task:visible">
                                        <flux:button size="xs" icon="check" variant="ghost" wire:click="completeTask({{ $task->id }})" />
                                        <flux:button size="xs" icon="pencil" variant="ghost" wire:click="editTask({{ $task->id }})" />
                                        <flux:button size="xs" icon="trash" variant="ghost" wire:click="deleteTask({{ $task->id }})" wire:confirm="{{ __('Are you sure you want to delete this task?') }}" />
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    @endif

    {{-- Completed Tasks --}}
    @if ($this->completedTasks->isNotEmpty())
        <div class="mt-8">
            <button type="button" class="flex items-center gap-2 mb-3 cursor-pointer" wire:click="$toggle('showCompleted')">
                <svg class="size-4 text-zinc-400 transition-transform {{ $showCompleted ? 'rotate-90' : '' }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                <flux:heading size="sm">{{ __('Completed') }}</flux:heading>
                <flux:badge size="sm" color="zinc">{{ $this->completedTasks->count() }}</flux:badge>
            </button>

            @if ($showCompleted)
                <div class="space-y-1">
                    @foreach ($this->completedTasks as $task)
                        <div class="group/task flex items-center gap-3 rounded-lg border border-zinc-200 dark:border-zinc-700 px-4 py-3 opacity-60">
                            <div class="size-2.5 shrink-0 rounded-full bg-zinc-300 dark:bg-zinc-600"></div>

                            <div class="flex-1 min-w-0">
                                <span class="text-sm line-through text-zinc-500 dark:text-zinc-400">{{ $task->name }}</span>
                            </div>

                            <flux:text size="xs">{{ $task->completed_at->isoFormat('D MMM') }}</flux:text>

                            <div class="flex items-center gap-0.5 shrink-0 invisible group-hover/task:visible">
                                <flux:button size="xs" icon="arrow-uturn-left" variant="ghost" wire:click="reopenTask({{ $task->id }})" />
                                <flux:button size="xs" icon="trash" variant="ghost" wire:click="deleteTask({{ $task->id }})" wire:confirm="{{ __('Are you sure?') }}" />
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    {{-- Task Form Modal --}}
    <flux:modal name="task-form" class="md:w-96">
        <form wire:submit="saveTask" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingTaskId ? __('Edit Task') : __('New Task') }}</flux:heading>
            </div>

            <flux:input wire:model="taskName" :label="__('Name')" placeholder="{{ __('e.g. Fix the kitchen light') }}" autofocus />

            <flux:textarea wire:model="taskDescription" :label="__('Description')" placeholder="{{ __('Optional details...') }}" rows="3" />

            <flux:select wire:model="taskPriority" :label="__('Priority')">
                @foreach (self::priorityLabels() as $value => $label)
                    <flux:select.option :value="$value">{{ __($label) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="taskDueDate" :label="__('Due date')" type="date" />

            <div class="flex">
                <flux:spacer />
                <flux:button type="submit" variant="primary">
                    {{ $editingTaskId ? __('Update') : __('Create') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
