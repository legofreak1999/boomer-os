<?php

use App\Models\Category;
use App\Models\Receipt;
use App\Models\Store;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Title('Edit Receipt')] class extends Component {
    public Receipt $receipt;

    #[Validate('required|date')]
    public string $date = '';

    #[Validate('required|exists:stores,id')]
    public string $storeId = '';

    /** @var array<int, array{category_id: int, category_name: string, amount: int}> */
    public array $rows = [];

    public ?int $editingIndex = null;

    public string $editAmount = '';

    public string $editCategoryId = '';

    public int $calendarYear;

    public int $calendarMonth;

    public function mount(Receipt $receipt): void
    {
        $this->receipt = $receipt;
        $this->date = $receipt->date->format('Y-m-d');
        $this->storeId = (string) $receipt->store_id;
        $this->calendarYear = $receipt->date->year;
        $this->calendarMonth = $receipt->date->month;

        $this->rows = $receipt->items->map(fn ($item) => [
            'category_id' => $item->category_id,
            'category_name' => $item->category->name,
            'amount' => $item->amount,
        ])->all();
    }

    public function selectDate(string $date): void
    {
        $this->date = $date;
        $parsed = \Carbon\Carbon::parse($date);
        $this->calendarYear = $parsed->year;
        $this->calendarMonth = $parsed->month;
        unset($this->calendarWeeks, $this->calendarTitle);
    }

    public function previousMonth(): void
    {
        $d = \Carbon\Carbon::createFromDate($this->calendarYear, $this->calendarMonth, 1)->subMonth();
        $this->calendarYear = $d->year;
        $this->calendarMonth = $d->month;
        unset($this->calendarWeeks, $this->calendarTitle);
    }

    public function nextMonth(): void
    {
        $d = \Carbon\Carbon::createFromDate($this->calendarYear, $this->calendarMonth, 1)->addMonth();
        $this->calendarYear = $d->year;
        $this->calendarMonth = $d->month;
        unset($this->calendarWeeks, $this->calendarTitle);
    }

    /**
     * @return array<int, array<int, array{date: string, day: int, currentMonth: bool, isToday: bool, isSelected: bool}>>
     */
    #[Computed]
    public function calendarWeeks(): array
    {
        $firstOfMonth = \Carbon\Carbon::createFromDate($this->calendarYear, $this->calendarMonth, 1);
        $startOfCalendar = $firstOfMonth->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
        $lastOfMonth = $firstOfMonth->copy()->endOfMonth();
        $endOfCalendar = $lastOfMonth->copy()->endOfWeek(\Carbon\Carbon::SUNDAY);

        $today = now()->format('Y-m-d');
        $weeks = [];
        $current = $startOfCalendar->copy();

        while ($current->lte($endOfCalendar)) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $week[] = [
                    'date' => $current->format('Y-m-d'),
                    'day' => $current->day,
                    'currentMonth' => $current->month === $this->calendarMonth,
                    'isToday' => $current->format('Y-m-d') === $today,
                    'isSelected' => $current->format('Y-m-d') === $this->date,
                ];
                $current->addDay();
            }
            $weeks[] = $week;
        }

        return $weeks;
    }

    #[Computed]
    public function calendarTitle(): string
    {
        return \Carbon\Carbon::createFromDate($this->calendarYear, $this->calendarMonth, 1)->isoFormat('MMMM YYYY');
    }

    #[Computed]
    public function stores()
    {
        return Store::orderBy('name')->get();
    }

    #[Computed]
    public function categories()
    {
        return Category::orderBy('name')->get();
    }

    #[Computed]
    public function totalCents(): int
    {
        return collect($this->rows)->sum('amount');
    }

    public function addRow(int $categoryId, int $amountCents): void
    {
        $category = Category::findOrFail($categoryId);

        $this->rows[] = [
            'category_id' => $categoryId,
            'category_name' => $category->name,
            'amount' => $amountCents,
        ];
    }

    public function addManualRow(): void
    {
        $this->validate([
            'editAmount' => 'required|numeric|min:0.01',
            'editCategoryId' => 'required|exists:categories,id',
        ]);

        $category = Category::findOrFail($this->editCategoryId);
        $this->rows[] = [
            'category_id' => (int) $this->editCategoryId,
            'category_name' => $category->name,
            'amount' => (int) round((float) $this->editAmount * 100),
        ];

        $this->reset('editAmount', 'editCategoryId');
    }

    public function removeRow(int $index): void
    {
        unset($this->rows[$index]);
        $this->rows = array_values($this->rows);
    }

    public function startEditRow(int $index): void
    {
        $this->editingIndex = $index;
        $this->editAmount = number_format($this->rows[$index]['amount'] / 100, 2, '.', '');
        $this->editCategoryId = (string) $this->rows[$index]['category_id'];
    }

    public function updateRow(): void
    {
        $this->validate([
            'editAmount' => 'required|numeric|min:0.01',
            'editCategoryId' => 'required|exists:categories,id',
        ]);

        $category = Category::findOrFail($this->editCategoryId);
        $this->rows[$this->editingIndex] = [
            'category_id' => (int) $this->editCategoryId,
            'category_name' => $category->name,
            'amount' => (int) round((float) $this->editAmount * 100),
        ];

        $this->reset('editingIndex', 'editAmount', 'editCategoryId');
    }

    public function cancelEdit(): void
    {
        $this->reset('editingIndex', 'editAmount', 'editCategoryId');
    }

    public function saveAndGoBack(): void
    {
        $this->validate();

        if (empty($this->rows)) {
            Flux::toast('Add at least one item to the receipt.', variant: 'danger');

            return;
        }

        DB::transaction(function () {
            $this->receipt->update([
                'date' => $this->date,
                'store_id' => $this->storeId,
            ]);

            $this->receipt->items()->delete();

            foreach ($this->rows as $row) {
                $this->receipt->items()->create([
                    'category_id' => $row['category_id'],
                    'amount' => $row['amount'],
                ]);
            }
        });

        Flux::toast('Receipt updated.');
        $this->redirect(route('expenses.index'), navigate: true);
    }

    public string $newStoreName = '';

    public string $newCategoryName = '';

    public function quickCreateStore(): void
    {
        $this->validate(['newStoreName' => 'required|string|max:255|unique:stores,name']);
        $store = Store::create(['name' => $this->newStoreName]);
        $this->storeId = (string) $store->id;
        $this->reset('newStoreName');
        unset($this->stores);
        Flux::modal('quick-add-store')->close();
        Flux::toast('Store created.');
    }

    public function quickCreateCategory(): void
    {
        $this->validate(['newCategoryName' => 'required|string|max:255|unique:categories,name']);
        $category = Category::create(['name' => $this->newCategoryName]);
        $this->reset('newCategoryName');
        unset($this->categories);
        Flux::modal('quick-add-category')->close();
        Flux::toast('Category created.');
        $this->dispatch('category-created', id: $category->id);
    }
}; ?>

@include('partials.expenses.receipt-form', [
    'formTitle' => __('Edit Receipt'),
    'formSubtitle' => __('Update this receipt.'),
    'showSaveAndNew' => false,
])
