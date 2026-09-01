<?php

namespace App\Models;

use Database\Factories\ChoreCompletionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['chore_list_item_id', 'user_id', 'time_centipoints', 'base_time_centipoints', 'escalation_level', 'difficulty_centipoints', 'bounty_cents', 'reward_note', 'counts_toward_reward', 'completed_at'])]
class ChoreCompletion extends Model
{
    /** @use HasFactory<ChoreCompletionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Hundredths of a point, same idea as storing money as cents: a
            // chore's effort can be split between multiple credited people
            // (e.g. 1 point / 2 people), and dividing whole points would
            // silently floor that down to 0 for both. Divide by 100 when
            // displaying an actual point value.
            'time_centipoints' => 'integer',
            'base_time_centipoints' => 'integer',
            'escalation_level' => 'integer',
            'difficulty_centipoints' => 'integer',
            'bounty_cents' => 'integer',
            'counts_toward_reward' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function choreListItem(): BelongsTo
    {
        return $this->belongsTo(ChoreListItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
