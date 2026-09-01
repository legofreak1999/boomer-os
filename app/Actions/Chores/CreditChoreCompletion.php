<?php

namespace App\Actions\Chores;

use App\Models\ChoreCompletion;
use App\Models\ChoreList;
use App\Models\ChoreListItem;
use Carbon\CarbonInterface;

class CreditChoreCompletion
{
    /**
     * Create one ChoreCompletion per currently-assigned user (or
     * $actingUserId if nobody is assigned), splitting time and difficulty
     * evenly between them. Shared by the initial check-off
     * (ToggleChoreListItemCompletion) and by re-syncing credit when
     * assignees change on an already-checked item (SyncChoreCompletionCredit)
     * so both stay consistent with a single split implementation.
     */
    public function __invoke(ChoreListItem $item, int $actingUserId, CarbonInterface $completedAt, ?int $totalBountyCents): void
    {
        $item->loadMissing('chore.difficultyRatings', 'choreList', 'users');
        $chore = $item->chore;

        $timePoints = $chore->time_points + ($item->escalation_level * $chore->escalation_increment);

        if ($chore->escalation_cap !== null) {
            $timePoints = min($timePoints, $chore->escalation_cap);
        }

        $creditedUserIds = $item->users->pluck('id')->all();

        if (empty($creditedUserIds)) {
            $creditedUserIds = [$actingUserId];
        }

        $completerCount = count($creditedUserIds);
        $countsTowardReward = $item->choreList->hasRepeat() && $item->choreList->repeat_type !== ChoreList::REPEAT_YEARLY;

        foreach ($creditedUserIds as $creditedUserId) {
            ChoreCompletion::create([
                'chore_list_item_id' => $item->id,
                'user_id' => $creditedUserId,
                // Divide centipoints (hundredths of a point), not whole
                // points: flooring a whole-point division could drop points
                // entirely (a 1-point chore split between two people would
                // floor to 0 for both), whereas an even 2-way split of
                // centipoints is always exact. Dividing the total and the
                // pre-escalation base by the same count still means
                // (time_centipoints - base_time_centipoints) reconciles
                // exactly to this share's escalation bonus.
                'time_centipoints' => intdiv($timePoints * 100, $completerCount),
                'base_time_centipoints' => intdiv($chore->time_points * 100, $completerCount),
                'escalation_level' => $item->escalation_level,
                'difficulty_centipoints' => intdiv($chore->difficultyPointsFor($creditedUserId) * 100, $completerCount),
                'bounty_cents' => $totalBountyCents !== null ? intdiv($totalBountyCents, $completerCount) : null,
                // Not split like the bounty: a text reward is descriptive,
                // not a divisible amount, so every credited completer's line
                // carries the same note. Read from the item (not passed in)
                // since, unlike the bounty, it isn't cleared at check time.
                'reward_note' => $item->reward_note,
                'counts_toward_reward' => $countsTowardReward,
                'completed_at' => $completedAt,
            ]);
        }
    }
}
