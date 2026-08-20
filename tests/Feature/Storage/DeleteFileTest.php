<?php

namespace Tests\Feature\Storage;

use App\Models\StorageFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DeleteFileTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_removes_file_from_primary_and_db(): void
    {
        Storage::fake(config('filesystems.storage_primary'));
        Storage::fake(config('filesystems.storage_secondary'));

        $this->actingAs(User::factory()->create());

        Storage::disk(config('filesystems.storage_primary'))->put('files/test.pdf', 'content');

        $storageFile = StorageFile::create([
            'filename' => 'test.pdf',
            'storage_key' => 'files/test.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 7,
            'synced_to_primary_at' => now(),
            'synced_to_secondary_at' => null,
        ]);

        Livewire::test('pages::storage.index')
            ->call('deleteFile', $storageFile->id);

        $this->assertDatabaseMissing('storage_files', ['id' => $storageFile->id]);
        Storage::disk(config('filesystems.storage_primary'))->assertMissing('files/test.pdf');
    }

    public function test_delete_also_removes_from_secondary_when_synced(): void
    {
        Storage::fake(config('filesystems.storage_primary'));
        Storage::fake(config('filesystems.storage_secondary'));

        $this->actingAs(User::factory()->create());

        Storage::disk(config('filesystems.storage_primary'))->put('files/test.pdf', 'content');
        Storage::disk(config('filesystems.storage_secondary'))->put('files/test.pdf', 'content');

        $storageFile = StorageFile::create([
            'filename' => 'test.pdf',
            'storage_key' => 'files/test.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 7,
            'synced_to_primary_at' => now(),
            'synced_to_secondary_at' => now(),
        ]);

        Livewire::test('pages::storage.index')
            ->call('deleteFile', $storageFile->id);

        $this->assertDatabaseMissing('storage_files', ['id' => $storageFile->id]);
        Storage::disk(config('filesystems.storage_primary'))->assertMissing('files/test.pdf');
        Storage::disk(config('filesystems.storage_secondary'))->assertMissing('files/test.pdf');
    }
}
