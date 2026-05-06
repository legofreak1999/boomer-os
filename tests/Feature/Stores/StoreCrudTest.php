<?php

namespace Tests\Feature\Stores;

use App\Models\Receipt;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StoreCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_stores(): void
    {
        $this->get(route('stores.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_stores(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('stores.index'))->assertOk();
    }

    public function test_user_can_create_a_store(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::stores.index')
            ->set('name', 'Albert Heijn')
            ->call('createStore')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('stores', ['name' => 'Albert Heijn']);
    }

    public function test_store_name_is_required(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::stores.index')
            ->set('name', '')
            ->call('createStore')
            ->assertHasErrors(['name' => 'required']);
    }

    public function test_user_can_update_a_store(): void
    {
        $this->actingAs(User::factory()->create());
        $store = Store::factory()->create(['name' => 'Old Name']);

        Livewire::test('pages::stores.index')
            ->call('editStore', $store->id)
            ->set('name', 'New Name')
            ->call('updateStore')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('stores', ['id' => $store->id, 'name' => 'New Name']);
    }

    public function test_user_can_delete_a_store_without_receipts(): void
    {
        $this->actingAs(User::factory()->create());
        $store = Store::factory()->create();

        Livewire::test('pages::stores.index')
            ->call('confirmDelete', $store->id);

        $this->assertDatabaseMissing('stores', ['id' => $store->id]);
    }

    public function test_user_cannot_delete_a_store_with_receipts(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $store = Store::factory()->create();
        Receipt::factory()->create(['store_id' => $store->id, 'user_id' => $user->id]);

        Livewire::test('pages::stores.index')
            ->call('confirmDelete', $store->id);

        $this->assertDatabaseHas('stores', ['id' => $store->id]);
    }
}
