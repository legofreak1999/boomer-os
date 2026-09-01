<?php

namespace Tests\Feature\Chores;

use App\Models\Chore;
use App\Models\ChoreCategory;
use App\Models\ChoreDifficultyRating;
use App\Models\ChoreListItem;
use App\Models\User;
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

    public function test_time_points_must_be_between_one_and_ten(): void
    {
        $this->actingAs(User::factory()->create());

        $category = ChoreCategory::factory()->create();

        Livewire::test('pages::chores.chores')
            ->set('choreName', 'Vacuum')
            ->set('choreCategoryId', $category->id)
            ->set('choreTimePoints', 0)
            ->call('saveChore')
            ->assertHasErrors(['choreTimePoints']);
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
