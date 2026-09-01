<?php

namespace Tests\Feature\Chores;

use App\Actions\Chores\ToggleChoreListItemCompletion;
use App\Models\AppSetting;
use App\Models\Chore;
use App\Models\ChoreCategory;
use App\Models\ChoreCompletion;
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

    public function test_can_save_list_height(): void
    {
        $this->actingAs(User::factory()->create());

        $list = ChoreList::factory()->create();

        $component = Livewire::test('pages::chores.index')
            ->call('saveListHeight', $list->id, 480);

        $this->assertEquals(480, $component->get('listHeights')[$list->id]);
    }

    public function test_save_list_height_snaps_to_step(): void
    {
        $this->actingAs(User::factory()->create());

        $list = ChoreList::factory()->create();

        $component = Livewire::test('pages::chores.index')
            ->call('saveListHeight', $list->id, 500);

        // 500 rounds to 480 (nearest multiple of 48)
        $this->assertEquals(480, $component->get('listHeights')[$list->id]);
    }

    public function test_save_list_height_clamps_to_bounds(): void
    {
        $this->actingAs(User::factory()->create());

        $list = ChoreList::factory()->create();

        $component = Livewire::test('pages::chores.index')
            ->call('saveListHeight', $list->id, 50);

        $this->assertEquals(96, $component->get('listHeights')[$list->id]);

        $component->call('saveListHeight', $list->id, 5000);

        $this->assertEquals(2000, $component->get('listHeights')[$list->id]);
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

    public function test_can_assign_user_to_chore_item(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $item = ChoreListItem::factory()->create();

        Livewire::test('pages::chores.index')
            ->call('toggleUserAssignment', $item->id, $user->id);

        $this->assertTrue($item->users()->where('user_id', $user->id)->exists());
    }

    public function test_can_assign_multiple_users_to_chore_item(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $this->actingAs($user1);

        $item = ChoreListItem::factory()->create();

        Livewire::test('pages::chores.index')
            ->call('toggleUserAssignment', $item->id, $user1->id)
            ->call('toggleUserAssignment', $item->id, $user2->id);

        $this->assertEquals(2, $item->users()->count());
        $this->assertTrue($item->users()->where('user_id', $user1->id)->exists());
        $this->assertTrue($item->users()->where('user_id', $user2->id)->exists());
    }

    public function test_can_toggle_off_user_assignment(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $item = ChoreListItem::factory()->create();
        $item->users()->attach($user->id);

        Livewire::test('pages::chores.index')
            ->call('toggleUserAssignment', $item->id, $user->id);

        $this->assertFalse($item->users()->where('user_id', $user->id)->exists());
    }

    public function test_can_clear_all_assignees(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $this->actingAs($user1);

        $item = ChoreListItem::factory()->create();
        $item->users()->attach([$user1->id, $user2->id]);

        Livewire::test('pages::chores.index')
            ->call('clearAssignees', $item->id);

        $this->assertEquals(0, $item->users()->count());
    }

    public function test_can_set_item_priority(): void
    {
        $this->actingAs(User::factory()->create());

        $item = ChoreListItem::factory()->create();

        Livewire::test('pages::chores.index')
            ->call('setItemPriority', $item->id, 'high');

        $this->assertEquals('high', $item->refresh()->priority);
    }

    public function test_can_clear_item_priority(): void
    {
        $this->actingAs(User::factory()->create());

        $item = ChoreListItem::factory()->create(['priority' => 'high']);

        Livewire::test('pages::chores.index')
            ->call('setItemPriority', $item->id, null);

        $this->assertNull($item->refresh()->priority);
    }

    public function test_can_duplicate_list(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $list = ChoreList::factory()->create([
            'name' => 'Original',
            'repeat_type' => 'weekly',
            'repeat_value' => 1,
            'repeat_start_date' => '2026-05-01',
        ]);
        $item = ChoreListItem::factory()->create([
            'chore_list_id' => $list->id,
            'priority' => 'high',
        ]);
        $item->users()->attach($user->id);

        Livewire::test('pages::chores.index')
            ->call('duplicateList', $list->id);

        $copy = ChoreList::where('name', 'Original (copy)')->first();
        $this->assertNotNull($copy);
        $this->assertEquals('weekly', $copy->repeat_type);
        $this->assertEquals(1, $copy->repeat_value);
        $this->assertEquals(1, $copy->items()->count());
        $this->assertEquals('high', $copy->items()->first()->priority);
        $this->assertFalse($copy->items()->first()->is_checked);
        $this->assertTrue($copy->items()->first()->users()->where('user_id', $user->id)->exists());
    }

    public function test_duplicate_list_resets_checked_state(): void
    {
        $this->actingAs(User::factory()->create());

        $list = ChoreList::factory()->create(['name' => 'Test']);
        ChoreListItem::factory()->create([
            'chore_list_id' => $list->id,
            'is_checked' => true,
        ]);

        Livewire::test('pages::chores.index')
            ->call('duplicateList', $list->id);

        $copy = ChoreList::where('name', 'Test (copy)')->first();
        $this->assertFalse($copy->items()->first()->is_checked);
    }

    public function test_bulk_set_priority_applies_to_category_items(): void
    {
        $this->actingAs(User::factory()->create());

        $category = ChoreCategory::factory()->create();
        $chore1 = Chore::factory()->create(['chore_category_id' => $category->id]);
        $chore2 = Chore::factory()->create(['chore_category_id' => $category->id]);
        $list = ChoreList::factory()->create();
        $item1 = ChoreListItem::factory()->create(['chore_list_id' => $list->id, 'chore_id' => $chore1->id]);
        $item2 = ChoreListItem::factory()->create(['chore_list_id' => $list->id, 'chore_id' => $chore2->id]);

        Livewire::test('pages::chores.index')
            ->call('bulkSetPriority', $list->id, $category->id, 'high');

        $this->assertEquals('high', $item1->refresh()->priority);
        $this->assertEquals('high', $item2->refresh()->priority);
    }

    public function test_bulk_set_priority_includes_subcategory_items(): void
    {
        $this->actingAs(User::factory()->create());

        $parent = ChoreCategory::factory()->create();
        $child = ChoreCategory::factory()->childOf($parent)->create();
        $chore = Chore::factory()->create(['chore_category_id' => $child->id]);
        $list = ChoreList::factory()->create();
        $item = ChoreListItem::factory()->create(['chore_list_id' => $list->id, 'chore_id' => $chore->id]);

        Livewire::test('pages::chores.index')
            ->call('bulkSetPriority', $list->id, $parent->id, 'low');

        $this->assertEquals('low', $item->refresh()->priority);
    }

    public function test_bulk_set_priority_does_not_affect_other_lists(): void
    {
        $this->actingAs(User::factory()->create());

        $category = ChoreCategory::factory()->create();
        $chore = Chore::factory()->create(['chore_category_id' => $category->id]);
        $list1 = ChoreList::factory()->create();
        $list2 = ChoreList::factory()->create();
        $item1 = ChoreListItem::factory()->create(['chore_list_id' => $list1->id, 'chore_id' => $chore->id]);
        $item2 = ChoreListItem::factory()->create(['chore_list_id' => $list2->id, 'chore_id' => $chore->id]);

        Livewire::test('pages::chores.index')
            ->call('bulkSetPriority', $list1->id, $category->id, 'high');

        $this->assertEquals('high', $item1->refresh()->priority);
        $this->assertNull($item2->refresh()->priority);
    }

    public function test_bulk_assign_user_to_category(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $category = ChoreCategory::factory()->create();
        $chore1 = Chore::factory()->create(['chore_category_id' => $category->id]);
        $chore2 = Chore::factory()->create(['chore_category_id' => $category->id]);
        $list = ChoreList::factory()->create();
        $item1 = ChoreListItem::factory()->create(['chore_list_id' => $list->id, 'chore_id' => $chore1->id]);
        $item2 = ChoreListItem::factory()->create(['chore_list_id' => $list->id, 'chore_id' => $chore2->id]);

        Livewire::test('pages::chores.index')
            ->call('bulkAssignUser', $list->id, $category->id, $user->id);

        $this->assertTrue($item1->users()->where('user_id', $user->id)->exists());
        $this->assertTrue($item2->users()->where('user_id', $user->id)->exists());
    }

    public function test_bulk_assign_user_includes_subcategories(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $parent = ChoreCategory::factory()->create();
        $child = ChoreCategory::factory()->childOf($parent)->create();
        $chore = Chore::factory()->create(['chore_category_id' => $child->id]);
        $list = ChoreList::factory()->create();
        $item = ChoreListItem::factory()->create(['chore_list_id' => $list->id, 'chore_id' => $chore->id]);

        Livewire::test('pages::chores.index')
            ->call('bulkAssignUser', $list->id, $parent->id, $user->id);

        $this->assertTrue($item->users()->where('user_id', $user->id)->exists());
    }

    public function test_bulk_remove_user_from_category(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $category = ChoreCategory::factory()->create();
        $chore = Chore::factory()->create(['chore_category_id' => $category->id]);
        $list = ChoreList::factory()->create();
        $item = ChoreListItem::factory()->create(['chore_list_id' => $list->id, 'chore_id' => $chore->id]);
        $item->users()->attach($user->id);

        Livewire::test('pages::chores.index')
            ->call('bulkRemoveUser', $list->id, $category->id, $user->id);

        $this->assertFalse($item->users()->where('user_id', $user->id)->exists());
    }

    public function test_bulk_clear_assignees_from_category(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $this->actingAs($user1);

        $category = ChoreCategory::factory()->create();
        $chore = Chore::factory()->create(['chore_category_id' => $category->id]);
        $list = ChoreList::factory()->create();
        $item = ChoreListItem::factory()->create(['chore_list_id' => $list->id, 'chore_id' => $chore->id]);
        $item->users()->attach([$user1->id, $user2->id]);

        Livewire::test('pages::chores.index')
            ->call('bulkClearAssignees', $list->id, $category->id);

        $this->assertEquals(0, $item->users()->count());
    }

    public function test_bulk_assign_user_credits_completion_for_an_already_checked_item(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $category = ChoreCategory::factory()->create();
        $chore = Chore::factory()->create(['chore_category_id' => $category->id]);
        $list = ChoreList::factory()->create();
        $item = ChoreListItem::factory()->create(['chore_list_id' => $list->id, 'chore_id' => $chore->id, 'is_checked' => true]);

        Livewire::test('pages::chores.index')
            ->call('bulkAssignUser', $list->id, $category->id, $user->id);

        $this->assertDatabaseHas('chore_completions', [
            'chore_list_item_id' => $item->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_bulk_clear_assignees_resyncs_credit_to_the_acting_user_for_a_checked_item(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $this->actingAs($user1);

        $category = ChoreCategory::factory()->create();
        $chore = Chore::factory()->create(['chore_category_id' => $category->id]);
        $list = ChoreList::factory()->create();
        $item = ChoreListItem::factory()->create(['chore_list_id' => $list->id, 'chore_id' => $chore->id, 'is_checked' => false]);
        $item->users()->attach([$user1->id, $user2->id]);
        (new ToggleChoreListItemCompletion)($item, $user1->id);

        Livewire::test('pages::chores.index')
            ->call('bulkClearAssignees', $list->id, $category->id);

        // Nobody assigned falls back to crediting whoever performed the
        // clear — same "nobody to credit otherwise" rule as everywhere else
        // in this split, not a genuinely uncredited/unclaimed state.
        $this->assertSame(1, ChoreCompletion::where('chore_list_item_id', $item->id)->count());
        $this->assertDatabaseHas('chore_completions', [
            'chore_list_item_id' => $item->id,
            'user_id' => $user1->id,
        ]);
    }

    public function test_filter_by_user_hides_list_with_no_matches(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $this->actingAs($user1);

        $list = ChoreList::factory()->create();
        $item = ChoreListItem::factory()->create(['chore_list_id' => $list->id]);
        $item->users()->attach($user1->id);

        $component = Livewire::test('pages::chores.index')
            ->call('toggleFilter', 'user', (string) $user2->id);

        $this->assertTrue($component->instance()->choreLists->isEmpty());
    }

    public function test_filter_by_user_shows_matching_items(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $this->actingAs($user1);

        $list = ChoreList::factory()->create();
        $item1 = ChoreListItem::factory()->create(['chore_list_id' => $list->id]);
        $item2 = ChoreListItem::factory()->create(['chore_list_id' => $list->id]);
        $item1->users()->attach($user1->id);
        $item2->users()->attach($user2->id);

        $component = Livewire::test('pages::chores.index')
            ->call('toggleFilter', 'user', (string) $user1->id);

        $filteredList = $component->instance()->choreLists->first();
        $this->assertEquals(1, $filteredList->items->count());
        $this->assertEquals($item1->id, $filteredList->items->first()->id);
    }

    public function test_filter_by_priority(): void
    {
        $this->actingAs(User::factory()->create());

        $list = ChoreList::factory()->create();
        $item1 = ChoreListItem::factory()->create(['chore_list_id' => $list->id, 'priority' => 'high']);
        $item2 = ChoreListItem::factory()->create(['chore_list_id' => $list->id, 'priority' => 'low']);

        $component = Livewire::test('pages::chores.index')
            ->call('toggleFilter', 'priority', 'high');

        $filteredList = $component->instance()->choreLists->first();
        $this->assertEquals(1, $filteredList->items->count());
        $this->assertEquals($item1->id, $filteredList->items->first()->id);
    }

    public function test_filter_by_category(): void
    {
        $this->actingAs(User::factory()->create());

        $cat1 = ChoreCategory::factory()->create();
        $cat2 = ChoreCategory::factory()->create();
        $chore1 = Chore::factory()->create(['chore_category_id' => $cat1->id]);
        $chore2 = Chore::factory()->create(['chore_category_id' => $cat2->id]);
        $list = ChoreList::factory()->create();
        $item1 = ChoreListItem::factory()->create(['chore_list_id' => $list->id, 'chore_id' => $chore1->id]);
        ChoreListItem::factory()->create(['chore_list_id' => $list->id, 'chore_id' => $chore2->id]);

        $component = Livewire::test('pages::chores.index')
            ->call('toggleFilter', 'category', (string) $cat1->id);

        $filteredList = $component->instance()->choreLists->first();
        $this->assertEquals(1, $filteredList->items->count());
        $this->assertEquals($item1->id, $filteredList->items->first()->id);
    }

    public function test_filter_by_category_includes_subcategories(): void
    {
        $this->actingAs(User::factory()->create());

        $parent = ChoreCategory::factory()->create();
        $child = ChoreCategory::factory()->childOf($parent)->create();
        $chore = Chore::factory()->create(['chore_category_id' => $child->id]);
        $list = ChoreList::factory()->create();
        $item = ChoreListItem::factory()->create(['chore_list_id' => $list->id, 'chore_id' => $chore->id]);

        $component = Livewire::test('pages::chores.index')
            ->call('toggleFilter', 'category', (string) $parent->id);

        $filteredList = $component->instance()->choreLists->first();
        $this->assertEquals(1, $filteredList->items->count());
        $this->assertEquals($item->id, $filteredList->items->first()->id);
    }

    public function test_combined_filters(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $list = ChoreList::factory()->create();
        $item1 = ChoreListItem::factory()->create(['chore_list_id' => $list->id, 'priority' => 'high']);
        $item2 = ChoreListItem::factory()->create(['chore_list_id' => $list->id, 'priority' => 'low']);
        $item1->users()->attach($user->id);
        $item2->users()->attach($user->id);

        $component = Livewire::test('pages::chores.index')
            ->call('toggleFilter', 'user', (string) $user->id)
            ->call('toggleFilter', 'priority', 'high');

        $filteredList = $component->instance()->choreLists->first();
        $this->assertEquals(1, $filteredList->items->count());
        $this->assertEquals($item1->id, $filteredList->items->first()->id);
    }

    public function test_toggle_filter_removes_on_second_call(): void
    {
        $this->actingAs(User::factory()->create());

        $list = ChoreList::factory()->create();
        ChoreListItem::factory()->create(['chore_list_id' => $list->id, 'priority' => 'high']);

        $component = Livewire::test('pages::chores.index')
            ->call('toggleFilter', 'priority', 'low');

        $this->assertTrue($component->instance()->choreLists->isEmpty());

        // Toggle off the filter
        $component->call('toggleFilter', 'priority', 'low');

        $this->assertFalse($component->instance()->choreLists->isEmpty());
    }

    public function test_clear_filters(): void
    {
        $this->actingAs(User::factory()->create());

        $list = ChoreList::factory()->create();
        ChoreListItem::factory()->create(['chore_list_id' => $list->id, 'priority' => 'high']);

        $component = Livewire::test('pages::chores.index')
            ->call('toggleFilter', 'priority', 'low');

        $this->assertTrue($component->instance()->choreLists->isEmpty());

        $component->call('clearFilters');

        $this->assertEmpty($component->get('filterPriorities'));
        $this->assertEmpty($component->get('filterUserIds'));
        $this->assertEmpty($component->get('filterCategoryIds'));
        $this->assertFalse($component->instance()->choreLists->isEmpty());
    }

    public function test_toggling_item_creates_a_completion_credited_to_the_logged_in_user(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $item = ChoreListItem::factory()->create(['is_checked' => false]);

        Livewire::test('pages::chores.index')
            ->call('toggleChoreItem', $item->id);

        $this->assertDatabaseHas('chore_completions', [
            'chore_list_item_id' => $item->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_assigning_the_other_user_before_checking_credits_them_instead(): void
    {
        $this->actingAs(User::factory()->create());
        $otherUser = User::factory()->create();

        $item = ChoreListItem::factory()->create(['is_checked' => false]);

        Livewire::test('pages::chores.index')
            ->call('toggleUserAssignment', $item->id, $otherUser->id)
            ->call('toggleChoreItem', $item->id);

        $this->assertDatabaseHas('chore_completions', [
            'chore_list_item_id' => $item->id,
            'user_id' => $otherUser->id,
        ]);
    }

    public function test_assigning_both_users_splits_credit_evenly(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $otherUser = User::factory()->create();

        $chore = Chore::factory()->create(['time_points' => 4]);
        $item = ChoreListItem::factory()->create(['chore_id' => $chore->id, 'is_checked' => false]);

        Livewire::test('pages::chores.index')
            ->call('toggleUserAssignment', $item->id, $user->id)
            ->call('toggleUserAssignment', $item->id, $otherUser->id)
            ->call('toggleChoreItem', $item->id);

        $this->assertSame(2, ChoreCompletion::where('chore_list_item_id', $item->id)->count());
        $this->assertDatabaseHas('chore_completions', [
            'chore_list_item_id' => $item->id,
            'user_id' => $user->id,
            'time_centipoints' => 200,
        ]);
        $this->assertDatabaseHas('chore_completions', [
            'chore_list_item_id' => $item->id,
            'user_id' => $otherUser->id,
            'time_centipoints' => 200,
        ]);
    }

    public function test_assigning_a_user_after_checking_the_item_credits_them_too(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $otherUser = User::factory()->create();

        $chore = Chore::factory()->create(['time_points' => 4]);
        $item = ChoreListItem::factory()->create(['chore_id' => $chore->id, 'is_checked' => false]);

        Livewire::test('pages::chores.index')
            ->call('toggleUserAssignment', $item->id, $user->id)
            ->call('toggleChoreItem', $item->id)
            ->call('toggleUserAssignment', $item->id, $otherUser->id);

        $this->assertSame(2, ChoreCompletion::where('chore_list_item_id', $item->id)->count());
        $this->assertDatabaseHas('chore_completions', [
            'chore_list_item_id' => $item->id,
            'user_id' => $user->id,
            'time_centipoints' => 200,
        ]);
        $this->assertDatabaseHas('chore_completions', [
            'chore_list_item_id' => $item->id,
            'user_id' => $otherUser->id,
            'time_centipoints' => 200,
        ]);
    }

    public function test_can_set_a_money_reward_on_item(): void
    {
        $this->actingAs(User::factory()->create());

        $item = ChoreListItem::factory()->create();

        Livewire::test('pages::chores.index')
            ->set("bountyInputs.{$item->id}", '15.00')
            ->call('setReward', $item->id, 'money');

        $item->refresh();
        $this->assertSame(1500, $item->bounty_cents);
        $this->assertNull($item->reward_note);
    }

    public function test_can_set_a_text_reward_on_item(): void
    {
        $this->actingAs(User::factory()->create());

        $item = ChoreListItem::factory()->create();

        Livewire::test('pages::chores.index')
            ->set("rewardNoteInputs.{$item->id}", 'Winner picks dinner')
            ->call('setReward', $item->id, 'text');

        $item->refresh();
        $this->assertSame('Winner picks dinner', $item->reward_note);
        $this->assertNull($item->bounty_cents);
    }

    public function test_setting_a_money_reward_clears_an_existing_text_reward(): void
    {
        $this->actingAs(User::factory()->create());

        $item = ChoreListItem::factory()->create(['reward_note' => 'Something']);

        Livewire::test('pages::chores.index')
            ->set("bountyInputs.{$item->id}", '15.00')
            ->call('setReward', $item->id, 'money');

        $item->refresh();
        $this->assertSame(1500, $item->bounty_cents);
        $this->assertNull($item->reward_note);
    }

    public function test_setting_a_text_reward_clears_an_existing_money_reward(): void
    {
        $this->actingAs(User::factory()->create());

        $item = ChoreListItem::factory()->create(['bounty_cents' => 1000]);

        Livewire::test('pages::chores.index')
            ->set("rewardNoteInputs.{$item->id}", 'Winner picks dinner')
            ->call('setReward', $item->id, 'text');

        $item->refresh();
        $this->assertSame('Winner picks dinner', $item->reward_note);
        $this->assertNull($item->bounty_cents);
    }

    public function test_can_clear_reward_on_item(): void
    {
        $this->actingAs(User::factory()->create());

        $item = ChoreListItem::factory()->create(['bounty_cents' => 1000]);

        Livewire::test('pages::chores.index')
            ->call('clearReward', $item->id);

        $item->refresh();
        $this->assertNull($item->bounty_cents);
        $this->assertNull($item->reward_note);
    }

    public function test_bounty_amount_is_capped_at_config_sanity_bound(): void
    {
        $this->actingAs(User::factory()->create());
        AppSetting::set('chore_reward_settings', ['bounty_max_cents' => 10000]);

        $item = ChoreListItem::factory()->create();

        Livewire::test('pages::chores.index')
            ->set("bountyInputs.{$item->id}", '150.00')
            ->call('setReward', $item->id, 'money')
            ->assertHasErrors(["bountyInputs.{$item->id}"]);

        $this->assertNull($item->refresh()->bounty_cents);
    }

    public function test_reward_note_is_displayed_on_chore_list_item_row(): void
    {
        $this->actingAs(User::factory()->create());

        ChoreListItem::factory()->create(['reward_note' => 'Winner picks dinner']);

        $this->get(route('chores.index'))->assertSee('Winner picks dinner');
    }

    public function test_reward_note_is_trimmed_and_empty_becomes_null(): void
    {
        $this->actingAs(User::factory()->create());

        $item = ChoreListItem::factory()->create();

        Livewire::test('pages::chores.index')
            ->set("rewardNoteInputs.{$item->id}", '   ')
            ->call('setReward', $item->id, 'text');

        $this->assertNull($item->refresh()->reward_note);
    }

    public function test_money_reward_badge_still_shows_after_checking_the_item(): void
    {
        $this->actingAs(User::factory()->create());

        $item = ChoreListItem::factory()->create(['is_checked' => false, 'bounty_cents' => 1500]);

        Livewire::test('pages::chores.index')
            ->call('toggleChoreItem', $item->id);

        // The item's own bounty_cents is cleared once claimed, but the
        // amount should still be visible — not look like it vanished.
        $this->assertNull($item->refresh()->bounty_cents);
        $this->get(route('chores.index'))->assertSee('&euro;15', escape: false);
    }

    public function test_text_reward_still_shows_after_checking_the_item(): void
    {
        $this->actingAs(User::factory()->create());

        $item = ChoreListItem::factory()->create(['is_checked' => false, 'reward_note' => 'Winner picks dinner']);

        Livewire::test('pages::chores.index')
            ->call('toggleChoreItem', $item->id);

        $this->get(route('chores.index'))->assertSee('Winner picks dinner');
    }

    public function test_money_reward_badge_shows_the_full_amount_after_a_split_completion(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $otherUser = User::factory()->create();

        $item = ChoreListItem::factory()->create(['is_checked' => false, 'bounty_cents' => 1000]);
        $item->users()->attach([$user->id, $otherUser->id]);

        Livewire::test('pages::chores.index')
            ->call('toggleChoreItem', $item->id);

        // Each completion row only holds its own half (500); the badge
        // should still read the original, undivided total.
        $this->get(route('chores.index'))->assertSee('&euro;10', escape: false);
    }

    public function test_escalation_badge_is_shown_when_level_is_above_zero(): void
    {
        $this->actingAs(User::factory()->create());

        $chore = Chore::factory()->create(['time_points' => 1, 'escalation_increment' => 2, 'escalation_cap' => 9]);
        ChoreListItem::factory()->create(['chore_id' => $chore->id, 'escalation_level' => 3]);

        // 1 + 3*2 = 7, bonus = 7 - 1 = 6
        $this->get(route('chores.index'))->assertSee('+6');
    }

    public function test_escalation_badge_is_not_shown_when_level_is_zero(): void
    {
        $this->actingAs(User::factory()->create());

        $chore = Chore::factory()->create(['escalation_increment' => 2]);
        ChoreListItem::factory()->create(['chore_id' => $chore->id, 'escalation_level' => 0]);

        $this->get(route('chores.index'))->assertDontSee('Missed', escape: false);
    }
}
