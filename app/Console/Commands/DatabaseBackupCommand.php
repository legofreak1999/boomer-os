<?php

namespace App\Console\Commands;

use App\Actions\Storage\DatabaseBackup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('storage:backup')]
#[Description('Create a MySQL database backup and upload to both Hetzner Object Storage buckets')]
class DatabaseBackupCommand extends Command
{
    public function handle(DatabaseBackup $backup): int
    {
        try {
            $result = $backup();
            $this->info("Backup created: {$result->filename} ({$result->humanSize()})");
            $this->line('Primary:   '.($result->synced_to_primary_at ? 'OK ('.$result->synced_to_primary_at.')' : 'FAILED'));
            $this->line('Secondary: '.($result->synced_to_secondary_at ? 'OK ('.$result->synced_to_secondary_at.')' : 'FAILED'));

            if ($result->notes) {
                $this->warn($result->notes);
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Backup failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
