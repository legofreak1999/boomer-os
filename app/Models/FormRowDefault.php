<?php

namespace App\Models;

use Database\Factories\FormRowDefaultFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['form_row_id', 'form_column_id', 'value', 'locked', 'description'])]
class FormRowDefault extends Model
{
    /** @use HasFactory<FormRowDefaultFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'locked' => 'boolean',
        ];
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
