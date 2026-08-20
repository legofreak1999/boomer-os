<?php

namespace App\Actions\Storage;

use App\Models\StorageFile;
use Illuminate\Support\Facades\Storage;

class DeleteStorageFile
{
    public function __invoke(StorageFile $storageFile): void
    {
        try {
            Storage::disk(config('filesystems.storage_primary'))->delete($storageFile->storage_key);
        } catch (\Throwable) {
        }

        if ($storageFile->synced_to_secondary_at) {
            try {
                Storage::disk(config('filesystems.storage_secondary'))->delete($storageFile->storage_key);
            } catch (\Throwable) {
            }
        }

        $storageFile->delete();
    }
}
