<?php

namespace App\Actions\Storage;

use App\Models\StorageFolder;

class CreateFolder
{
    public function __invoke(string $name, ?int $parentId = null): StorageFolder
    {
        return StorageFolder::create([
            'name' => $name,
            'parent_id' => $parentId,
        ]);
    }
}
