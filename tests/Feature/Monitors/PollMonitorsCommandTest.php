<?php

namespace Tests\Feature\Monitors;

use App\Jobs\CheckMonitorJob;
use App\Models\Monitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PollMonitorsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_job_for_a_monitor_that_has_never_polled(): void
    {
        Queue::fake();

        $monitor = Monitor::factory()->create(['enabled' => true, 'last_polled_at' => null]);

        $this->artisan('monitors:poll')->assertExitCode(0);

        Queue::assertPushed(CheckMonitorJob::class, fn (CheckMonitorJob $job) => $job->monitorId === $monitor->id);
    }

    public function test_does_not_dispatch_before_interval_elapses(): void
    {
        Queue::fake();

        Monitor::factory()->create([
            'enabled' => true,
            'interval_minutes' => 15,
            'last_polled_at' => now()->subMinute(),
        ]);

        $this->artisan('monitors:poll')->assertExitCode(0);

        Queue::assertNotPushed(CheckMonitorJob::class);
    }

    public function test_dispatches_when_interval_has_elapsed(): void
    {
        Queue::fake();

        $monitor = Monitor::factory()->create([
            'enabled' => true,
            'interval_minutes' => 15,
            'last_polled_at' => now()->subMinutes(30),
        ]);

        $this->artisan('monitors:poll')->assertExitCode(0);

        Queue::assertPushed(CheckMonitorJob::class, fn (CheckMonitorJob $job) => $job->monitorId === $monitor->id);
    }

    public function test_ignores_disabled_monitors(): void
    {
        Queue::fake();

        Monitor::factory()->create(['enabled' => false, 'last_polled_at' => null]);

        $this->artisan('monitors:poll')->assertExitCode(0);

        Queue::assertNotPushed(CheckMonitorJob::class);
    }
}
