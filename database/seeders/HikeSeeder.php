<?php

namespace Database\Seeders;

use App\Models\HikeLocation;
use App\Models\HikeTag;
use App\Models\HikeTrail;
use App\Models\HikeTrailClosure;
use Illuminate\Database\Seeder;

class HikeSeeder extends Seeder
{
    public function run(): void
    {
        $tags = collect([
            'forest', 'dunes', 'dog-friendly', 'wheelchair', 'scenic',
            'hills', 'waterside', 'family', 'challenging', 'nature-reserve',
        ])->map(fn ($name) => HikeTag::firstOrCreate(['name' => $name]));

        $tagsByName = $tags->keyBy('name');

        // Location 1: Veluwe
        $veluwe = HikeLocation::firstOrCreate(
            ['name' => 'Hoge Veluwe'],
            ['description' => 'National park with forests and heathland', 'parking_lat' => 52.0825, 'parking_lng' => 5.8364],
        );
        $veluwe->tags()->syncWithoutDetaching([$tagsByName['forest']->id, $tagsByName['nature-reserve']->id, $tagsByName['scenic']->id]);

        $t1 = HikeTrail::firstOrCreate(
            ['hike_location_id' => $veluwe->id, 'name' => 'Kröller-Müller Loop'],
            ['distance_m' => 8500, 'duration_s' => 6300, 'difficulty' => 'moderate', 'waypoints' => [['lat' => 52.0825, 'lng' => 5.8364, 'straight' => false], ['lat' => 52.09, 'lng' => 5.85, 'straight' => false]]],
        );
        $t1->tags()->syncWithoutDetaching([$tagsByName['scenic']->id, $tagsByName['family']->id]);

        $t2 = HikeTrail::firstOrCreate(
            ['hike_location_id' => $veluwe->id, 'name' => 'Wildpad'],
            ['distance_m' => 12000, 'duration_s' => 9000, 'difficulty' => 'hard', 'waypoints' => [['lat' => 52.08, 'lng' => 5.83, 'straight' => false], ['lat' => 52.10, 'lng' => 5.87, 'straight' => false]]],
        );
        $t2->tags()->syncWithoutDetaching([$tagsByName['challenging']->id, $tagsByName['forest']->id]);

        // Location 2: Kennemerduinen
        $dunes = HikeLocation::firstOrCreate(
            ['name' => 'Kennemerduinen'],
            ['description' => 'Coastal dunes near Haarlem', 'parking_lat' => 52.3975, 'parking_lng' => 4.5600],
        );
        $dunes->tags()->syncWithoutDetaching([$tagsByName['dunes']->id, $tagsByName['dog-friendly']->id]);

        $t3 = HikeTrail::firstOrCreate(
            ['hike_location_id' => $dunes->id, 'name' => 'Dune Walk'],
            ['distance_m' => 5500, 'duration_s' => 4200, 'difficulty' => 'easy', 'waypoints' => [['lat' => 52.3975, 'lng' => 4.56, 'straight' => false], ['lat' => 52.40, 'lng' => 4.57, 'straight' => false]]],
        );
        $t3->tags()->syncWithoutDetaching([$tagsByName['family']->id, $tagsByName['dog-friendly']->id]);

        // Add a seasonal closure
        HikeTrailClosure::firstOrCreate(
            ['hike_trail_id' => $t2->id, 'start_date' => '2026-11-01'],
            ['end_date' => '2027-03-01', 'reason' => 'Muddy and unsafe in winter'],
        );

        // Location 3: Loonse en Drunense Duinen
        $loon = HikeLocation::firstOrCreate(
            ['name' => 'Loonse en Drunense Duinen'],
            ['description' => 'Sahara of Brabant - inland sand dunes', 'parking_lat' => 51.6400, 'parking_lng' => 5.0750],
        );
        $loon->tags()->syncWithoutDetaching([$tagsByName['dunes']->id, $tagsByName['scenic']->id, $tagsByName['family']->id]);

        HikeTrail::firstOrCreate(
            ['hike_location_id' => $loon->id, 'name' => 'Sand Dune Circuit'],
            ['distance_m' => 6000, 'duration_s' => 4500, 'difficulty' => 'easy', 'waypoints' => [['lat' => 51.64, 'lng' => 5.075, 'straight' => false], ['lat' => 51.645, 'lng' => 5.08, 'straight' => false]]],
        );
    }
}
