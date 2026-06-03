<?php

namespace Tests\Feature\Forms;

use App\Models\Form;
use App\Models\FormCell;
use App\Models\FormColumn;
use App\Models\FormResponse;
use App\Models\FormRow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FormViewOtherUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_visiting_with_another_user_renders_read_only(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($me);

        $form = Form::factory()->create();
        $column = FormColumn::factory()->create(['form_id' => $form->id]);
        $row = FormRow::factory()->create(['form_id' => $form->id]);
        $response = FormResponse::create(['form_id' => $form->id, 'user_id' => $other->id]);
        FormCell::create([
            'form_response_id' => $response->id,
            'form_row_id' => $row->id,
            'form_column_id' => $column->id,
            'value' => 'theirs',
        ]);

        Livewire::test('pages::forms.fill', ['form' => $form, 'user' => $other])
            ->assertSet('readOnly', true)
            ->assertSet("cells.{$row->id}.{$column->id}", 'theirs');
    }

    public function test_read_only_view_does_not_write_when_cells_change(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($me);

        $form = Form::factory()->create();
        $column = FormColumn::factory()->create(['form_id' => $form->id]);
        $row = FormRow::factory()->create(['form_id' => $form->id]);
        $otherResponse = FormResponse::create(['form_id' => $form->id, 'user_id' => $other->id]);
        FormCell::create([
            'form_response_id' => $otherResponse->id,
            'form_row_id' => $row->id,
            'form_column_id' => $column->id,
            'value' => 'theirs',
        ]);

        Livewire::test('pages::forms.fill', ['form' => $form, 'user' => $other])
            ->set("cells.{$row->id}.{$column->id}", 'hack attempt');

        $this->assertDatabaseMissing('form_responses', [
            'form_id' => $form->id,
            'user_id' => $me->id,
        ]);
        $this->assertDatabaseHas('form_cells', [
            'form_response_id' => $otherResponse->id,
            'form_row_id' => $row->id,
            'form_column_id' => $column->id,
            'value' => 'theirs',
        ]);
    }

    public function test_visiting_without_user_param_is_editable(): void
    {
        $me = User::factory()->create();
        $this->actingAs($me);
        $form = Form::factory()->create();

        Livewire::test('pages::forms.fill', ['form' => $form])
            ->assertSet('readOnly', false)
            ->assertSet('viewedUser.id', $me->id);
    }

    public function test_user_tabs_include_self_and_other_responders(): void
    {
        $me = User::factory()->create(['name' => 'Me User']);
        $other = User::factory()->create(['name' => 'Other User']);
        $stranger = User::factory()->create(['name' => 'Stranger']);
        $this->actingAs($me);

        $form = Form::factory()->create();
        FormResponse::create(['form_id' => $form->id, 'user_id' => $other->id]);

        $component = Livewire::test('pages::forms.fill', ['form' => $form]);

        $tabs = $component->instance()->userTabs;
        $tabIds = $tabs->pluck('id')->all();

        $this->assertContains($me->id, $tabIds);
        $this->assertContains($other->id, $tabIds);
        $this->assertNotContains($stranger->id, $tabIds);
        $this->assertEquals($me->id, $tabIds[0]);
    }
}
