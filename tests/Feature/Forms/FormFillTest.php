<?php

namespace Tests\Feature\Forms;

use App\Models\Form;
use App\Models\FormCell;
use App\Models\FormColumn;
use App\Models\FormResponse;
use App\Models\FormRow;
use App\Models\FormRowDefault;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FormFillTest extends TestCase
{
    use RefreshDatabase;

    private function makeFormWithSingleCell(): array
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $form = Form::factory()->create();
        $column = FormColumn::factory()->create(['form_id' => $form->id]);
        $row = FormRow::factory()->create(['form_id' => $form->id]);

        return [$user, $form, $row, $column];
    }

    public function test_autosave_creates_response_and_cell_on_first_input(): void
    {
        [$user, $form, $row, $column] = $this->makeFormWithSingleCell();

        $this->assertDatabaseCount('form_responses', 0);

        Livewire::test('pages::forms.fill', ['form' => $form])
            ->set("cells.{$row->id}.{$column->id}", 'Hello');

        $this->assertDatabaseHas('form_responses', [
            'form_id' => $form->id,
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('form_cells', [
            'form_row_id' => $row->id,
            'form_column_id' => $column->id,
            'value' => 'Hello',
        ]);
    }

    public function test_subsequent_save_updates_existing_cell(): void
    {
        [, $form, $row, $column] = $this->makeFormWithSingleCell();

        $component = Livewire::test('pages::forms.fill', ['form' => $form])
            ->set("cells.{$row->id}.{$column->id}", 'first');

        $component->set("cells.{$row->id}.{$column->id}", 'second');

        $this->assertDatabaseCount('form_cells', 1);
        $this->assertDatabaseHas('form_cells', [
            'form_row_id' => $row->id,
            'form_column_id' => $column->id,
            'value' => 'second',
        ]);
    }

    public function test_empty_string_clears_cell_to_null(): void
    {
        [, $form, $row, $column] = $this->makeFormWithSingleCell();

        $component = Livewire::test('pages::forms.fill', ['form' => $form])
            ->set("cells.{$row->id}.{$column->id}", 'something');

        $component->set("cells.{$row->id}.{$column->id}", '');

        $this->assertDatabaseHas('form_cells', [
            'form_row_id' => $row->id,
            'form_column_id' => $column->id,
            'value' => null,
        ]);
    }

    public function test_no_response_created_when_form_only_opened(): void
    {
        [, $form] = $this->makeFormWithSingleCell();

        Livewire::test('pages::forms.fill', ['form' => $form])
            ->assertOk();

        $this->assertDatabaseCount('form_responses', 0);
    }

    public function test_default_value_is_loaded_when_no_user_cell(): void
    {
        [, $form, $row, $column] = $this->makeFormWithSingleCell();
        FormRowDefault::factory()->create([
            'form_row_id' => $row->id,
            'form_column_id' => $column->id,
            'value' => 'default text',
        ]);

        Livewire::test('pages::forms.fill', ['form' => $form])
            ->assertSet("cells.{$row->id}.{$column->id}", 'default text');
    }

    public function test_user_value_overrides_unlocked_default(): void
    {
        [$user, $form, $row, $column] = $this->makeFormWithSingleCell();
        FormRowDefault::factory()->create([
            'form_row_id' => $row->id,
            'form_column_id' => $column->id,
            'value' => 'default text',
        ]);
        $response = FormResponse::create(['form_id' => $form->id, 'user_id' => $user->id]);
        FormCell::create([
            'form_response_id' => $response->id,
            'form_row_id' => $row->id,
            'form_column_id' => $column->id,
            'value' => 'user value',
        ]);

        Livewire::test('pages::forms.fill', ['form' => $form])
            ->assertSet("cells.{$row->id}.{$column->id}", 'user value');
    }

    public function test_locked_default_cannot_be_overwritten(): void
    {
        [$user, $form, $row, $column] = $this->makeFormWithSingleCell();
        FormRowDefault::factory()->locked()->create([
            'form_row_id' => $row->id,
            'form_column_id' => $column->id,
            'value' => 'locked text',
        ]);

        Livewire::test('pages::forms.fill', ['form' => $form])
            ->assertSet("cells.{$row->id}.{$column->id}", 'locked text')
            ->set("cells.{$row->id}.{$column->id}", 'hack attempt');

        $this->assertDatabaseCount('form_cells', 0);
        $this->assertDatabaseCount('form_responses', 0);
    }

    public function test_unique_constraint_blocks_duplicate_response(): void
    {
        $user = User::factory()->create();
        $form = Form::factory()->create();

        FormResponse::create(['form_id' => $form->id, 'user_id' => $user->id]);

        $this->expectException(QueryException::class);

        FormResponse::create(['form_id' => $form->id, 'user_id' => $user->id]);
    }
}
