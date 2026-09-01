<?php

namespace App\Models;

use Database\Factories\ChoreListItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable(['chore_list_id', 'chore_id', 'is_checked', 'priority', 'escalation_level', 'bounty_cents', 'reward_note'])]
class ChoreListItem extends Model
{
    /** @use HasFactory<ChoreListItemFactory> */
    use HasFactory;

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_LOW = 'low';

    public const PRIORITIES = [
        self::PRIORITY_HIGH,
        self::PRIORITY_MEDIUM,
        self::PRIORITY_LOW,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_checked' => 'boolean',
            'escalation_level' => 'integer',
            'bounty_cents' => 'integer',
        ];
    }

    public function choreList(): BelongsTo
    {
        return $this->belongsTo(ChoreList::class);
    }

    public function chore(): BelongsTo
    {
        return $this->belongsTo(Chore::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function completions(): HasMany
    {
        return $this->hasMany(ChoreCompletion::class);
    }

    /**
     * The completions from this item's most recent "check the box" event,
     * out of its full completion history — needs the completions relation
     * already loaded (or it'll trigger a query).
     *
     * @return Collection<int, ChoreCompletion>
     */
    public function currentCompletionBatch(): Collection
    {
        if ($this->completions->isEmpty()) {
            return collect();
        }

        return $this->completions->where('completed_at', $this->completions->max('completed_at'));
    }

    /**
     * The reward actually in effect for this item right now: its own
     * pending bounty/note while unchecked, or whatever was snapshotted onto
     * its most recent completion batch once checked — checking clears the
     * item's own bounty_cents, so without this the reward would otherwise
     * look like it vanished the moment the item is completed.
     *
     * @return array{bounty_cents: int|null, reward_note: string|null}
     */
    public function displayReward(): array
    {
        if (! $this->is_checked) {
            return ['bounty_cents' => $this->bounty_cents, 'reward_note' => $this->reward_note];
        }

        $batch = $this->currentCompletionBatch();

        if ($batch->isEmpty()) {
            return ['bounty_cents' => null, 'reward_note' => $this->reward_note];
        }

        $bountyCents = $batch->every(fn (ChoreCompletion $completion) => $completion->bounty_cents !== null)
            ? $batch->sum('bounty_cents')
            : null;

        return ['bounty_cents' => $bountyCents, 'reward_note' => $batch->first()->reward_note];
    }
}
