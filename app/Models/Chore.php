<?php

namespace App\Models;

use Database\Factories\ChoreFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'chore_category_id', 'time_points', 'escalation_increment', 'escalation_cap'])]
class Chore extends Model
{
    /** @use HasFactory<ChoreFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'time_points' => 'integer',
            'escalation_increment' => 'integer',
            'escalation_cap' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ChoreCategory::class, 'chore_category_id');
    }

    public function choreListItems(): HasMany
    {
        return $this->hasMany(ChoreListItem::class);
    }

    public function difficultyRatings(): HasMany
    {
        return $this->hasMany(ChoreDifficultyRating::class);
    }

    public function difficultyPointsFor(int $userId): int
    {
        return $this->difficultyRatings->firstWhere('user_id', $userId)?->difficulty_points ?? 1;
    }
}
