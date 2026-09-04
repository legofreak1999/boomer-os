<?php

namespace Tests\Feature\Chores;

use App\Models\Chore;
use App\Models\ChoreCategory;
use App\Models\ChoreDifficultyRating;
use App\Models\ChoreListItem;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ChoreManageTest extends TestCase
{
    use RefreshDatabase;

    // --- Page ---

    public function test_manage_page_is_displayed(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('chores.manage'))->assertOk();
    }

    public function test_manage_page_redirects_unauthenticated(): void
    {
        $this->get(route('chores.manage'))->assertRedirect(route('login'));
    }

    // --- Categories ---

    public function test_can_create_category(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::chores.chores')
            ->set('categoryName', 'Kitchen')
            ->call('saveCategory')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('chore_categories', ['name' => 'Kitchen', 'parent_id' => null]);
    }

    public function test_can_create_subcategory(): void
    {
        $this->actingAs(User::factory()->create());

        $parent = ChoreCategory::factory()->create(['name' => 'Kitchen']);

        Livewire::test('pages::chores.chores')
            ->set('categoryName', 'Counters')
            ->set('categoryParentId', $parent->id)
            ->call('saveCategory')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('chore_categories', ['name' => 'Counters', 'parent_id' => $parent->id]);
    }

    public function test_category_name_is_required(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::chores.chores')
            ->set('categoryName', '')
            ->call('saveCategory')
            ->assertHasErrors(['categoryName']);
    }

    public function test_can_update_category(): void
    {
        $this->actingAs(User::factory()->create());

        $category = ChoreCategory::factory()->create(['name' => 'Old']);

        Livewire::test('pages::chores.chores')
            ->call('editCategory', $category->id)
            ->set('categoryName', 'New')
            ->call('saveCategory')
            ->assertHasNoErrors();

        $this->assertEquals('New', $category->refresh()->name);
    }

    public function test_can_update_category_parent(): void
    {
        $this->actingAs(User::factory()->create());

        $parent = ChoreCategory::factory()->create();
        $category = ChoreCategory::factory()->create();

        Livewire::test('pages::chores.chores')
            ->call('editCategory', $category->id)
            ->set('categoryParentId', $parent->id)
            ->call('saveCategory')
            ->assertHasNoErrors();

        $this->assertEquals($parent->id, $category->refresh()->parent_id);
    }

    public function test_edit_category_loads_parent(): void
    {
        $this->actingAs(User::factory()->create());

        $parent = ChoreCategory::factory()->create();
        $child = ChoreCategory::factory()->childOf($parent)->create();

        Livewire::test('pages::chores.chores')
            ->call('editCategory', $child->id)
            ->assertSet('categoryParentId', $parent->id);
    }

    public function test_can_delete_category_without_chores(): void
    {
        $this->actingAs(User::factory()->create());

        $category = ChoreCategory::factory()->create();

        Livewire::test('pages::chores.chores')
            ->call('deleteCategory', $category->id);

        $this->assertDatabaseMissing('chore_categories', ['id' => $category->id]);
    }

    public function test_cannot_delete_category_with_chores(): void
    {
        $this->actingAs(User::factory()->create());

        $category = ChoreCategory::factory()->create();
        Chore::factory()->create(['chore_category_id' => $category->id]);

        Livewire::test('pages::chores.chores')
            ->call('deleteCategory', $category->id);

        $this->assertDatabaseHas('chore_categories', ['id' => $category->id]);
    }

    public function test_cannot_delete_category_with_children(): void
    {
        $this->actingAs(User::factory()->create());

        $parent = ChoreCategory::factory()->create();
        ChoreCategory::factory()->childOf($parent)->create();

        Livewire::test('pages::chores.chores')
            ->call('deleteCategory', $parent->id);

        $this->assertDatabaseHas('chore_categories', ['id' => $parent->id]);
    }

    public function test_cannot_set_a_categorys_parent_to_itself(): void
    {
        $this->actingAs(User::factory()->create());

        $category = ChoreCategory::factory()->create();

        Livewire::test('pages::chores.chores')
            ->call('editCategory', $category->id)
            ->set('categoryParentId', $category->id)
            ->call('saveCategory')
            ->assertHasErrors('categoryParentId');

        $this->assertNull($category->refresh()->parent_id);
    }

    public function test_cannot_set_a_categorys_parent_to_its_own_descendant(): void
    {
        $this->actingAs(User::factory()->create());

        $grandparent = ChoreCategory::factory()->create();
        $parent = ChoreCategory::factory()->childOf($grandparent)->create();
        $child = ChoreCategory::factory()->childOf($parent)->create();

        // Would create grandparent -> child -> grandparent, a cycle.
        Livewire::test('pages::chores.chores')
            ->call('editCategory', $grandparent->id)
            ->set('categoryParentId', $child->id)
            ->call('saveCategory')
            ->assertHasErrors('categoryParentId');

        $this->assertNull($grandparent->refresh()->parent_id);
    }

    public function test_descendant_category_ids_includes_grandchildren(): void
    {
        $this->actingAs(User::factory()->create());

        $root = ChoreCategory::factory()->create();
        $child = ChoreCategory::factory()->childOf($root)->create();
        $grandchild = ChoreCategory::factory()->childOf($child)->create();
        $unrelated = ChoreCategory::factory()->create();

        $component = Livewire::test('pages::chores.chores');
        $descendants = $component->instance()->descendantCategoryIds($root->id);

        $this->assertEqualsCanonicalizing([$child->id, $grandchild->id], $descendants);
        $this->assertNotContains($unrelated->id, $descendants);
    }

    public function test_ancestors_and_full_path_do_not_infinite_loop_on_a_cycle(): void
    {
        // A cycle shouldn't be reachable via the app (the parent picker
        // excludes self+descendants, and saveCategory rejects it
        // server-side too), but ancestors()/fullPath() still need a
        // backstop in case one ever exists (direct DB edit, a future bug).
        $a = ChoreCategory::factory()->create(['name' => 'A']);
        $b = ChoreCategory::factory()->create(['name' => 'B', 'parent_id' => $a->id]);
        $a->update(['parent_id' => $b->id]);

        $a->refresh();
        $b->refresh();

        $this->assertIsArray($a->ancestors());
        $this->assertIsString($a->fullPath());
        $this->assertIsArray($b->ancestors());
        $this->assertIsString($b->fullPath());
    }

    public function test_deleting_a_category_with_children_is_rejected_at_the_database_level(): void
    {
        // Defense-in-depth backstop: even bypassing the app-level guard
        // (which already blocks this via deleteCategory()), the foreign key
        // itself must restrict, not cascade — a cascade here would silently
        // wipe an entire subcategory subtree.
        $parent = ChoreCategory::factory()->create();
        ChoreCategory::factory()->childOf($parent)->create();

        $this->expectException(QueryException::class);

        $parent->delete();
    }

    public function test_category_full_path(): void
    {
        $root = ChoreCategory::factory()->create(['name' => 'Kitchen']);
        $child = ChoreCategory::factory()->childOf($root)->create(['name' => 'Counters']);
        $grandchild = ChoreCategory::factory()->childOf($child)->create(['name' => 'Marble']);

        $this->assertEquals('Kitchen', $root->fullPath());
        $this->assertEquals('Kitchen > Counters', $child->fullPath());
        $this->assertEquals('Kitchen > Counters > Marble', $grandchild->fullPath());
    }

    // --- Chores ---

    public function test_can_create_chore(): void
    {
        $this->actingAs(User::factory()->create());

        $category = ChoreCategory::factory()->create();

        Livewire::test('pages::chores.chores')
            ->set('choreName', 'Vacuum')
            ->set('choreCategoryId', $category->id)
            ->call('saveChore')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('chores', ['name' => 'Vacuum', 'chore_category_id' => $category->id]);
    }

    public function test_can_create_chore_in_subcategory(): void
    {
        $this->actingAs(User::factory()->create());

        $parent = ChoreCategory::factory()->create();
        $child = ChoreCategory::factory()->childOf($parent)->create();

        Livewire::test('pages::chores.chores')
            ->set('choreName', 'Wipe counters')
            ->set('choreCategoryId', $child->id)
            ->call('saveChore')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('chores', ['name' => 'Wipe counters', 'chore_category_id' => $child->id]);
    }

    public function test_chore_requires_name_and_category(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::chores.chores')
            ->set('choreName', '')
            ->set('choreCategoryId', null)
            ->call('saveChore')
            ->assertHasErrors(['choreName', 'choreCategoryId']);
    }

    public function test_can_update_chore(): void
    {
        $this->actingAs(User::factory()->create());

        $chore = Chore::factory()->create(['name' => 'Old']);
        $newCategory = ChoreCategory::factory()->create();

        Livewire::test('pages::chores.chores')
            ->call('editChore', $chore->id)
            ->set('choreName', 'New')
            ->set('choreCategoryId', $newCategory->id)
            ->call('saveChore')
            ->assertHasNoErrors();

        $chore->refresh();
        $this->assertEquals('New', $chore->name);
        $this->assertEquals($newCategory->id, $chore->chore_category_id);
    }

    public function test_can_delete_chore_not_in_list(): void
    {
        $this->actingAs(User::factory()->create());

        $chore = Chore::factory()->create();

        Livewire::test('pages::chores.chores')
            ->call('deleteChore', $chore->id);

        $this->assertDatabaseMissing('chores', ['id' => $chore->id]);
    }

    public function test_cannot_delete_chore_used_in_list(): void
    {
        $this->actingAs(User::factory()->create());

        $chore = Chore::factory()->create();
        ChoreListItem::factory()->create(['chore_id' => $chore->id]);

        Livewire::test('pages::chores.chores')
            ->call('deleteChore', $chore->id);

        $this->assertDatabaseHas('chores', ['id' => $chore->id]);
    }

    // --- Reward fields ---

    public function test_can_create_chore_with_time_points(): void
    {
        $this->actingAs(User::factory()->create());

        $category = ChoreCategory::factory()->create();

        Livewire::test('pages::chores.chores')
            ->set('choreName', 'Vacuum')
            ->set('choreCategoryId', $category->id)
            ->set('choreTimePoints', 4)
            ->call('saveChore')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('chores', [
            'name' => 'Vacuum',
            'time_points' => 4,
        ]);
    }

    public function test_time_points_defaults_to_one(): void
    {
        $this->actingAs(User::factory()->create());

        $category = ChoreCategory::factory()->create();

        Livewire::test('pages::chores.chores')
            ->set('choreName', 'Vacuum')
            ->set('choreCategoryId', $category->id)
            ->call('saveChore')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('chores', ['name' => 'Vacuum', 'time_points' => 1]);
    }

    public function test_time_points_must_be_between_one_and_a_hundred(): void
    {
        $this->actingAs(User::factory()->create());

        $category = ChoreCategory::factory()->create();

        Livewire::test('pages::chores.chores')
            ->set('choreName', 'Vacuum')
            ->set('choreCategoryId', $category->id)
            ->set('choreTimePoints', 0)
            ->call('saveChore')
            ->assertHasErrors(['choreTimePoints']);

        Livewire::test('pages::chores.chores')
            ->set('choreName', 'Vacuum')
            ->set('choreCategoryId', $category->id)
            ->set('choreTimePoints', 101)
            ->call('saveChore')
            ->assertHasErrors(['choreTimePoints']);

        Livewire::test('pages::chores.chores')
            ->set('choreName', 'Vacuum')
            ->set('choreCategoryId', $category->id)
            ->set('choreTimePoints', 100)
            ->call('saveChore')
            ->assertHasNoErrors();
    }

    public function test_difficulty_points_must_be_between_one_and_a_hundred(): void
    {
        $this->actingAs(User::factory()->create());

        $category = ChoreCategory::factory()->create();
        $user = User::factory()->create();

        Livewire::test('pages::chores.chores')
            ->set('choreName', 'Dishes')
            ->set('choreCategoryId', $category->id)
            ->set("choreDifficultyPoints.{$user->id}", 101)
            ->call('saveChore')
            ->assertHasErrors(["choreDifficultyPoints.{$user->id}"]);

        Livewire::test('pages::chores.chores')
            ->set('choreName', 'Dishes')
            ->set('choreCategoryId', $category->id)
            ->set("choreDifficultyPoints.{$user->id}", 100)
            ->call('saveChore')
            ->assertHasNoErrors();
    }

    public function test_escalation_increment_must_be_between_zero_and_a_hundred(): void
    {
        $this->actingAs(User::factory()->create());

        $category = ChoreCategory::factory()->create();

        Livewire::test('pages::chores.chores')
            ->set('choreName', 'Dishes')
            ->set('choreCategoryId', $category->id)
            ->set('choreEscalationIncrement', 101)
            ->set('choreEscalationCap', 150)
            ->call('saveChore')
            ->assertHasErrors(['choreEscalationIncrement']);

        Livewire::test('pages::chores.chores')
            ->set('choreName', 'Dishes')
            ->set('choreCategoryId', $category->id)
            ->set('choreEscalationIncrement', 100)
            ->set('choreEscalationCap', 150)
            ->call('saveChore')
            ->assertHasNoErrors();
    }

    public function test_escalation_cap_is_required_when_escalation_enabled(): void
    {
        $this->actingAs(User::factory()->create());

        $category = ChoreCategory::factory()->create();

        Livewire::test('pages::chores.chores')
            ->set('choreName', 'Dishes')
            ->set('choreCategoryId', $category->id)
            ->set('choreEscalationIncrement', 2)
            ->set('choreEscalationCap', null)
            ->call('saveChore')
            ->assertHasErrors(['choreEscalationCap']);
    }

    public function test_escalation_cap_must_be_at_least_time_points(): void
    {
        $this->actingAs(User::factory()->create());

        $category = ChoreCategory::factory()->create();

        Livewire::test('pages::chores.chores')
            ->set('choreName', 'Dishes')
            ->set('choreCategoryId', $category->id)
            ->set('choreTimePoints', 5)
            ->set('choreEscalationIncrement', 2)
            ->set('choreEscalationCap', 3)
            ->call('saveChore')
            ->assertHasErrors(['choreEscalationCap']);
    }

    public function test_can_save_per_user_difficulty_ratings(): void
    {
        $this->actingAs(User::factory()->create());

        $category = ChoreCategory::factory()->create();
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Livewire::test('pages::chores.chores')
            ->set('choreName', 'Dishes')
            ->set('choreCategoryId', $category->id)
            ->set("choreDifficultyPoints.{$userA->id}", 8)
            ->set("choreDifficultyPoints.{$userB->id}", 2)
            ->call('saveChore')
            ->assertHasNoErrors();

        $chore = Chore::where('name', 'Dishes')->firstOrFail();
        $this->assertSame(8, $chore->difficultyPointsFor($userA->id));
        $this->assertSame(2, $chore->difficultyPointsFor($userB->id));
    }

    public function test_edit_chore_loads_reward_fields_and_difficulty_ratings(): void
    {
        $this->actingAs(User::factory()->create());

        $user = User::factory()->create();
        $chore = Chore::factory()->create([
            'time_points' => 3,
            'escalation_increment' => 1,
            'escalation_cap' => 5,
        ]);
        ChoreDifficultyRating::factory()->create([
            'chore_id' => $chore->id,
            'user_id' => $user->id,
            'difficulty_points' => 7,
        ]);

        Livewire::test('pages::chores.chores')
            ->call('editChore', $chore->id)
            ->assertSet('choreTimePoints', 3)
            ->assertSet('choreEscalationIncrement', 1)
            ->assertSet('choreEscalationCap', 5)
            ->assertSet("choreDifficultyPoints.{$user->id}", 7);
    }
}
