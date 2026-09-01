<?php

namespace App\Actions\Chores;

use App\Models\AppSetting;
use App\Models\ChoreDayBonus;
use App\Models\ChoreList;

class CalculateListBonusShares
{
    /**
     * A one-time list bonus is split between contributors by their point
     * weight (time + effective difficulty, same formula as the main
     * monthly reward) across ALL of that list's completions — not just
     * ones dated within the browsed month, since a list can stay open for
     * weeks before it's finally completed. Called once, at the moment the
     * list is archived (see CompleteChoreList), so the result can be
     * persisted as ChoreListBonusPayout rows rather than recomputed live.
     *
     * @return list<array{user_id: int, weight_centipoints: int, share_cents: int}>
     */
    public function __invoke(ChoreList $list): array
    {
        $completions = $list->items->flatMap(fn ($item) => $item->completions);

        if ($completions->isEmpty()) {
            return [];
        }

        $settings = array_merge(
            CalculateMonthlyRewardSummary::DEFAULT_SETTINGS,
            AppSetting::get('chore_reward_settings', [])
        );

        $userIds = $completions->pluck('user_id')->unique();
        $dayBonusLevels = ChoreDayBonus::query()
            ->whereIn('user_id', $userIds)
            ->get()
            ->mapWithKeys(fn (ChoreDayBonus $bonus) => ["{$bonus->user_id}|{$bonus->date->toDateString()}" => $bonus->level]);

        $weightCentipointsByUser = [];

        foreach ($completions as $completion) {
            $level = $dayBonusLevels->get("{$completion->user_id}|{$completion->completed_at->toDateString()}");
            $multiplier = ChoreDayBonus::multiplierFor($level, $settings);
            $weight = $completion->time_centipoints + ($completion->difficulty_centipoints * $multiplier);

            $weightCentipointsByUser[$completion->user_id] = ($weightCentipointsByUser[$completion->user_id] ?? 0) + $weight;
        }

        $totalWeightCentipoints = array_sum($weightCentipointsByUser);

        return collect($weightCentipointsByUser)
            ->map(function (int $weightCentipoints, int $userId) use ($totalWeightCentipoints, $list) {
                $shareCents = $totalWeightCentipoints > 0
                    ? (int) round($list->bonus_cents * $weightCentipoints / $totalWeightCentipoints)
                    : 0;

                return [
                    'user_id' => $userId,
                    'weight_centipoints' => $weightCentipoints,
                    'share_cents' => $shareCents,
                ];
            })
            ->values()
            ->all();
    }
}
