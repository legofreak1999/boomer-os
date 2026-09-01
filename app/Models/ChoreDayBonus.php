<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ChoreDayBonusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'date', 'level'])]
class ChoreDayBonus extends Model
{
    /** @use HasFactory<ChoreDayBonusFactory> */
    use HasFactory;

    public const LEVEL_BAD = 'bad';

    public const LEVEL_SUPER_BAD = 'super_bad';

    public const LEVELS = [
        self::LEVEL_BAD,
        self::LEVEL_SUPER_BAD,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function levelFor(int $userId, CarbonInterface $date): ?string
    {
        return static::where('user_id', $userId)->whereDate('date', $date)->value('level');
    }

    /**
     * Set, update, or clear a user's day-bonus flag for a given date.
     * Passing a null level clears the flag (deletes the row) rather than
     * storing a "neutral" value.
     */
    public static function setLevel(int $userId, string $date, ?string $level): void
    {
        $existing = static::where('user_id', $userId)->whereDate('date', $date)->first();

        if ($level === null) {
            $existing?->delete();
        } elseif ($existing) {
            $existing->update(['level' => $level]);
        } else {
            static::create(['user_id' => $userId, 'date' => $date, 'level' => $level]);
        }
    }

    /**
     * The difficulty multiplier for a given day-bonus level, using
     * whichever bad_day_multiplier/super_bad_day_multiplier are currently
     * configured. Shared by every reward calculation that needs to apply a
     * day-bonus flag live (CalculateMonthlyRewardSummary,
     * CalculateListBonusShares) so they can't drift apart.
     *
     * @param  array{bad_day_multiplier: int, super_bad_day_multiplier: int, ...}  $settings
     */
    public static function multiplierFor(?string $level, array $settings): int
    {
        $multiplier = match ($level) {
            self::LEVEL_BAD => $settings['bad_day_multiplier'],
            self::LEVEL_SUPER_BAD => $settings['super_bad_day_multiplier'],
            default => 1,
        };

        // Only validated in the settings form, not at the point of use — a
        // stray 0 or negative value would zero out or invert difficulty
        // points on a flagged day instead of boosting them.
        return max(1, $multiplier);
    }
}
