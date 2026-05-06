<?php

namespace Tests\Feature\Categories;

use App\Models\Category;
use App\Models\ReceiptItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CategoryCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_categories(): void
    {
        $this->get(route('categories.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_categories(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('categories.index'))->assertOk();
    }

    public function test_user_can_create_a_category(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::categories.index')
            ->set('name', 'Groceries')
            ->call('createCategory')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('categories', ['name' => 'Groceries']);
    }

    public function test_category_name_is_required(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::categories.index')
            ->set('name', '')
            ->call('createCategory')
            ->assertHasErrors(['name' => 'required']);
    }

    public function test_user_can_update_a_category(): void
    {
        $this->actingAs(User::factory()->create());
        $category = Category::factory()->create(['name' => 'Old Name']);

        Livewire::test('pages::categories.index')
            ->call('editCategory', $category->id)
            ->set('name', 'New Name')
            ->call('updateCategory')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'New Name']);
    }

    public function test_user_can_delete_a_category_without_items(): void
    {
        $this->actingAs(User::factory()->create());
        $category = Category::factory()->create();

        Livewire::test('pages::categories.index')
            ->call('confirmDelete', $category->id);

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_user_cannot_delete_a_category_with_items(): void
    {
        $this->actingAs(User::factory()->create());
        $category = Category::factory()->create();
        ReceiptItem::factory()->create(['category_id' => $category->id]);

        Livewire::test('pages::categories.index')
            ->call('confirmDelete', $category->id);

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }
}
