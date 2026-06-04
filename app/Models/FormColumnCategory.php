<?php

namespace App\Models;

use Database\Factories\FormColumnCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['form_id', 'name', 'position'])]
class FormColumnCategory extends Model
{
    /** @use HasFactory<FormColumnCategoryFactory> */
    use HasFactory;

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function columns(): HasMany
    {
        return $this->hasMany(FormColumn::class)->orderBy('position');
    }
}
