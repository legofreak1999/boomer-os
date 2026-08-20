<?php

namespace Tests\Feature\Storage;

use App\Actions\Storage\DatabaseBackup;
use App\Models\StorageBackup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_creates_db_record_with_timestamps(): void
    {
        Storage::fake(config('filesystems.storage_primary'));
        Storage::fake(config('filesystems.storage_secondary'));

        $backup = (new DatabaseBackup)();

        $this->assertDatabaseCount('storage_backups', 1);

        $record = StorageBackup::first();
        $this->assertNotNull($record->synced_to_primary_at);
        $this->assertNotNull($record->synced_to_secondary_at);
        $this->assertStringStartsWith('backup-', $record->filename);
        $this->assertStringEndsWith('.sql.gz', $record->filename);

        Storage::disk(config('filesystems.storage_primary'))->assertExists($record->storage_key);
        Storage::disk(config('filesystems.storage_secondary'))->assertExists($record->storage_key);
    }

    public function test_retention_deletes_backups_beyond_30(): void
    {
        Storage::fake(config('filesystems.storage_primary'));
        Storage::fake(config('filesystems.storage_secondary'));

        // Seed 30 existing backups
        for ($i = 1; $i <= 30; $i++) {
            $key = "backups/backup-old-{$i}.sql.gz";
            Storage::disk(config('filesystems.storage_primary'))->put($key, 'x');
            Storage::disk(config('filesystems.storage_secondary'))->put($key, 'x');
            StorageBackup::create([
                'filename' => "backup-old-{$i}.sql.gz",
                'storage_key' => $key,
                'size_bytes' => 1,
                'synced_to_primary_at' => now()->subDays(31 - $i),
                'synced_to_secondary_at' => now()->subDays(31 - $i),
            ]);
        }

        // Create a new backup (makes 31 total, oldest should be pruned)
        (new DatabaseBackup)();

        $this->assertDatabaseCount('storage_backups', 30);
    }
}
