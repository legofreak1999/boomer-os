<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['filename', 'storage_key', 'mime_type', 'size_bytes', 'folder_id', 'synced_to_primary_at', 'synced_to_secondary_at'])]
class StorageFile extends Model
{
    use HasFactory;

    public function folder(): BelongsTo
    {
        return $this->belongsTo(StorageFolder::class, 'folder_id');
    }

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'folder_id' => 'integer',
            'synced_to_primary_at' => 'datetime',
            'synced_to_secondary_at' => 'datetime',
        ];
    }

    public function humanSize(): string
    {
        $bytes = $this->size_bytes;

        if ($bytes >= 1_073_741_824) {
            return number_format($bytes / 1_073_741_824, 2).' GB';
        }

        if ($bytes >= 1_048_576) {
            return number_format($bytes / 1_048_576, 2).' MB';
        }

        if ($bytes >= 1_024) {
            return number_format($bytes / 1_024, 2).' KB';
        }

        return $bytes.' B';
    }
}
