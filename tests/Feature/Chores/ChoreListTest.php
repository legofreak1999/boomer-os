<?php

namespace Tests\Feature\Chores;

use App\Models\Chore;
use App\Models\ChoreList;
use App\Models\ChoreListItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ChoreListTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_page_is_displayed(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('chores.index'))->assertOk();
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $this->get(route('chores.index'))->assertRedirect(route('login'));
    }

    public function test_can_create_list_with_chores(): void
    {
        $this->actingAs(User::factory()->create());

        $chore = Chore::factory()->create();

        Livewire::test('pages::chores.index')
            ->set('listName', 'Weekly Cleaning')
            ->set('selectedChoreIds', [(string) $chore->id])
            ->call('saveList')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('chore_lists', ['name' => 'Weekly Cleaning']);
        $this->assertDatabaseHas('chore_list_items', ['chore_id' => $chore->id]);
    }

    public function test_list_requires_name_and_chores(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::chores.index')
            ->set('listName', '')
            ->set('selectedChoreIds', [])
            ->call('saveList')
            ->assertHasErrors(['listName', 'selectedChoreIds']);
    }

    public function test_can_create_list_with_repeat(): void
    {
        $this->actingAs(User::factory()->create());

        $chore = Chore::factory()->create();

        Livewire::test('pages::chores.index')
            ->set('listName', 'Weekly')
            ->set('listRepeatType', 'weekly')
            ->set('listRepeatValue', 3)
            ->set('listRepeatStartDate', '2026-05-08')
            ->set('selectedChoreIds', [(string) $chore->id])
            ->call('saveList')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('chore_lists', [
            'name' => 'Weekly',
            'repeat_type' => 'weekly',
            'repeat_value' => 3,
        ]);
    }

    public function test_can_toggle_chore_item(): void
    {
        $this->actingAs(User::factory()->create());

        $item = ChoreListItem::factory()->create(['is_checked' => false]);

        Livewire::test('pages::chores.index')
            ->call('toggleChoreItem', $item->id);

        $this->assertTrue($item->refresh()->is_checked);
    }

    public function test_complete_non_repeating_list_deletes_it(): void
    {
        $this->actingAs(User::factory()->create());

        $list = ChoreList::factory()->create(['repeat_type' => null]);
        ChoreListItem::factory()->create(['chore_list_id' => $list->id, 'is_checked' => true]);

        Livewire::test('pages::chores.index')
            ->call('completeList', $list->id);

        $this->assertDatabaseMissing('chore_lists', ['id' => $list->id]);
    }

    public function test_complete_repeating_list_hides_it(): void
    {
        $this->actingAs(User::factory()->create());

        $list = ChoreList::factory()->create([
            'repeat_type' => 'weekly',
            'repeat_value' => 1,
            'repeat_start_date' => '2026-05-01',
        ]);
        $item = ChoreListItem::factory()->create(['chore_list_id' => $list->id, 'is_checked' => true]);

        Livewire::test('pages::chores.index')
            ->call('completeList', $list->id);

        $list->refresh();
        $this->assertTrue($list->is_hidden);
        // Items stay checked for reference
        $this->assertTrue($item->refresh()->is_checked);
    }

    public function test_can_toggle_hidden(): void
    {
        $this->actingAs(User::factory()->create());

        $list = ChoreList::factory()->create(['is_hidden' => false]);

        Livewire::test('pages::chores.index')
            ->call('toggleHidden', $list->id);

        $this->assertTrue($list->refresh()->is_hidden);
    }

    public function test_can_delete_list(): void
    {
        $this->actingAs(User::factory()->create());

        $list = ChoreList::factory()->create();

        Livewire::test('pages::chores.index')
            ->call('deleteList', $list->id);

        $this->assertDatabaseMissing('chore_lists', ['id' => $list->id]);
    }

    public function test_can_sort_lists(): void
    {
        $this->actingAs(User::factory()->create());

        $list = ChoreList::factory()->create(['position' => 0]);

        Livewire::test('pages::chores.index')
            ->call('handleSort', $list->id, 5);

        $this->assertEquals(5, $list->refresh()->position);
    }

    public function test_can_edit_list(): void
    {
        $this->actingAs(User::factory()->create());

        $chore1 = Chore::factory()->create();
        $chore2 = Chore::factory()->create();
        $list = ChoreList::factory()->create(['name' => 'Old Name']);
        ChoreListItem::factory()->create(['chore_list_id' => $list->id, 'chore_id' => $chore1->id]);

        Livewire::test('pages::chores.index')
            ->call('editList', $list->id)
            ->set('listName', 'New Name')
            ->set('selectedChoreIds', [(string) $chore2->id])
            ->call('saveList')
            ->assertHasNoErrors();

        $list->refresh();
        $this->assertEquals('New Name', $list->name);
        $this->assertEquals(1, $list->items()->count());
        $this->assertEquals($chore2->id, $list->items()->first()->chore_id);
    }
}
