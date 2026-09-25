<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// State, timestamps and events are only changed through IncidentService, so they are not mass-assignable.
#[Fillable(['severity', 'provider_ticket', 'rfo_summary', 'rfo_root_cause', 'rfo_corrective_action'])]
class Incident extends Model
{
    use Auditable, HasFactory;

    protected function casts(): array
    {
        return [
            'state' => IncidentState::class,
            'severity' => IncidentSeverity::class,
            'opened_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Monitor, $this> */
    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    /** @return BelongsTo<Circuit, $this> */
    public function circuit(): BelongsTo
    {
        return $this->belongsTo(Circuit::class);
    }

    /** @return HasMany<IncidentEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(IncidentEvent::class)->orderBy('id');
    }

    /** Seconds from detection to acknowledgement (MTTA input). */
    public function timeToAcknowledge(): ?int
    {
        return $this->acknowledged_at?->diffInSeconds($this->opened_at, true);
    }

    /** Seconds from detection to resolution (MTTR input). */
    public function timeToResolve(): ?int
    {
        return $this->resolved_at?->diffInSeconds($this->opened_at, true);
    }
}
