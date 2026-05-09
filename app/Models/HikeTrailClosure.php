<?php

namespace App\Models;

use Database\Factories\HikeTrailClosureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['hike_trail_id', 'start_date', 'end_date', 'reason'])]
class HikeTrailClosure extends Model
{
    /** @use HasFactory<HikeTrailClosureFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function trail(): BelongsTo
    {
        return $this->belongsTo(HikeTrail::class, 'hike_trail_id');
    }
}
