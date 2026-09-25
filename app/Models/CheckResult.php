<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['monitor_id', 'checked_at', 'success', 'latency_ms', 'detail'])]
class CheckResult extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['checked_at' => 'datetime', 'success' => 'boolean'];
    }

    /** @return BelongsTo<Monitor, $this> */
    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }
}
