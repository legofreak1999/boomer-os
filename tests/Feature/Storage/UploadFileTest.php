<?php

namespace Tests\Feature\Storage;

use App\Jobs\SyncFileToSecondaryJob;
use App\Models\StorageFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class UploadFileTest extends TestCase
{
    use RefreshDatabase;

    public function test_storage_page_requires_authentication(): void
    {
        $this->get(route('storage.index'))->assertRedirect(route('login'));
    }

    public function test_storage_page_is_accessible(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('storage.index'))->assertOk();
    }

    public function test_upload_creates_db_record_and_dispatches_sync_job(): void
    {
        Storage::fake(config('filesystems.storage_primary'));
        Queue::fake();

        $this->actingAs(User::factory()->create());

        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        Livewire::test('pages::storage.index')
            ->set('uploadFile', $file)
            ->call('upload')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('storage_files', 1);

        $storageFile = StorageFile::first();
        $this->assertEquals('document.pdf', $storageFile->filename);
        $this->assertEquals('application/pdf', $storageFile->mime_type);
        $this->assertNotNull($storageFile->synced_to_primary_at);
        $this->assertNull($storageFile->synced_to_secondary_at);

        Storage::disk(config('filesystems.storage_primary'))->assertExists($storageFile->storage_key);

        Queue::assertPushed(SyncFileToSecondaryJob::class, function (SyncFileToSecondaryJob $job) use ($storageFile) {
            return $job->storageFileId === $storageFile->id;
        });
    }

    public function test_upload_requires_a_file(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::storage.index')
            ->set('uploadFile', null)
            ->call('upload')
            ->assertHasErrors(['uploadFile']);
    }
}
