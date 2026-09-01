<?php

namespace App\Models;

use Database\Factories\ChoreDifficultyRatingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['chore_id', 'user_id', 'difficulty_points'])]
class ChoreDifficultyRating extends Model
{
    /** @use HasFactory<ChoreDifficultyRatingFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'difficulty_points' => 'integer',
        ];
    }

    public function chore(): BelongsTo
    {
        return $this->belongsTo(Chore::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
