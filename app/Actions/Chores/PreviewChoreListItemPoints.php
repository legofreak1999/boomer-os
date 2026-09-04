<?php

namespace App\Actions\Chores;

use App\Models\AppSetting;
use App\Models\ChoreDayBonus;
use App\Models\ChoreListItem;

class PreviewChoreListItemPoints
{
    /**
     * A live, unpersisted estimate of how many points $userId would earn
     * for checking off $item right now — same escalation math as
     * CreditChoreCompletion, same "unassigned defaults to whoever acts"
     * fallback, and the same day-mood multiplier (applied to difficulty
     * only) as CalculateMonthlyRewardSummary/CalculateListBonusShares. Used
     * to preview a chore's point value on hover before it's completed, not
     * to record anything.
     *
     *
     * @param  array{bad_day_multiplier: int, super_bad_day_multiplier: int, ...}|null  $settings
     * @return array{
     *     is_credited: bool,
     *     time_base: int,
     *     escalation_bonus: int,
     *     difficulty_base: int,
     *     mood_level: string|null,
     *     multiplier: int,
     *     assignee_count: int,
     *     time_share: float,
     *     difficulty_share: float,
     *     total: float,
     * }
     *
     * $moodLevel and $settings can be passed in already-fetched (e.g. from a
     * Livewire computed property) so a caller previewing many items in one
     * render doesn't re-query the day-bonus/app-setting rows per item.
     */
    public function __invoke(ChoreListItem $item, int $userId, ?string $moodLevel = null, ?array $settings = null): array
    {
        $chore = $item->chore;

        $timePoints = $chore->time_points + ($item->escalation_level * $chore->escalation_increment);

        if ($chore->escalation_cap !== null) {
            $timePoints = min($timePoints, $chore->escalation_cap);
        }

        $escalationBonus = $timePoints - $chore->time_points;

        $moodLevel ??= ChoreDayBonus::levelFor($userId, now());
        $settings ??= array_merge(
            CalculateMonthlyRewardSummary::DEFAULT_SETTINGS,
            AppSetting::get('chore_reward_settings', [])
        );
        $multiplier = ChoreDayBonus::multiplierFor($moodLevel, $settings);
        $difficultyBase = $chore->difficultyPointsFor($userId);

        $assigneeIds = $item->users->pluck('id')->all();
        $isCredited = empty($assigneeIds) || in_array($userId, $assigneeIds, true);
        $assigneeCount = empty($assigneeIds) ? 1 : count($assigneeIds);

        if (! $isCredited) {
            return [
                'is_credited' => false,
                'time_base' => $chore->time_points,
                'escalation_bonus' => $escalationBonus,
                'difficulty_base' => $difficultyBase,
                'mood_level' => $moodLevel,
                'multiplier' => $multiplier,
                'assignee_count' => $assigneeCount,
                'time_share' => 0.0,
                'difficulty_share' => 0.0,
                'total' => 0.0,
            ];
        }

        $timeShare = $timePoints / $assigneeCount;
        $difficultyShare = ($difficultyBase * $multiplier) / $assigneeCount;

        // PHP's `/` returns int for evenly-divisible operands, but this
        // return shape promises float for every numeric share (see docblock)
        // so callers don't have to juggle int|float.
        $timeShare = (float) $timeShare;
        $difficultyShare = (float) $difficultyShare;

        return [
            'is_credited' => true,
            'time_base' => $chore->time_points,
            'escalation_bonus' => $escalationBonus,
            'difficulty_base' => $difficultyBase,
            'mood_level' => $moodLevel,
            'multiplier' => $multiplier,
            'assignee_count' => $assigneeCount,
            'time_share' => $timeShare,
            'difficulty_share' => $difficultyShare,
            'total' => round($timeShare + $difficultyShare, 1),
        ];
    }
}
