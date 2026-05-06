<?php

use App\Models\ReceiptItem;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Expenses Dashboard')] class extends Component {
    public string $startDate = '';
    public string $endDate = '';
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
        } elseif ($mode === 'year') {
            $this->startDate = $current->startOfYear()->format('Y-m-d');
            $this->endDate = $current->endOfYear()->format('Y-m-d');
        } else {
            $this->startDate = $current->startOfMonth()->format('Y-m-d');
            $this->endDate = $current->endOfMonth()->format('Y-m-d');
        }

        $this->invalidateAll();
    }

    public function selectMonth(int $year, int $month): void
    {
        $date = \Carbon\Carbon::createFromDate($year, $month, 1);
        $this->startDate = $date->startOfMonth()->format('Y-m-d');
        $this->endDate = $date->endOfMonth()->format('Y-m-d');
        $this->invalidateAll();
    }

    public function selectWeek(string $date): void
    {
        $d = \Carbon\Carbon::parse($date);
        $this->startDate = $d->startOfWeek(\Carbon\Carbon::MONDAY)->format('Y-m-d');
        $this->endDate = $d->endOfWeek(\Carbon\Carbon::SUNDAY)->format('Y-m-d');
        $this->invalidateAll();
    }

    public function selectYear(int $year): void
    {
        $this->startDate = \Carbon\Carbon::createFromDate($year, 1, 1)->format('Y-m-d');
        $this->endDate = \Carbon\Carbon::createFromDate($year, 12, 31)->format('Y-m-d');
        $this->invalidateAll();
    }

    public function updatedStartDate(): void
    {
        $this->invalidateAll();
    }

    public function updatedEndDate(): void
    {
        $this->invalidateAll();
    }

    private function invalidateAll(): void
    {
        unset($this->totalPeriod, $this->receiptCount, $this->byCategory, $this->byStore, $this->trendData, $this->isMonthlyTrend);
    }

    #[Computed]
    public function isMonthlyTrend(): bool
    {
        return \Carbon\Carbon::parse($this->startDate)->diffInDays(\Carbon\Carbon::parse($this->endDate)) > 31;
    }

    #[Computed]
    public function totalPeriod(): int
    {
        return ReceiptItem::query()
            ->join('receipts', 'receipt_items.receipt_id', '=', 'receipts.id')
            ->whereBetween('receipts.date', [$this->startDate, $this->endDate])
            ->sum('receipt_items.amount');
    }

    #[Computed]
    public function receiptCount(): int
    {
        return \App\Models\Receipt::whereBetween('date', [$this->startDate, $this->endDate])->count();
    }

    #[Computed]
    public function byCategory()
    {
        return ReceiptItem::query()
            ->join('receipts', 'receipt_items.receipt_id', '=', 'receipts.id')
            ->join('categories', 'receipt_items.category_id', '=', 'categories.id')
            ->whereBetween('receipts.date', [$this->startDate, $this->endDate])
            ->selectRaw('categories.name, SUM(receipt_items.amount) as total')
            ->groupBy('categories.name')
            ->orderByDesc('total')
            ->get();
    }

    #[Computed]
    public function byStore()
    {
        return ReceiptItem::query()
            ->join('receipts', 'receipt_items.receipt_id', '=', 'receipts.id')
            ->join('stores', 'receipts.store_id', '=', 'stores.id')
            ->whereBetween('receipts.date', [$this->startDate, $this->endDate])
            ->selectRaw('stores.name, SUM(receipt_items.amount) as total')
            ->groupBy('stores.name')
            ->orderByDesc('total')
            ->get();
    }

    #[Computed]
    public function trendData()
    {
        if ($this->isMonthlyTrend) {
            return $this->monthlyTrendData();
        }

        return $this->dailyTrendData();
    }

    private function dailyTrendData()
    {
        $rows = ReceiptItem::query()
            ->join('receipts', 'receipt_items.receipt_id', '=', 'receipts.id')
            ->whereBetween('receipts.date', [$this->startDate, $this->endDate])
            ->selectRaw('DATE(receipts.date) as day, SUM(receipt_items.amount) as total')
            ->groupByRaw('DATE(receipts.date)')
            ->get()
            ->keyBy('day');

        $days = [];
        $current = \Carbon\Carbon::parse($this->startDate)->copy();
        $end = \Carbon\Carbon::parse($this->endDate);
        while ($current->lte($end)) {
            $key = $current->format('Y-m-d');
            $days[] = (object) [
                'label' => $current->format('j'),
                'total' => isset($rows[$key]) ? (int) $rows[$key]->total : 0,
                'tooltip' => $current->isoFormat('dd D MMM') . ': € ' . number_format((isset($rows[$key]) ? (int) $rows[$key]->total : 0) / 100, 2, ',', '.'),
            ];
            $current->addDay();
        }

        return collect($days);
    }

    private function monthlyTrendData()
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $yearExpr = "strftime('%Y', receipts.date)";
            $monthExpr = "CAST(strftime('%m', receipts.date) AS INTEGER)";
        } else {
            $yearExpr = 'YEAR(receipts.date)';
            $monthExpr = 'MONTH(receipts.date)';
        }

        $rows = ReceiptItem::query()
            ->join('receipts', 'receipt_items.receipt_id', '=', 'receipts.id')
            ->whereBetween('receipts.date', [$this->startDate, $this->endDate])
            ->selectRaw("{$yearExpr} as year, {$monthExpr} as month, SUM(receipt_items.amount) as total")
            ->groupByRaw("{$yearExpr}, {$monthExpr}")
            ->orderByRaw("{$yearExpr}, {$monthExpr}")
            ->get()
            ->keyBy(fn ($row) => $row->year . '-' . $row->month);

        $months = [];
        $current = \Carbon\Carbon::parse($this->startDate)->copy()->startOfMonth();
        $end = \Carbon\Carbon::parse($this->endDate);
        while ($current->lte($end)) {
            $key = $current->year . '-' . $current->month;
            $total = isset($rows[$key]) ? (int) $rows[$key]->total : 0;
            $months[] = (object) [
                'label' => $current->isoFormat('MMM'),
                'total' => $total,
                'tooltip' => $current->isoFormat('MMM YYYY') . ': € ' . number_format($total / 100, 2, ',', '.'),
            ];
            $current->addMonth();
        }

        return collect($months);
    }
}; ?>

@php
    $barColors = ['#3b82f6', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981', '#06b6d4', '#f97316', '#6366f1', '#14b8a6', '#e11d48', '#84cc16', '#0ea5e9'];
@endphp

<div>
    <div style="margin-bottom: 1.5rem;">
        <flux:heading size="xl">{{ __('Expenses Dashboard') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Detailed breakdown for the selected period.') }}</flux:text>
    </div>

    @include('partials.expenses.period-filter', ['showYearMode' => true])

    {{-- Summary cards --}}
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 2rem;">
        <div style="border-radius: 0.75rem; padding: 1.5rem; border-left: 4px solid #3b82f6; background: rgba(59,130,246,0.08);" class="border border-zinc-200 dark:border-zinc-700">
            <div style="font-size: 0.875rem; color: #60a5fa; font-weight: 500;">{{ __('Period Total') }}</div>
            <div style="margin-top: 0.5rem; font-size: 1.5rem; font-weight: 700; font-variant-numeric: tabular-nums;" class="text-zinc-900 dark:text-zinc-100">&euro; {{ number_format($this->totalPeriod / 100, 2, ',', '.') }}</div>
        </div>
        <div style="border-radius: 0.75rem; padding: 1.5rem; border-left: 4px solid #8b5cf6; background: rgba(139,92,246,0.08);" class="border border-zinc-200 dark:border-zinc-700">
            <div style="font-size: 0.875rem; color: #a78bfa; font-weight: 500;">{{ __('Receipts') }}</div>
            <div style="margin-top: 0.5rem; font-size: 1.5rem; font-weight: 700; font-variant-numeric: tabular-nums;" class="text-zinc-900 dark:text-zinc-100">{{ $this->receiptCount }}</div>
        </div>
        <div style="border-radius: 0.75rem; padding: 1.5rem; border-left: 4px solid #10b981; background: rgba(16,185,129,0.08);" class="border border-zinc-200 dark:border-zinc-700">
            <div style="font-size: 0.875rem; color: #34d399; font-weight: 500;">{{ __('Avg per Receipt') }}</div>
            <div style="margin-top: 0.5rem; font-size: 1.5rem; font-weight: 700; font-variant-numeric: tabular-nums;" class="text-zinc-900 dark:text-zinc-100">
                &euro; {{ $this->receiptCount > 0 ? number_format(($this->totalPeriod / $this->receiptCount) / 100, 2, ',', '.') : '0,00' }}
            </div>
        </div>
    </div>

    {{-- By Category & By Store --}}
    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; margin-bottom: 2rem;">
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <flux:heading size="lg" class="mb-4">{{ __('By Category') }}</flux:heading>
            @if ($this->byCategory->isNotEmpty())
                @php $totalAll = $this->byCategory->sum('total') ?: 1; @endphp
                @foreach ($this->byCategory as $index => $row)
                    @php $pct = round(($row->total / $totalAll) * 100); @endphp
                    <div style="margin-bottom: 0.75rem;">
                        <div style="display: flex; justify-content: space-between; font-size: 0.875rem; margin-bottom: 0.375rem;">
                            <span style="display: flex; align-items: center; gap: 0.5rem;">
                                <span style="width: 0.5rem; height: 0.5rem; border-radius: 9999px; background: {{ $barColors[$index % count($barColors)] }}; flex-shrink: 0;"></span>
                                <span class="text-zinc-700 dark:text-zinc-300">{{ $row->name }}</span>
                            </span>
                            <span>
                                <span style="font-variant-numeric: tabular-nums; font-weight: 600;" class="text-zinc-900 dark:text-zinc-100">&euro; {{ number_format($row->total / 100, 2, ',', '.') }}</span>
                                <span style="font-size: 0.75rem; margin-left: 0.25rem;" class="text-zinc-500 dark:text-zinc-400">{{ $pct }}%</span>
                            </span>
                        </div>
                        <div style="height: 0.5rem; border-radius: 9999px; overflow: hidden; background: rgba(113,113,122,0.2);">
                            <div style="height: 0.5rem; border-radius: 9999px; width: {{ $pct }}%; background: {{ $barColors[$index % count($barColors)] }};"></div>
                        </div>
                    </div>
                @endforeach
            @else
                <flux:text>{{ __('No data for this period.') }}</flux:text>
            @endif
        </div>

        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <flux:heading size="lg" class="mb-4">{{ __('By Store') }}</flux:heading>
            @if ($this->byStore->isNotEmpty())
                @php $totalAllStores = $this->byStore->sum('total') ?: 1; @endphp
                @foreach ($this->byStore as $index => $row)
                    @php $pct = round(($row->total / $totalAllStores) * 100); @endphp
                    <div style="margin-bottom: 0.75rem;">
                        <div style="display: flex; justify-content: space-between; font-size: 0.875rem; margin-bottom: 0.375rem;">
                            <span style="display: flex; align-items: center; gap: 0.5rem;">
                                <span style="width: 0.5rem; height: 0.5rem; border-radius: 9999px; background: {{ $barColors[$index % count($barColors)] }}; flex-shrink: 0;"></span>
                                <span class="text-zinc-700 dark:text-zinc-300">{{ $row->name }}</span>
                            </span>
                            <span>
                                <span style="font-variant-numeric: tabular-nums; font-weight: 600;" class="text-zinc-900 dark:text-zinc-100">&euro; {{ number_format($row->total / 100, 2, ',', '.') }}</span>
                                <span style="font-size: 0.75rem; margin-left: 0.25rem;" class="text-zinc-500 dark:text-zinc-400">{{ $pct }}%</span>
                            </span>
                        </div>
                        <div style="height: 0.5rem; border-radius: 9999px; overflow: hidden; background: rgba(113,113,122,0.2);">
                            <div style="height: 0.5rem; border-radius: 9999px; width: {{ $pct }}%; background: {{ $barColors[$index % count($barColors)] }};"></div>
                        </div>
                    </div>
                @endforeach
            @else
                <flux:text>{{ __('No data for this period.') }}</flux:text>
            @endif
        </div>
    </div>

    {{-- Trend chart --}}
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
        <flux:heading size="lg" class="mb-4">{{ $this->isMonthlyTrend ? __('Monthly Trend') : __('Daily Trend') }}</flux:heading>
        @if ($this->trendData->isNotEmpty())
            @php
                $maxVal = $this->trendData->max('total') ?: 1;
                $itemCount = $this->trendData->count();
                $ySteps = 4;
            @endphp
            <div style="display: flex; gap: 0;">
                {{-- Y-axis labels --}}
                <div style="display: flex; flex-direction: column; justify-content: space-between; height: 160px; padding-right: 0.5rem; flex-shrink: 0;">
                    @for ($i = $ySteps; $i >= 0; $i--)
                        <span style="font-size: 0.625rem; font-variant-numeric: tabular-nums; line-height: 1; white-space: nowrap;" class="text-zinc-500 dark:text-zinc-400">&euro;{{ number_format(($maxVal * $i / $ySteps) / 100, 0, ',', '.') }}</span>
                    @endfor
                </div>
                {{-- Chart area --}}
                <div style="flex: 1; min-width: 0;">
                    {{-- Grid lines + bars --}}
                    <div style="position: relative; height: 160px;">
                        {{-- Horizontal grid lines --}}
                        @for ($i = 0; $i <= $ySteps; $i++)
                            <div style="position: absolute; left: 0; right: 0; bottom: {{ ($i / $ySteps) * 100 }}%; border-bottom: 1px solid rgba(113,113,122,0.15);"></div>
                        @endfor
                        {{-- Bars --}}
                        <style>
                            .dt-bar .dt-tip { opacity: 0; transition: opacity 0.1s; }
                            .dt-bar:hover .dt-tip { opacity: 1; }
                        </style>
                        <div style="display: flex; align-items: flex-end; gap: {{ $this->isMonthlyTrend ? '4' : '2' }}px; height: 100%; position: relative; z-index: 1;">
                            @foreach ($this->trendData as $index => $item)
                                @php $hue = $itemCount > 1 ? round(($index / ($itemCount - 1)) * 300) : 0; @endphp
                                <div class="dt-bar" style="flex: 1; display: flex; align-items: flex-end; height: 100%; position: relative;">
                                    <div style="width: 100%; border-radius: 0.25rem 0.25rem 0 0; min-height: 2px; height: {{ $item->total > 0 ? max(4, round(($item->total / $maxVal) * 100)) : 2 }}%; background: {{ $item->total > 0 ? "hsl({$hue}, 75%, 60%)" : 'rgba(113,113,122,0.2)' }};"></div>
                                    <div class="dt-tip" style="position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); margin-bottom: 0.25rem; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.6875rem; font-weight: 500; white-space: nowrap; background: #18181b; color: #f4f4f5; pointer-events: none; z-index: 10;">{{ $item->tooltip }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    {{-- X-axis labels --}}
                    <div style="display: flex; gap: {{ $this->isMonthlyTrend ? '4' : '2' }}px; margin-top: 0.25rem;">
                        @foreach ($this->trendData as $item)
                            <span style="flex: 1; text-align: center; font-size: 0.625rem;" class="text-zinc-500 dark:text-zinc-400">{{ $item->label }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
        @else
            <flux:text>{{ __('No data for this period.') }}</flux:text>
        @endif
    </div>
</div>
