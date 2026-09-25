<?php

namespace App\Console\Commands;

use App\Jobs\RunCheck;
use App\Models\Monitor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('monitors:dispatch')]
#[Description('Queue a check job for every enabled monitor that is due')]
class DispatchDueChecks extends Command
{
    /** Scheduler ticks land a little either side of the boundary; without slack a 30s monitor would slip to 60s. */
    private const GRACE_SECONDS = 3;

    public function handle(): int
    {
        $dispatched = 0;
        // One tick time for the whole run: claiming monitors at their own now() would push later ones past the next tick's grace window.
        $tick = now();

        Monitor::where('enabled', true)
            ->where(fn ($q) => $q->whereNull('next_check_at')->orWhere('next_check_at', '<=', $tick->copy()->addSeconds(self::GRACE_SECONDS)))
            ->each(function (Monitor $monitor) use (&$dispatched, $tick) {
                // Step from the previous slot to avoid drift, but never schedule into the past after downtime.
                $next = ($monitor->next_check_at ?? $tick)->copy()->addSeconds($monitor->interval_s);
                if ($next->lte($tick)) {
                    $next = $tick->copy()->addSeconds($monitor->interval_s);
                }

                // Claim by compare-and-set on next_check_at so overlapping ticks never queue the same check twice.
                $claimed = Monitor::whereKey($monitor->id)
                    ->when($monitor->next_check_at === null,
                        fn ($q) => $q->whereNull('next_check_at'),
                        fn ($q) => $q->where('next_check_at', $monitor->next_check_at))
                    ->update(['next_check_at' => $next]);

                if ($claimed) {
                    RunCheck::dispatch($monitor);
                    $dispatched++;
                }
            });

        $this->info("Dispatched {$dispatched} check(s).");

        return self::SUCCESS;
    }
}
