<?php

use App\Models\Category;
use App\Models\CompletedDay;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Expenses')] class extends Component {
    public string $startDate = '';
    public string $endDate = '';
    public ?string $expandedDate = null;
    public string $viewMode = 'month';

    public function mount(): void
    {
        $this->startDate = now()->startOfMonth()->format('Y-m-d');
        $this->endDate = now()->endOfMonth()->format('Y-m-d');
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = $mode;
        $current = \Carbon\Carbon::parse($this->startDate);

        if ($mode === 'week') {
            $this->startDate = $current->startOfWeek(\Carbon\Carbon::MONDAY)->format('Y-m-d');
            $this->endDate = $current->endOfWeek(\Carbon\Carbon::SUNDAY)->format('Y-m-d');
        } else {
            $this->startDate = $current->startOfMonth()->format('Y-m-d');
            $this->endDate = $current->endOfMonth()->format('Y-m-d');
        }

        $this->expandedDate = null;
        unset($this->gridData, $this->categoryTotals, $this->grandTotal, $this->completedDates);
    }

    public function selectWeek(string $date): void
    {
        $d = \Carbon\Carbon::parse($date);
        $this->startDate = $d->startOfWeek(\Carbon\Carbon::MONDAY)->format('Y-m-d');
        $this->endDate = $d->endOfWeek(\Carbon\Carbon::SUNDAY)->format('Y-m-d');
        $this->expandedDate = null;
        unset($this->gridData, $this->categoryTotals, $this->grandTotal, $this->completedDates);
    }

    #[Computed]
    public function categories()
    {
        return Category::orderBy('name')->get();
    }

    /**
     * Grid data: [date => [category_id => total_cents]]
     *
     * @return array<string, array<int, int>>
     */
    #[Computed]
    public function gridData(): array
    {
        $rows = ReceiptItem::query()
            ->join('receipts', 'receipt_items.receipt_id', '=', 'receipts.id')

            ->whereBetween('receipts.date', [$this->startDate, $this->endDate])
            ->selectRaw('DATE(receipts.date) as receipt_date, receipt_items.category_id, SUM(receipt_items.amount) as total')
            ->groupByRaw('DATE(receipts.date), receipt_items.category_id')
            ->get();

        $grid = [];
        foreach ($rows as $row) {
            $date = $row->receipt_date;
            $grid[$date][$row->category_id] = (int) $row->total;
        }

        // Fill in all days in the range, even those without receipts
        $current = \Carbon\Carbon::parse($this->startDate)->copy();
        $end = \Carbon\Carbon::parse($this->endDate);
        while ($current->lte($end)) {
            $key = $current->format('Y-m-d');
            if (! isset($grid[$key])) {
                $grid[$key] = [];
            }
            $current->addDay();
        }

        ksort($grid);

        return $grid;
    }

    /**
     * Category totals across all visible dates.
     *
     * @return array<int, int>
     */
    #[Computed]
    public function categoryTotals(): array
    {
        $totals = [];
        foreach ($this->gridData as $categories) {
            foreach ($categories as $categoryId => $amount) {
                $totals[$categoryId] = ($totals[$categoryId] ?? 0) + $amount;
            }
        }

        return $totals;
    }

    #[Computed]
    public function grandTotal(): int
    {
        return array_sum($this->categoryTotals);
    }

    /**
     * Dates marked as complete in the current range.
     *
     * @return array<string, bool>
     */
    #[Computed]
    public function completedDates(): array
    {
        return CompletedDay::whereBetween('date', [$this->startDate, $this->endDate])
            ->pluck('date')
            ->mapWithKeys(fn ($date) => [$date->format('Y-m-d') => true])
            ->all();
    }

    public function toggleDayComplete(string $date): void
    {
        $existing = CompletedDay::whereDate('date', $date)->first();

        if ($existing) {
            $existing->delete();
        } else {
            CompletedDay::create(['date' => $date]);
        }

        unset($this->completedDates);
    }

    /**
     * Receipts for a specific date (for expanded row detail).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Receipt>
     */
    #[Computed]
    public function expandedReceipts()
    {
        if (! $this->expandedDate) {
            return collect();
        }

        return Receipt::with(['store', 'items.category'])

            ->where('date', $this->expandedDate)
            ->get();
    }

    public function toggleDate(string $date): void
    {
        $this->expandedDate = $this->expandedDate === $date ? null : $date;
        unset($this->expandedReceipts);
    }

    public function updatedStartDate(): void
    {
        unset($this->gridData, $this->categoryTotals, $this->grandTotal, $this->completedDates);
        $this->expandedDate = null;
    }

    public function updatedEndDate(): void
    {
        unset($this->gridData, $this->categoryTotals, $this->grandTotal, $this->completedDates);
        $this->expandedDate = null;
    }

    public function selectMonth(int $year, int $month): void
    {
        $date = \Carbon\Carbon::createFromDate($year, $month, 1);
        $this->startDate = $date->startOfMonth()->format('Y-m-d');
        $this->endDate = $date->endOfMonth()->format('Y-m-d');
        $this->expandedDate = null;
        unset($this->gridData, $this->categoryTotals, $this->grandTotal, $this->completedDates);
    }

    public function deleteReceipt(int $receiptId): void
    {
        $receipt = Receipt::findOrFail($receiptId);
        $receipt->delete();

        unset($this->gridData, $this->categoryTotals, $this->grandTotal, $this->expandedReceipts);
        Flux::toast('Receipt deleted.');
    }
}; ?>

<div style="display: flex; flex-direction: column; max-height: calc(100vh - 4rem);">
    <div class="flex items-center justify-between mb-6" style="flex-shrink: 0;">
        <div>
            <flux:heading size="xl">{{ __('Expenses') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Overview of your expenses by date and category.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" :href="route('expenses.create')" wire:navigate>
            {{ __('New Receipt') }}
        </flux:button>
    </div>

    <div style="flex-shrink: 0;">
        @include('partials.expenses.period-filter')
    </div>

    {{-- Grid table --}}
    <style>
        .expense-grid {
            overflow: auto; border-radius: 0.5rem;
            flex: 1; min-height: 0;
            --eg-bg: #ffffff; --eg-bg-head: #fafafa; --eg-border: #e5e5e5; --eg-hover: #f5f5f5;
            --eg-text: #18181b; --eg-text-dim: #71717a; --eg-text-muted: #d4d4d8;
            border: 1px solid var(--eg-border);
        }
        .dark .expense-grid {
            --eg-bg: #262626; --eg-bg-head: #1c1c1c; --eg-border: #3f3f46; --eg-hover: #2a2a2e;
            --eg-text: #f4f4f5; --eg-text-dim: #a1a1aa; --eg-text-muted: #52525b;
        }
        .expense-grid table { width: 100%; font-size: 0.875rem; border-collapse: separate; border-spacing: 0; }
        .expense-grid .sticky-left { position: sticky; left: 0; z-index: 2; background: var(--eg-bg); border-right: 1px solid var(--eg-border); }
        .expense-grid .sticky-right { position: sticky; right: 0; z-index: 2; background: var(--eg-bg); border-left: 1px solid var(--eg-border); }
        .expense-grid thead th { position: sticky; top: 0; z-index: 3; background: var(--eg-bg-head); }
        .expense-grid thead .sticky-left,
        .expense-grid thead .sticky-right { z-index: 4; background: var(--eg-bg-head); }
        .expense-grid tfoot td { position: sticky; bottom: 0; z-index: 3; background: var(--eg-bg-head); }
        .expense-grid tfoot .sticky-left,
        .expense-grid tfoot .sticky-right { z-index: 4; background: var(--eg-bg-head); }
        .expense-grid td, .expense-grid th { white-space: nowrap; }
    </style>
    <div class="expense-grid">
        <table>
            <thead>
                <tr style="border-bottom: 1px solid var(--eg-border);">
                    <th class="sticky-left" style="padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--eg-text-dim);">{{ __('Date') }}</th>
                    @foreach ($this->categories as $category)
                        <th style="padding: 0.75rem 1rem; text-align: right; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--eg-text-dim);">{{ $category->name }}</th>
                    @endforeach
                    <th class="sticky-right" style="padding: 0.75rem 1rem; text-align: right; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--eg-text-dim);">{{ __('Total') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->gridData as $date => $categoryAmounts)
                    @php $isDayComplete = isset($this->completedDates[$date]) || !empty($categoryAmounts); @endphp
                    <tr style="cursor: pointer; transition: background-color 0.15s; border-bottom: 1px solid var(--eg-border);" onmouseenter="var h=getComputedStyle(this.closest('.expense-grid')).getPropertyValue('--eg-hover');this.querySelectorAll('td').forEach(td=>td.style.backgroundColor=h)" onmouseleave="var b=getComputedStyle(this.closest('.expense-grid')).getPropertyValue('--eg-bg');this.querySelectorAll('.sticky-left,.sticky-right').forEach(td=>td.style.backgroundColor=b);this.querySelectorAll('td:not(.sticky-left):not(.sticky-right)').forEach(td=>td.style.backgroundColor='')" wire:click="toggleDate('{{ $date }}')">
                        <td class="sticky-left" style="padding: 0.75rem 1rem; font-weight: 500; color: var(--eg-text);">
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <button
                                    type="button"
                                    wire:click.stop="toggleDayComplete('{{ $date }}')"
                                    style="width: 1.125rem; height: 1.125rem; border-radius: 0.25rem; border: 1.5px solid; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; {{ $isDayComplete ? 'background: #22c55e; border-color: #22c55e;' : 'border-color: #71717a;' }}"
                                    title="{{ $isDayComplete ? __('Mark as incomplete') : __('Mark as complete') }}"
                                >
                                    @if ($isDayComplete)
                                        <svg style="width: 0.75rem; height: 0.75rem; color: #fff;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                    @endif
                                </button>
                                <svg style="width: 1rem; height: 1rem; transition: transform 0.2s; color: var(--eg-text-dim); {{ $expandedDate === $date ? 'transform: rotate(90deg);' : '' }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                                @php $d = \Carbon\Carbon::parse($date); @endphp
                                <span style="display: inline-flex; justify-content: space-between; width: 5.5rem;"><span>{{ $d->isoFormat('dd') }}</span><span>{{ $d->isoFormat('D MMM') }}</span></span>
                            </div>
                        </td>
                        @foreach ($this->categories as $category)
                            <td style="padding: 0.75rem 1rem; text-align: right; font-variant-numeric: tabular-nums; color: var(--eg-text);">
                                @if (isset($categoryAmounts[$category->id]))
                                    &euro; {{ number_format($categoryAmounts[$category->id] / 100, 2, ',', '.') }}
                                @elseif ($isDayComplete)
                                    <span style="opacity: 0.4;">&euro; 0,00</span>
                                @else
                                    <span style="color: var(--eg-text-muted);">&mdash;</span>
                                @endif
                            </td>
                        @endforeach
                        <td class="sticky-right" style="padding: 0.75rem 1rem; text-align: right; font-weight: 600; font-variant-numeric: tabular-nums; color: var(--eg-text);">
                            &euro; {{ number_format(array_sum($categoryAmounts) / 100, 2, ',', '.') }}
                        </td>
                    </tr>

                    {{-- Expanded detail row --}}
                    @if ($expandedDate === $date)
                        <tr>
                            <td colspan="{{ $this->categories->count() + 2 }}" style="padding: 1rem;">
                                <div style="margin: 0.5rem 0; display: flex; flex-direction: column; gap: 0.75rem; max-width: 32rem;">
                                    @foreach ($this->expandedReceipts as $receipt)
                                        <div style="border-radius: 0.5rem; padding: 0.75rem; border: 1px solid var(--eg-border); background: var(--eg-bg-head);">
                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                                <div class="flex items-center gap-2">
                                                    <flux:badge size="sm" color="zinc">{{ $receipt->store->name }}</flux:badge>
                                                    <span style="font-size: 0.75rem; color: var(--eg-text-dim);">
                                                        &euro; {{ number_format($receipt->items->sum('amount') / 100, 2, ',', '.') }} {{ __('total') }}
                                                    </span>
                                                </div>
                                                <div class="flex items-center gap-1">
                                                    <flux:button size="sm" icon="pencil" variant="ghost" :href="route('expenses.edit', $receipt)" wire:navigate />
                                                    <flux:button size="sm" icon="trash" variant="ghost" wire:click="deleteReceipt({{ $receipt->id }})" wire:confirm="{{ __('Are you sure you want to delete this receipt?') }}" />
                                                </div>
                                            </div>
                                            <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                                                @foreach ($receipt->items as $item)
                                                    <div style="display: flex; justify-content: space-between; font-size: 0.875rem;">
                                                        <span style="color: var(--eg-text-dim);">{{ $item->category->name }}</span>
                                                        <span style="font-variant-numeric: tabular-nums; color: var(--eg-text);">&euro; {{ number_format($item->amount / 100, 2, ',', '.') }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="{{ $this->categories->count() + 2 }}" style="padding: 2rem; text-align: center;">
                            <flux:text>{{ __('No expenses found for this period.') }}</flux:text>
                        </td>
                    </tr>
                @endforelse
            </tbody>

            @if (count($this->gridData) > 0)
                <tfoot>
                    <tr style="border-top: 2px solid var(--eg-text-muted);">
                        <td class="sticky-left" style="padding: 0.75rem 1rem; font-weight: 600; color: var(--eg-text);">{{ __('Totals') }}</td>
                        @foreach ($this->categories as $category)
                            <td style="padding: 0.75rem 1rem; text-align: right; font-weight: 600; font-variant-numeric: tabular-nums; color: var(--eg-text);">
                                @if (isset($this->categoryTotals[$category->id]))
                                    &euro; {{ number_format($this->categoryTotals[$category->id] / 100, 2, ',', '.') }}
                                @else
                                    <span style="color: var(--eg-text-muted);">&mdash;</span>
                                @endif
                            </td>
                        @endforeach
                        <td class="sticky-right" style="padding: 0.75rem 1rem; text-align: right; font-weight: 700; font-variant-numeric: tabular-nums; color: var(--eg-text);">
                            &euro; {{ number_format($this->grandTotal / 100, 2, ',', '.') }}
                        </td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
