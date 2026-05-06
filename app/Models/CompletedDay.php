<?php

namespace App\Models;

use Database\Factories\CompletedDayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['date'])]
class CompletedDay extends Model
{
    /** @use HasFactory<CompletedDayFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
