<?php

namespace App\Actions\Chores;

use App\Models\ChoreList;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class CalculateArchivedListBonusPayouts
{
    /**
     * Reads back the ChoreListBonusPayout rows persisted by CompleteChoreList
     * at the moment each list was archived — the split itself isn't
     * recomputed here, only filtered to whichever lists were archived in
     * the browsed month.
     *
     * @return list<array{
     *   list_id: int, list_name: string, archived_at: string, bonus_cents: int,
     *   total_weight: float,
     *   shares: list<array{user_id: int, name: string, weight: float, share_cents: int}>,
     * }>
     */
    public function __invoke(CarbonInterface $monthStart): array
    {
        $monthStart = Carbon::parse($monthStart)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $lists = ChoreList::query()
            ->whereNotNull('archived_at')
            ->whereNotNull('bonus_cents')
            ->whereBetween('archived_at', [$monthStart, $monthEnd])
            ->with('bonusPayouts.user')
            ->orderBy('archived_at')
            ->get();

        return $lists->map(function (ChoreList $list) {
            $totalWeightCentipoints = $list->bonusPayouts->sum('weight_centipoints');

            return [
                'list_id' => $list->id,
                'list_name' => $list->name,
                'archived_at' => $list->archived_at->toDateString(),
                'bonus_cents' => $list->bonus_cents,
                'total_weight' => $totalWeightCentipoints / 100,
                'shares' => $list->bonusPayouts->map(fn ($payout) => [
                    'user_id' => $payout->user_id,
                    'name' => $payout->user->name,
                    'weight' => $payout->weight_centipoints / 100,
                    'share_cents' => $payout->share_cents,
                ])->all(),
            ];
        })->all();
    }
}
