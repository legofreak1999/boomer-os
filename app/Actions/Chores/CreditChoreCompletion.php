<?php

namespace App\Actions\Chores;

use App\Models\ChoreCompletion;
use App\Models\ChoreListItem;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class CreditChoreCompletion
{
    /**
     * Create one ChoreCompletion per currently-assigned user, splitting time
     * and difficulty evenly between them. Shared by the initial check-off
     * (ToggleChoreListItemCompletion) and by re-syncing credit when
     * assignees change on an already-checked item (SyncChoreCompletionCredit)
     * so both stay consistent with a single split implementation.
     *
     * $fallbackToActingUser controls what happens when nobody is assigned:
     * true (the default, used at initial check-off) credits $actingUserId —
     * whoever clicked the checkbox clearly did it. false (used when
     * re-syncing after an assignment change) leaves the item creditless
     * instead, since "someone was assigned and then removed" should show up
     * as genuinely unclaimed, not silently re-credit whoever happened to
     * remove them.
     */
    public function __invoke(ChoreListItem $item, int $actingUserId, CarbonInterface $completedAt, ?int $totalBountyCents, bool $fallbackToActingUser = true): void
    {
        $item->loadMissing('chore.difficultyRatings', 'choreList', 'users');
        $chore = $item->chore;

        $timePoints = $chore->time_points + ($item->escalation_level * $chore->escalation_increment);

        if ($chore->escalation_cap !== null) {
            $timePoints = min($timePoints, $chore->escalation_cap);
        }

        $creditedUserIds = $item->users->pluck('id')->all();

        if (empty($creditedUserIds)) {
            if (! $fallbackToActingUser) {
                return;
            }

            $creditedUserIds = [$actingUserId];
        }

        $countsTowardReward = $item->choreList->hasRepeat();

        // Split the pre-escalation base and the escalation bonus separately
        // (each with its own remainder-fair distribution — see
        // splitWithRemainder), then add them per-person. This guarantees
        // both that every completer's (time - base) still reconciles
        // exactly to their own escalation bonus share, and that the shares
        // sum back to the true total, for ANY completer count — not just
        // divisors of 100 the way naive intdiv-per-field would.
        $baseTotalCentipoints = $chore->time_points * 100;
        $bonusTotalCentipoints = ($timePoints - $chore->time_points) * 100;
        $baseShares = $this->splitWithRemainder($baseTotalCentipoints, $creditedUserIds);
        $bonusShares = $this->splitWithRemainder($bonusTotalCentipoints, $creditedUserIds);
        $bountyShares = $totalBountyCents !== null ? $this->splitWithRemainder($totalBountyCents, $creditedUserIds) : null;

        DB::transaction(function () use ($item, $chore, $creditedUserIds, $baseShares, $bonusShares, $bountyShares, $countsTowardReward, $completedAt) {
            foreach ($creditedUserIds as $creditedUserId) {
                ChoreCompletion::create([
                    'chore_list_item_id' => $item->id,
                    'user_id' => $creditedUserId,
                    'time_centipoints' => $baseShares[$creditedUserId] + $bonusShares[$creditedUserId],
                    'base_time_centipoints' => $baseShares[$creditedUserId],
                    'escalation_level' => $item->escalation_level,
                    // Difficulty isn't a shared total being divided across
                    // completion rows the way time/bounty are — it's each
                    // person's own personal rating, split by their own share
                    // of the work. There's no "sum must equal total" to
                    // preserve here, so round (not floor) each person's own
                    // number for the least-biased result.
                    'difficulty_centipoints' => (int) round($chore->difficultyPointsFor($creditedUserId) * 100 / count($creditedUserIds)),
                    'bounty_cents' => $bountyShares !== null ? $bountyShares[$creditedUserId] : null,
                    // Not split like the bounty: a text reward is descriptive,
                    // not a divisible amount, so every credited completer's line
                    // carries the same note. Read from the item (not passed in)
                    // since, unlike the bounty, it isn't cleared at check time.
                    'reward_note' => $item->reward_note,
                    'counts_toward_reward' => $countsTowardReward,
                    'completed_at' => $completedAt,
                ]);
            }
        });
    }

    /**
     * Split $total evenly among $userIds, giving the leftover remainder
     * units (if $total doesn't divide evenly) one each to the
     * lowest-numbered user IDs, so the shares always sum back to exactly
     * $total regardless of how many people are splitting it.
     *
     * @param  array<int, int>  $userIds
     * @return array<int, int> user_id => share
     */
    private function splitWithRemainder(int $total, array $userIds): array
    {
        $sorted = $userIds;
        sort($sorted);

        $count = count($sorted);
        $base = intdiv($total, $count);
        $remainder = $total % $count;

        $shares = [];
        foreach ($sorted as $index => $userId) {
            $shares[$userId] = $base + ($index < $remainder ? 1 : 0);
        }

        return $shares;
    }
}
