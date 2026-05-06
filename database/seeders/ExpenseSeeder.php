<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\Store;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class ExpenseSeeder extends Seeder
{
    /**
     * Seed a full year of realistic expense data.
     */
    public function run(): void
    {
        $user = User::first() ?? User::factory()->create([
            'name' => 'Thomas',
            'email' => 'thomas@fam-ruiter.eu',
        ]);

        $categories = collect([
            'Groceries', 'Household', 'Electronics', 'Clothing',
            'Dining Out', 'Transport', 'Health', 'Entertainment',
            'Subscriptions', 'Gifts',
        ])->map(fn (string $name) => Category::firstOrCreate(['name' => $name]));

        $stores = collect([
            'Albert Heijn', 'Jumbo', 'Lidl', 'Aldi',
            'Kruidvat', 'Action', 'IKEA', 'Bol.com',
            'Mediamarkt', 'H&M',
        ])->map(fn (string $name) => Store::firstOrCreate(['name' => $name]));

        // Map stores to likely categories
        $storeCategories = [
            'Albert Heijn' => ['Groceries', 'Household'],
            'Jumbo' => ['Groceries', 'Household'],
            'Lidl' => ['Groceries', 'Clothing'],
            'Aldi' => ['Groceries', 'Household'],
            'Kruidvat' => ['Health', 'Household'],
            'Action' => ['Household', 'Gifts', 'Entertainment'],
            'IKEA' => ['Household', 'Entertainment'],
            'Bol.com' => ['Electronics', 'Entertainment', 'Gifts'],
            'Mediamarkt' => ['Electronics'],
            'H&M' => ['Clothing'],
        ];

        $categoriesByName = $categories->keyBy('name');
        $storesByName = $stores->keyBy('name');

        $startDate = Carbon::now()->subYear()->startOfMonth();
        $endDate = Carbon::now();

        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            // 2-5 receipts per week, spread across the week
            $receiptsThisWeek = fake()->numberBetween(2, 5);

            for ($i = 0; $i < $receiptsThisWeek; $i++) {
                $receiptDate = $current->copy()->addDays(fake()->numberBetween(0, 6));

                if ($receiptDate->gt($endDate)) {
                    break;
                }

                $store = $storesByName->random();
                $possibleCategories = $storeCategories[$store->name] ?? ['Groceries'];

                $receipt = Receipt::create([
                    'date' => $receiptDate->format('Y-m-d'),
                    'store_id' => $store->id,
                    'user_id' => $user->id,
                ]);

                // 1-6 items per receipt
                $itemCount = fake()->numberBetween(1, 6);

                for ($j = 0; $j < $itemCount; $j++) {
                    $categoryName = fake()->randomElement($possibleCategories);
                    $category = $categoriesByName[$categoryName];

                    // Realistic price ranges per category (in cents)
                    $amount = match ($categoryName) {
                        'Groceries' => fake()->numberBetween(89, 4500),
                        'Household' => fake()->numberBetween(199, 3500),
                        'Electronics' => fake()->numberBetween(999, 29900),
                        'Clothing' => fake()->numberBetween(999, 7999),
                        'Dining Out' => fake()->numberBetween(800, 5500),
                        'Transport' => fake()->numberBetween(200, 8000),
                        'Health' => fake()->numberBetween(299, 2500),
                        'Entertainment' => fake()->numberBetween(500, 4000),
                        'Subscriptions' => fake()->numberBetween(499, 1999),
                        'Gifts' => fake()->numberBetween(500, 5000),
                        default => fake()->numberBetween(100, 5000),
                    };

                    ReceiptItem::create([
                        'receipt_id' => $receipt->id,
                        'category_id' => $category->id,
                        'amount' => $amount,
                    ]);
                }
            }

            $current->addWeek();
        }
    }
}
