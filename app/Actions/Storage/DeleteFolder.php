<?php

namespace App\Actions\Storage;

use App\Models\StorageFolder;

class DeleteFolder
{
    public function __invoke(StorageFolder $folder): void
    {
        if ($folder->files()->exists() || $folder->children()->exists()) {
            throw new \RuntimeException(__('Cannot delete a folder that still contains files or subfolders.'));
        }

        $folder->delete();
    }
}
