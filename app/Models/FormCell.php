<?php

namespace App\Models;

use Database\Factories\FormCellFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['form_response_id', 'form_row_id', 'form_column_id', 'value'])]
class FormCell extends Model
{
    /** @use HasFactory<FormCellFactory> */
    use HasFactory;

    public function response(): BelongsTo
    {
        return $this->belongsTo(FormResponse::class, 'form_response_id');
    }

    public function row(): BelongsTo
    {
        return $this->belongsTo(FormRow::class, 'form_row_id');
    }

    public function column(): BelongsTo
    {
        return $this->belongsTo(FormColumn::class, 'form_column_id');
    }
}
