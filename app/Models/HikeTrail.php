<?php

namespace App\Models;

use Database\Factories\HikeTrailFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

#[Fillable(['hike_location_id', 'name', 'description', 'distance_m', 'duration_s', 'waypoints', 'route_geojson', 'difficulty'])]
class HikeTrail extends Model
{
    /** @use HasFactory<HikeTrailFactory> */
    use HasFactory;

    public const DIFFICULTY_EASY = 'easy';

    public const DIFFICULTY_MODERATE = 'moderate';

    public const DIFFICULTY_HARD = 'hard';

    public const DIFFICULTIES = [
        self::DIFFICULTY_EASY,
        self::DIFFICULTY_MODERATE,
        self::DIFFICULTY_HARD,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'waypoints' => 'array',
            'route_geojson' => 'array',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(HikeLocation::class, 'hike_location_id');
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(HikeTag::class, 'taggable', 'hike_taggables', 'taggable_id', 'hike_tag_id');
    }

    public function closures(): HasMany
    {
        return $this->hasMany(HikeTrailClosure::class);
    }

    public function isCurrentlyClosed(): bool
    {
        $today = now()->startOfDay();

        return $this->closures()->where('start_date', '<=', $today)->where('end_date', '>=', $today)->exists();
    }

    public function distanceKm(): float
    {
        return round($this->distance_m / 1000, 2);
    }

    public function durationFormatted(): string
    {
        $hours = intdiv($this->duration_s, 3600);
        $mins = intdiv($this->duration_s % 3600, 60);

        return $hours > 0 ? "{$hours}h {$mins}min" : "{$mins} min";
    }
}
