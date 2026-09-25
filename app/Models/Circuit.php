<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\CircuitType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['provider_id', 'a_site_id', 'z_site_id', 'circuit_ref', 'name', 'type', 'bandwidth_mbps', 'sla_target'])]
class Circuit extends Model
{
    use Auditable, HasFactory;

    protected static function booted(): void
    {
        // Monitors point here polymorphically, so there is no FK to cascade: unlink them instead of leaving dangling ids.
        static::deleting(fn (Circuit $c) => $c->monitors()->update(['monitorable_type' => null, 'monitorable_id' => null]));
    }

    protected function casts(): array
    {
        return ['type' => CircuitType::class, 'sla_target' => 'float'];
    }

    /** @return BelongsTo<Provider, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    /** @return BelongsTo<Site, $this> */
    public function aSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'a_site_id');
    }

    /** @return BelongsTo<Site, $this> */
    public function zSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'z_site_id');
    }

    /** @return MorphMany<Monitor, $this> */
    public function monitors(): MorphMany
    {
        return $this->morphMany(Monitor::class, 'monitorable');
    }

    /** @return HasMany<MaintenanceWindow, $this> */
    public function maintenanceWindows(): HasMany
    {
        return $this->hasMany(MaintenanceWindow::class);
    }
}
