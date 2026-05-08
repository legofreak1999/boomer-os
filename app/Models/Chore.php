<?php

namespace App\Models;

use Database\Factories\ChoreFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'chore_category_id'])]
class Chore extends Model
{
    /** @use HasFactory<ChoreFactory> */
    use HasFactory;

    public function category(): BelongsTo
    {
        return $this->belongsTo(ChoreCategory::class, 'chore_category_id');
    }

    public function choreListItems(): HasMany
    {
        return $this->hasMany(ChoreListItem::class);
    }
}
