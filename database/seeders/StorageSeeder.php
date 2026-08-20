<?php

namespace Database\Seeders;

use App\Models\StorageBackup;
use App\Models\StorageFile;
use App\Models\StorageFolder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class StorageSeeder extends Seeder
{
    public function run(): void
    {
        $primaryDisk = config('filesystems.storage_primary');
        $secondaryDisk = config('filesystems.storage_secondary');

        // Create a folder structure
        $documents = StorageFolder::create(['name' => 'Documents']);
        $photos = StorageFolder::create(['name' => 'Photos']);
        $work = StorageFolder::create(['name' => 'Work', 'parent_id' => $documents->id]);

        // Root files (no folder)
        $rootFiles = StorageFile::factory(3)->create(['folder_id' => null]);

        // Files inside folders
        $docFiles = StorageFile::factory(4)->create(['folder_id' => $documents->id]);
        $photoFiles = StorageFile::factory(3)->create(['folder_id' => $photos->id]);
        $workFiles = StorageFile::factory(2)->create(['folder_id' => $work->id]);

        // One file pending sync
        $pending = StorageFile::factory()->pendingSync()->create(['folder_id' => null]);

        // Place placeholder files on local disks
        foreach ([$rootFiles, $docFiles, $photoFiles, $workFiles, collect([$pending])] as $files) {
            foreach ($files as $file) {
                Storage::disk($primaryDisk)->put($file->storage_key, fake()->text(512));

                if ($file->synced_to_secondary_at) {
                    Storage::disk($secondaryDisk)->put($file->storage_key, fake()->text(512));
                }
            }
        }

        // Backup history
        StorageBackup::factory(10)->create();
        StorageBackup::factory()->failed()->create();
    }
}
