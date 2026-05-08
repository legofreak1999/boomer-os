<?php

namespace App\Models;

use Database\Factories\ChoreCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class ChoreCategory extends Model
{
    /** @use HasFactory<ChoreCategoryFactory> */
    use HasFactory;

    public function chores(): HasMany
    {
        return $this->hasMany(Chore::class);
    }
}
