<?php

namespace App\Console\Commands;

use App\Models\CheckResult;
use App\Models\CheckRollup;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('checks:rollup {--hours=3 : Recompute closed buckets from this many hours back}')]
#[Description('Aggregate raw check results into 5-minute and hourly buckets (idempotent; safe to re-run or backfill)')]
class RollUpChecks extends Command
{
    private const SIZES = [300, 3600];

    public function handle(): int
    {
        $now = CarbonImmutable::now();
        $written = 0;

        foreach (self::SIZES as $size) {
            // Closed buckets only: a bucket still receiving results would be rolled up short.
            $end = $now->setTimestamp(intdiv($now->timestamp, $size) * $size);
            $start = $end->subHours((int) $this->option('hours'));
            $start = $start->setTimestamp(intdiv($start->timestamp, $size) * $size);

            $buckets = [];
            CheckResult::where('checked_at', '>=', $start)->where('checked_at', '<', $end)
                ->orderBy('id')->select(['monitor_id', 'checked_at', 'success', 'latency_ms'])
                ->lazy(5000)
                ->each(function (CheckResult $r) use (&$buckets, $size) {
                    $bucket = intdiv($r->checked_at->timestamp, $size) * $size;
                    $buckets[$r->monitor_id][$bucket][] = $r;
                });

            foreach ($buckets as $monitorId => $byBucket) {
                foreach ($byBucket as $bucket => $results) {
                    $latencies = collect($results)->filter(fn ($r) => $r->success && $r->latency_ms !== null)->pluck('latency_ms')->sort()->values();

                    CheckRollup::updateOrCreate(
                        ['monitor_id' => $monitorId, 'bucket_size' => $size, 'bucket_start' => $now->setTimestamp($bucket)],
                        [
                            'checks' => count($results),
                            'failures' => collect($results)->where('success', false)->count(),
                            'avg_latency_ms' => $latencies->isEmpty() ? null : (int) round($latencies->avg()),
                            // Nearest-rank p95.
                            'p95_latency_ms' => $latencies->isEmpty() ? null : $latencies[(int) ceil(0.95 * $latencies->count()) - 1],
                        ],
                    );
                    $written++;
                }
            }
        }

        $this->info("Wrote {$written} rollup bucket(s).");

        return self::SUCCESS;
    }
}
