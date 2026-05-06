<?php

use App\Models\Receipt;
use App\Models\ReceiptItem;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    #[Computed]
    public function totalThisMonth(): int
    {
        return $this->totalForPeriod(now()->startOfMonth(), now()->endOfMonth());
    }

    #[Computed]
    public function totalThisYear(): int
    {
        return $this->totalForPeriod(now()->startOfYear(), now()->endOfYear());
    }

    #[Computed]
    public function totalAllTime(): int
    {
        return ReceiptItem::query()->sum('amount');
    }

    #[Computed]
    public function monthlyTrend()
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $yearExpr = "strftime('%Y', receipts.date)";
            $monthExpr = "CAST(strftime('%m', receipts.date) AS INTEGER)";
        } else {
            $yearExpr = 'YEAR(receipts.date)';
            $monthExpr = 'MONTH(receipts.date)';
        }

        return ReceiptItem::query()
            ->join('receipts', 'receipt_items.receipt_id', '=', 'receipts.id')
            ->where('receipts.date', '>=', now()->subMonths(11)->startOfMonth())
            ->selectRaw("{$yearExpr} as year, {$monthExpr} as month, SUM(receipt_items.amount) as total")
            ->groupByRaw("{$yearExpr}, {$monthExpr}")
            ->orderByRaw("{$yearExpr}, {$monthExpr}")
            ->get();
    }

    #[Computed]
    public function recentReceipts()
    {
        return Receipt::with(['store', 'items'])
            ->latest('date')
            ->take(5)
            ->get();
    }

    private function totalForPeriod($start, $end): int
    {
        return ReceiptItem::query()
            ->join('receipts', 'receipt_items.receipt_id', '=', 'receipts.id')
            ->whereBetween('receipts.date', [$start, $end])
            ->sum('receipt_items.amount');
    }
}; ?>

<div>
    <div style="margin-bottom: 2rem;">
        <flux:heading size="xl">{{ __('Dashboard') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Overview of your home system.') }}</flux:text>
    </div>

    {{-- Summary cards --}}
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 2rem;">
        <div style="border-radius: 0.75rem; padding: 1.5rem; border-left: 4px solid #3b82f6; background: rgba(59,130,246,0.08);" class="border border-zinc-200 dark:border-zinc-700">
            <div style="font-size: 0.875rem; color: #60a5fa; font-weight: 500;">{{ __('This Month') }}</div>
            <div style="margin-top: 0.5rem; font-size: 1.5rem; font-weight: 700; font-variant-numeric: tabular-nums;" class="text-zinc-900 dark:text-zinc-100">&euro; {{ number_format($this->totalThisMonth / 100, 2, ',', '.') }}</div>
        </div>
        <div style="border-radius: 0.75rem; padding: 1.5rem; border-left: 4px solid #8b5cf6; background: rgba(139,92,246,0.08);" class="border border-zinc-200 dark:border-zinc-700">
            <div style="font-size: 0.875rem; color: #a78bfa; font-weight: 500;">{{ __('This Year') }}</div>
            <div style="margin-top: 0.5rem; font-size: 1.5rem; font-weight: 700; font-variant-numeric: tabular-nums;" class="text-zinc-900 dark:text-zinc-100">&euro; {{ number_format($this->totalThisYear / 100, 2, ',', '.') }}</div>
        </div>
        <div style="border-radius: 0.75rem; padding: 1.5rem; border-left: 4px solid #10b981; background: rgba(16,185,129,0.08);" class="border border-zinc-200 dark:border-zinc-700">
            <div style="font-size: 0.875rem; color: #34d399; font-weight: 500;">{{ __('All Time') }}</div>
            <div style="margin-top: 0.5rem; font-size: 1.5rem; font-weight: 700; font-variant-numeric: tabular-nums;" class="text-zinc-900 dark:text-zinc-100">&euro; {{ number_format($this->totalAllTime / 100, 2, ',', '.') }}</div>
        </div>
    </div>

    {{-- Monthly Trend & Recent Receipts --}}
    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem;">
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <flux:heading size="lg" class="mb-4">{{ __('Monthly Trend') }}</flux:heading>
            @if ($this->monthlyTrend->isNotEmpty())
                @php
                    $maxMonth = $this->monthlyTrend->max('total') ?: 1;
                    $count = $this->monthlyTrend->count();
                @endphp
                <div style="display: flex; align-items: flex-end; gap: 4px; height: 160px;">
                    @foreach ($this->monthlyTrend as $index => $month)
                        @php $hue = $count > 1 ? round(($index / ($count - 1)) * 300) : 0; @endphp
                        <div style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%;">
                            <div style="width: 100%; border-radius: 0.25rem 0.25rem 0 0; height: {{ max(4, round(($month->total / $maxMonth) * 130)) }}px; background: hsl({{ $hue }}, 75%, 60%);"></div>
                            <span style="margin-top: 0.25rem; font-size: 0.75rem;" class="text-zinc-500 dark:text-zinc-400">{{ \Carbon\Carbon::createFromDate($month->year, $month->month)->isoFormat('MMM') }}</span>
                            <span style="font-size: 0.75rem; font-variant-numeric: tabular-nums;" class="text-zinc-500 dark:text-zinc-400">&euro;{{ number_format($month->total / 100, 0, ',', '.') }}</span>
                        </div>
                    @endforeach
                </div>
            @else
                <flux:text>{{ __('No data yet.') }}</flux:text>
            @endif
        </div>

        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <flux:heading size="lg" class="mb-4">{{ __('Recent Receipts') }}</flux:heading>
            @if ($this->recentReceipts->isNotEmpty())
                @php $receiptColors = ['#3b82f6', '#8b5cf6', '#10b981', '#f59e0b', '#ec4899']; @endphp
                <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                    @foreach ($this->recentReceipts as $index => $receipt)
                        <div style="display: flex; align-items: center; gap: 0.75rem; padding: 0.5rem 0; {{ !$loop->last ? 'border-bottom: 1px solid' : '' }}" class="{{ !$loop->last ? 'border-zinc-100 dark:border-zinc-700' : '' }}">
                            <div style="width: 0.375rem; height: 2.25rem; border-radius: 9999px; background: {{ $receiptColors[$index % count($receiptColors)] }}; flex-shrink: 0;"></div>
                            <div style="flex: 1; min-width: 0;">
                                <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $receipt->store->name }}</div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $receipt->date->isoFormat('D MMM YYYY') }}</div>
                            </div>
                            <div class="text-sm font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">
                                &euro; {{ number_format($receipt->items->sum('amount') / 100, 2, ',', '.') }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <flux:text>{{ __('No receipts yet.') }}</flux:text>
            @endif
        </div>
    </div>
</div>
