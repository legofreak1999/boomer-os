<?php

namespace App\Models;

use Database\Factories\ChoreListItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['chore_list_id', 'chore_id', 'is_checked'])]
class ChoreListItem extends Model
{
    /** @use HasFactory<ChoreListItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_checked' => 'boolean',
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
}
