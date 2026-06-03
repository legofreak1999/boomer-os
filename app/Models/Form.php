<?php

namespace App\Models;

use Database\Factories\FormFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'position'])]
class Form extends Model
{
    /** @use HasFactory<FormFactory> */
    use HasFactory;

    public function columns(): HasMany
    {
        return $this->hasMany(FormColumn::class)->orderBy('position');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(FormCategory::class)->orderBy('position');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(FormRow::class)->orderBy('position');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(FormResponse::class);
    }

    public function responseFor(User $user): ?FormResponse
    {
        return $this->responses()->where('user_id', $user->id)->first();
    }
}
