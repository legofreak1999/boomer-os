<?php

namespace App\Models;

use Database\Factories\FormRowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['form_id', 'form_category_id', 'position'])]
class FormRow extends Model
{
    /** @use HasFactory<FormRowFactory> */
    use HasFactory;

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FormCategory::class, 'form_category_id');
    }

    public function defaults(): HasMany
    {
        return $this->hasMany(FormRowDefault::class);
    }

    public function cells(): HasMany
    {
        return $this->hasMany(FormCell::class);
    }

    public function defaultFor(FormColumn $column): ?FormRowDefault
    {
        return $this->defaults->firstWhere('form_column_id', $column->id);
    }
}
