<?php

namespace App\Actions\Chores;

use App\Models\AppSetting;
use App\Models\ChoreCompletion;
use App\Models\ChoreDayBonus;
use App\Models\ChoreList;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class CalculateMonthlyRewardSummary
{
    /**
     * @var array{floor_per_person_cents: int, bonus_pool_cents: int, bounty_max_cents: int, bad_day_multiplier: int, super_bad_day_multiplier: int}
     */
    public const DEFAULT_SETTINGS = [
        'floor_per_person_cents' => 5000,
        'bonus_pool_cents' => 20000,
        'bounty_max_cents' => 50000,
        'bad_day_multiplier' => 2,
        'super_bad_day_multiplier' => 3,
    ];

    /**
     * @return array{
     *   month_start: Carbon,
     *   target_points: float,
     *   time_completed: float,
     *   pool_payout_cents: int,
     *   breakdown: list<array{
     *     user_id: int,
     *     name: string,
     *     points: float,
     *     floor_cents: int,
     *     share_cents: int,
     *     pool_total_cents: int,
     *     bounty_cents: int,
     *     grand_total_cents: int,
     *     receipt: list<array{
     *       date: string,
     *       chore_name: string,
     *       time_points: float,
     *       base_time_points: float,
     *       escalation_level: int,
     *       escalation_bonus_points: float,
     *       base_difficulty_points: float,
     *       day_bonus_level: string|null,
     *       multiplier: int,
     *       effective_difficulty_points: float,
     *       weight: float,
     *       bounty_cents: int|null,
     *       reward_note: string|null,
     *       counts_toward_reward: bool,
     *       shared_with: list<string>,
     *       completer_count: int,
     *     }>,
     *   }>,
     * }
     */
    public function __invoke(CarbonInterface $monthStart): array
    {
        // Normalize to a concrete mutable Carbon: the app defaults now()/Date
        // to CarbonImmutable, but ChoreList::occurrencesBetween/shouldResetOn
        // are written against the mutable Carbon\Carbon.
        $monthStart = Carbon::parse($monthStart)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $settings = array_merge(self::DEFAULT_SETTINGS, AppSetting::get('chore_reward_settings', []));

        $targetPoints = ChoreList::query()
            ->whereNotNull('repeat_type')
            ->where('repeat_type', '!=', ChoreList::REPEAT_YEARLY)
            ->with('items.chore')
            ->get()
            ->sum(fn (ChoreList $list) => $list->occurrencesBetween($monthStart, $monthEnd)
                * $list->items->sum(fn ($item) => $item->chore->time_points));

        // Batch-load the month's day-bonus flags once, keyed by "userId|Y-m-d",
        // so applying the multiplier below never has to query per completion.
        $dayBonusLevels = ChoreDayBonus::query()
            ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->get()
            ->mapWithKeys(fn (ChoreDayBonus $bonus) => ["{$bonus->user_id}|{$bonus->date->toDateString()}" => $bonus->level]);

        $rawCompletions = ChoreCompletion::query()
            ->with('choreListItem.chore', 'user')
            ->whereBetween('completed_at', [$monthStart, $monthEnd])
            ->orderBy('completed_at')
            ->get();

        // Completions from the same "check the box" event share the exact
        // same item + timestamp (see ToggleChoreListItemCompletion) — group
        // by that to find who else shared credit for a given line.
        $batches = $rawCompletions
            ->filter(fn (ChoreCompletion $completion) => $completion->chore_list_item_id !== null)
            ->groupBy(fn (ChoreCompletion $completion) => $completion->chore_list_item_id.'|'.$completion->completed_at);

        $completions = $rawCompletions->map(function (ChoreCompletion $completion) use ($dayBonusLevels, $settings, $batches) {
            $level = $dayBonusLevels->get("{$completion->user_id}|{$completion->completed_at->toDateString()}");
            $multiplier = $this->multiplierFor($level, $settings);
            // The centipoint columns are nullable at the schema level (a
            // migration constraint, not something any current writer
            // leaves empty) — guard here so a future write path that omits
            // one fails safely (treated as 0) instead of a fatal TypeError.
            $timeCentipoints = $completion->time_centipoints ?? 0;
            $baseTimeCentipoints = $completion->base_time_centipoints ?? 0;
            $difficultyCentipoints = $completion->difficulty_centipoints ?? 0;
            // Stay in centipoints (hundredths of a point) for as long as
            // possible, same reasoning as money-as-cents: dividing by 100
            // once at the end avoids ever doing float math on these values.
            $effectiveDifficultyCentipoints = $difficultyCentipoints * $multiplier;
            $weightCentipoints = $timeCentipoints + $effectiveDifficultyCentipoints;

            $batchKey = $completion->chore_list_item_id.'|'.$completion->completed_at;
            $batch = $batches->get($batchKey, collect());
            $sharedWith = $batch
                ->reject(fn (ChoreCompletion $other) => $other->is($completion))
                ->map(fn (ChoreCompletion $other) => $other->user->name)
                ->values()
                ->all();

            return [
                'user_id' => $completion->user_id,
                'date' => $completion->completed_at->toDateString(),
                'chore_name' => $completion->choreListItem?->chore?->name ?? 'Unknown chore',
                'time_points' => $timeCentipoints / 100,
                'base_time_points' => $baseTimeCentipoints / 100,
                'escalation_level' => $completion->escalation_level,
                'escalation_bonus_points' => ($timeCentipoints - $baseTimeCentipoints) / 100,
                'base_difficulty_points' => $difficultyCentipoints / 100,
                'day_bonus_level' => $level,
                'multiplier' => $multiplier,
                'effective_difficulty_points' => $effectiveDifficultyCentipoints / 100,
                'weight' => $weightCentipoints / 100,
                'bounty_cents' => $completion->bounty_cents,
                'reward_note' => $completion->reward_note,
                'counts_toward_reward' => $completion->counts_toward_reward,
                'shared_with' => $sharedWith,
                // Lets the receipt show the split math (e.g. "8 ÷ 2 = 4")
                // instead of just the already-divided number, since time and
                // difficulty are otherwise indistinguishable from a solo,
                // unshared completion once stored.
                'completer_count' => max(1, $batch->count()),
            ];
        });

        $counting = $completions->where('counts_toward_reward', true);

        // A chore that escalates (missed cycles making it worth more) earns
        // more points than the baseline target assumed when it's finally
        // done — without this, that catch-up effort would just push
        // time_completed past a target that never grew to match, capping
        // the payout at 100% early even though real debt existed. Bounded
        // automatically by each chore's own configurable escalation_cap.
        $targetPoints += round($counting->sum('escalation_bonus_points'), 2);

        // Not cast to int: a completion's time_points can be a fraction when
        // split between multiple people, and truncating here before the pool
        // math would reintroduce the same lost-points problem the split
        // itself was fixed to avoid.
        $timeCompleted = round($counting->sum('time_points'), 2);

        $poolPayoutCents = $targetPoints > 0
            ? min($settings['bonus_pool_cents'], (int) round($settings['bonus_pool_cents'] * $timeCompleted / $targetPoints))
            : 0;

        $totalWeight = round($counting->sum('weight'), 2);

        $breakdown = User::orderBy('name')->get()->map(function (User $user) use ($completions, $counting, $totalWeight, $poolPayoutCents, $settings) {
            $userReceipt = $completions->where('user_id', $user->id)->values();
            $userWeight = round($counting->where('user_id', $user->id)->sum('weight'), 2);

            $shareCents = $totalWeight > 0 ? (int) round($poolPayoutCents * $userWeight / $totalWeight) : 0;
            $floorCents = $settings['floor_per_person_cents'];
            $bountyCents = (int) $completions->where('user_id', $user->id)->sum('bounty_cents');

            return [
                'user_id' => $user->id,
                'name' => $user->name,
                'points' => $userWeight,
                'floor_cents' => $floorCents,
                'share_cents' => $shareCents,
                'pool_total_cents' => $floorCents + $shareCents,
                'bounty_cents' => $bountyCents,
                'grand_total_cents' => $floorCents + $shareCents + $bountyCents,
                'receipt' => $userReceipt->all(),
            ];
        })->all();

        return [
            'month_start' => $monthStart,
            'target_points' => $targetPoints,
            'time_completed' => $timeCompleted,
            'pool_payout_cents' => $poolPayoutCents,
            'breakdown' => $breakdown,
        ];
    }

    private function multiplierFor(?string $level, array $settings): int
    {
        $multiplier = match ($level) {
            ChoreDayBonus::LEVEL_BAD => $settings['bad_day_multiplier'],
            ChoreDayBonus::LEVEL_SUPER_BAD => $settings['super_bad_day_multiplier'],
            default => 1,
        };

        // These settings are only validated in the settings form, not at
        // the point of use — a stray 0 or negative value (however it got
        // into storage) would zero out or invert difficulty points on a
        // flagged day instead of boosting them, so clamp defensively here.
        return max(1, $multiplier);
    }
}
