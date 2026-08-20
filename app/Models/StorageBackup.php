<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['filename', 'storage_key', 'size_bytes', 'synced_to_primary_at', 'synced_to_secondary_at', 'notes'])]
class StorageBackup extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'synced_to_primary_at' => 'datetime',
            'synced_to_secondary_at' => 'datetime',
        ];
    }

    public function humanSize(): string
    {
        if (! $this->size_bytes) {
            return '—';
        }

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
