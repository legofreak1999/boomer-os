<?php

namespace App\Models;

use Database\Factories\FormResponseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['form_id', 'user_id'])]
class FormResponse extends Model
{
    /** @use HasFactory<FormResponseFactory> */
    use HasFactory;

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cells(): HasMany
    {
        return $this->hasMany(FormCell::class);
    }

    public function cellFor(FormRow $row, FormColumn $column): ?FormCell
    {
        return $this->cells->first(fn ($c) => $c->form_row_id === $row->id && $c->form_column_id === $column->id);
    }
}
