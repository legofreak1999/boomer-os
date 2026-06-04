<?php

namespace App\Models;

use Database\Factories\FormColumnFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['form_id', 'form_column_category_id', 'label', 'type', 'options', 'position'])]
class FormColumn extends Model
{
    /** @use HasFactory<FormColumnFactory> */
    use HasFactory;

    public const TYPE_TEXT = 'text';

    public const TYPE_SELECT = 'select';

    public const TYPE_TEXTAREA = 'textarea';

    public const TYPES = [
        self::TYPE_TEXT,
        self::TYPE_SELECT,
        self::TYPE_TEXTAREA,
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

    protected function formColumnCategoryId(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $value === null || $value === '' || $value === 0 || $value === '0'
                ? null
                : (int) $value,
        );
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FormColumnCategory::class, 'form_column_category_id');
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
