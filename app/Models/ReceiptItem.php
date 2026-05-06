<?php

namespace App\Models;

use Database\Factories\ReceiptItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['receipt_id', 'category_id', 'amount'])]
class ReceiptItem extends Model
{
    /** @use HasFactory<ReceiptItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
