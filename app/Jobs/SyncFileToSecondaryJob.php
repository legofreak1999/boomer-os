<?php

namespace App\Jobs;

use App\Models\StorageFile;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class SyncFileToSecondaryJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public readonly int $storageFileId) {}

    public function handle(): void
    {
        $file = StorageFile::find($this->storageFileId);

        if (! $file || $file->synced_to_secondary_at) {
            return;
        }

        $stream = Storage::disk(config('filesystems.storage_primary'))->readStream($file->storage_key);
        Storage::disk(config('filesystems.storage_secondary'))->writeStream($file->storage_key, $stream);

        $file->update(['synced_to_secondary_at' => now()]);
    }
}
