<?php

use App\Actions\Chores\CalculateArchivedListBonusPayouts;
use App\Actions\Chores\CalculateMonthlyRewardSummary;
use App\Actions\Chores\SyncChoreCompletionCredit;
use App\Models\ChoreDayBonus;
use App\Models\ChoreListItem;
use App\Models\User;
use Carbon\Carbon;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Rewards')] class extends Component {
    public string $month;

    public array $expandedReceipts = [];

    public bool $showBonusForm = false;

    public ?int $bonusUserId = null;

    public string $bonusDate = '';

    public string $bonusLevel = '';

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
    }

    public function previousMonth(): void
    {
        $this->month = Carbon::createFromFormat('Y-m', $this->month)->subMonthNoOverflow()->format('Y-m');
        $this->refreshData();
    }

    public function nextMonth(): void
    {
        $this->month = Carbon::createFromFormat('Y-m', $this->month)->addMonthNoOverflow()->format('Y-m');
        $this->refreshData();
    }

    #[Computed]
    public function summary(): array
    {
        $monthStart = Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();

        return (new CalculateMonthlyRewardSummary)($monthStart);
    }

    /**
     * One-time list bonuses (see CompleteChoreList/archived_at), already
     * split and persisted as ChoreListBonusPayout rows the moment each list
     * was archived, filtered here to whichever ones fall in the browsed
     * month — a separate record from the main monthly summary, shown in
     * its own receipt below.
     */
    #[Computed]
    public function listBonuses(): array
    {
        $monthStart = Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();

        return (new CalculateArchivedListBonusPayouts)($monthStart);
    }

    #[Computed]
    public function monthLabel(): string
    {
        return Carbon::createFromFormat('Y-m', $this->month)->isoFormat('MMMM YYYY');
    }

    #[Computed]
    public function users()
    {
        return User::orderBy('name')->get();
    }

    /**
     * Checked-off items nobody is assigned to — assignment is completion
     * credit in this app, so these earned nothing until someone claims them.
     * Not month-scoped: it reflects each item's current state, not history.
     */
    #[Computed]
    public function unclaimedItems()
    {
        return ChoreListItem::query()
            ->where('is_checked', true)
            ->whereDoesntHave('users')
            ->with('chore.category', 'choreList')
            ->get();
    }

    public function claimJob(int $itemId, int $userId): void
    {
        $item = ChoreListItem::findOrFail($itemId);
        $item->users()->attach($userId);
        app(SyncChoreCompletionCredit::class)($item, auth()->id());
        unset($this->unclaimedItems);
        $this->refreshData();
    }

    #[Computed]
    public function dayBonusesThisMonth()
    {
        $monthStart = Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        return ChoreDayBonus::with('user')
            ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->orderByDesc('date')
            ->get();
    }

    public function toggleReceipt(int $userId): void
    {
        if (in_array($userId, $this->expandedReceipts)) {
            $this->expandedReceipts = array_values(array_diff($this->expandedReceipts, [$userId]));
        } else {
            $this->expandedReceipts[] = $userId;
        }
    }

    public function newBonus(): void
    {
        $this->bonusUserId = $this->users->first()?->id;

        // Defaulting to today would silently save the bonus under the
        // wrong month while browsing a past one — default to that month's
        // last day instead (still always in the past, so it stays valid
        // against the before_or_equal:today rule below).
        $browsedMonth = Carbon::createFromFormat('Y-m', $this->month);
        $this->bonusDate = $browsedMonth->isSameMonth(now())
            ? now()->toDateString()
            : $browsedMonth->copy()->endOfMonth()->toDateString();

        $this->bonusLevel = ChoreDayBonus::LEVEL_BAD;
        $this->showBonusForm = true;
        $this->resetValidation();
    }

    public function editBonus(int $userId, string $date): void
    {
        $this->bonusUserId = $userId;
        $this->bonusDate = $date;
        $this->bonusLevel = ChoreDayBonus::levelFor($userId, Carbon::parse($date)) ?? '';
        $this->showBonusForm = true;
        $this->resetValidation();
    }

    public function saveBonus(): void
    {
        $this->validate([
            'bonusUserId' => ['required', 'exists:users,id'],
            'bonusDate' => ['required', 'date', 'before_or_equal:today'],
            'bonusLevel' => ['in:,'.implode(',', ChoreDayBonus::LEVELS)],
        ]);

        ChoreDayBonus::setLevel($this->bonusUserId, $this->bonusDate, $this->bonusLevel !== '' ? $this->bonusLevel : null);

        $this->showBonusForm = false;
        $this->refreshData();
        Flux::toast(variant: 'success', text: __('Day bonus saved.'));
    }

    public function clearBonus(int $userId, string $date): void
    {
        ChoreDayBonus::setLevel($userId, $date, null);
        $this->refreshData();
    }

    public function cancelBonusForm(): void
    {
        $this->showBonusForm = false;
    }

    private function refreshData(): void
    {
        unset($this->summary, $this->dayBonusesThisMonth, $this->monthLabel, $this->listBonuses);
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl">{{ __('Rewards') }}</flux:heading>
            <flux:text class="mt-1">{{ __('This month\'s chore reward split.') }}</flux:text>
        </div>
        <div class="flex items-center gap-2">
            <flux:button size="sm" icon="chevron-left" variant="ghost" wire:click="previousMonth" />
            <flux:heading size="sm" class="min-w-32 text-center">{{ $this->monthLabel }}</flux:heading>
            <flux:button size="sm" icon="chevron-right" variant="ghost" wire:click="nextMonth" />
        </div>
    </div>

    @php $summary = $this->summary; @endphp

    <div class="grid grid-cols-1 gap-4 mb-6 sm:grid-cols-3">
        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text size="sm" class="text-zinc-500">{{ __('Household target') }}</flux:text>
            <flux:heading size="lg">{{ $summary['target_points'] }} {{ __('pts') }}</flux:heading>
        </div>
        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text size="sm" class="text-zinc-500">{{ __('Completed together') }}</flux:text>
            <flux:heading size="lg">{{ $summary['time_completed'] }} {{ __('pts') }}</flux:heading>
        </div>
        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text size="sm" class="text-zinc-500">{{ __('Bonus pool unlocked') }}</flux:text>
            <flux:heading size="lg">&euro; {{ number_format($summary['pool_payout_cents'] / 100, 2, ',', '.') }}</flux:heading>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        @foreach ($summary['breakdown'] as $row)
            @php
                // The user's own share out of each archived list's payout —
                // a separate record from the main monthly summary above,
                // not baked into $row itself.
                $userListBonusLines = collect($this->listBonuses)
                    ->map(function ($payout) use ($row) {
                        $share = collect($payout['shares'])->firstWhere('user_id', $row['user_id']);

                        return $share ? [...$payout, 'share' => $share] : null;
                    })
                    ->filter()
                    ->values();
                $userListBonusCents = $userListBonusLines->sum('share.share_cents');
                $adjustedGrandTotalCents = $row['grand_total_cents'] + $userListBonusCents;
            @endphp
            <div class="rounded-lg border border-zinc-200 p-5 dark:border-zinc-700" wire:key="breakdown-{{ $row['user_id'] }}">
                <flux:heading size="lg" class="mb-3">{{ $row['name'] }}</flux:heading>

                <div class="space-y-1">
                    <flux:text size="sm" class="text-zinc-500">{{ __('Points completed') }}: {{ $row['points'] }}</flux:text>
                    <flux:text size="sm" class="text-zinc-500">{{ __('Floor') }}: &euro; {{ number_format($row['floor_cents'] / 100, 2, ',', '.') }}</flux:text>
                    <flux:text size="sm" class="text-zinc-500">{{ __('Pool share') }}: &euro; {{ number_format($row['share_cents'] / 100, 2, ',', '.') }}</flux:text>
                    <flux:text size="sm" class="font-medium">{{ __('Pool total') }}: &euro; {{ number_format($row['pool_total_cents'] / 100, 2, ',', '.') }}</flux:text>

                    @if ($row['bounty_cents'] > 0)
                        <flux:text size="sm" class="text-amber-600 dark:text-amber-400">+ &euro; {{ number_format($row['bounty_cents'] / 100, 2, ',', '.') }} {{ __('in bounties') }}</flux:text>
                    @endif
                    @if ($userListBonusCents > 0)
                        <flux:text size="sm" class="text-amber-600 dark:text-amber-400">+ &euro; {{ number_format($userListBonusCents / 100, 2, ',', '.') }} {{ __('in list bonuses') }}</flux:text>
                    @endif
                </div>

                <flux:separator class="my-3" />

                <flux:heading size="xl">&euro; {{ number_format($adjustedGrandTotalCents / 100, 2, ',', '.') }}</flux:heading>

                @if ($userListBonusLines->isNotEmpty())
                    <div class="mt-3">
                        <flux:heading size="sm" class="mb-1">{{ __('List Bonuses') }}</flux:heading>
                        @include('pages.chores._receipt-list-bonuses-table', ['lines' => $userListBonusLines])
                    </div>
                @endif

                @if (count($row['receipt']) > 0)
                    <flux:button size="xs" variant="ghost" class="mt-3" wire:click="toggleReceipt({{ $row['user_id'] }})">
                        {{ in_array($row['user_id'], $expandedReceipts) ? __('Hide breakdown') : __('View breakdown') }} ({{ count($row['receipt']) }})
                    </flux:button>

                    @if (in_array($row['user_id'], $expandedReceipts))
                        @php
                            $pointsLines = collect($row['receipt'])->where('counts_toward_reward', true)->values();
                            $nonCountingLines = collect($row['receipt'])->where('counts_toward_reward', false)->values();
                            // Deliberately not exclusive with the two above: a
                            // line with both a reward and points shows up in
                            // both sections, since it's genuinely both.
                            $rewardLines = collect($row['receipt'])->filter(fn ($line) => $line['bounty_cents'] || $line['reward_note'])->values();
                        @endphp

                        @if ($pointsLines->isNotEmpty())
                            <div class="mt-3">
                                <flux:heading size="sm" class="mb-1">{{ __('Points') }}</flux:heading>
                                @include('pages.chores._receipt-points-table', ['lines' => $pointsLines])
                            </div>
                        @endif

                        @if ($rewardLines->isNotEmpty())
                            <div class="mt-3">
                                <flux:heading size="sm" class="mb-1">{{ __('Rewards') }}</flux:heading>
                                @include('pages.chores._receipt-rewards-table', ['lines' => $rewardLines])
                            </div>
                        @endif

                        @if ($nonCountingLines->isNotEmpty())
                            <div class="mt-3">
                                <flux:heading size="sm" class="mb-1">{{ __("Points that don't count") }}</flux:heading>
                                @include('pages.chores._receipt-points-table', ['lines' => $nonCountingLines])
                            </div>
                        @endif
                    @endif
                @endif
            </div>
        @endforeach
    </div>

    {{-- Day bonus editor --}}
    <div class="rounded-lg border border-zinc-200 p-5 dark:border-zinc-700 mt-6">
        <div class="flex items-center justify-between mb-3">
            <flux:heading size="lg">{{ __('Day bonuses this month') }}</flux:heading>
            <flux:button size="sm" icon="plus" variant="ghost" wire:click="newBonus">{{ __('Add') }}</flux:button>
        </div>

        @if ($showBonusForm)
            <form wire:submit="saveBonus" class="flex flex-wrap items-end gap-3 mb-4 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                <div>
                    <flux:text size="sm" class="mb-1">{{ __('Person') }}</flux:text>
                    <flux:select wire:model="bonusUserId" size="sm" class="w-36">
                        @foreach ($this->users as $u)
                            <flux:select.option :value="$u->id">{{ $u->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                <div>
                    <flux:text size="sm" class="mb-1">{{ __('Date') }}</flux:text>
                    <flux:input type="date" wire:model="bonusDate" max="{{ now()->toDateString() }}" size="sm" class="w-40" />
                </div>
                <div>
                    <flux:text size="sm" class="mb-1">{{ __('Level') }}</flux:text>
                    <flux:select wire:model="bonusLevel" size="sm" class="w-36">
                        <flux:select.option value="">{{ __('Neutral') }}</flux:select.option>
                        <flux:select.option value="bad">{{ __('Bad day') }}</flux:select.option>
                        <flux:select.option value="super_bad">{{ __('Super bad day') }}</flux:select.option>
                    </flux:select>
                </div>
                <flux:button size="sm" variant="primary" type="submit">{{ __('Save') }}</flux:button>
                <flux:button size="sm" variant="ghost" wire:click="cancelBonusForm">{{ __('Cancel') }}</flux:button>
            </form>
        @endif

        @forelse ($this->dayBonusesThisMonth as $bonus)
            <div class="flex items-center justify-between py-1.5 border-b border-zinc-100 dark:border-zinc-800 last:border-0" wire:key="bonus-{{ $bonus->id }}">
                <flux:text size="sm">
                    {{ $bonus->date->format('M j') }} — {{ $bonus->user->name }} —
                    {{ $bonus->level === 'super_bad' ? __('Super bad day') : __('Bad day') }}
                </flux:text>
                <div class="flex gap-1">
                    <flux:button size="xs" icon="pencil" variant="ghost" wire:click="editBonus({{ $bonus->user_id }}, '{{ $bonus->date->toDateString() }}')" />
                    <flux:button size="xs" icon="x-mark" variant="ghost" wire:click="clearBonus({{ $bonus->user_id }}, '{{ $bonus->date->toDateString() }}')" />
                </div>
            </div>
        @empty
            <flux:text size="sm" class="text-zinc-500">{{ __('No bad or super bad days flagged this month.') }}</flux:text>
        @endforelse
    </div>

    {{-- Unclaimed jobs --}}
    @if ($this->unclaimedItems->isNotEmpty())
        <div class="rounded-lg border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/30 p-5 mt-6">
            <flux:heading size="lg">{{ __('Unclaimed jobs') }} ({{ $this->unclaimedItems->count() }})</flux:heading>
            <flux:text size="sm" class="text-zinc-500 mb-3">
                {{ __("Already checked off, but nobody's credited for it yet. Not scoped to the month above — always shows every outstanding item.") }}
            </flux:text>
            <div class="space-y-1.5">
                @foreach ($this->unclaimedItems as $item)
                    <div class="flex items-center justify-between gap-2 rounded-md bg-white dark:bg-zinc-800 px-3 py-1.5" wire:key="unclaimed-{{ $item->id }}">
                        <div class="min-w-0 truncate">
                            <span class="font-medium">{{ $item->chore->name }}</span>
                            <span class="text-xs text-zinc-500">{{ $item->choreList->name }}</span>
                        </div>
                        <div class="flex gap-1 shrink-0">
                            @foreach ($this->users as $user)
                                <flux:button size="xs" variant="ghost" wire:click="claimJob({{ $item->id }}, {{ $user->id }})">
                                    {{ __('Claim as :name', ['name' => $user->name]) }}
                                </flux:button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
