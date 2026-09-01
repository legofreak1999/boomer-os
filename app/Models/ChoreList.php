<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\ChoreListFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'position', 'is_hidden', 'repeat_type', 'repeat_value', 'repeat_start_date'])]
class ChoreList extends Model
{
    /** @use HasFactory<ChoreListFactory> */
    use HasFactory;

    public const REPEAT_DAILY = 'daily';

    public const REPEAT_WEEKLY = 'weekly';

    public const REPEAT_MONTHLY_DAY = 'monthly_day';

    public const REPEAT_MONTHLY_LAST = 'monthly_last';

    public const REPEAT_YEARLY = 'yearly';

    public const REPEAT_TYPES = [
        self::REPEAT_DAILY,
        self::REPEAT_WEEKLY,
        self::REPEAT_MONTHLY_DAY,
        self::REPEAT_MONTHLY_LAST,
        self::REPEAT_YEARLY,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_hidden' => 'boolean',
            'repeat_start_date' => 'date',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ChoreListItem::class);
    }

    public function isComplete(): bool
    {
        return $this->items()->exists() && $this->items()->where('is_checked', false)->doesntExist();
    }

    public function hasRepeat(): bool
    {
        return $this->repeat_type !== null;
    }

    public function complete(): void
    {
        if ($this->hasRepeat()) {
            $this->update(['is_hidden' => true]);
        } else {
            $this->delete();
        }
    }

    public function shouldResetOn(Carbon $date): bool
    {
        if (! $this->hasRepeat() || ! $this->repeat_start_date) {
            return false;
        }

        return match ($this->repeat_type) {
            self::REPEAT_DAILY => $this->repeat_start_date->diffInDays($date) % $this->repeat_value === 0,
            self::REPEAT_WEEKLY => $date->dayOfWeekIso === $this->repeat_value,
            self::REPEAT_MONTHLY_DAY => $date->day === $this->repeat_value,
            self::REPEAT_MONTHLY_LAST => $date->isLastOfMonth(),
            self::REPEAT_YEARLY => $date->month === $this->repeat_value && $date->day === $this->repeat_start_date->day,
            default => false,
        };
    }

    /**
     * Count how many times this list would occur within the given date range.
     *
     * Guards against dates before repeat_start_date because shouldResetOn's
     * daily branch uses diffInDays(), which is unsigned/absolute.
     */
    public function occurrencesBetween(Carbon $start, Carbon $end): int
    {
        if (! $this->hasRepeat() || ! $this->repeat_start_date) {
            return 0;
        }

        $count = 0;
        $cursor = $start->copy()->startOfDay();
        $end = $end->copy()->startOfDay();

        while ($cursor->lte($end)) {
            if ($cursor->gte($this->repeat_start_date) && $this->shouldResetOn($cursor)) {
                $count++;
            }
            $cursor->addDay();
        }

        return $count;
    }
}
