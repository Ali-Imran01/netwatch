<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['circuit_id', 'monitor_id', 'starts_at', 'ends_at', 'provider_ref', 'notes'])]
class MaintenanceWindow extends Model
{
    use Auditable, HasFactory;

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    /** @return BelongsTo<Circuit, $this> */
    public function circuit(): BelongsTo
    {
        return $this->belongsTo(Circuit::class);
    }

    /** @return BelongsTo<Monitor, $this> */
    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    /** Windows that are open right now. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('starts_at', '<=', now())->where('ends_at', '>', now());
    }

    /** Does this window silence the given monitor (directly, or through its circuit)? */
    public function covers(Monitor $monitor): bool
    {
        return ($this->monitor_id !== null && (int) $this->monitor_id === (int) $monitor->id)
            || ($this->circuit_id !== null && $monitor->monitorable_type === Circuit::class && (int) $monitor->monitorable_id === (int) $this->circuit_id);
    }
}
