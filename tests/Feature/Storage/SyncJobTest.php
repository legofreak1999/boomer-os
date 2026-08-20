<?php

namespace Tests\Feature\Storage;

use App\Jobs\SyncFileToSecondaryJob;
use App\Models\StorageFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SyncJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_copies_file_to_secondary_and_updates_timestamp(): void
    {
        Storage::fake(config('filesystems.storage_primary'));
        Storage::fake(config('filesystems.storage_secondary'));

        Storage::disk(config('filesystems.storage_primary'))->put('files/uuid-test.pdf', 'hello');

        $storageFile = StorageFile::create([
            'filename' => 'test.pdf',
            'storage_key' => 'files/uuid-test.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 5,
            'synced_to_primary_at' => now(),
            'synced_to_secondary_at' => null,
        ]);

        (new SyncFileToSecondaryJob($storageFile->id))->handle();

        Storage::disk(config('filesystems.storage_secondary'))->assertExists('files/uuid-test.pdf');
        $this->assertNotNull($storageFile->fresh()->synced_to_secondary_at);
    }

    public function test_job_is_idempotent_when_already_synced(): void
    {
        Storage::fake(config('filesystems.storage_primary'));
        Storage::fake(config('filesystems.storage_secondary'));

        $syncedAt = now()->subMinutes(5);

        $storageFile = StorageFile::create([
            'filename' => 'test.pdf',
            'storage_key' => 'files/uuid-test.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 5,
            'synced_to_primary_at' => now(),
            'synced_to_secondary_at' => $syncedAt,
        ]);

        (new SyncFileToSecondaryJob($storageFile->id))->handle();

        $this->assertEquals(
            $syncedAt->toDateTimeString(),
            $storageFile->fresh()->synced_to_secondary_at->toDateTimeString(),
        );
    }
}
