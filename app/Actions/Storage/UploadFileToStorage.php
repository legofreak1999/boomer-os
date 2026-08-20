<?php

namespace App\Actions\Storage;

use App\Jobs\SyncFileToSecondaryJob;
use App\Models\StorageFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class UploadFileToStorage
{
    public function __invoke(TemporaryUploadedFile $file, ?int $folderId = null): StorageFile
    {
        $originalName = $file->getClientOriginalName();
        $safeName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)).'.'.($file->getClientOriginalExtension() ?: 'bin');
        $storageKey = 'files/'.Str::uuid().'-'.$safeName;

        Storage::disk(config('filesystems.storage_primary'))->putFileAs(
            'files',
            $file->getRealPath(),
            basename($storageKey),
        );

        $storageFile = StorageFile::create([
            'filename' => $originalName,
            'storage_key' => $storageKey,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'folder_id' => $folderId,
            'synced_to_primary_at' => now(),
            'synced_to_secondary_at' => null,
        ]);

        SyncFileToSecondaryJob::dispatch($storageFile->id);

        return $storageFile;
    }
}
