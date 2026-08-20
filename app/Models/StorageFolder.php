<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'parent_id'])]
class StorageFolder extends Model
{
    use HasFactory;

    public function parent(): BelongsTo
    {
        return $this->belongsTo(StorageFolder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(StorageFolder::class, 'parent_id')->orderBy('name');
    }

    public function files(): HasMany
    {
        return $this->hasMany(StorageFile::class, 'folder_id');
    }

    /** @return StorageFolder[] */
    public function ancestors(): array
    {
        $ancestors = [];
        $current = $this;

        while ($current->parent_id !== null) {
            $current = $current->parent;
            array_unshift($ancestors, $current);
        }

        return $ancestors;
    }
}
