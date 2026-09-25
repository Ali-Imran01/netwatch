<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['monitor_id', 'checked_at', 'success', 'latency_ms', 'detail'])]
class CheckResult extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['checked_at' => 'datetime', 'success' => 'boolean'];
    }

    public function monitor()
    {
        return $this->belongsTo(Monitor::class);
    }
}
