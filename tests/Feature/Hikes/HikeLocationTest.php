<?php

namespace Tests\Feature\Hikes;

use App\Models\HikeLocation;
use App\Models\HikeTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HikeLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_page_is_displayed(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('hikes.index'))->assertOk();
    }

    public function test_create_page_is_displayed(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('hikes.create'))->assertOk();
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $this->get(route('hikes.index'))->assertRedirect(route('login'));
    }

    public function test_can_create_location(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::hikes.create')
            ->set('name', 'Test Park')
            ->set('parkingLat', 52.1234)
            ->set('parkingLng', 5.6789)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('hike_locations', ['name' => 'Test Park']);
    }

    public function test_location_requires_name_and_parking(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::hikes.create')
            ->set('name', '')
            ->set('parkingLat', null)
            ->call('save')
            ->assertHasErrors(['name', 'parkingLat']);
    }

    public function test_can_create_location_with_tags(): void
    {
        $this->actingAs(User::factory()->create());

        $tag = HikeTag::factory()->create();

        Livewire::test('pages::hikes.create')
            ->set('name', 'Tagged Park')
            ->set('parkingLat', 52.0)
            ->set('parkingLng', 5.0)
            ->set('selectedTagIds', [(string) $tag->id])
            ->call('save')
            ->assertHasNoErrors();

        $location = HikeLocation::where('name', 'Tagged Park')->first();
        $this->assertTrue($location->tags->contains($tag));
    }

    public function test_can_update_location(): void
    {
        $this->actingAs(User::factory()->create());

        $location = HikeLocation::factory()->create(['name' => 'Old']);

        Livewire::test('pages::hikes.edit', ['hikeLocation' => $location])
            ->set('name', 'New')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals('New', $location->refresh()->name);
    }

    public function test_can_delete_location(): void
    {
        $this->actingAs(User::factory()->create());

        $location = HikeLocation::factory()->create();

        Livewire::test('pages::hikes.edit', ['hikeLocation' => $location])
            ->call('deleteLocation');

        $this->assertDatabaseMissing('hike_locations', ['id' => $location->id]);
    }

    public function test_tags_page_is_displayed(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('hikes.tags'))->assertOk();
    }

    public function test_can_create_tag(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::hikes.tags')
            ->set('tagName', 'forest')
            ->call('saveTag')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('hike_tags', ['name' => 'forest']);
    }

    public function test_can_delete_unused_tag(): void
    {
        $this->actingAs(User::factory()->create());

        $tag = HikeTag::factory()->create();

        Livewire::test('pages::hikes.tags')
            ->call('deleteTag', $tag->id);

        $this->assertDatabaseMissing('hike_tags', ['id' => $tag->id]);
    }
}
