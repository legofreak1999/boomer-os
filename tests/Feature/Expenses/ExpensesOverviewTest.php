<?php

namespace Tests\Feature\Expenses;

use App\Models\Category;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExpensesOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_expenses(): void
    {
        $this->get(route('expenses.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_expenses(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('expenses.index'))->assertOk();
    }

    public function test_grid_shows_correct_totals(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $store = Store::factory()->create();
        $groceries = Category::factory()->create(['name' => 'Groceries']);
        $electronics = Category::factory()->create(['name' => 'Electronics']);

        $receipt = Receipt::factory()->create([
            'date' => '2026-05-06',
            'store_id' => $store->id,
            'user_id' => $user->id,
        ]);

        ReceiptItem::factory()->create(['receipt_id' => $receipt->id, 'category_id' => $groceries->id, 'amount' => 1500]);
        ReceiptItem::factory()->create(['receipt_id' => $receipt->id, 'category_id' => $electronics->id, 'amount' => 5000]);

        $component = Livewire::test('pages::expenses.index')
            ->set('startDate', '2026-05-01')
            ->set('endDate', '2026-05-31');

        $gridData = $component->get('gridData');

        $this->assertArrayHasKey('2026-05-06', $gridData);
        $this->assertEquals(1500, $gridData['2026-05-06'][$groceries->id]);
        $this->assertEquals(5000, $gridData['2026-05-06'][$electronics->id]);
    }

    public function test_expanding_date_shows_receipts(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $store = Store::factory()->create(['name' => 'Albert Heijn']);
        $category = Category::factory()->create();

        $receipt = Receipt::factory()->create([
            'date' => '2026-05-06',
            'store_id' => $store->id,
            'user_id' => $user->id,
        ]);
        ReceiptItem::factory()->create(['receipt_id' => $receipt->id, 'category_id' => $category->id, 'amount' => 1500]);

        $component = Livewire::test('pages::expenses.index')
            ->set('startDate', '2026-05-01')
            ->set('endDate', '2026-05-31')
            ->call('toggleDate', '2026-05-06');

        $this->assertEquals('2026-05-06', $component->get('expandedDate'));
    }

    public function test_date_filter_excludes_out_of_range(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $store = Store::factory()->create();
        $category = Category::factory()->create();

        $receipt = Receipt::factory()->create([
            'date' => '2026-04-15',
            'store_id' => $store->id,
            'user_id' => $user->id,
        ]);
        ReceiptItem::factory()->create(['receipt_id' => $receipt->id, 'category_id' => $category->id, 'amount' => 1500]);

        $component = Livewire::test('pages::expenses.index')
            ->set('startDate', '2026-05-01')
            ->set('endDate', '2026-05-31');

        // Grid has all days but none should have category amounts
        $gridData = $component->get('gridData');
        $this->assertArrayHasKey('2026-05-01', $gridData);
        $this->assertEmpty($gridData['2026-05-01']);
        $this->assertArrayNotHasKey('2026-04-15', $gridData);
    }

    public function test_toggling_day_complete_creates_record(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::expenses.index')
            ->set('startDate', '2026-05-01')
            ->set('endDate', '2026-05-31')
            ->call('toggleDayComplete', '2026-05-10');

        $this->assertDatabaseCount('completed_days', 1);
    }

    public function test_toggling_day_complete_twice_removes_record(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::expenses.index')
            ->set('startDate', '2026-05-01')
            ->set('endDate', '2026-05-31')
            ->call('toggleDayComplete', '2026-05-10')
            ->call('toggleDayComplete', '2026-05-10');

        $this->assertDatabaseCount('completed_days', 0);
    }

    public function test_completed_date_appears_in_computed(): void
    {
        $this->actingAs(User::factory()->create());
        \App\Models\CompletedDay::create(['date' => '2026-05-15']);

        $component = Livewire::test('pages::expenses.index')
            ->set('startDate', '2026-05-01')
            ->set('endDate', '2026-05-31');

        $completed = $component->get('completedDates');
        $this->assertArrayHasKey('2026-05-15', $completed);
    }
}
