<?php

namespace Tests\Feature\Chores;

use App\Models\Chore;
use App\Models\ChoreCategory;
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

        $this->assertDatabaseHas('chore_categories', ['name' => 'Kitchen']);
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
}
