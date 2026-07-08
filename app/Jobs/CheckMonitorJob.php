<?php

namespace App\Jobs;

use App\Actions\Monitors\CheckMonitor;
use App\Models\Monitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckMonitorJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public function __construct(public int $monitorId) {}

    public function handle(CheckMonitor $check): void
    {
        $monitor = Monitor::find($this->monitorId);
        if (! $monitor || ! $monitor->enabled) {
            return;
        }

        $check($monitor);
    }
}
