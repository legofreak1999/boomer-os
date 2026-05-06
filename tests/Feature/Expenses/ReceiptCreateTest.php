<?php

namespace Tests\Feature\Expenses;

use App\Models\Category;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReceiptCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_receipt_create(): void
    {
        $this->get(route('expenses.create'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_receipt_form(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('expenses.create'))->assertOk();
    }

    public function test_user_can_add_rows(): void
    {
        $this->actingAs(User::factory()->create());
        $category = Category::factory()->create();

        $component = Livewire::test('pages::expenses.create')
            ->call('addRow', $category->id, 1250);

        $this->assertCount(1, $component->get('rows'));
        $this->assertEquals(1250, $component->get('rows.0.amount'));
        $this->assertEquals($category->id, $component->get('rows.0.category_id'));
    }

    public function test_user_can_remove_rows(): void
    {
        $this->actingAs(User::factory()->create());
        $category = Category::factory()->create();

        Livewire::test('pages::expenses.create')
            ->call('addRow', $category->id, 1250)
            ->call('addRow', $category->id, 500)
            ->call('removeRow', 0)
            ->assertSet('rows', fn ($rows) => count($rows) === 1 && $rows[0]['amount'] === 500);
    }

    public function test_user_can_save_receipt(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $store = Store::factory()->create();
        $category = Category::factory()->create();

        Livewire::test('pages::expenses.create')
            ->set('date', '2026-05-06')
            ->set('storeId', $store->id)
            ->call('addRow', $category->id, 1250)
            ->call('addRow', $category->id, 800)
            ->call('saveAndGoBack')
            ->assertHasNoErrors()
            ->assertRedirect(route('expenses.index'));

        $this->assertDatabaseHas('receipts', [
            'store_id' => $store->id,
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseCount('receipt_items', 2);
    }

    public function test_save_requires_date_and_store(): void
    {
        $this->actingAs(User::factory()->create());
        $category = Category::factory()->create();

        Livewire::test('pages::expenses.create')
            ->set('date', '')
            ->set('storeId', '')
            ->call('addRow', $category->id, 1250)
            ->call('saveAndGoBack')
            ->assertHasErrors(['date', 'storeId']);
    }

    public function test_save_and_new_resets_rows(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $store = Store::factory()->create();
        $category = Category::factory()->create();

        Livewire::test('pages::expenses.create')
            ->set('date', '2026-05-06')
            ->set('storeId', $store->id)
            ->call('addRow', $category->id, 1250)
            ->call('saveAndNew')
            ->assertHasNoErrors()
            ->assertSet('rows', [])
            ->assertNoRedirect();

        $this->assertDatabaseCount('receipts', 1);
    }
}
