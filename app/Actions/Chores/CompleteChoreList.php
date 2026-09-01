<?php

namespace App\Actions\Chores;

use App\Models\ChoreList;
use App\Models\ChoreListBonusPayout;
use Illuminate\Support\Facades\DB;

class CompleteChoreList
{
    /**
     * Repeating lists just hide until their next reset. A non-repeating
     * list normally gets removed outright once it's done — but if it
     * carries a one-time bonus reward, the split is calculated right now
     * (while its completions are still guaranteed intact) and persisted as
     * ChoreListBonusPayout rows, and the list is archived instead of
     * deleted so it keeps showing on the Rewards page under whichever
     * month it was completed in.
     */
    public function __invoke(ChoreList $list): void
    {
        if ($list->hasRepeat()) {
            $list->update(['is_hidden' => true]);

            return;
        }

        if ($list->bonus_cents === null) {
            $list->delete();

            return;
        }

        DB::transaction(function () use ($list) {
            $shares = app(CalculateListBonusShares::class)($list);

            foreach ($shares as $share) {
                ChoreListBonusPayout::create([
                    'chore_list_id' => $list->id,
                    'user_id' => $share['user_id'],
                    'weight_centipoints' => $share['weight_centipoints'],
                    'share_cents' => $share['share_cents'],
                ]);
            }

            $list->update(['archived_at' => now()]);
        });
    }
}
