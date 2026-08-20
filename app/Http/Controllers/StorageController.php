<?php

namespace App\Http\Controllers;

use App\Models\StorageFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StorageController extends Controller
{
    public function download(StorageFile $storageFile): StreamedResponse
    {
        abort_unless(
            Storage::disk(config('filesystems.storage_primary'))->exists($storageFile->storage_key),
            404,
        );

        return Storage::disk(config('filesystems.storage_primary'))->download(
            $storageFile->storage_key,
            $storageFile->filename,
        );
    }
}
