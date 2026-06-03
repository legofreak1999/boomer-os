<?php

namespace Tests\Feature\Forms;

use App\Models\Form;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FormIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_forms_page_is_displayed(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('forms.index'))->assertOk();
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $this->get(route('forms.index'))->assertRedirect(route('login'));
    }

    public function test_can_create_form(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::forms.index')
            ->set('formName', 'Annual review 2026')
            ->set('formDescription', 'Yearly check-in.')
            ->call('saveForm')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('forms', [
            'name' => 'Annual review 2026',
            'description' => 'Yearly check-in.',
        ]);
    }

    public function test_form_name_is_required(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::forms.index')
            ->set('formName', '')
            ->call('saveForm')
            ->assertHasErrors(['formName']);
    }

    public function test_can_rename_form(): void
    {
        $this->actingAs(User::factory()->create());

        $form = Form::factory()->create(['name' => 'Old name']);

        Livewire::test('pages::forms.index')
            ->call('editForm', $form->id)
            ->set('formName', 'New name')
            ->call('saveForm')
            ->assertHasNoErrors();

        $this->assertEquals('New name', $form->refresh()->name);
    }

    public function test_can_delete_form(): void
    {
        $this->actingAs(User::factory()->create());

        $form = Form::factory()->create();

        Livewire::test('pages::forms.index')
            ->call('deleteForm', $form->id);

        $this->assertDatabaseMissing('forms', ['id' => $form->id]);
    }
}
