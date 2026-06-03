<?php

namespace Tests\Feature\Forms;

use App\Models\Form;
use App\Models\FormCategory;
use App\Models\FormCell;
use App\Models\FormColumn;
use App\Models\FormResponse;
use App\Models\FormRow;
use App\Models\FormRowDefault;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FormStructureTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    public function test_can_create_text_column(): void
    {
        $this->actingUser();
        $form = Form::factory()->create();

        Livewire::test('pages::forms.structure', ['form' => $form])
            ->set('columnLabel', 'Question')
            ->set('columnType', 'text')
            ->call('saveColumn')
            ->assertHasNoErrors();

        $column = FormColumn::where('label', 'Question')->first();
        $this->assertNotNull($column);
        $this->assertEquals('text', $column->type);
        $this->assertNull($column->options);
    }

    public function test_can_create_select_column_with_options(): void
    {
        $this->actingUser();
        $form = Form::factory()->create();

        Livewire::test('pages::forms.structure', ['form' => $form])
            ->set('columnLabel', 'Decision')
            ->set('columnType', 'select')
            ->set('columnOptionsRaw', "Yes\nNo\nMaybe")
            ->call('saveColumn')
            ->assertHasNoErrors();

        $column = FormColumn::where('label', 'Decision')->first();
        $this->assertNotNull($column);
        $this->assertEquals('select', $column->type);
        $this->assertEquals(['Yes', 'No', 'Maybe'], $column->options);
    }

    public function test_select_column_requires_options(): void
    {
        $this->actingUser();
        $form = Form::factory()->create();

        Livewire::test('pages::forms.structure', ['form' => $form])
            ->set('columnLabel', 'Decision')
            ->set('columnType', 'select')
            ->set('columnOptionsRaw', '')
            ->call('saveColumn')
            ->assertHasErrors(['columnOptionsRaw']);
    }

    public function test_can_reorder_columns(): void
    {
        $this->actingUser();
        $form = Form::factory()->create();
        $a = FormColumn::factory()->create(['form_id' => $form->id, 'position' => 0]);
        $b = FormColumn::factory()->create(['form_id' => $form->id, 'position' => 1]);
        $c = FormColumn::factory()->create(['form_id' => $form->id, 'position' => 2]);

        Livewire::test('pages::forms.structure', ['form' => $form])
            ->call('sortColumns', $a->id, 2);

        $this->assertEquals(0, $b->refresh()->position);
        $this->assertEquals(1, $c->refresh()->position);
        $this->assertEquals(2, $a->refresh()->position);
    }

    public function test_can_create_category(): void
    {
        $this->actingUser();
        $form = Form::factory()->create();

        Livewire::test('pages::forms.structure', ['form' => $form])
            ->set('categoryName', 'Outdoors')
            ->call('saveCategory')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('form_categories', [
            'form_id' => $form->id,
            'name' => 'Outdoors',
        ]);
    }

    public function test_can_create_row_with_defaults_and_lock(): void
    {
        $this->actingUser();
        $form = Form::factory()->create();
        $col1 = FormColumn::factory()->create(['form_id' => $form->id, 'label' => 'Question']);
        $col2 = FormColumn::factory()->select(['Yes', 'No'])->create(['form_id' => $form->id, 'label' => 'Decision']);

        Livewire::test('pages::forms.structure', ['form' => $form])
            ->call('openRowModal')
            ->set("rowDefaults.{$col1->id}", 'Go to the beach?')
            ->set("rowLocks.{$col1->id}", true)
            ->set("rowDefaults.{$col2->id}", 'Yes')
            ->call('saveRow')
            ->assertHasNoErrors();

        $row = FormRow::where('form_id', $form->id)->first();
        $this->assertNotNull($row);

        $d1 = FormRowDefault::where('form_row_id', $row->id)->where('form_column_id', $col1->id)->first();
        $this->assertEquals('Go to the beach?', $d1->value);
        $this->assertTrue($d1->locked);

        $d2 = FormRowDefault::where('form_row_id', $row->id)->where('form_column_id', $col2->id)->first();
        $this->assertEquals('Yes', $d2->value);
        $this->assertFalse($d2->locked);
    }

    public function test_empty_default_is_not_saved(): void
    {
        $this->actingUser();
        $form = Form::factory()->create();
        $column = FormColumn::factory()->create(['form_id' => $form->id]);

        Livewire::test('pages::forms.structure', ['form' => $form])
            ->call('openRowModal')
            ->set("rowDefaults.{$column->id}", '')
            ->call('saveRow')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('form_row_defaults', 0);
    }

    public function test_clearing_default_on_edit_deletes_it(): void
    {
        $this->actingUser();
        $form = Form::factory()->create();
        $column = FormColumn::factory()->create(['form_id' => $form->id]);
        $row = FormRow::factory()->create(['form_id' => $form->id]);
        FormRowDefault::factory()->create([
            'form_row_id' => $row->id,
            'form_column_id' => $column->id,
            'value' => 'old value',
        ]);

        Livewire::test('pages::forms.structure', ['form' => $form])
            ->call('editRow', $row->id)
            ->set("rowDefaults.{$column->id}", '')
            ->call('saveRow');

        $this->assertDatabaseCount('form_row_defaults', 0);
    }

    public function test_deleting_category_keeps_rows_as_uncategorized(): void
    {
        $this->actingUser();
        $form = Form::factory()->create();
        $category = FormCategory::factory()->create(['form_id' => $form->id]);
        $row = FormRow::factory()->create([
            'form_id' => $form->id,
            'form_category_id' => $category->id,
        ]);

        Livewire::test('pages::forms.structure', ['form' => $form])
            ->call('deleteCategory', $category->id);

        $this->assertDatabaseMissing('form_categories', ['id' => $category->id]);
        $this->assertDatabaseHas('form_rows', ['id' => $row->id, 'form_category_id' => null]);
    }

    public function test_deleting_column_cascades_defaults_and_cells(): void
    {
        $this->actingUser();
        $form = Form::factory()->create();
        $column = FormColumn::factory()->create(['form_id' => $form->id]);
        $row = FormRow::factory()->create(['form_id' => $form->id]);
        $default = FormRowDefault::factory()->create([
            'form_row_id' => $row->id,
            'form_column_id' => $column->id,
        ]);
        $response = FormResponse::factory()->create(['form_id' => $form->id]);
        $cell = FormCell::factory()->create([
            'form_response_id' => $response->id,
            'form_row_id' => $row->id,
            'form_column_id' => $column->id,
        ]);

        Livewire::test('pages::forms.structure', ['form' => $form])
            ->call('deleteColumn', $column->id);

        $this->assertDatabaseMissing('form_columns', ['id' => $column->id]);
        $this->assertDatabaseMissing('form_row_defaults', ['id' => $default->id]);
        $this->assertDatabaseMissing('form_cells', ['id' => $cell->id]);
    }

    public function test_deleting_row_cascades_defaults_and_cells(): void
    {
        $this->actingUser();
        $form = Form::factory()->create();
        $column = FormColumn::factory()->create(['form_id' => $form->id]);
        $row = FormRow::factory()->create(['form_id' => $form->id]);
        FormRowDefault::factory()->create([
            'form_row_id' => $row->id,
            'form_column_id' => $column->id,
        ]);
        $response = FormResponse::factory()->create(['form_id' => $form->id]);
        FormCell::factory()->create([
            'form_response_id' => $response->id,
            'form_row_id' => $row->id,
            'form_column_id' => $column->id,
        ]);

        Livewire::test('pages::forms.structure', ['form' => $form])
            ->call('deleteRow', $row->id);

        $this->assertDatabaseMissing('form_rows', ['id' => $row->id]);
        $this->assertDatabaseCount('form_row_defaults', 0);
        $this->assertDatabaseCount('form_cells', 0);
    }

    public function test_can_reorder_rows_within_category(): void
    {
        $this->actingUser();
        $form = Form::factory()->create();
        $a = FormRow::factory()->create(['form_id' => $form->id, 'position' => 0]);
        $b = FormRow::factory()->create(['form_id' => $form->id, 'position' => 1]);
        $c = FormRow::factory()->create(['form_id' => $form->id, 'position' => 2]);

        Livewire::test('pages::forms.structure', ['form' => $form])
            ->call('sortRows', $a->id, 2, null);

        $this->assertEquals(0, $b->refresh()->position);
        $this->assertEquals(1, $c->refresh()->position);
        $this->assertEquals(2, $a->refresh()->position);
    }
}
