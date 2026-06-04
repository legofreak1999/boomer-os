<?php

namespace Tests\Feature\Forms;

use App\Models\Form;
use App\Models\FormCell;
use App\Models\FormColumn;
use App\Models\FormColumnCategory;
use App\Models\FormResponse;
use App\Models\FormRow;
use App\Models\FormRowCategory;
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
        $this->assertEquals([['Yes', 'No', 'Maybe']], $column->options);
    }

    public function test_can_create_textarea_column(): void
    {
        $this->actingUser();
        $form = Form::factory()->create();

        Livewire::test('pages::forms.structure', ['form' => $form])
            ->set('columnLabel', 'Notes')
            ->set('columnType', 'textarea')
            ->call('saveColumn')
            ->assertHasNoErrors();

        $column = FormColumn::where('label', 'Notes')->first();
        $this->assertNotNull($column);
        $this->assertEquals('textarea', $column->type);
        $this->assertNull($column->options);
    }

    public function test_select_column_options_group_into_horizontal_rows(): void
    {
        $this->actingUser();
        $form = Form::factory()->create();

        Livewire::test('pages::forms.structure', ['form' => $form])
            ->set('columnLabel', 'Stance')
            ->set('columnType', 'select')
            ->set('columnOptionsRaw', "test\n\n1\n2\n3\n\ntest 2")
            ->call('saveColumn')
            ->assertHasNoErrors();

        $column = FormColumn::where('label', 'Stance')->first();
        $this->assertEquals(
            [['test'], ['1', '2', '3'], ['test 2']],
            $column->options,
        );
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

    public function test_can_create_column_category(): void
    {
        $this->actingUser();
        $form = Form::factory()->create();

        Livewire::test('pages::forms.structure', ['form' => $form])
            ->set('columnCategoryName', 'Decision details')
            ->call('saveColumnCategory')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('form_column_categories', [
            'form_id' => $form->id,
            'name' => 'Decision details',
        ]);
    }

    public function test_can_assign_column_to_category_on_create(): void
    {
        $this->actingUser();
        $form = Form::factory()->create();
        $category = FormColumnCategory::factory()->create(['form_id' => $form->id]);

        Livewire::test('pages::forms.structure', ['form' => $form])
            ->set('columnLabel', 'Confidence')
            ->set('columnType', 'text')
            ->set('columnCategoryId', $category->id)
            ->call('saveColumn')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('form_columns', [
            'label' => 'Confidence',
            'form_column_category_id' => $category->id,
        ]);
    }

    public function test_deleting_column_category_orphans_columns(): void
    {
        $this->actingUser();
        $form = Form::factory()->create();
        $category = FormColumnCategory::factory()->create(['form_id' => $form->id]);
        $column = FormColumn::factory()->create([
            'form_id' => $form->id,
            'form_column_category_id' => $category->id,
        ]);

        Livewire::test('pages::forms.structure', ['form' => $form])
            ->call('deleteColumnCategory', $category->id);

        $this->assertDatabaseMissing('form_column_categories', ['id' => $category->id]);
        $this->assertDatabaseHas('form_columns', ['id' => $column->id, 'form_column_category_id' => null]);
    }

    public function test_sorting_column_into_other_category_run_updates_its_category(): void
    {
        $this->actingUser();
        $form = Form::factory()->create();
        $catA = FormColumnCategory::factory()->create(['form_id' => $form->id, 'name' => 'A']);
        $catB = FormColumnCategory::factory()->create(['form_id' => $form->id, 'name' => 'B']);
        $colA1 = FormColumn::factory()->create(['form_id' => $form->id, 'position' => 0, 'form_column_category_id' => $catA->id]);
        $colA2 = FormColumn::factory()->create(['form_id' => $form->id, 'position' => 1, 'form_column_category_id' => $catA->id]);
        $colB1 = FormColumn::factory()->create(['form_id' => $form->id, 'position' => 2, 'form_column_category_id' => $catB->id]);

        // Drag colA1 between colB1's neighbours (position 2 after removing it from current spot)
        Livewire::test('pages::forms.structure', ['form' => $form])
            ->call('sortColumns', $colA1->id, 2);

        // colA1 should now be in catB
        $this->assertEquals($catB->id, $colA1->refresh()->form_column_category_id);
    }

    public function test_can_create_row_category(): void
    {
        $this->actingUser();
        $form = Form::factory()->create();

        Livewire::test('pages::forms.structure', ['form' => $form])
            ->set('rowCategoryName', 'Outdoors')
            ->call('saveRowCategory')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('form_row_categories', [
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

    public function test_deleting_row_category_keeps_rows_as_uncategorized(): void
    {
        $this->actingUser();
        $form = Form::factory()->create();
        $category = FormRowCategory::factory()->create(['form_id' => $form->id]);
        $row = FormRow::factory()->create([
            'form_id' => $form->id,
            'form_row_category_id' => $category->id,
        ]);

        Livewire::test('pages::forms.structure', ['form' => $form])
            ->call('deleteRowCategory', $category->id);

        $this->assertDatabaseMissing('form_row_categories', ['id' => $category->id]);
        $this->assertDatabaseHas('form_rows', ['id' => $row->id, 'form_row_category_id' => null]);
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
            ->call('sortRows', $a->id, 2);

        $this->assertEquals(0, $b->refresh()->position);
        $this->assertEquals(1, $c->refresh()->position);
        $this->assertEquals(2, $a->refresh()->position);
    }
}
