<?php

namespace App\Console\Commands;

use App\Jobs\CheckMonitorJob;
use App\Models\Monitor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('monitors:poll')]
#[Description('Dispatch a check job for each enabled monitor that is due')]
class PollMonitorsCommand extends Command
{
    public function handle(): void
    {
        $dispatched = 0;

        Monitor::where('enabled', true)
            ->get()
            ->filter(fn (Monitor $monitor) => $monitor->isDue())
            ->each(function (Monitor $monitor) use (&$dispatched) {
                CheckMonitorJob::dispatch($monitor->id);
                $dispatched++;
            });

        $this->info("Dispatched {$dispatched} monitor check(s).");
    }
}
