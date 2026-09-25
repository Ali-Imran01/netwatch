<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['monitor_id', 'bucket_start', 'bucket_size', 'checks', 'failures', 'avg_latency_ms', 'p95_latency_ms'])]
class CheckRollup extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['bucket_start' => 'datetime'];
    }
}
