<?php

namespace App\Models;

use Database\Factories\HikeLocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Http;

#[Fillable(['name', 'description', 'parking_lat', 'parking_lng', 'drive_distance_m', 'drive_duration_s'])]
class HikeLocation extends Model
{
    /** @use HasFactory<HikeLocationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parking_lat' => 'decimal:7',
            'parking_lng' => 'decimal:7',
        ];
    }

    public function trails(): HasMany
    {
        return $this->hasMany(HikeTrail::class);
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(HikeTag::class, 'taggable', 'hike_taggables', 'taggable_id', 'hike_tag_id');
    }

    public function driveTimeFormatted(): string
    {
        if (! $this->drive_duration_s) {
            return '-';
        }

        $hours = intdiv($this->drive_duration_s, 3600);
        $mins = intdiv($this->drive_duration_s % 3600, 60);

        return $hours > 0 ? "{$hours}h {$mins}min" : "{$mins} min";
    }

    public function driveDistanceKm(): ?float
    {
        return $this->drive_distance_m ? round($this->drive_distance_m / 1000, 1) : null;
    }

    public function calculateDriveTime(float $homeLat, float $homeLng): void
    {
        $url = sprintf(
            'https://routing.openstreetmap.de/routed-car/route/v1/driving/%s,%s;%s,%s?overview=false',
            $homeLng, $homeLat, $this->parking_lng, $this->parking_lat,
        );

        try {
            $response = Http::timeout(10)->get($url);
            $data = $response->json();

            if (($data['code'] ?? '') === 'Ok' && ! empty($data['routes'])) {
                $this->update([
                    'drive_distance_m' => (int) $data['routes'][0]['distance'],
                    'drive_duration_s' => (int) $data['routes'][0]['duration'],
                ]);
            }
        } catch (\Throwable) {
            // Silently fail — drive time stays null
        }
    }
}
