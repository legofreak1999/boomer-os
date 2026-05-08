<?php

namespace Tests\Feature\Tasks;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_tasks_page_is_displayed(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('tasks.index'))->assertOk();
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $this->get(route('tasks.index'))->assertRedirect(route('login'));
    }

    public function test_can_create_task(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::tasks.index')
            ->set('taskName', 'Fix the light')
            ->set('taskPriority', 'high')
            ->call('saveTask')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tasks', [
            'name' => 'Fix the light',
            'priority' => 'high',
        ]);
    }

    public function test_task_name_is_required(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::tasks.index')
            ->set('taskName', '')
            ->call('saveTask')
            ->assertHasErrors(['taskName']);
    }

    public function test_can_create_task_with_due_date(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::tasks.index')
            ->set('taskName', 'Replace seat')
            ->set('taskDueDate', '2026-06-01')
            ->call('saveTask')
            ->assertHasNoErrors();

        $task = Task::where('name', 'Replace seat')->first();
        $this->assertNotNull($task);
        $this->assertEquals('2026-06-01', $task->due_date->format('Y-m-d'));
    }

    public function test_can_create_task_with_description(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::tasks.index')
            ->set('taskName', 'Fix tap')
            ->set('taskDescription', 'The one in the bathroom')
            ->call('saveTask')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tasks', [
            'name' => 'Fix tap',
            'description' => 'The one in the bathroom',
        ]);
    }

    public function test_can_update_task(): void
    {
        $this->actingAs(User::factory()->create());

        $task = Task::factory()->create(['name' => 'Old name']);

        Livewire::test('pages::tasks.index')
            ->call('editTask', $task->id)
            ->set('taskName', 'New name')
            ->set('taskPriority', 'low')
            ->call('saveTask')
            ->assertHasNoErrors();

        $task->refresh();
        $this->assertEquals('New name', $task->name);
        $this->assertEquals('low', $task->priority);
    }

    public function test_can_complete_task(): void
    {
        $this->actingAs(User::factory()->create());

        $task = Task::factory()->create();

        Livewire::test('pages::tasks.index')
            ->call('completeTask', $task->id);

        $task->refresh();
        $this->assertTrue($task->is_completed);
        $this->assertNotNull($task->completed_at);
    }

    public function test_can_reopen_task(): void
    {
        $this->actingAs(User::factory()->create());

        $task = Task::factory()->create([
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        Livewire::test('pages::tasks.index')
            ->call('reopenTask', $task->id);

        $task->refresh();
        $this->assertFalse($task->is_completed);
        $this->assertNull($task->completed_at);
    }

    public function test_can_delete_task(): void
    {
        $this->actingAs(User::factory()->create());

        $task = Task::factory()->create();

        Livewire::test('pages::tasks.index')
            ->call('deleteTask', $task->id);

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_can_sort_tasks_within_priority(): void
    {
        $this->actingAs(User::factory()->create());

        $task1 = Task::factory()->create(['priority' => 'high', 'position' => 0]);
        $task2 = Task::factory()->create(['priority' => 'high', 'position' => 1]);
        $task3 = Task::factory()->create(['priority' => 'high', 'position' => 2]);

        // Move task1 to position 2 (end)
        Livewire::test('pages::tasks.index')
            ->call('handleSort', $task1->id, 2);

        $this->assertEquals(0, $task2->refresh()->position);
        $this->assertEquals(1, $task3->refresh()->position);
        $this->assertEquals(2, $task1->refresh()->position);
    }

    public function test_overdue_detection(): void
    {
        $task = Task::factory()->create([
            'due_date' => now()->subDays(2),
            'is_completed' => false,
        ]);

        $this->assertTrue($task->isOverdue());
    }

    public function test_completed_task_is_not_overdue(): void
    {
        $task = Task::factory()->create([
            'due_date' => now()->subDays(2),
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        $this->assertFalse($task->isOverdue());
    }
}
