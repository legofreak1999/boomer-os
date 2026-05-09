<?php

namespace Tests\Feature\Hikes;

use App\Models\HikeLocation;
use App\Models\HikeTrail;
use App\Models\HikeTrailClosure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HikeTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_trail_editor_page_is_displayed(): void
    {
        $this->actingAs(User::factory()->create());

        $location = HikeLocation::factory()->create();

        $this->get(route('hikes.trails.create', $location))->assertOk();
    }

    public function test_can_create_trail(): void
    {
        $this->actingAs(User::factory()->create());

        $location = HikeLocation::factory()->create();

        Livewire::test('pages::hikes.trail-editor', ['hikeLocation' => $location])
            ->set('trailName', 'Test Trail')
            ->set('difficulty', 'easy')
            ->set('waypoints', [
                ['lat' => 52.0, 'lng' => 5.0, 'straight' => false],
                ['lat' => 52.01, 'lng' => 5.01, 'straight' => false],
            ])
            ->set('distanceM', 5000)
            ->set('durationS', 3600)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('hike_trails', [
            'hike_location_id' => $location->id,
            'name' => 'Test Trail',
        ]);
    }

    public function test_trail_requires_name_and_waypoints(): void
    {
        $this->actingAs(User::factory()->create());

        $location = HikeLocation::factory()->create();

        Livewire::test('pages::hikes.trail-editor', ['hikeLocation' => $location])
            ->set('trailName', '')
            ->set('waypoints', [])
            ->call('save')
            ->assertHasErrors(['trailName', 'waypoints']);
    }

    public function test_can_update_trail(): void
    {
        $this->actingAs(User::factory()->create());

        $location = HikeLocation::factory()->create();
        $trail = HikeTrail::factory()->create([
            'hike_location_id' => $location->id,
            'name' => 'Old Trail',
        ]);

        Livewire::test('pages::hikes.trail-editor', ['hikeLocation' => $location, 'hikeTrail' => $trail])
            ->set('trailName', 'New Trail')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals('New Trail', $trail->refresh()->name);
    }

    public function test_can_add_closure(): void
    {
        $this->actingAs(User::factory()->create());

        $location = HikeLocation::factory()->create();

        Livewire::test('pages::hikes.trail-editor', ['hikeLocation' => $location])
            ->set('closureStartDate', '2026-11-01')
            ->set('closureEndDate', '2027-03-01')
            ->set('closureReason', 'Winter closure')
            ->call('addClosure')
            ->assertHasNoErrors();
    }

    public function test_trail_is_currently_closed(): void
    {
        $trail = HikeTrail::factory()->create();
        HikeTrailClosure::factory()->create([
            'hike_trail_id' => $trail->id,
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
        ]);

        $this->assertTrue($trail->isCurrentlyClosed());
    }

    public function test_trail_is_not_closed_outside_period(): void
    {
        $trail = HikeTrail::factory()->create();
        HikeTrailClosure::factory()->create([
            'hike_trail_id' => $trail->id,
            'start_date' => now()->addMonths(2),
            'end_date' => now()->addMonths(4),
        ]);

        $this->assertFalse($trail->isCurrentlyClosed());
    }

    public function test_distance_and_duration_helpers(): void
    {
        $trail = HikeTrail::factory()->create([
            'distance_m' => 8500,
            'duration_s' => 6300,
        ]);

        $this->assertEquals(8.5, $trail->distanceKm());
        $this->assertEquals('1h 45min', $trail->durationFormatted());
    }

    public function test_can_delete_trail_from_location_edit(): void
    {
        $this->actingAs(User::factory()->create());

        $location = HikeLocation::factory()->create();
        $trail = HikeTrail::factory()->create(['hike_location_id' => $location->id]);

        Livewire::test('pages::hikes.edit', ['hikeLocation' => $location])
            ->call('deleteTrail', $trail->id);

        $this->assertDatabaseMissing('hike_trails', ['id' => $trail->id]);
    }
}
