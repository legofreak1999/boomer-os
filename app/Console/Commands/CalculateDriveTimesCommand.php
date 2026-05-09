<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Models\HikeLocation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('hikes:calculate-drive-times')]
#[Description('Calculate driving times from home to all hike locations')]
class CalculateDriveTimesCommand extends Command
{
    public function handle(): void
    {
        $home = AppSetting::get('home_location');

        if (! $home || ! isset($home['lat'], $home['lng'])) {
            $this->error('No home location set. Configure it in Settings > Home Location.');

            return;
        }

        $locations = HikeLocation::all();
        $this->info("Calculating drive times for {$locations->count()} location(s)...");

        foreach ($locations as $location) {
            $location->calculateDriveTime($home['lat'], $home['lng']);
            $this->line("  {$location->name}: {$location->driveTimeFormatted()}");
            usleep(200000);
        }

        $this->info('Done.');
    }
}
