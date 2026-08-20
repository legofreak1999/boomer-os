<?php

namespace App\Actions\Storage;

use App\Models\StorageBackup;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class DatabaseBackup
{
    public function __invoke(): StorageBackup
    {
        $filename = 'backup-'.now()->format('Y-m-d-His').'.sql.gz';
        $storageKey = 'backups/'.$filename;
        $localDir = storage_path('app/private/backups');
        $localPath = $localDir.'/'.$filename;

        if (! is_dir($localDir)) {
            mkdir($localDir, 0755, true);
        }

        $db = config('database.connections.mysql');

        $process = new Process([
            'sh', '-c',
            sprintf(
                'mysqldump --single-transaction -h %s -P %s -u %s %s | gzip > %s',
                escapeshellarg((string) $db['host']),
                escapeshellarg((string) $db['port']),
                escapeshellarg((string) $db['username']),
                escapeshellarg((string) $db['database']),
                escapeshellarg($localPath),
            ),
        ], null, ['MYSQL_PWD' => $db['password']]);

        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('mysqldump failed: '.$process->getErrorOutput());
        }

        $sizeBytes = file_exists($localPath) ? filesize($localPath) : null;
        $notes = [];
        $primaryAt = null;
        $secondaryAt = null;

        try {
            Storage::disk(config('filesystems.storage_primary'))->put($storageKey, fopen($localPath, 'rb'));
            $primaryAt = now();
        } catch (\Throwable $e) {
            $notes[] = 'Primary upload failed: '.$e->getMessage();
        }

        try {
            Storage::disk(config('filesystems.storage_secondary'))->put($storageKey, fopen($localPath, 'rb'));
            $secondaryAt = now();
        } catch (\Throwable $e) {
            $notes[] = 'Secondary upload failed: '.$e->getMessage();
        }

        $backup = StorageBackup::create([
            'filename' => $filename,
            'storage_key' => $storageKey,
            'size_bytes' => $sizeBytes,
            'synced_to_primary_at' => $primaryAt,
            'synced_to_secondary_at' => $secondaryAt,
            'notes' => $notes ? implode("\n", $notes) : null,
        ]);

        @unlink($localPath);

        $this->enforceRetention();

        return $backup;
    }

    private function enforceRetention(): void
    {
        $keepIds = StorageBackup::latest()->take(30)->pluck('id');
        $excess = StorageBackup::whereNotIn('id', $keepIds)->get();

        foreach ($excess as $backup) {
            try {
                Storage::disk(config('filesystems.storage_primary'))->delete($backup->storage_key);
            } catch (\Throwable) {
            }

            try {
                Storage::disk(config('filesystems.storage_secondary'))->delete($backup->storage_key);
            } catch (\Throwable) {
            }

            $backup->delete();
        }
    }
}
