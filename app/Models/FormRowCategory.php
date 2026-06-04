<?php

namespace App\Models;

use Database\Factories\FormRowCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['form_id', 'name', 'position'])]
class FormRowCategory extends Model
{
    /** @use HasFactory<FormRowCategoryFactory> */
    use HasFactory;

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(FormRow::class)->orderBy('position');
    }
}
