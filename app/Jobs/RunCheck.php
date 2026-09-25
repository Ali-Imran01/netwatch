<?php

namespace App\Jobs;

use App\Models\Monitor;
use App\Services\CheckRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;

#[DeleteWhenMissingModels]
class RunCheck implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public Monitor $monitor)
    {
        $this->onQueue('checks');
    }

    public function handle(CheckRunner $runner): void
    {
        $runner->run($this->monitor);
    }
}
