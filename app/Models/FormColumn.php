<?php

namespace App\Models;

use Database\Factories\FormColumnFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['form_id', 'label', 'type', 'options', 'position'])]
class FormColumn extends Model
{
    /** @use HasFactory<FormColumnFactory> */
    use HasFactory;

    public const TYPE_TEXT = 'text';

    public const TYPE_SELECT = 'select';

    public const TYPES = [
        self::TYPE_TEXT,
        self::TYPE_SELECT,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'options' => 'array',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function defaults(): HasMany
    {
        return $this->hasMany(FormRowDefault::class);
    }

    public function cells(): HasMany
    {
        return $this->hasMany(FormCell::class);
    }
}
