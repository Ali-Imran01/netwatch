<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\MonitorType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'type', 'target', 'port', 'interval_s', 'timeout_ms', 'monitorable_type', 'monitorable_id', 'enabled'])]
class Monitor extends Model
{
    use Auditable, HasFactory;

    protected function casts(): array
    {
        return [
            'type' => MonitorType::class,
            'enabled' => 'boolean',
            'last_success' => 'boolean',
            'next_check_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    public function monitorable()
    {
        return $this->morphTo();
    }

    public function results()
    {
        return $this->hasMany(CheckResult::class);
    }
}
