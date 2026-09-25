<?php

namespace App\Console\Commands;

use App\Models\CheckResult;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('checks:prune {--days=14 : Keep results newer than this many days}')]
#[Description('Delete raw check results older than the retention window')]
class PruneCheckResults extends Command
{
    public function handle(): int
    {
        $deleted = 0;

        // Delete in slices so a large backlog never holds one long table lock.
        do {
            $batch = CheckResult::where('checked_at', '<', now()->subDays((int) $this->option('days')))->limit(10000)->delete();
            $deleted += $batch;
        } while ($batch > 0);

        $this->info("Pruned {$deleted} check result(s).");

        return self::SUCCESS;
    }
}
