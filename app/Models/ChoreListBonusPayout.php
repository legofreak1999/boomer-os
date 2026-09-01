<?php

namespace App\Models;

use Database\Factories\ChoreListBonusPayoutFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['chore_list_id', 'user_id', 'weight_centipoints', 'share_cents'])]
class ChoreListBonusPayout extends Model
{
    /** @use HasFactory<ChoreListBonusPayoutFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight_centipoints' => 'integer',
            'share_cents' => 'integer',
        ];
    }

    public function choreList(): BelongsTo
    {
        return $this->belongsTo(ChoreList::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
