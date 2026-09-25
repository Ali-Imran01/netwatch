<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\MonitorState;
use App\Enums\MonitorType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;

#[Fillable(['name', 'type', 'target', 'port', 'interval_s', 'timeout_ms', 'monitorable_type', 'monitorable_id', 'enabled'])]
class Monitor extends Model
{
    use Auditable, HasFactory;

    protected function casts(): array
    {
        return [
            'type' => MonitorType::class,
            'state' => MonitorState::class,
            'state_changed_at' => 'datetime',
            'enabled' => 'boolean',
            'last_success' => 'boolean',
            'next_check_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function monitorable(): MorphTo
    {
        return $this->morphTo();
    }

    /** True while a maintenance window covers this monitor. Pass a preloaded set of active windows when checking many monitors. */
    public function inMaintenance(?Collection $activeWindows = null): bool
    {
        return ($activeWindows ?? MaintenanceWindow::active()->get())->contains(fn (MaintenanceWindow $w) => $w->covers($this));
    }

    /** @return HasMany<CheckResult, $this> */
    public function results(): HasMany
    {
        return $this->hasMany(CheckResult::class);
    }
}
